<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/** @return list<array<string, mixed>> */
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
            'id' => 5,
            'username' => 'ops',
            'display_name' => 'Ops Desk',
            'role' => 'ops',
            'skin_slug' => 'brutalist',
            'email' => 'ops@decisionsciencecorp.com',
        ],
    ];
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
    }
    return true;
}

function repsDashLogout(): void
{
    unset($_SESSION['reps_dash_user'], $_SESSION['reps_dash_skin']);
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
