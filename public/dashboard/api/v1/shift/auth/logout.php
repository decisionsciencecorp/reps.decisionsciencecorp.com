<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
try {
    $res = repsShiftAuthLogout();
} catch (Throwable $e) {
    repsApiError('cardinal_blocked', $e->getMessage(), 403);
}
if (!($res['ok'] ?? false)) {
    repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
}
repsApiJson(['ok' => true, 'result' => $res['body']]);
