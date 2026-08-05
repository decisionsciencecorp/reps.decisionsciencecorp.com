<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = repsDashRequireLogin();
$return = repsDashSafeReturnPath((string) ($_POST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '/dashboard/')));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard/');
    exit;
}

if (!repsDashUsesLearnerChrome((string) $user['role'])) {
    header('Location: ' . $return);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'finish') {
    repsDashSetOnboardingState('done');
    header('Location: /dashboard/');
    exit;
}
if ($action === 'restart') {
    repsDashSetOnboardingState('wizard');
    header('Location: /dashboard/?wizard=1');
    exit;
}

header('Location: ' . $return);
exit;
