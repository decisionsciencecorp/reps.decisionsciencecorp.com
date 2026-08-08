<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$res = repsShiftGetWorkers();
if (!($res['ok'] ?? false)) {
    repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
}
repsApiJson(['ok' => true, 'live_base' => repsShiftIsLiveJoinshiftBase(), 'workers' => $res['body']]);

