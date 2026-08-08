<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$dataUser = repsApiDataUser($user);
$shops = repsDashShopsForUser($dataUser);

repsApiJson([
    'ok' => true,
    'count' => count($shops),
    'live_data' => repsDashLiveDataEnabled(),
    'shops' => array_values($shops),
]);
