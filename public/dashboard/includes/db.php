<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * SQLite access + idempotent migrate/seed for dashboard users.
 */

function repsDashDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = REPS_DASH_DB_PATH;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create DB directory: ' . $dir);
        }
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    repsDashDbMigrate($pdo);
    return $pdo;
}

function repsDashDbMigrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )'
    );

    $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_map('strval', $applied ?: []);

    if (!in_array('001_users', $applied, true)) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL COLLATE NOCASE UNIQUE,
                email TEXT NOT NULL DEFAULT \'\',
                password_hash TEXT NOT NULL,
                display_name TEXT NOT NULL,
                role TEXT NOT NULL,
                skin_slug TEXT,
                shop_id INTEGER,
                operator_id INTEGER,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active)');
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute(['001_users']);
    }

    repsDashDbSeedUsers($pdo);
}

/**
 * Seed Slice A demo seats if missing (idempotent by username).
 *
 * @return list<array<string, mixed>>
 */
function repsDashSeedAccountDefs(): array
{
    return [
        [
            'username' => 'mark',
            'display_name' => 'Mark Hopkins',
            'role' => 'admin',
            'skin_slug' => null,
            'email' => 'mark@decisionsciencecorp.com',
        ],
        [
            'username' => 'ops',
            'display_name' => 'Ops Desk',
            'role' => 'ops',
            'skin_slug' => 'brutalist',
            'email' => 'ops@decisionsciencecorp.com',
        ],
        [
            'username' => 'jim',
            'display_name' => 'Jim (Affiliate)',
            'role' => 'sales',
            'skin_slug' => null,
            'email' => 'jim@example.com',
        ],
        [
            'username' => 'seven',
            'display_name' => 'Seven Stone',
            'role' => 'sales',
            'skin_slug' => 'obsidian',
            'email' => 'seven@example.com',
        ],
        [
            'username' => 'chuck',
            'display_name' => 'Chuck',
            'role' => 'sales',
            'skin_slug' => 'ledger',
            'email' => 'chuck@example.com',
        ],
        [
            'username' => 'maria',
            'display_name' => 'Maria Lopez',
            'role' => 'business_owner',
            'skin_slug' => 'hey',
            'email' => 'maria@fleetwash.example',
            'shop_id' => 104,
        ],
        [
            'username' => 'alex',
            'display_name' => 'Alex Rivera',
            'role' => 'employee',
            'skin_slug' => null,
            'email' => 'alex@fleetwash.example',
            'shop_id' => 104,
            'operator_id' => 1,
        ],
        [
            'username' => 'pat',
            'display_name' => 'Pat Solo',
            'role' => 'individual',
            'skin_slug' => null,
            'email' => 'pat@example.com',
            'operator_id' => 9,
        ],
        [
            'username' => 'agent',
            'display_name' => 'Agent Bot',
            'role' => 'agent',
            'skin_slug' => 'brutalist',
            'email' => 'agent@decisionsciencecorp.com',
        ],
    ];
}

function repsDashDbSeedUsers(PDO $pdo): void
{
    $hash = password_hash(REPS_DASH_SEED_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, display_name, role, skin_slug, shop_id, operator_id)
         SELECT ?, ?, ?, ?, ?, ?, ?, ?
         WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = ? COLLATE NOCASE)'
    );
    foreach (repsDashSeedAccountDefs() as $row) {
        $username = (string) $row['username'];
        $stmt->execute([
            $username,
            (string) ($row['email'] ?? ''),
            $hash,
            (string) $row['display_name'],
            (string) $row['role'],
            $row['skin_slug'] ?? null,
            isset($row['shop_id']) ? (int) $row['shop_id'] : null,
            isset($row['operator_id']) ? (int) $row['operator_id'] : null,
            $username,
        ]);
    }
}

/** @return array<string, mixed>|null */
function repsDashUserRowToSessionShape(?array $row): ?array
{
    if ($row === null) {
        return null;
    }
    $out = [
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'display_name' => (string) $row['display_name'],
        'role' => (string) $row['role'],
        'skin_slug' => $row['skin_slug'] !== null && $row['skin_slug'] !== '' ? (string) $row['skin_slug'] : null,
        'email' => (string) ($row['email'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 1) === 1,
    ];
    if (isset($row['shop_id']) && $row['shop_id'] !== null && $row['shop_id'] !== '') {
        $out['shop_id'] = (int) $row['shop_id'];
    }
    if (isset($row['operator_id']) && $row['operator_id'] !== null && $row['operator_id'] !== '') {
        $out['operator_id'] = (int) $row['operator_id'];
    }
    return $out;
}

/** @return list<array<string, mixed>> */
function repsDashListUsers(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM users';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY role, username';
    $rows = repsDashDb()->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $shaped = repsDashUserRowToSessionShape($row);
        if ($shaped) {
            $out[] = $shaped;
        }
    }
    return $out;
}

/** @return array<string, mixed>|null */
function repsDashFindUserByUsername(string $username): ?array
{
    $stmt = repsDashDb()->prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ? repsDashUserRowToSessionShape($row) : null;
}

