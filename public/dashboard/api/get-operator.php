<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$dataUser = repsApiDataUser($user);
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    repsApiError('bad_request', 'id is required.', 400);
}
if (!repsDashCanViewOperator($dataUser, $id)) {
    repsApiError('not_found', 'Operator not found or out of scope.', 404);
}
$op = repsDashFindOperator($id);
if ($op === null) {
    repsApiError('not_found', 'Operator not found or out of scope.', 404);
}

$stats = repsDashOperatorDetailStats($id);

repsApiJson([
    'ok' => true,
    'live_data' => repsDashLiveDataEnabled(),
    'operator' => $op,
    'stats' => $stats,
]);
