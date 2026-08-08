<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsApiRequireUser();
// Money nav is role-gated in chrome; API allows any authenticated principal
// and returns mode-appropriate payload (ledger only for admin/ops/agent).
$summary = repsApiMoneySummary($user);

repsApiJson([
    'ok' => true,
    'money' => $summary,
]);
