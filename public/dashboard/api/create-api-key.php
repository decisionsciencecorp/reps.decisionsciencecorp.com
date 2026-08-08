<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
if (($user['role'] ?? '') !== 'admin') {
    repsApiError('forbidden', 'Only admin can create API keys.', 403);
}

$rawBody = file_get_contents('php://input');
$body = [];
if (is_string($rawBody) && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}
$userId = (int) ($body['user_id'] ?? $_POST['user_id'] ?? 0);
$name = (string) ($body['name'] ?? $_POST['name'] ?? 'default');
if ($userId <= 0) {
    repsApiError('bad_request', 'user_id is required.', 400);
}

$result = repsApiCreateKey($userId, $name, (int) $user['id']);
if (!$result['ok']) {
    repsApiError($result['error'] ?? 'create_failed', 'Could not create API key.', 400);
}

repsApiJson([
    'ok' => true,
    'id' => $result['id'],
    'key' => $result['key'],
    'preview' => $result['preview'],
    'message' => 'Store this key now — it is shown only once.',
], 201);
