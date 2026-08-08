<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

repsApiJson([
    'ok' => true,
    'service' => 'reps-dashboard-api',
    'live_data' => repsDashLiveDataEnabled(),
    'time' => gmdate('c'),
]);
