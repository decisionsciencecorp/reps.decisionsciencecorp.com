<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$dataUser = repsApiDataUser($user);
$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    repsApiError('bad_request', 'id is required.', 400);
}
if (!repsDashCanViewSession($dataUser, $id)) {
    repsApiError('not_found', 'Session not found or out of scope.', 404);
}
$session = repsDashFindSession($id);
if ($session === null) {
    repsApiError('not_found', 'Session not found or out of scope.', 404);
}

repsApiJson([
    'ok' => true,
    'live_data' => repsDashLiveDataEnabled(),
    'session' => $session,
]);
