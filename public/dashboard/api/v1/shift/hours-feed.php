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
    $ingested = repsShiftIngestFeed($res['body'], null, null);
}
repsApiJson(['ok' => true, 'live_base' => repsShiftIsLiveJoinshiftBase(), 'hours_feed' => $res['body'], 'ingest' => $ingested]);

