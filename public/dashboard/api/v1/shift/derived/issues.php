<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . "/includes/bootstrap.php";
$user = repsApiRequireShiftCaller();
$issues = repsShiftDerivedIssues($user);
repsApiJson(['ok' => true, 'count' => count($issues), 'issues' => $issues]);
