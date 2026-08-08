<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
repsApiJson([
    'ok' => true,
    'me' => repsApiMePayload($user),
]);
