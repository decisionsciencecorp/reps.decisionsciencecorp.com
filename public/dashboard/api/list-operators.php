<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$dataUser = repsApiDataUser($user);
$ops = repsDashOperatorsForUser($dataUser);

repsApiJson([
    'ok' => true,
    'count' => count($ops),
    'live_data' => repsDashLiveDataEnabled(),
    'operators' => array_values($ops),
]);
