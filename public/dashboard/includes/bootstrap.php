<?php
declare(strict_types=1);

/**
 * Reps Dashboard — Slice A visual shell (demo auth + mock data).
 * Real auth / API / Shift sync land in later slices per PRD Doc #990.
 *
 * Load order (do not shuffle casually):
 *   skin → mock fixtures → repository → scope → economics → auth → access
 *   → onboarding → education → rollups → partials → layout
 */

define('REPS_DASH_LOADED', true);
define('REPS_DASH_ROOT', dirname(__DIR__));
define('REPS_DASH_NAME', 'Reps Dashboard');

require_once __DIR__ . '/skin.php';
require_once __DIR__ . '/mock-data.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/scope.php';
require_once __DIR__ . '/economics.php';
require_once __DIR__ . '/auth-demo.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/onboarding.php';
require_once __DIR__ . '/education-content.php';
require_once __DIR__ . '/rollups.php';
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('reps_dash_sess');
    session_start();
}
