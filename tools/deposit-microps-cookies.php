#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Deposit MicroPS Netscape cookie jar into dashboard app_meta (idempotent).
 *
 * Canonical key: microps.cookie_jar
 *
 * Usage:
 *   php tools/deposit-microps-cookies.php
 *   REPS_MICROPS_COOKIE_JAR=/path/to/jar REPS_DASH_DB_PATH=/path/dashboard.sqlite \
 *     php tools/deposit-microps-cookies.php
 *
 * Reads the staging jar (~/.ssh/microps-cookies.pass by default). Never prints cookie values.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$text = repsMicropsCookieJarFromFile();
if ($text === '') {
    fwrite(STDERR, "Missing readable MicroPS cookie jar (REPS_MICROPS_COOKIE_JAR / ~/.ssh/microps-cookies.pass).\n");
    exit(2);
}

$res = repsMicropsStoreCookieJarInDb($text);
if (!($res['ok'] ?? false)) {
    fwrite(STDERR, "Deposit failed.\n");
    exit(1);
}

$fromDb = repsMicropsCookieJarFromDb();
fwrite(STDOUT, 'ok bytes=' . (int) $res['bytes'] . ' db_bytes=' . strlen($fromDb) . "\n");
fwrite(STDOUT, 'configured=' . (repsMicropsHasCredentials() ? 'yes' : 'no') . "\n");
fwrite(STDOUT, 'source=app_meta:' . REPS_MICROPS_COOKIE_META . "\n");
exit(0);
