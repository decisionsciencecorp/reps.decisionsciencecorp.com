<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$dataUser = repsApiDataUser($user);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { repsApiError('bad_request', 'id required', 400); }
if (!repsDashCanViewOperator($dataUser, $id)) {
    repsApiError('not_found', 'Operator not in scope', 404);
}
$op = repsDashFindOperator($id);
if ($op === null) { repsApiError('not_found', 'Operator not found', 404); }
$stats = repsDashOperatorDetailStats($id);
repsApiJson(['ok' => true, 'operator' => $op, 'stats' => $stats]);
