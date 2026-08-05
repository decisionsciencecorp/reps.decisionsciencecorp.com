<?php
declare(strict_types=1);

/**
 * Legacy /apply — redirect to join funnel (single queue: dashboard apply_leads).
 */

$qs = [];
if (!empty($_GET['rep'])) {
    $qs['rep'] = (string) $_GET['rep'];
}
$target = '/join.php';
if ($qs !== []) {
    $target .= '?' . http_build_query($qs);
}
header('Location: ' . $target, true, 302);
exit;
