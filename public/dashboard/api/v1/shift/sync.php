<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repsApiError('method_not_allowed', 'POST only', 405);
}
$res = repsShiftPollLive();
if (!($res['ok'] ?? false)) {
    $code = !empty($res['refused']) ? 409 : 502;
    repsApiError('sync_failed', (string)($res['error'] ?? 'fail'), $code, ['detail' => $res]);
}
repsApiJson(['ok' => true, 'sync' => $res]);

