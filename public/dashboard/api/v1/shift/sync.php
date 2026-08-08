<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repsApiError('method_not_allowed', 'POST only', 405);
}
$res = repsShiftPollLive();
if (!($res['ok'] ?? false)) {
    repsApiError('sync_failed', (string)($res['error'] ?? 'fail'), 502, ['detail' => $res]);
}
repsApiJson(['ok' => true, 'sync' => $res]);

