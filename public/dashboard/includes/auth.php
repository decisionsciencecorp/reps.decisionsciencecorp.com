<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Session auth (Slice B). Demo seat defs remain as seed fixtures in db.php.
 */

/** Human labels for role badges / picker. */
function repsDashRoleLabel(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'ops' => 'Ops',
        'sales' => 'Sales',
        'business_owner' => 'Business owner',
        'employee' => 'Employee',
        'individual' => 'Individual',
        'agent' => 'Agent',
        default => $role,
    };
}

/** Whether Dev Mode chrome (role picker) is shown. app_meta dash.dev_mode overrides env. */
function repsDashIsDevMode(): bool
{
    try {
        $meta = repsDashAppMetaGet('dash.dev_mode', '');
        if ($meta !== '') {
            return filter_var($meta, FILTER_VALIDATE_BOOLEAN);
        }
    } catch (Throwable $e) {
        // fall through
    }
    return defined('REPS_DASH_DEV_MODE') && (bool) REPS_DASH_DEV_MODE;
}

/**
 * Slice A fixture usernames — never offered in Dev Mode once demo seed is locked.
 *
 * @return list<string>
 */
function repsDashFixtureUsernames(): array
{
    return [
        'mark',
        'ops',
        'agent',
        'maria',
        'alex',
        'pat',
        'jim',
        'jim-work',
        'seven-work',
    ];
}

/**
 * Worker seats eligible for Partner worker match (admin / ops).
 *
 * @return list<array<string, mixed>>
 */
function repsDashMatchableWorkerSeats(): array
{
    $rows = repsDashDb()->query(
        "SELECT id, username, display_name, role, shop_id, operator_id
         FROM users
         WHERE is_active = 1 AND role IN ('individual','employee','business_owner')
         ORDER BY display_name COLLATE NOCASE, username COLLATE NOCASE"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_values(array_filter(array_map(
        static fn(array $row): ?array => repsDashUserRowToSessionShape($row),
        $rows
    )));
}

/**
 * Dev Mode seat picker + quick login — active DB seats only on a live book.
 * Local demo may fall back to seed defs when the DB is empty and seed is not locked.
 *
 * @return list<array<string, mixed>>
 */
function repsDashDevSwitchableSeats(): array
{
    $fromDb = [];
    $dbOk = false;
    try {
        $fromDb = repsDashListUsers(true);
        $dbOk = true;
    } catch (Throwable $e) {
        $fromDb = [];
    }

    if ($dbOk) {
        if (repsDashDemoSeedLocked()) {
            $deny = array_flip(array_map('strtolower', repsDashFixtureUsernames()));
            $fromDb = array_values(array_filter(
                $fromDb,
                static fn(array $acct): bool => !isset($deny[strtolower((string) ($acct['username'] ?? ''))])
            ));
        }
        return $fromDb;
    }

    if (repsDashDemoSeedLocked()) {
        return [];
    }

    $out = [];
    $i = 1;
    foreach (repsDashSeedAccountDefs() as $row) {
        $row['id'] = $i++;
        $out[] = $row;
    }
    return $out;
}

/** @return list<array<string, mixed>> */
function repsDashDemoAccounts(): array
{
    return repsDashDevSwitchableSeats();
}

function repsDashFindAccount(string $username): ?array
{
    try {
        $u = repsDashFindUserByUsername($username);
        if ($u !== null && !empty($u['is_active'])) {
            return $u;
        }
    } catch (Throwable $e) {
        // ignore
    }
    return null;
}

function repsDashCurrentUser(): ?array
{
    $userId = $_SESSION['reps_dash_user_id'] ?? null;
    if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
        // Legacy Slice A session: username only
        $username = $_SESSION['reps_dash_user'] ?? null;
        if (is_string($username) && $username !== '') {
            $user = repsDashFindAccount($username);
            if ($user) {
                $_SESSION['reps_dash_user_id'] = (int) $user['id'];
                return repsDashApplySessionSkin($user);
            }
        }
        return null;
    }
    $user = repsDashFindUserById((int) $userId);
    if ($user === null || empty($user['is_active'])) {
        return null;
    }
    return repsDashApplySessionSkin($user);
}

