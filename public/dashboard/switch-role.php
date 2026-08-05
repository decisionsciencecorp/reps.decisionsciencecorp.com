<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!repsDashIsDevMode()) {
    http_response_code(403);
    echo 'Dev Mode role switch is disabled.';
    exit;
}

repsDashRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard/');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$return = repsDashSafeReturnPath((string) ($_POST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '')));

if ($username === '' || !repsDashLoginDemo($username)) {
    header('Location: ' . $return . (str_contains($return, '?') ? '&' : '?') . 'dev_switch=fail');
    exit;
}

// If the current page is not in this role's nav, land on home.
$user = repsDashCurrentUser();
$navKeys = $user ? repsDashNavKeysForRole((string) $user['role']) : ['home'];
$path = parse_url($return, PHP_URL_PATH) ?: '/dashboard/';
$pathMap = [
    '/dashboard/' => 'home',
    '/dashboard/index.php' => 'home',
    '/dashboard/shops.php' => 'shops',
    '/dashboard/operators.php' => 'operators',
    '/dashboard/sessions.php' => 'sessions',
    '/dashboard/money.php' => 'money',
    '/dashboard/users.php' => 'users',
    '/dashboard/settings.php' => 'settings',
];
$key = $pathMap[$path] ?? null;
if ($key !== null && !in_array($key, $navKeys, true)) {
    $return = '/dashboard/';
}

header('Location: ' . $return);
exit;
