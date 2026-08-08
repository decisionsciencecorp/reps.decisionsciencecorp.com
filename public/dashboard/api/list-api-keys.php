<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$role = (string) ($user['role'] ?? '');
$targetId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : (int) $user['id'];

if ($role !== 'admin' && $targetId !== (int) $user['id']) {
    repsApiError('forbidden', 'Cannot list keys for another user.', 403);
}

$includeRevoked = isset($_GET['include_revoked']) && (string) $_GET['include_revoked'] === '1';
$keys = repsApiListKeysForUser($targetId, $includeRevoked);

repsApiJson([
    'ok' => true,
    'user_id' => $targetId,
    'count' => count($keys),
    'api_keys' => $keys,
]);
