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

function repsDashAppMetaGet(string $key, string $default = ''): string
{
    $stmt = repsDashDb()->prepare('SELECT value FROM app_meta WHERE key = ? LIMIT 1');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function repsDashAppMetaSet(string $key, string $value): void
{
    $stmt = repsDashDb()->prepare(
        'INSERT INTO app_meta (key, value, updated_at) VALUES (?, ?, datetime(\'now\'))
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime(\'now\')'
    );
    $stmt->execute([$key, $value]);
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

    if (!in_array('002_shop_notes', $applied, true)) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shop_notes (
                shop_id INTEGER PRIMARY KEY,
                notes TEXT NOT NULL DEFAULT \'\',
                updated_by_user_id INTEGER,
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute(['002_shop_notes']);
    }

    if (!in_array('003_apply_leads', $applied, true)) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS apply_leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT \'\',
                email TEXT NOT NULL DEFAULT \'\',
                path TEXT NOT NULL DEFAULT \'\',
                notes TEXT NOT NULL DEFAULT \'\',
                status TEXT NOT NULL DEFAULT \'open\',
                assigned_sales_rep TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_apply_leads_status ON apply_leads(status)');
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute(['003_apply_leads']);
    }

    if (!in_array('004_join_funnel', $applied, true)) {
        $cols = $pdo->query('PRAGMA table_info(apply_leads)')->fetchAll(PDO::FETCH_ASSOC);
        $names = array_map(static fn($c) => (string) $c['name'], $cols ?: []);
        $add = static function (PDO $pdo, array $names, string $col, string $ddl) {
            if (!in_array($col, $names, true)) {
                $pdo->exec('ALTER TABLE apply_leads ADD COLUMN ' . $ddl);
            }
        };
        $add($pdo, $names, 'join_kind', "join_kind TEXT NOT NULL DEFAULT 'operator'");
        $add($pdo, $names, 'assign_source', "assign_source TEXT NOT NULL DEFAULT 'none'");
        $add($pdo, $names, 'metro', "metro TEXT NOT NULL DEFAULT ''");
        $add($pdo, $names, 'expectations_ack', 'expectations_ack INTEGER NOT NULL DEFAULT 0');
        $add($pdo, $names, 'graduated_user_id', 'graduated_user_id INTEGER');
        $add($pdo, $names, 'last_event_at', "last_event_at TEXT");

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS lead_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                actor_user_id INTEGER,
                event_type TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lead_events_lead ON lead_events(lead_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lead_events_created ON lead_events(created_at)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );

        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute(['004_join_funnel']);
    }

    if (!in_array('005_payouts', $applied, true)) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS settlement_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source TEXT NOT NULL,
                source_key TEXT NOT NULL,
                amount_cents INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT \'usd\',
                status TEXT NOT NULL DEFAULT \'recorded\',
                meta_json TEXT NOT NULL DEFAULT \'{}\',
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                UNIQUE(source, source_key)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_settlement_status ON settlement_events(status)');

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

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS payout_payees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id INTEGER NOT NULL,
                display_name TEXT NOT NULL DEFAULT \'\',
                email TEXT NOT NULL DEFAULT \'\',
                stripe_account_id TEXT,
                onboarding_status TEXT NOT NULL DEFAULT \'none\',
                payouts_enabled INTEGER NOT NULL DEFAULT 0,
                charges_enabled INTEGER NOT NULL DEFAULT 0,
                payouts_enabled_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                UNIQUE(entity_type, entity_id)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_payout_payees_acct ON payout_payees(stripe_account_id)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ledger_lines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hour_key TEXT NOT NULL,
                bucket TEXT NOT NULL,
                amount_cents INTEGER NOT NULL,
                hours REAL NOT NULL DEFAULT 0,
                shop_id INTEGER,
                operator_id INTEGER,
                affiliate_user_id INTEGER,
                affiliate_username TEXT,
                capture_payee TEXT,
                settlement_id INTEGER,
                status TEXT NOT NULL DEFAULT \'pending\',
                stripe_transfer_id TEXT,
                disbursement_batch_id INTEGER,
                accepted_at TEXT,
                transferred_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ledger_hour ON ledger_lines(hour_key)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ledger_status ON ledger_lines(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ledger_bucket ON ledger_lines(bucket)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS disbursement_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                label TEXT NOT NULL DEFAULT \'\',
                status TEXT NOT NULL DEFAULT \'pending\',
                line_count INTEGER NOT NULL DEFAULT 0,
                transferred_count INTEGER NOT NULL DEFAULT 0,
                skipped_count INTEGER NOT NULL DEFAULT 0,
                failed_count INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                finished_at TEXT
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS disbursement_transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                batch_id INTEGER NOT NULL,
                ledger_line_id INTEGER NOT NULL,
                stripe_transfer_id TEXT NOT NULL DEFAULT \'\',
                amount_cents INTEGER NOT NULL,
                destination TEXT NOT NULL DEFAULT \'\',
                status TEXT NOT NULL DEFAULT \'created\',
                error TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_disburse_tr_stripe ON disbursement_transfers(stripe_transfer_id)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS stripe_webhook_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT NOT NULL UNIQUE,
                type TEXT NOT NULL,
                livemode INTEGER NOT NULL DEFAULT 0,
                processed_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );

        $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        $userNames = array_map(static fn($c) => (string) $c['name'], $userCols ?: []);
        if (!in_array('stripe_account_id', $userNames, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN stripe_account_id TEXT');
        }

        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute(['005_payouts']);
    }

    repsDashDbSeedUsers($pdo);
    repsDashDbSeedApplyLeads($pdo);
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

/** @return array<int, string> shop_id => notes */
function repsDashShopNotesMap(): array
{
    $rows = repsDashDb()->query('SELECT shop_id, notes FROM shop_notes')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['shop_id']] = (string) $row['notes'];
    }
    return $out;
}

