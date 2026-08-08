<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Local operator rows keyed by Shift team user_id (UUID string).
 */

function repsOperatorsEnsureSchema(): void
{
    // Full schema lives in migration 006; keep a minimal CREATE for early call sites.
    $pdo = repsDashDb();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS operators (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shift_user_id TEXT NOT NULL UNIQUE,
            display_name TEXT NOT NULL DEFAULT \'\',
            email TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_operators_shift ON operators(shift_user_id)');
}

/**
 * Upsert operator from a Shift hours-feed session row.
 *
 * @param array<string, mixed> $session
 * @return int Local operators.id (0 if no user_id)
 */
function repsOperatorEnsureFromShiftSession(array $session): int
{
    $shiftUserId = trim((string) ($session['user_id'] ?? ''));
    if ($shiftUserId === '') {
        return 0;
    }
    $first = trim((string) ($session['first_name'] ?? ''));
    $last = trim((string) ($session['last_name'] ?? ''));
    $name = trim($first . ' ' . $last);
    if ($name === '') {
        $name = $shiftUserId;
    }
    return repsOperatorEnsure($shiftUserId, $name);
}

function repsOperatorEnsure(string $shiftUserId, string $displayName = '', string $email = ''): int
{
    repsOperatorsEnsureSchema();
    $shiftUserId = trim($shiftUserId);
    if ($shiftUserId === '') {
        return 0;
    }
    $pdo = repsDashDb();
    $stmt = $pdo->prepare('SELECT id, display_name FROM operators WHERE shift_user_id = ? LIMIT 1');
    $stmt->execute([$shiftUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $id = (int) $row['id'];
        if ($displayName !== '' && $displayName !== (string) $row['display_name']) {
            $pdo->prepare(
                'UPDATE operators SET display_name = ?, updated_at = datetime(\'now\') WHERE id = ?'
            )->execute([$displayName, $id]);
        }
        if ($email !== '') {
            $pdo->prepare(
                'UPDATE operators SET email = ?, updated_at = datetime(\'now\') WHERE id = ?'
            )->execute([$email, $id]);
        }
        return $id;
    }
    $pdo->prepare(
        'INSERT INTO operators (shift_user_id, display_name, email) VALUES (?, ?, ?)'
    )->execute([$shiftUserId, $displayName !== '' ? $displayName : $shiftUserId, $email]);
    return (int) $pdo->lastInsertId();
}

/** @return array<string, mixed>|null */
function repsOperatorById(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    repsOperatorsEnsureSchema();
    $stmt = repsDashDb()->prepare('SELECT * FROM operators WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @return array<string, mixed>|null */
function repsOperatorByShiftUserId(string $shiftUserId): ?array
{
    $shiftUserId = trim($shiftUserId);
    if ($shiftUserId === '') {
        return null;
    }
    $stmt = repsDashDb()->prepare('SELECT * FROM operators WHERE shift_user_id = ? LIMIT 1');
    $stmt->execute([$shiftUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Map DB operator + shop name → repository/mock-shaped row.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function repsOperatorToRepoRow(array $row, string $shopName = ''): array
{
    $shopId = isset($row['shop_id']) && $row['shop_id'] !== null && $row['shop_id'] !== ''
        ? (int) $row['shop_id']
        : 0;
    $last = (string) ($row['last_session_at'] ?? '');
    if ($last === '') {
        $last = '—';
    }
    return [
        'id' => (int) $row['id'],
        'name' => (string) ($row['display_name'] ?? ''),
        'shop_id' => $shopId,
        'shop' => $shopName !== '' ? $shopName : ($shopId > 0 ? ('Shop #' . $shopId) : '— (individual)'),
        'phone' => (string) ($row['phone'] ?? ''),
        'status' => (string) ($row['status'] ?? 'active'),
        'matched' => !empty($row['matched_user_id']),
        'matched_user_id' => isset($row['matched_user_id']) ? (int) $row['matched_user_id'] : null,
        'assigned_sales_rep' => $row['assigned_sales_rep'] ?? null,
        'accepted_7d' => (float) ($row['accepted_7d'] ?? 0),
        'rejected_7d' => (float) ($row['rejected_7d'] ?? 0),
        'last_session' => $last,
        'shift_user_id' => (string) ($row['shift_user_id'] ?? ''),
        'partner_code' => (string) ($row['partner_code'] ?? ''),
    ];
}
