<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Join funnel + sales Leads CRM helpers (Doc #997).
 */

/** @return list<string> */
function repsDashSalesUsernames(): array
{
    $rows = repsDashDb()->query(
        "SELECT username FROM users WHERE role = 'sales' AND is_active = 1 ORDER BY username COLLATE NOCASE"
    )->fetchAll(PDO::FETCH_COLUMN);
    return array_map('strval', $rows ?: []);
}

function repsDashIsActiveSalesUsername(string $username): bool
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return false;
    }
    foreach (repsDashSalesUsernames() as $u) {
        if (strtolower($u) === $username) {
            return true;
        }
    }
    return false;
}

function repsDashNextRoundRobinSalesUsername(): ?string
{
    $pool = repsDashSalesUsernames();
    if ($pool === []) {
        return null;
    }
    $idx = (int) repsDashAppMetaGet('leads_rr_index', '0');
    if ($idx < 0) {
        $idx = 0;
    }
    $pick = $pool[$idx % count($pool)];
    repsDashAppMetaSet('leads_rr_index', (string) (($idx + 1) % count($pool)));
    return $pick;
}

/**
 * Admin/ops may reassign a shop’s sales rep. Audited in shop_assign_events.
 *
 * @return array{ok:bool,error?:string,shop?:array<string,mixed>}
 */
function repsDashReassignShopSalesRep(int $shopId, ?string $toRep, array $actor, string $note = ''): array
{
    $role = (string) ($actor['role'] ?? '');
    if (!in_array($role, ['admin', 'ops'], true)) {
        return ['ok' => false, 'error' => 'Only admin/ops can reassign shops.'];
    }
    $shop = repsDashFindShop($shopId);
    if ($shop === null) {
        return ['ok' => false, 'error' => 'Shop not found.'];
    }
    $to = $toRep !== null ? trim($toRep) : '';
    if ($to === '') {
        $to = null;
    } elseif (!repsDashIsActiveSalesUsername($to)) {
        return ['ok' => false, 'error' => 'Not an active sales username.'];
    } else {
        foreach (repsDashSalesUsernames() as $u) {
            if (strtolower($u) === strtolower($to)) {
                $to = $u;
                break;
            }
        }
    }
    $from = $shop['assigned_sales_rep'] ?? null;
    $from = $from !== null && $from !== '' ? (string) $from : null;
    if ($from === $to) {
        return ['ok' => true, 'shop' => $shop];
    }

    // Prefer live shops table when present; mock-only IDs fall through as no-op write.
    try {
        $stmt = repsDashDb()->prepare('UPDATE shops SET assigned_sales_rep = ? WHERE id = ?');
        $stmt->execute([$to, $shopId]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not update shop assignment.'];
    }

    $ins = repsDashDb()->prepare(
        'INSERT INTO shop_assign_events (shop_id, from_rep, to_rep, actor_user_id, note)
         VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $shopId,
        $from,
        $to,
        (int) ($actor['id'] ?? 0) ?: null,
        trim($note),
    ]);

    $fresh = repsDashFindShop($shopId) ?? $shop;
    $fresh['assigned_sales_rep'] = $to;
    return ['ok' => true, 'shop' => $fresh];
}

/**
 * @return list<array<string, mixed>>
 */
function repsDashListShopAssignEvents(int $shopId, int $limit = 20): array
{
    $stmt = repsDashDb()->prepare(
        'SELECT * FROM shop_assign_events WHERE shop_id = ? ORDER BY id DESC LIMIT ?'
    );
    $stmt->execute([$shopId, max(1, min(100, $limit))]);
    return $stmt->fetchAll() ?: [];
}

function repsDashJoinKindFromPath(string $path): string
{
    return $path === 'company' ? 'shop' : 'operator';
}

function repsDashGraduateRoleForJoinKind(string $joinKind): string
{
    return match ($joinKind) {
        'shop' => 'business_owner',
        'affiliate' => 'sales',
        default => 'individual',
    };
}