/** @return array<string, mixed>|null */
function repsDashFindUserById(int $id): ?array
{
    $stmt = repsDashDb()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? repsDashUserRowToSessionShape($row) : null;
}

/** @return array<string, mixed>|null raw row including password_hash */
function repsDashFindUserRawByUsername(string $username): ?array
{
    $stmt = repsDashDb()->prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string,id?:int}
 */
function repsDashCreateUser(array $data): array
{
    $username = strtolower(trim((string) ($data['username'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $role = (string) ($data['role'] ?? '');
    $display = trim((string) ($data['display_name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));

    if ($username === '' || !preg_match('/^[a-z0-9._-]{2,40}$/', $username)) {
        return ['ok' => false, 'error' => 'Username must be 2–40 chars: letters, numbers, ._-'];
    }
    if ($display === '') {
        return ['ok' => false, 'error' => 'Display name is required.'];
    }
    if (!repsDashIsValidRole($role)) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }
    if (strlen($password) < REPS_DASH_PASSWORD_MIN) {
        return ['ok' => false, 'error' => 'Password must be at least ' . REPS_DASH_PASSWORD_MIN . ' characters.'];
    }
    if (repsDashFindUserByUsername($username) !== null) {
        return ['ok' => false, 'error' => 'That username is already taken.'];
    }

    $shopId = isset($data['shop_id']) && $data['shop_id'] !== '' && $data['shop_id'] !== null
        ? (int) $data['shop_id'] : null;
    $operatorId = isset($data['operator_id']) && $data['operator_id'] !== '' && $data['operator_id'] !== null
        ? (int) $data['operator_id'] : null;
    $skin = isset($data['skin_slug']) && $data['skin_slug'] !== ''
        ? (string) $data['skin_slug'] : null;

    $stmt = repsDashDb()->prepare(
        'INSERT INTO users (username, email, password_hash, display_name, role, skin_slug, shop_id, operator_id, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $display,
        $role,
        $skin,
        $shopId,
        $operatorId,
    ]);
    return ['ok' => true, 'id' => (int) repsDashDb()->lastInsertId()];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string}
 */
function repsDashUpdateUser(int $id, array $data): array
{
    $existing = repsDashFindUserById($id);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $display = trim((string) ($data['display_name'] ?? $existing['display_name']));
    $email = trim((string) ($data['email'] ?? $existing['email']));
    $role = (string) ($data['role'] ?? $existing['role']);
    $isActive = array_key_exists('is_active', $data)
        ? ((int) $data['is_active'] === 1 ? 1 : 0)
        : ($existing['is_active'] ? 1 : 0);

    if ($display === '') {
        return ['ok' => false, 'error' => 'Display name is required.'];
    }
    if (!repsDashIsValidRole($role)) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }

    // Do not deactivate the last active admin.
    if ($existing['role'] === 'admin' && ($role !== 'admin' || $isActive === 0)) {
        $n = (int) repsDashDb()->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id != " . (int) $id
        )->fetchColumn();
        if ($n < 1) {
            return ['ok' => false, 'error' => 'Cannot remove or deactivate the last active admin.'];
        }
    }

    $shopId = array_key_exists('shop_id', $data)
        ? ($data['shop_id'] !== '' && $data['shop_id'] !== null ? (int) $data['shop_id'] : null)
        : ($existing['shop_id'] ?? null);
    $operatorId = array_key_exists('operator_id', $data)
        ? ($data['operator_id'] !== '' && $data['operator_id'] !== null ? (int) $data['operator_id'] : null)
        : ($existing['operator_id'] ?? null);
    $skin = array_key_exists('skin_slug', $data)
        ? ($data['skin_slug'] !== '' && $data['skin_slug'] !== null ? (string) $data['skin_slug'] : null)
        : ($existing['skin_slug'] ?? null);

    $stmt = repsDashDb()->prepare(
        'UPDATE users SET display_name = ?, email = ?, role = ?, skin_slug = ?, shop_id = ?, operator_id = ?,
         is_active = ?, updated_at = datetime(\'now\') WHERE id = ?'
    );
    $stmt->execute([$display, $email, $role, $skin, $shopId, $operatorId, $isActive, $id]);
    return ['ok' => true];
}

/** @return array{ok:bool,error?:string} */
function repsDashSetUserPassword(int $id, string $password): array
{
    if (strlen($password) < REPS_DASH_PASSWORD_MIN) {
        return ['ok' => false, 'error' => 'Password must be at least ' . REPS_DASH_PASSWORD_MIN . ' characters.'];
    }
    if (repsDashFindUserById($id) === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }
    $stmt = repsDashDb()->prepare(
        'UPDATE users SET password_hash = ?, updated_at = datetime(\'now\') WHERE id = ?'
    );
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    return ['ok' => true];
}

function repsDashPersistUserSkin(int $userId, string $skinSlug): void
{
    $stmt = repsDashDb()->prepare(
        'UPDATE users SET skin_slug = ?, updated_at = datetime(\'now\') WHERE id = ?'
    );
    $stmt->execute([$skinSlug, $userId]);
}
