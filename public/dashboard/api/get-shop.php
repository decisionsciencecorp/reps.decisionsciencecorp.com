<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$dataUser = repsApiDataUser($user);
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    repsApiError('bad_request', 'id is required.', 400);
}
if (!repsDashCanViewShop($dataUser, $id)) {
    repsApiError('not_found', 'Shop not found or out of scope.', 404);
}
$shop = repsDashFindShop($id);
if ($shop === null) {
    repsApiError('not_found', 'Shop not found or out of scope.', 404);
}

repsApiJson([
    'ok' => true,
    'live_data' => repsDashLiveDataEnabled(),
    'shop' => $shop,
]);