/**
 * Resolve assignee for an inbound join.
 *
 * @return array{assigned: ?string, assign_source: string, status: string}
 */
function repsDashResolveLeadAssignment(string $joinKind, ?string $requestedRep): array
{
    if ($joinKind === 'affiliate') {
        return ['assigned' => null, 'assign_source' => 'none', 'status' => 'open'];
    }
    $req = $requestedRep !== null ? strtolower(trim($requestedRep)) : '';
    if ($req !== '' && repsDashIsActiveSalesUsername($req)) {
        // Canonical casing from DB
        foreach (repsDashSalesUsernames() as $u) {
            if (strtolower($u) === $req) {
                return ['assigned' => $u, 'assign_source' => 'referral', 'status' => 'claimed'];
            }
        }
    }
    $rr = repsDashNextRoundRobinSalesUsername();
    if ($rr === null) {
        return ['assigned' => null, 'assign_source' => 'none', 'status' => 'open'];
    }
    return ['assigned' => $rr, 'assign_source' => 'round_robin', 'status' => 'claimed'];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string,id?:int,lead?:array<string,mixed>}
 */
function repsDashCreateApplyLead(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $path = trim((string) ($data['path'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $metro = trim((string) ($data['metro'] ?? ''));
    $joinKind = (string) ($data['join_kind'] ?? '');
    $expectations = !empty($data['expectations_ack']) ? 1 : 0;
    $requestedRep = isset($data['affiliate_code']) ? (string) $data['affiliate_code'] : null;

    if ($name === '' || $phone === '' || $email === '') {
        return ['ok' => false, 'error' => 'Name, phone, and email are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email.'];
    }
    if ($expectations !== 1) {
        return ['ok' => false, 'error' => 'You must acknowledge expectations before submitting.'];
    }

    if ($joinKind === '') {
        if ($path === 'affiliate' || ($data['join_kind'] ?? '') === 'affiliate') {
            $joinKind = 'affiliate';
            $path = $path !== '' ? $path : 'affiliate';
        } else {
            if (!in_array($path, ['on_job', 'at_home', 'company'], true)) {
                return ['ok' => false, 'error' => 'Invalid capture path.'];
            }
            $joinKind = repsDashJoinKindFromPath($path);
        }
    }
    if (!in_array($joinKind, ['operator', 'shop', 'affiliate'], true)) {
        return ['ok' => false, 'error' => 'Invalid join kind.'];
    }

    $assign = repsDashResolveLeadAssignment($joinKind, $requestedRep);
    $now = gmdate('Y-m-d H:i:s');

    $stmt = repsDashDb()->prepare(
        'INSERT INTO apply_leads (
            name, phone, email, path, notes, status, assigned_sales_rep,
            join_kind, assign_source, metro, expectations_ack, last_event_at, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $phone,
        $email,
        $path,
        $notes,
        $assign['status'],
        $assign['assigned'],
        $joinKind,
        $assign['assign_source'],
        $metro,
        $expectations,
        $now,
        $now,
        $now,
    ]);
    $id = (int) repsDashDb()->lastInsertId();

    $body = 'Lead created (' . $joinKind . ')';
    if ($assign['assign_source'] === 'referral') {
        $body .= ' · referred to @' . $assign['assigned'];
    } elseif ($assign['assign_source'] === 'round_robin') {
        $body .= ' · round-robin to @' . $assign['assigned'];
    } else {
        $body .= ' · admin queue';
    }
    repsDashAddLeadEvent($id, 'created', $body, null);
    if ($assign['assigned'] !== null) {
        repsDashAddLeadEvent($id, 'assigned', 'Assigned to @' . $assign['assigned'] . ' (' . $assign['assign_source'] . ')', null);
    }

    $lead = repsDashFindApplyLead($id);
    return ['ok' => true, 'id' => $id, 'lead' => $lead];
}

/**
 * @return array{ok:bool,error?:string,id?:int}
 */
function repsDashAddLeadEvent(int $leadId, string $eventType, string $body, ?int $actorUserId): array
{
    $allowed = ['created', 'assigned', 'note', 'called', 'status', 'graduated'];
    if (!in_array($eventType, $allowed, true)) {
        return ['ok' => false, 'error' => 'Invalid event type.'];
    }
    if (repsDashFindApplyLead($leadId) === null) {
        return ['ok' => false, 'error' => 'Lead not found.'];
    }
    $stmt = repsDashDb()->prepare(
        'INSERT INTO lead_events (lead_id, actor_user_id, event_type, body, created_at)
         VALUES (?, ?, ?, ?, datetime(\'now\'))'
    );
    $stmt->execute([$leadId, $actorUserId, $eventType, $body]);
    $eventId = (int) repsDashDb()->lastInsertId();
    repsDashDb()->prepare(
        'UPDATE apply_leads SET last_event_at = datetime(\'now\'), updated_at = datetime(\'now\') WHERE id = ?'
    )->execute([$leadId]);

    repsDashEmitLeadWebhook($eventType, $leadId, $actorUserId, $body);
    return ['ok' => true, 'id' => $eventId];
}

/** @return list<array<string, mixed>> */
function repsDashListLeadEvents(int $leadId, int $limit = 100): array
{
    $stmt = repsDashDb()->prepare(
        'SELECT * FROM lead_events WHERE lead_id = ? ORDER BY datetime(created_at) DESC, id DESC LIMIT ?'
    );
    $stmt->bindValue(1, $leadId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

/**
 * Affiliate partner leads are admin/ops only — never sales.
 */
function repsDashCanViewAffiliateLeads(array $user): bool
{
    return in_array((string) ($user['role'] ?? ''), ['admin', 'ops'], true);
}

/**
 * Whether this user may open a given lead row.
 */
function repsDashCanViewLead(array $user, array $lead): bool
{
    $role = (string) ($user['role'] ?? '');
    $kind = (string) ($lead['join_kind'] ?? 'operator');
    if ($kind === 'affiliate') {
        return repsDashCanViewAffiliateLeads($user);
    }
    if (in_array($role, ['admin', 'ops'], true)) {
        return true;
    }
    if ($role === 'sales') {
        $assignee = (string) ($lead['assigned_sales_rep'] ?? '');
        return $assignee === '' || $assignee === (string) ($user['username'] ?? '');
    }
    return false;
}

/**
 * @return list<array<string, mixed>>
 */
function repsDashListLeadFeedForUser(array $user, int $limit = 20): array
{
    $role = (string) ($user['role'] ?? '');
    $username = (string) ($user['username'] ?? '');
    $limit = max(1, min(100, $limit));

    if (in_array($role, ['admin', 'ops'], true)) {
        $sql = 'SELECT e.*, l.name AS lead_name, l.join_kind, l.assigned_sales_rep
                FROM lead_events e
                INNER JOIN apply_leads l ON l.id = e.lead_id
                ORDER BY datetime(e.created_at) DESC, e.id DESC
                LIMIT ' . (int) $limit;
        return repsDashDb()->query($sql)->fetchAll() ?: [];
    }

    if ($role === 'sales') {
        $stmt = repsDashDb()->prepare(
            "SELECT e.*, l.name AS lead_name, l.join_kind, l.assigned_sales_rep
             FROM lead_events e
             INNER JOIN apply_leads l ON l.id = e.lead_id
             WHERE l.assigned_sales_rep = ?
               AND l.join_kind != 'affiliate'
             ORDER BY datetime(e.created_at) DESC, e.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $username);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
    return [];
}

function repsDashLeadsSeenMetaKey(int $userId): string
{
    return 'leads_seen_at_' . $userId;
}

function repsDashMarkLeadsSeen(array $user): void
{
    $id = (int) ($user['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    repsDashAppMetaSet(repsDashLeadsSeenMetaKey($id), gmdate('Y-m-d H:i:s'));
}

function repsDashLeadsBadgeCount(array $user): int
{
    $role = (string) ($user['role'] ?? '');
    if (!in_array($role, ['admin', 'ops', 'sales'], true)) {
        return 0;
    }
    $seen = repsDashAppMetaGet(repsDashLeadsSeenMetaKey((int) $user['id']), '1970-01-01 00:00:00');
    if ($role === 'sales') {
        $stmt = repsDashDb()->prepare(
            "SELECT COUNT(*) FROM apply_leads
             WHERE assigned_sales_rep = ?
               AND join_kind != 'affiliate'
               AND status IN ('open','claimed')
               AND datetime(COALESCE(last_event_at, created_at)) > datetime(?)"
        );
        $stmt->execute([(string) $user['username'], $seen]);
        return (int) $stmt->fetchColumn();
    }
    $stmt = repsDashDb()->prepare(
        "SELECT COUNT(*) FROM apply_leads
         WHERE status IN ('open','claimed')
           AND datetime(COALESCE(last_event_at, created_at)) > datetime(?)"
    );
    $stmt->execute([$seen]);
    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array<string, mixed>>
 */
function repsDashListApplyLeadsForUser(
    array $user,
    ?string $status = null,
    ?string $joinKind = null,
    bool $myQueueOnly = false,
    ?string $path = null
): array {
    $role = (string) ($user['role'] ?? '');
    $sql = 'SELECT * FROM apply_leads WHERE 1=1';
    $params = [];

    if ($role === 'sales') {
        // Sales: own operator/shop queue only — never affiliate partner leads.
        $sql .= " AND assigned_sales_rep = ? AND join_kind != 'affiliate'";
        $params[] = (string) $user['username'];
        // Ignore affiliate kind/path filters from the query string.
        if ($joinKind === 'affiliate' || $path === 'affiliate') {
            return [];
        }
    } elseif ($myQueueOnly && in_array($role, ['admin', 'ops'], true)) {
        $sql .= ' AND assigned_sales_rep = ?';
        $params[] = (string) $user['username'];
    } elseif (!in_array($role, ['admin', 'ops'], true)) {
        return [];
    }

    if ($status !== null && $status !== '') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($joinKind !== null && $joinKind !== '') {
        if ($joinKind === 'affiliate' && !repsDashCanViewAffiliateLeads($user)) {
            return [];
        }
        $sql .= ' AND join_kind = ?';
        $params[] = $joinKind;
    }
    if ($path !== null && $path !== '') {
        if ($path === 'affiliate' && !repsDashCanViewAffiliateLeads($user)) {
            return [];
        }
        $sql .= ' AND path = ?';
        $params[] = $path;
    }
    $sql .= ' ORDER BY datetime(COALESCE(last_event_at, created_at)) DESC, id DESC';
    $stmt = repsDashDb()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
    return array_map('repsDashApplyLeadRowShape', $rows);
}

function repsDashCanGraduateLead(array $user, array $lead): bool
{
    if (!empty($lead['graduated_user_id'])) {
        return false;
    }
    $role = (string) ($user['role'] ?? '');
    $kind = (string) ($lead['join_kind'] ?? 'operator');
    if ($kind === 'affiliate') {
        return in_array($role, ['admin', 'ops'], true);
    }
    if (in_array($role, ['admin', 'ops'], true)) {
        return true;
    }
    if ($role === 'sales') {
        return ($lead['assigned_sales_rep'] ?? null) === ($user['username'] ?? '');
    }
    return false;
}

/**
 * @return array{ok:bool,error?:string,user?:array<string,mixed>,temp_password?:string}
 */
function repsDashGraduateLeadToUser(int $leadId, array $actor): array
{
    $lead = repsDashFindApplyLead($leadId);
    if ($lead === null) {
        return ['ok' => false, 'error' => 'Lead not found.'];
    }
    // Idempotent: already graduated — return existing seat (temp password not re-shown).
    if (!empty($lead['graduated_user_id'])) {
        $existing = repsDashFindUserById((int) $lead['graduated_user_id']);
        return ['ok' => true, 'user' => $existing, 'temp_password' => null];
    }
    if (!repsDashCanGraduateLead($actor, $lead)) {
        return ['ok' => false, 'error' => 'Not allowed to graduate this lead.'];
    }

    $role = repsDashGraduateRoleForJoinKind((string) $lead['join_kind']);
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', explode('@', (string) $lead['email'])[0] ?? '') ?: '');
    if (strlen($base) < 2) {
        $base = 'user' . $leadId;
    }
    $username = substr($base, 0, 32);
    $n = 0;
    while (repsDashFindUserByUsername($username) !== null) {
        $n++;
        $username = substr($base, 0, 28) . $n;
    }
    $temp = bin2hex(random_bytes(4)) . 'A1!';
    $created = repsDashCreateUser([
        'username' => $username,
        'display_name' => (string) $lead['name'],
        'email' => (string) $lead['email'],
        'role' => $role,
        'password' => $temp,
    ]);
    if (empty($created['ok'])) {
        return ['ok' => false, 'error' => $created['error'] ?? 'Could not create user.'];
    }
    $userId = (int) $created['id'];
    repsDashDb()->prepare(
        'UPDATE apply_leads SET graduated_user_id = ?, status = ?, updated_at = datetime(\'now\'), last_event_at = datetime(\'now\')
         WHERE id = ?'
    )->execute([$userId, 'closed', $leadId]);
    repsDashAddLeadEvent(
        $leadId,
        'graduated',
        'Graduated to @' . $username . ' (' . $role . ')',
        (int) ($actor['id'] ?? 0) ?: null
    );
    $user = repsDashFindUserById($userId);
    return ['ok' => true, 'user' => $user, 'temp_password' => $temp];
}

function repsDashWebhookPayload(string $event, int $leadId, ?int $actorUserId, string $body): string
{
    $lead = repsDashFindApplyLead($leadId);
    $actorName = null;
    if ($actorUserId) {
        $u = repsDashFindUserById($actorUserId);
        $actorName = $u['username'] ?? null;
    }
    $payload = [
        'event' => $event,
        'lead_id' => $leadId,
        'join_kind' => $lead['join_kind'] ?? null,
        'assigned_sales_rep' => $lead['assigned_sales_rep'] ?? null,
        'actor_username' => $actorName,
        'body' => $body,
        'occurred_at' => gmdate('c'),
        'lead' => $lead === null ? null : [
            'name' => $lead['name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
            'status' => $lead['status'],
            'metro' => $lead['metro'] ?? '',
            'path' => $lead['path'] ?? '',
        ],
    ];
    return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}';
}

function repsDashWebhookConfig(): array
{
    $url = (string) (getenv('REPS_LEADS_WEBHOOK_URL') ?: '');
    if ($url === '' && defined('REPS_LEADS_WEBHOOK_URL')) {
        $url = (string) REPS_LEADS_WEBHOOK_URL;
    }
    $secret = (string) (getenv('REPS_LEADS_WEBHOOK_SECRET') ?: '');
    if ($secret === '' && defined('REPS_LEADS_WEBHOOK_SECRET')) {
        $secret = (string) REPS_LEADS_WEBHOOK_SECRET;
    }
    return ['url' => $url, 'secret' => $secret];
}

function repsDashEmitLeadWebhook(string $event, int $leadId, ?int $actorUserId, string $body): bool
{
    $cfg = repsDashWebhookConfig();
    $url = $cfg['url'];
    if ($url === '') {
        return false;
    }
    $json = repsDashWebhookPayload($event, $leadId, $actorUserId, $body);
    $headers = "Content-Type: application/json\r\n";
    if ($cfg['secret'] !== '') {
        $sig = hash_hmac('sha256', $json, $cfg['secret']);
        $headers .= 'X-Reps-Signature: sha256=' . $sig . "\r\n";
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $json,
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false;
}
