<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$res = repsShiftGetHoursFeed();
if (!($res['ok'] ?? false)) {
    repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
}
$ingest = isset($_GET['ingest']) && (string)$_GET['ingest'] === '1';
$ingested = null;
if ($ingest) {
    $allowEmpty = isset($_GET['force_empty']) && (string)$_GET['force_empty'] === '1';
    $ingested = repsShiftIngestFeed($res['body'], null, null, $allowEmpty ? ['allow_empty_sessions' => true] : []);
    if (!($ingested['ok'] ?? false) && !empty($ingested['refused'])) {
        repsApiError('ingest_refused', (string)($ingested['error'] ?? 'refused'), 409, ['detail' => $ingested]);
    }
}
repsApiJson(['ok' => true, 'live_base' => repsShiftIsLiveJoinshiftBase(), 'hours_feed' => $res['body'], 'ingest' => $ingested]);

