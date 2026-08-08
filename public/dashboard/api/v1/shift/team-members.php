<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'GET') {
    $res = repsShiftGetTeamMembers();
    if (!($res['ok'] ?? false)) {
        repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
    }
    repsApiJson(['ok' => true, 'live_base' => repsShiftIsLiveJoinshiftBase(), 'team' => $res['body']]);
}
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($body)) { $body = []; }
    $name = trim((string)($body['name'] ?? $_POST['name'] ?? ''));
    $phone = trim((string)($body['phone'] ?? $_POST['phone'] ?? ''));
    if ($name === '' || $phone === '') {
        repsApiError('bad_request', 'name and phone required', 400);
    }
    try {
        $res = repsShiftInviteTeamMember($name, $phone);
    } catch (Throwable $e) {
        repsApiError('cardinal_blocked', $e->getMessage(), 403);
    }
    if (!($res['ok'] ?? false)) {
        repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
    }
    $ingested = repsApiShiftIngestAfterWrite(true);
    repsApiJson(['ok' => true, 'result' => $res['body'], 'ingest' => $ingested], 201);
}
if ($method === 'DELETE') {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') { repsApiError('bad_request', 'id required', 400); }
    try {
        $res = repsShiftDeleteTeamMember($id);
    } catch (Throwable $e) {
        repsApiError('cardinal_blocked', $e->getMessage(), 403);
    }
    if (!($res['ok'] ?? false)) {
        repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
    }
    $ingested = repsApiShiftIngestAfterWrite(true);
    repsApiJson(['ok' => true, 'result' => $res['body'], 'ingest' => $ingested]);
}
repsApiError('method_not_allowed', 'GET, POST, or DELETE', 405);

