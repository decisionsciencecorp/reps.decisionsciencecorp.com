<?php
declare(strict_types=1);

/**
 * Shared POST handler for /join and /join/partner.
 * Covered by PHPUnit + e2e.
 */

function repsJoinBootstrap(): void
{
    // Standalone page entry: unit tests already bootstrapped the dashboard.
    // @codeCoverageIgnoreStart
    if (!defined('REPS_DASH_LOADED')) {
        define('REPS_DASH_LOADED', true);
    }
    // @codeCoverageIgnoreEnd
    require_once __DIR__ . '/dashboard/includes/config.php';
    // @codeCoverageIgnoreStart
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(REPS_DASH_SESSION_NAME);
        session_start();
    }
    // @codeCoverageIgnoreEnd
    require_once __DIR__ . '/dashboard/includes/csrf.php';
    require_once __DIR__ . '/dashboard/includes/db.php';
    require_once __DIR__ . '/dashboard/includes/leads-crm.php';
    require_once __DIR__ . '/includes/affiliate_pages.php';
    try {
        repsDashDb();
    } catch (Throwable $e) { // @codeCoverageIgnore
        // @codeCoverageIgnoreStart
        http_response_code(500);
        echo 'Join system unavailable.';
        exit;
        // @codeCoverageIgnoreEnd
    }
}

/**
 * @return array{ok:bool,error?:string,id?:int}
 */
function repsJoinHandlePost(string $mode): array
{
    if (!repsDashCsrfVerify()) {
        return ['ok' => false, 'error' => 'Invalid form token. Refresh and try again.'];
    }
    if (!empty($_POST['company_website'] ?? '')) {
        return ['ok' => true, 'id' => 0]; // honeypot
    }

    if ($mode === 'partner') {
        return repsDashCreateApplyLead([
            'name' => $_POST['name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'path' => 'affiliate',
            'join_kind' => 'affiliate',
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'metro' => trim((string) ($_POST['metro'] ?? '')),
            'expectations_ack' => !empty($_POST['expectations_ack']) ? 1 : 0,
        ]);
    }

    $affiliateCode = trim((string) ($_POST['affiliate_code'] ?? ''));
    if ($affiliateCode === '') {
        $affiliateCode = trim((string) ($_GET['rep'] ?? ''));
    }

    return repsDashCreateApplyLead([
        'name' => $_POST['name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'path' => $_POST['path'] ?? '',
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'metro' => trim((string) ($_POST['metro'] ?? '')),
        'expectations_ack' => !empty($_POST['expectations_ack']) ? 1 : 0,
        'affiliate_code' => $affiliateCode,
    ]);
}
