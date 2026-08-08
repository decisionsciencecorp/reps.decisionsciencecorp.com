<?php
declare(strict_types=1);

/**
 * Human-readable API index (auth not required).
 */

header('Content-Type: text/plain; charset=utf-8');
$readme = __DIR__ . '/README.md';
if (is_readable($readme)) {
    readfile($readme);
    exit;
}
echo "Reps Dashboard API — see README.md in repo.\n";
