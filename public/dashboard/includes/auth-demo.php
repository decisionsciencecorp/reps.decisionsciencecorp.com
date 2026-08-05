<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Demo seats for Slice A / Dev Mode. Real auth is Slice B.
 *
 * Optional scoping keys:
 * - shop_id: business_owner (one shop)
 * - operator_id: employee / individual (self)
 *
 * @return list<array<string, mixed>>
 */
function repsDashDemoAccounts(): array
{
    return [
        [
            'id' => 1,
            'username' => 'mark',
            'display_name' => 'Mark Hopkins',
            'role' => 'admin',
            'skin_slug' => null,
            'email' => 'mark@decisionsciencecorp.com',
        ],
        [
            'id' => 5,
            'username' => 'ops',
            'display_name' => 'Ops Desk',
            'role' => 'ops',
            'skin_slug' => 'brutalist',
            'email' => 'ops@decisionsciencecorp.com',
        ],
        [
            'id' => 2,
            'username' => 'jim',
            'display_name' => 'Jim (Affiliate)',
            'role' => 'sales',
            'skin_slug' => null,
            'email' => 'jim@example.com',
        ],
        [
            'id' => 3,
            'username' => 'seven',
            'display_name' => 'Seven Stone',
            'role' => 'sales',
            'skin_slug' => 'obsidian',
            'email' => 'seven@example.com',
        ],
        [
            'id' => 4,
            'username' => 'chuck',
            'display_name' => 'Chuck',
            'role' => 'sales',
            'skin_slug' => 'ledger',
            'email' => 'chuck@example.com',
        ],
        [
            'id' => 6,
            'username' => 'maria',
            'display_name' => 'Maria Lopez',
            'role' => 'business_owner',
            'skin_slug' => 'hey',
            'email' => 'maria@fleetwash.example',
            'shop_id' => 104,
        ],
        [
            'id' => 7,
            'username' => 'alex',
            'display_name' => 'Alex Rivera',
            'role' => 'employee',
            'skin_slug' => null,
            'email' => 'alex@fleetwash.example',
            'shop_id' => 104,
            'operator_id' => 1,
        ],
        [
            'id' => 8,
            'username' => 'pat',
            'display_name' => 'Pat Solo',
            'role' => 'individual',
            'skin_slug' => null,
            'email' => 'pat@example.com',
            'operator_id' => 9,
        ],
        [
            'id' => 9,
            'username' => 'agent',
            'display_name' => 'Agent Bot',
            'role' => 'agent',
            'skin_slug' => 'brutalist',
            'email' => 'agent@decisionsciencecorp.com',
        ],
    ];
}

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

/** Whether Dev Mode chrome (role picker) is shown. Slice A = always on. */
function repsDashIsDevMode(): bool
{
    if (defined('REPS_DASH_DEV_MODE')) {
        return (bool) REPS_DASH_DEV_MODE;
    }
    return true;
}

function repsDashFindAccount(string $username): ?array
{
    foreach (repsDashDemoAccounts() as $row) {
        if ($row['username'] === $username) {
            return $row;
        }
    }
    return null;
}

function repsDashCurrentUser(): ?array
{
    $username = $_SESSION['reps_dash_user'] ?? null;
    if (!is_string($username) || $username === '') {
        return null;
    }
    $user = repsDashFindAccount($username);
    if ($user && !empty($_SESSION['reps_dash_skin'])) {
        $user['skin_slug'] = $_SESSION['reps_dash_skin'];
    }
    return $user;
}

function repsDashLoginDemo(string $username): bool
{
    $user = repsDashFindAccount($username);
    if ($user === null) {
        return false;
    }
    $_SESSION['reps_dash_user'] = $user['username'];
    if (!empty($user['skin_slug'])) {
        $_SESSION['reps_dash_skin'] = $user['skin_slug'];
    } else {
        unset($_SESSION['reps_dash_skin']);
    }
    // Learner seats: wizard until finished (per demo username). Admin/ops/agent skip.
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
    return true;
}

function repsDashLogout(): void
{
    unset(
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