function repsDashSaveShopNotes(int $shopId, string $notes, ?int $updatedByUserId = null): void
{
    $stmt = repsDashDb()->prepare(
        'INSERT INTO shop_notes (shop_id, notes, updated_by_user_id, updated_at)
         VALUES (?, ?, ?, datetime(\'now\'))
         ON CONFLICT(shop_id) DO UPDATE SET
           notes = excluded.notes,
           updated_by_user_id = excluded.updated_by_user_id,
           updated_at = datetime(\'now\')'
    );
    $stmt->execute([$shopId, $notes, $updatedByUserId]);
}

/** @return list<array<string, mixed>> */
function repsDashSeedApplyLeadDefs(): array
{
    return [
        [
            'name' => 'Dee Patel',
            'phone' => '(214) 555-0120',
            'email' => 'dee@lakespa.example',
            'path' => 'company',
            'notes' => 'Warm intro from Jim — Lake Highlands Auto Spa. Wants Friday pitch.',
            'status' => 'open',
            'assigned_sales_rep' => null,
            'created_at' => '2026-08-03 09:14:00',
        ],
        [
            'name' => 'Chris Nguyen',
            'phone' => '(469) 555-0331',
            'email' => 'chris.n@example.com',
            'path' => 'on_job',
            'notes' => 'Detail tech — evenings after shop closes.',
            'status' => 'open',
            'assigned_sales_rep' => null,
            'created_at' => '2026-08-04 11:02:00',
        ],
        [
            'name' => 'Ava Morales',
            'phone' => '(817) 555-0442',
            'email' => 'ava.m@example.com',
            'path' => 'at_home',
            'notes' => 'Has spare Android. Asked about headset shipping.',
            'status' => 'claimed',
            'assigned_sales_rep' => 'jim',
            'created_at' => '2026-08-02 16:40:00',
        ],
        [
            'name' => 'Fleet Wash HQ inbound',
            'phone' => '(817) 555-0177',
            'email' => 'ops@fleetwash.example',
            'path' => 'company',
            'notes' => 'Second location interest — already have North Texas Fleet Wash live.',
            'status' => 'open',
            'assigned_sales_rep' => null,
            'created_at' => '2026-08-05 08:20:00',
        ],
        [
            'name' => 'Sam Ortiz',
            'phone' => '(972) 555-0555',
            'email' => 'sam.ortiz@example.com',
            'path' => 'on_job',
            'notes' => 'Closed — duplicate of existing operator lead.',
            'status' => 'closed',
            'assigned_sales_rep' => 'seven',
            'created_at' => '2026-07-28 13:05:00',
        ],
    ];
}

function repsDashDbSeedApplyLeads(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM apply_leads')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO apply_leads (name, phone, email, path, notes, status, assigned_sales_rep, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (repsDashSeedApplyLeadDefs() as $row) {
        $created = (string) $row['created_at'];
        $stmt->execute([
            $row['name'],
            $row['phone'],
            $row['email'],
            $row['path'],
            $row['notes'],
            $row['status'],
            $row['assigned_sales_rep'],
            $created,
            $created,
        ]);
    }
}

/** @return list<array<string, mixed>> */
function repsDashListApplyLeads(?string $status = null): array
{
    if ($status !== null && $status !== '') {
        $stmt = repsDashDb()->prepare(
            'SELECT * FROM apply_leads WHERE status = ? ORDER BY datetime(created_at) DESC, id DESC'
        );
        $stmt->execute([$status]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = repsDashDb()->query(
            'SELECT * FROM apply_leads ORDER BY datetime(created_at) DESC, id DESC'
        )->fetchAll();
    }
    return array_map('repsDashApplyLeadRowShape', $rows ?: []);
}

/** @return array<string, mixed>|null */
function repsDashFindApplyLead(int $id): ?array
{
    $stmt = repsDashDb()->prepare('SELECT * FROM apply_leads WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? repsDashApplyLeadRowShape($row) : null;
}

/** @param array<string, mixed> $row */
function repsDashApplyLeadRowShape(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'phone' => (string) $row['phone'],
        'email' => (string) $row['email'],
        'path' => (string) $row['path'],
        'notes' => (string) $row['notes'],
        'status' => (string) $row['status'],
        'assigned_sales_rep' => $row['assigned_sales_rep'] !== null && $row['assigned_sales_rep'] !== ''
            ? (string) $row['assigned_sales_rep'] : null,
        'join_kind' => (string) ($row['join_kind'] ?? 'operator'),
        'assign_source' => (string) ($row['assign_source'] ?? 'none'),
        'metro' => (string) ($row['metro'] ?? ''),
        'expectations_ack' => (int) ($row['expectations_ack'] ?? 0) === 1,
        'graduated_user_id' => isset($row['graduated_user_id']) && $row['graduated_user_id'] !== null && $row['graduated_user_id'] !== ''
            ? (int) $row['graduated_user_id'] : null,
        'last_event_at' => (string) ($row['last_event_at'] ?? $row['created_at'] ?? ''),
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function repsDashCountOpenApplyLeads(): int
{
    return (int) repsDashDb()->query(
        "SELECT COUNT(*) FROM apply_leads WHERE status IN ('open', 'claimed')"
    )->fetchColumn();
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string}
 */
function repsDashUpdateApplyLead(int $id, array $data): array
{
    $existing = repsDashFindApplyLead($id);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Lead not found.'];
    }
    $status = (string) ($data['status'] ?? $existing['status']);
    if (!in_array($status, ['open', 'claimed', 'closed'], true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }
    $rep = array_key_exists('assigned_sales_rep', $data)
        ? ($data['assigned_sales_rep'] !== null && $data['assigned_sales_rep'] !== ''
            ? (string) $data['assigned_sales_rep'] : null)
        : $existing['assigned_sales_rep'];
    $notes = array_key_exists('notes', $data)
        ? (string) $data['notes']
        : $existing['notes'];
    if ($status === 'claimed' && ($rep === null || $rep === '')) {
        return ['ok' => false, 'error' => 'Claimed leads need an assigned sales rep.'];
    }
    if ($status === 'open') {
        $rep = null;
    }
    $stmt = repsDashDb()->prepare(
        'UPDATE apply_leads SET status = ?, assigned_sales_rep = ?, notes = ?, updated_at = datetime(\'now\'),
         last_event_at = datetime(\'now\'), assign_source = CASE WHEN ? != \'\' THEN ? ELSE assign_source END
         WHERE id = ?'
    );
    $manualSource = array_key_exists('assigned_sales_rep', $data) ? 'manual' : '';
    $stmt->execute([$status, $rep, $notes, $manualSource, $manualSource !== '' ? 'manual' : '', $id]);
    return ['ok' => true];
}
