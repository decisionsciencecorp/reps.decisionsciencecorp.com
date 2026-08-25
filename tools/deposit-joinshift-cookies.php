#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Deposit JoinShift Netscape cookie jar into dashboard app_meta (idempotent).
 *
 * Canonical key: joinshift.cookie_jar
 *
 * Usage:
 *   php tools/deposit-joinshift-cookies.php
 *   REPS_SHIFT_COOKIE_JAR=/path/to/jar REPS_DASH_DB_PATH=/path/dashboard.sqlite \
 *     php tools/deposit-joinshift-cookies.php
 *
 * Reads the staging jar (~/.ssh/joinshift-cookies.txt by default). Never prints cookie values.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$text = repsShiftCookieJarFromFile();
if ($text === '') {
    fwrite(STDERR, "Missing readable JoinShift cookie jar (REPS_SHIFT_COOKIE_JAR / ~/.ssh/joinshift-cookies.txt).\n");
    exit(2);
}

$res = repsShiftStoreCookieJarInDb($text);
if (!($res['ok'] ?? false)) {
    fwrite(STDERR, "Deposit failed.\n");
    exit(1);
}

$fromDb = repsShiftCookieJarFromDb();
fwrite(STDOUT, 'ok bytes=' . (int) $res['bytes'] . ' db_bytes=' . strlen($fromDb) . "\n");
fwrite(STDOUT, 'configured=' . (repsShiftHasCredentials() ? 'yes' : 'no') . "\n");
fwrite(STDOUT, 'source=app_meta:' . REPS_JOINSHIFT_COOKIE_META . "\n");
exit(0);
