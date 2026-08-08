<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Slice D — JSON API helpers (auth, response, scoped entity payloads).
 */

/**
 * @param array<string, mixed> $payload
 */
function repsApiJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<string, mixed> $extra
 */
function repsApiError(string $code, string $message, int $status = 400, array $extra = []): never
{
    repsApiJson(array_merge(['ok' => false, 'error' => $code, 'message' => $message], $extra), $status);
}

function repsApiExtractBearerOrKey(): string
{
    $hdr = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($hdr !== '') {
        return trim($hdr);
    }
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
        return trim($m[1]);
    }
    $q = trim((string) ($_GET['api_key'] ?? ''));
    // Query-string keys are rejected — same bar as Tasks.
    if ($q !== '') {
        repsApiError('api_key_query_forbidden', 'Pass API key via X-API-Key or Authorization: Bearer only.', 400);
    }
    return '';
}

function repsApiHashKey(string $raw): string
{
    return hash('sha256', $raw);
}

/**
 * @return array{ok: bool, key?: string, id?: int, preview?: string, error?: string}
 */
function repsApiCreateKey(int $userId, string $name, ?int $createdByUserId = null): array
{
    $user = repsDashFindUserById($userId);
    if ($user === null || empty($user['is_active'])) {
        return ['ok' => false, 'error' => 'user_not_found'];
    }
    $name = trim($name);
    if ($name === '') {
        $name = 'default';
    }
    if (strlen($name) > 80) {
        return ['ok' => false, 'error' => 'name_too_long'];
    }
    $raw = 'reps_' . bin2hex(random_bytes(24));
    $hash = repsApiHashKey($raw);
    $preview = substr($raw, 0, 10) . '…' . substr($raw, -4);
    $pdo = repsDashDb();
    $stmt = $pdo->prepare(
        'INSERT INTO api_keys (user_id, key_name, api_key_hash, key_preview, created_by_user_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $name, $hash, $preview, $createdByUserId]);
    return [
        'ok' => true,
        'id' => (int) $pdo->lastInsertId(),
        'key' => $raw,
        'preview' => $preview,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function repsApiListKeysForUser(int $userId, bool $includeRevoked = false): array
{
    $sql = 'SELECT id, user_id, key_name, key_preview, created_by_user_id, last_used_at, revoked_at, created_at
            FROM api_keys WHERE user_id = ?';
    if (!$includeRevoked) {
        $sql .= ' AND (revoked_at IS NULL OR revoked_at = \'\')';
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = repsDashDb()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function repsApiRevokeKey(int $keyId, int $actorUserId): array
{
    $stmt = repsDashDb()->prepare('SELECT * FROM api_keys WHERE id = ? LIMIT 1');
    $stmt->execute([$keyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if (!empty($row['revoked_at'])) {
        return ['ok' => true, 'already' => true];
    }
    $actor = repsDashFindUserById($actorUserId);
    $ownerId = (int) $row['user_id'];
    $isAdmin = $actor && ($actor['role'] ?? '') === 'admin';
    if (!$isAdmin && $ownerId !== $actorUserId) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    repsDashDb()->prepare(
        "UPDATE api_keys SET revoked_at = datetime('now') WHERE id = ?"
    )->execute([$keyId]);
    return ['ok' => true];
}

/**
 * Resolve user from API key. Updates last_used_at (throttled ~60s).
 *
 * @return array<string, mixed>|null
 */
function repsApiUserFromKey(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) < 16) {
        return null;
    }
    $hash = repsApiHashKey($raw);
    $stmt = repsDashDb()->prepare(
        "SELECT ak.id AS key_id, ak.last_used_at, u.*
         FROM api_keys ak
         JOIN users u ON u.id = ak.user_id
         WHERE ak.api_key_hash = ?
           AND (ak.revoked_at IS NULL OR ak.revoked_at = '')
           AND u.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $keyId = (int) $row['key_id'];
    $last = (string) ($row['last_used_at'] ?? '');
    $touch = $last === '' || (strtotime($last) !== false && (time() - (int) strtotime($last)) >= 60);
    if ($touch) {
        repsDashDb()->prepare(
            "UPDATE api_keys SET last_used_at = datetime('now') WHERE id = ?"
        )->execute([$keyId]);
    }
    unset($row['key_id'], $row['last_used_at'], $row['password_hash']);
    $user = repsDashUserRowToSessionShape($row);
    if ($user === null) {
        return null;
    }
    $user['api_auth'] = true;
    $user['api_key_id'] = $keyId;
    return $user;
}

/**
 * Require session cookie and/or API key. Never both conflicting users —
 * API key wins when present.
 *
 * @return array<string, mixed>
 */
function repsApiRequireUser(): array
{
    $raw = repsApiExtractBearerOrKey();
    if ($raw !== '') {
        $user = repsApiUserFromKey($raw);
        if ($user === null) {
            repsApiError('unauthorized', 'Invalid or revoked API key.', 401);
        }
        return $user;
    }
    $user = repsDashCurrentUser();
    if ($user === null) {
        repsApiError('unauthorized', 'Login session or API key required.', 401);
    }
    $user['api_auth'] = false;
    return $user;
}

/**
 * Agent seats are API principals — elevate to ops-equivalent book for reads.
 *
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function repsApiDataUser(array $user): array
{
    if (($user['role'] ?? '') === 'agent' && !empty($user['api_auth'])) {
        $elevated = $user;
        $elevated['role'] = 'ops';
        $elevated['_agent_elevated'] = true;
        return $elevated;
    }
    return $user;
}

/**
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function repsApiMePayload(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'username' => (string) ($user['username'] ?? ''),
        'display_name' => (string) ($user['display_name'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'shop_id' => isset($user['shop_id']) ? (int) $user['shop_id'] : null,
        'operator_id' => isset($user['operator_id']) ? (int) $user['operator_id'] : null,
        'auth' => !empty($user['api_auth']) ? 'api_key' : 'session',
        'live_data' => repsDashLiveDataEnabled(),
    ];
}

/**
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function repsApiMoneySummary(array $user): array
{
    $dataUser = repsApiDataUser($user);
    $pulse = repsDashPulseForUser($dataUser);
    $role = (string) ($user['role'] ?? '');
    $mode = repsDashMoneyModeForRole($role === 'agent' ? 'ops' : $role);
    $ledger = null;
    if (in_array($role, ['admin', 'ops', 'agent'], true) || $mode === 'dsc_command') {
        $ledger = repsLedgerTotals();
    }
    return [
        'mode' => $mode,
        'hourly_rate' => repsDashMoneyHourlyRate(),
        'pulse' => $pulse,
        'ledger' => $ledger,
        'live_data' => repsDashLiveDataEnabled(),
        'shops_visible' => count(repsDashShopsForUser($dataUser)),
        'operators_visible' => count(repsDashOperatorsForUser($dataUser)),
        'sessions_visible' => count(repsDashSessionsForUser($dataUser)),
    ];
}
