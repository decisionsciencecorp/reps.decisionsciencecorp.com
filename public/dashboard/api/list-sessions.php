<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
$dataUser = repsApiDataUser($user);
$sessions = repsDashSessionsForUser($dataUser);

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = max(1, min(500, $limit));
$offset = max(0, $offset);

$total = count($sessions);
$slice = array_slice(array_values($sessions), $offset, $limit);

repsApiJson([
    'ok' => true,
    'count' => count($slice),
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'live_data' => repsDashLiveDataEnabled(),
    'sessions' => $slice,
]);
