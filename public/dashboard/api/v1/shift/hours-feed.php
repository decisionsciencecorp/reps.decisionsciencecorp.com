<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$res = repsMicropsGetMappedHoursFeed();
if (!($res['ok'] ?? false)) {
    repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
}
$ingest = isset($_GET['ingest']) && (string)$_GET['ingest'] === '1';
$ingested = null;
if ($ingest) {
    $allowEmpty = isset($_GET['force_empty']) && (string)$_GET['force_empty'] === '1';
    $team = null;
    $workers = null;
    $teamRes = repsShiftGetTeamMembers();
    $workersRes = repsShiftGetWorkers();
    if ($teamRes['ok'] ?? false) {
        $team = $teamRes['body'];
    }
    if ($workersRes['ok'] ?? false) {
        $workers = $workersRes['body'];
    }
    $ingested = repsShiftIngestFeed($res['body'], $team, $workers, $allowEmpty ? ['allow_empty_sessions' => true] : []);
    if (!($ingested['ok'] ?? false) && !empty($ingested['refused'])) {
        repsApiError('ingest_refused', (string)($ingested['error'] ?? 'refused'), 409, ['detail' => $ingested]);
    }
}
repsApiJson([
    'ok' => true,
    'hours_source' => 'microps',
    'matching_source' => 'joinshift',
    'live_base' => repsMicropsIsLiveBase(),
    'hours_feed' => $res['body'],
    'ingest' => $ingested,
]);
