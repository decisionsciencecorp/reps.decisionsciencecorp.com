<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repsApiError('method_not_allowed', 'POST only', 405);
}
$raw = file_get_contents('php://input');
$body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($body)) { $body = []; }
try {
    $res = repsShiftPostReminders($body);
} catch (Throwable $e) {
    repsApiError('cardinal_blocked', $e->getMessage(), 403);
}
if (!($res['ok'] ?? false)) {
    repsApiError('shift_upstream', (string)($res['error'] ?? 'fail'), (int)($res['status'] ?? 502), ['detail' => $res]);
}
repsApiJson(['ok' => true, 'result' => $res['body'] ?? null]);
