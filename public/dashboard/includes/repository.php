<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Data access seam — live SQLite when shift.live_data is on (or sessions exist).
 * Mock fixtures are Slice A / local demo only — never used as a silent fill-in
 * when the live book is intentionally empty.
 */

require_once __DIR__ . '/shift-sync.php';

/** @return list<array<string, mixed>> */
function repsDashAllShops(): array
{
    if (repsDashLiveDataEnabled()) {
        return repsDashDbShopsAsRows();
    }
    $shops = repsDashMockShops();
    try {
        $overlays = repsDashShopNotesMap();
    } catch (Throwable $e) {
        return $shops;
    }
    if ($overlays === []) {
        return $shops;
    }
    foreach ($shops as &$shop) {
        $id = (int) $shop['id'];
        if (array_key_exists($id, $overlays)) {
            $shop['notes'] = $overlays[$id];
        }
    }
    unset($shop);
    return $shops;
}

/** @return list<array<string, mixed>> */
function repsDashDbShopsAsRows(): array
{
    $pdo = repsDashDb();
    $rows = $pdo->query(
        "SELECT * FROM shops WHERE status IS NULL OR status != 'retired' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        return [];
    }
    $notes = [];
    try {
        $notes = repsDashShopNotesMap();
    } catch (Throwable $e) {
        $notes = [];
    }
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $opCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM operators WHERE shop_id = ' . $id
        )->fetchColumn();
        $acc = (float) $pdo->query(
            'SELECT COALESCE(SUM(accepted_7d),0) FROM operators WHERE shop_id = ' . $id
        )->fetchColumn();
        $rej = (float) $pdo->query(
            'SELECT COALESCE(SUM(rejected_7d),0) FROM operators WHERE shop_id = ' . $id
        )->fetchColumn();
        $den = $acc + $rej;
        $out[] = [
            'id' => $id,
            'name' => (string) $r['name'],
            'status' => (string) $r['status'],
            'assigned_sales_rep' => $r['assigned_sales_rep'] ?? null,
            'contact_name' => (string) ($r['contact_name'] ?? ''),
            'contact_phone' => (string) ($r['contact_phone'] ?? ''),
            'agreed_shop_split' => (float) ($r['agreed_shop_split'] ?? 0.5),
            'notes' => array_key_exists($id, $notes) ? $notes[$id] : (string) ($r['notes'] ?? ''),
            'operators' => $opCount,
            'accepted_hours_7d' => $acc,
            'reject_rate' => $den > 0 ? round($rej / $den, 4) : 0.0,
        ];
    }
    return $out;
}

/** @return list<array<string, mixed>> */
function repsDashAllOperators(): array
{
    if (repsDashLiveDataEnabled()) {
        return repsDashDbOperatorsAsRows();
    }
    return repsDashMockOperators();
}

/** @return list<array<string, mixed>> */
function repsDashDbOperatorsAsRows(): array
{
    $pdo = repsDashDb();
    $shopNames = [];
    foreach ($pdo->query('SELECT id, name FROM shops')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $s) {
        $shopNames[(int) $s['id']] = (string) $s['name'];
    }
    $rows = $pdo->query(
        "SELECT * FROM operators
         WHERE shift_user_id NOT LIKE 'reps-user-%'
           AND shift_user_id NOT LIKE 'sandbox-%'
           AND COALESCE(status,'') != 'retired'
         ORDER BY display_name COLLATE NOCASE"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $sid = isset($r['shop_id']) ? (int) $r['shop_id'] : 0;
        $out[] = repsOperatorToRepoRow($r, $shopNames[$sid] ?? '');
    }
    return $out;
}

/** @return list<array<string, mixed>> */
function repsDashAllSessions(): array
{
    if (repsDashLiveDataEnabled()) {
        $sessions = repsDashDbSessionsAsRows();
        if ($sessions !== []) {
            return $sessions;
        }
    }
    return repsDashMockSessions();
}

/** @return list<array<string, mixed>> */
function repsDashDbSessionsAsRows(): array
{
    $pdo = repsDashDb();
    $sql = 'SELECT s.*, o.display_name AS operator_name, sh.name AS shop_name
            FROM sessions s
            LEFT JOIN operators o ON o.id = s.operator_id
            LEFT JOIN shops sh ON sh.id = s.shop_id
            ORDER BY s.completed_at DESC, s.session_id DESC
            LIMIT 2000';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $shopId = isset($r['shop_id']) && $r['shop_id'] !== null ? (int) $r['shop_id'] : 0;
        $out[] = [
            'session_id' => (string) $r['session_id'],
            'operator_id' => (int) ($r['operator_id'] ?? 0),
            'operator' => (string) ($r['operator_name'] ?? ''),
            'shop' => (string) ($r['shop_name'] ?? ($shopId > 0 ? 'Shop #' . $shopId : '—')),
            'shop_id' => $shopId,
            'status' => (string) ($r['status'] ?? ''),
            'duration_hours' => (float) ($r['duration_hours'] ?? 0),
            'accepted_hours' => (float) ($r['accepted_hours'] ?? 0),
            'rejection_reason' => (string) ($r['rejection_reason'] ?? ''),
            'partner_code' => (string) ($r['partner_code'] ?? ''),
            'completed_at' => (string) ($r['completed_at'] ?? ''),
            'day' => (string) ($r['day'] ?? ''),
            'shift_user_id' => (string) ($r['shift_user_id'] ?? ''),
        ];
    }
    return $out;
}

function repsDashFindOperator(int $id): ?array
{
    foreach (repsDashAllOperators() as $op) {
        if ((int) $op['id'] === $id) {
            return $op;
        }
    }
    return null;
}

function repsDashFindShop(int $id): ?array
{
    foreach (repsDashAllShops() as $shop) {
        if ((int) $shop['id'] === $id) {
            return $shop;
        }
    }
    return null;
}

function repsDashFindSession(string $sessionId): ?array
{
    foreach (repsDashAllSessions() as $session) {
        if ((string) ($session['session_id'] ?? '') === $sessionId) {
            return $session;
        }
    }
    return null;
}

/** @return list<array<string, mixed>> */
function repsDashSessionsForOperator(int $operatorId): array
{
    return array_values(array_filter(
        repsDashAllSessions(),
        static fn(array $s): bool => (int) ($s['operator_id'] ?? 0) === $operatorId
    ));
}