/** @param array<string, mixed> $user */
function repsDashApplySessionSkin(array $user): array
{
    if (!empty($_SESSION['reps_dash_skin'])) {
        $user['skin_slug'] = $_SESSION['reps_dash_skin'];
    }
    return $user;
}

function repsDashEstablishSession(array $user): void
{
    $_SESSION['reps_dash_user_id'] = (int) $user['id'];
    $_SESSION['reps_dash_user'] = (string) $user['username']; // legacy compat
    if (!empty($user['skin_slug'])) {
        $_SESSION['reps_dash_skin'] = $user['skin_slug'];
    } else {
        unset($_SESSION['reps_dash_skin']);
    }
    if (function_exists('repsDashUsesLearnerChrome') && repsDashUsesLearnerChrome((string) $user['role'])) {
        $obUser = (string) ($_SESSION['reps_dash_onboarding_user'] ?? '');
        if ($obUser !== $user['username'] || !isset($_SESSION['reps_dash_onboarding'])) {
            $_SESSION['reps_dash_onboarding'] = 'wizard';
            $_SESSION['reps_dash_onboarding_user'] = $user['username'];
        }
    } else {
        $_SESSION['reps_dash_onboarding'] = 'done';
        $_SESSION['reps_dash_onboarding_user'] = $user['username'];
    }
}

function repsDashLogin(string $username, string $password): bool
{
    $raw = repsDashFindUserRawByUsername($username);
    if ($raw === null || (int) ($raw['is_active'] ?? 0) !== 1) {
        return false;
    }
    if (!password_verify($password, (string) $raw['password_hash'])) {
        return false;
    }
    $user = repsDashUserRowToSessionShape($raw);
    if ($user === null) {
        return false;
    }
    repsDashEstablishSession($user);
    return true;
}

/**
 * Dev Mode / legacy: switch seat without password (only when Dev Mode on).
 */
function repsDashLoginDemo(string $username): bool
{
    if (!repsDashIsDevMode()) {
        return false;
    }
    $user = repsDashFindAccount($username);
    if ($user === null) {
        return false;
    }
    repsDashEstablishSession($user);
    return true;
}

function repsDashLogout(): void
{
    unset(
        $_SESSION['reps_dash_user_id'],
        $_SESSION['reps_dash_user'],
        $_SESSION['reps_dash_skin'],
        $_SESSION['reps_dash_onboarding'],
        $_SESSION['reps_dash_onboarding_user']
    );
}

function repsDashRequireLogin(): array
{
    $user = repsDashCurrentUser();
    if ($user === null) {
        header('Location: /dashboard/login.php');
        exit;
    }
    return $user;
}

function repsDashIsAdminOrOps(?array $user = null): bool
{
    $user = $user ?? repsDashCurrentUser();
    if (!$user) {
        return false;
    }
    return in_array($user['role'], ['admin', 'ops'], true);
}

function repsDashIsAdmin(?array $user = null): bool
{
    $user = $user ?? repsDashCurrentUser();
    return $user && $user['role'] === 'admin';
}

function repsDashSafeReturnPath(?string $raw): string
{
    if (!is_string($raw) || $raw === '') {
        return '/dashboard/';
    }
    $path = parse_url($raw, PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, '/dashboard')) {
        return '/dashboard/';
    }
    $query = parse_url($raw, PHP_URL_QUERY);
    return $path . (is_string($query) && $query !== '' ? '?' . $query : '');
}

/** Redirect home if this role cannot open the given nav key. */
function repsDashRequireNavKey(string $navKey, ?array $user = null): void
{
    $user = $user ?? repsDashCurrentUser();
    if (!$user) {
        header('Location: /dashboard/login.php');
        exit;
    }
    $allowed = repsDashNavKeysForRole((string) $user['role']);
    if (!in_array($navKey, $allowed, true)) {
        header('Location: /dashboard/');
        exit;
    }
}
