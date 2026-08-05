<?php
declare(strict_types=1);

/**
 * Fail if clover coverage for include paths is below --min (default 90).
 *
 * Usage: php tools/coverage-gate.php --min=90 [--clover=coverage/clover.xml]
 */

$opts = getopt('', ['min::', 'clover::', 'paths::']);
$min = isset($opts['min']) ? (float) $opts['min'] : 90.0;
$clover = $opts['clover'] ?? (dirname(__DIR__) . '/coverage/clover.xml');

if (!is_readable($clover)) {
    fwrite(STDERR, "coverage-gate: missing clover at {$clover}\n");
    exit(2);
}

$xml = @simplexml_load_file($clover);
if ($xml === false) {
    fwrite(STDERR, "coverage-gate: invalid clover XML\n");
    exit(2);
}

$files = [];
foreach ($xml->project->file ?? [] as $file) {
    $name = (string) ($file['name'] ?? '');
    $metrics = $file->metrics;
    $statements = (int) ($metrics['statements'] ?? 0);
    $covered = (int) ($metrics['coveredstatements'] ?? 0);
    if ($statements <= 0) {
        continue;
    }
    $pct = 100.0 * $covered / $statements;
    $files[$name] = ['statements' => $statements, 'covered' => $covered, 'pct' => $pct];
}

if ($files === []) {
    // Fallback: package totals
    $m = $xml->project->metrics ?? null;
    if ($m === null) {
        fwrite(STDERR, "coverage-gate: no file metrics in clover\n");
        exit(2);
    }
    $statements = (int) $m['statements'];
    $covered = (int) $m['coveredstatements'];
    $pct = $statements > 0 ? 100.0 * $covered / $statements : 0.0;
    echo sprintf("coverage-gate: project %.2f%% (%d/%d) min=%.1f\n", $pct, $covered, $statements, $min);
    exit($pct + 0.0001 >= $min ? 0 : 1);
}

$fail = false;
$totalS = 0;
$totalC = 0;
foreach ($files as $name => $row) {
    $short = basename($name);
    echo sprintf("  %-40s %6.2f%% (%d/%d)\n", $short, $row['pct'], $row['covered'], $row['statements']);
    $totalS += $row['statements'];
    $totalC += $row['covered'];
    if ($row['pct'] + 0.0001 < $min) {
        $fail = true;
    }
}
$overall = $totalS > 0 ? 100.0 * $totalC / $totalS : 0.0;
echo sprintf("coverage-gate: overall %.2f%% (%d/%d) min=%.1f per-file\n", $overall, $totalC, $totalS, $min);
exit($fail ? 1 : 0);
