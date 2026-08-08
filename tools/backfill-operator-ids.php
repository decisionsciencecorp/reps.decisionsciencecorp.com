#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Set ledger_lines.operator_id from a Shift hours-feed JSON (session user_id).
 *
 * Usage:
 *   php tools/backfill-operator-ids.php --feed=/path/hours-feed.json
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$opts = getopt('', ['feed:']);
$feedPath = (string) ($opts['feed'] ?? '');
if ($feedPath === '' || !is_readable($feedPath)) {
    fwrite(STDERR, "Need --feed=/path/to/hours-feed.json\n");
    exit(2);
}

$feed = json_decode((string) file_get_contents($feedPath), true);
if (!is_array($feed)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(2);
}

$bySession = [];
foreach ($feed['sessions'] ?? [] as $s) {
    if (!is_array($s)) {
        continue;
    }
    $sid = (string) ($s['session_id'] ?? '');
    if ($sid === '') {
        continue;
    }
    $bySession[$sid] = $s;
}

repsOperatorsEnsureSchema();
$pdo = repsDashDb();
$stmt = $pdo->query(
    "SELECT id, hour_key FROM ledger_lines
     WHERE (operator_id IS NULL OR operator_id = 0)
       AND hour_key LIKE 'shift_sess_%'"
);
$updated = 0;
$skipped = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $hk = (string) $row['hour_key'];
    $sid = substr($hk, strlen('shift_sess_'));
    if (!isset($bySession[$sid])) {
        $skipped++;
        continue;
    }
    $opId = repsOperatorEnsureFromShiftSession($bySession[$sid]);
    if ($opId <= 0) {
        $skipped++;
        continue;
    }
    $pdo->prepare(
        'UPDATE ledger_lines SET operator_id = ?, updated_at = datetime(\'now\') WHERE id = ?'
    )->execute([$opId, (int) $row['id']]);
    $updated++;
}

fwrite(STDOUT, json_encode([
    'updated' => $updated,
    'skipped' => $skipped,
    'sessions_indexed' => count($bySession),
], JSON_UNESCAPED_SLASHES) . "\n");
