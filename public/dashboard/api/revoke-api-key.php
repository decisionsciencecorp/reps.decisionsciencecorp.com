<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();

$rawBody = file_get_contents('php://input');
$body = [];
if (is_string($rawBody) && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}
$keyId = (int) ($body['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
if ($keyId <= 0) {
    repsApiError('bad_request', 'id is required.', 400);
}

$result = repsApiRevokeKey($keyId, (int) $user['id']);
if (!$result['ok']) {
    $code = $result['error'] ?? 'revoke_failed';
    $status = $code === 'forbidden' ? 403 : ($code === 'not_found' ? 404 : 400);
    repsApiError($code, 'Could not revoke API key.', $status);
}

repsApiJson(['ok' => true, 'revoked' => true, 'already' => !empty($result['already'])]);
