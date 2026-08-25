<?php
declare(strict_types=1);

/**
 * Reps Dashboard bootstrap.
 *
 * Load order:
 *   config → csrf → db → skin → mock → operators → shift-client → microps → shift-sync → repository
 *   → scope → economics → auth → api → access → onboarding → education → rollups → layout
 */

define('REPS_DASH_LOADED', true);
define('REPS_DASH_ROOT', dirname(__DIR__));
define('REPS_DASH_NAME', 'Reps Dashboard');

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(REPS_DASH_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/skin.php';
require_once __DIR__ . '/mock-data.php';
require_once __DIR__ . '/operators.php';
require_once __DIR__ . '/shift-client.php';
require_once __DIR__ . '/microps-client.php';
require_once __DIR__ . '/microps-map.php';
require_once __DIR__ . '/shift-sync.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/scope.php';
require_once __DIR__ . '/economics.php';
require_once __DIR__ . '/stripe-client.php';
require_once __DIR__ . '/stripe-connect.php';
require_once __DIR__ . '/settlement.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/payouts-disburse.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/shift-derived.php';
require_once __DIR__ . '/leads-crm.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/onboarding.php';
require_once __DIR__ . '/education-content.php';
require_once __DIR__ . '/rollups.php';
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/affiliate-panel.php';
require_once __DIR__ . '/layout.php';

// Ensure schema/seed exist early (CLI + web).
try {
    repsDashDb();
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'DB init failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo 'Dashboard database is unavailable.';
    exit;
}
