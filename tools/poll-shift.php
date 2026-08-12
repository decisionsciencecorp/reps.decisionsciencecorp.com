#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Poll Shift hours-feed (+ team/workers) into Reps SQLite, or ingest offline JSON.
 *
 *   php tools/poll-shift.php
 *   php tools/poll-shift.php --feed=/tmp/hours-feed.json --team=/tmp/team.json --workers=/tmp/workers.json
 *   php tools/poll-shift.php --force-empty   # allow sessions:[] when local book already has rows
 *   REPS_SHIFT_COOKIE_JAR=/tmp/joinshift/cookies.txt php tools/poll-shift.php
 *
 * Empty hours-feed while local sessions exist is refused by default (upstream outage guard).
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$opts = getopt('', ['feed:', 'team:', 'workers:', 'live::', 'force-empty']);
$allowEmpty = array_key_exists('force-empty', $opts);
$ingestOpts = $allowEmpty ? ['allow_empty_sessions' => true] : [];

$feedPath = (string) ($opts['feed'] ?? '');
if ($feedPath !== '') {
    if (!is_readable($feedPath)) {
        fwrite(STDERR, "Cannot read --feed\n");
        exit(2);
    }
    $feed = json_decode((string) file_get_contents($feedPath), true);
    if (!is_array($feed)) {
        fwrite(STDERR, "Invalid feed JSON\n");
        exit(2);
    }
    $team = null;
    $workers = null;
    if (!empty($opts['team']) && is_readable((string) $opts['team'])) {
        $team = json_decode((string) file_get_contents((string) $opts['team']), true);
        $team = is_array($team) ? $team : null;
    }
    if (!empty($opts['workers']) && is_readable((string) $opts['workers'])) {
        $workers = json_decode((string) file_get_contents((string) $opts['workers']), true);
        $workers = is_array($workers) ? $workers : null;
    }
    $res = repsShiftIngestFeed($feed, $team, $workers, $ingestOpts);
} else {
    $res = repsShiftPollLive($ingestOpts);
}

fwrite(STDOUT, json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(($res['ok'] ?? false) ? 0 : 1);
