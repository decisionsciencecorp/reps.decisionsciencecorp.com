<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$day = trim((string)($_GET['date'] ?? ''));
$oid = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;
$out = repsShiftDerivedDay($day, $oid, $user);
if ($out === null) {
    repsApiError('bad_request', 'date=YYYY-MM-DD required', 400);
}
repsApiJson(['ok' => true, 'day' => $out]);
