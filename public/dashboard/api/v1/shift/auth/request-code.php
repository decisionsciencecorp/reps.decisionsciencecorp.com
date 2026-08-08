<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$raw = file_get_contents('php://input');
$body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($body)) { $body = []; }
$phone = trim((string)($body['phone'] ?? ''));
if ($phone === '') { repsApiError('bad_request', 'phone required', 400); }
try {
    $res = repsShiftAuthRequestCode($phone);
} catch (Throwable $e) {
    repsApiError('cardinal_blocked', $e->getMessage(), 403);
}
if (!($res['ok'] ?? false)) {
    repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
}
repsApiJson(['ok' => true, 'result' => $res['body']]);
