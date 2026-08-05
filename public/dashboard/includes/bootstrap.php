<?php
declare(strict_types=1);

/**
 * Reps Dashboard — Slice A visual shell (demo auth + mock data).
 * Real auth / API / Shift sync land in later slices per PRD Doc #990.
 */

define('REPS_DASH_LOADED', true);
define('REPS_DASH_ROOT', dirname(__DIR__));
define('REPS_DASH_NAME', 'Reps Dashboard');

require_once __DIR__ . '/skin.php';
require_once __DIR__ . '/mock-data.php';
require_once __DIR__ . '/auth-demo.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/onboarding.php';
require_once __DIR__ . '/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('reps_dash_sess');
    session_start();
}
