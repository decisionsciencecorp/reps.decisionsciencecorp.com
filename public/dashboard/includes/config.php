<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Dashboard config — DB path, session.
 *
 * Multihost: public/ lands in html/; SQLite lives at ../db/dashboard.sqlite
 * (same sibling pattern as Docket/CRM). Override with REPS_DASH_DB_PATH.
 */

if (!function_exists('repsDashEnvOrDefault')) {
    function repsDashEnvOrDefault(string $name, ?string $default = null): ?string
    {
        $v = getenv($name);
        if ($v === false || $v === '') {
            return $default;
        }
        return $v;
    }
}

if (!defined('REPS_DASH_DB_PATH')) {
    $envDb = repsDashEnvOrDefault('REPS_DASH_DB_PATH');
    if (is_string($envDb) && $envDb !== '') {
        define('REPS_DASH_DB_PATH', $envDb);
    } else {
        // includes → dashboard → public|html → site root
        define('REPS_DASH_DB_PATH', dirname(__DIR__, 3) . '/db/dashboard.sqlite');
    }
}

if (!defined('REPS_DASH_SESSION_NAME')) {
    define('REPS_DASH_SESSION_NAME', 'reps_dash_sess');
}

if (!defined('REPS_DASH_PASSWORD_MIN')) {
    define('REPS_DASH_PASSWORD_MIN', 8);
}

/** Shared seed password for local/test fixture seats (PHPUnit bootstrap). */
if (!defined('REPS_DASH_SEED_PASSWORD')) {
    define('REPS_DASH_SEED_PASSWORD', (string) (repsDashEnvOrDefault('REPS_DASH_SEED_PASSWORD', 'reps-demo') ?? 'reps-demo'));
}

if (!defined('REPS_LEADS_WEBHOOK_URL')) {
    define('REPS_LEADS_WEBHOOK_URL', (string) (repsDashEnvOrDefault('REPS_LEADS_WEBHOOK_URL', '') ?? ''));
}
if (!defined('REPS_LEADS_WEBHOOK_SECRET')) {
    define('REPS_LEADS_WEBHOOK_SECRET', (string) (repsDashEnvOrDefault('REPS_LEADS_WEBHOOK_SECRET', '') ?? ''));
}

/** @return list<string> */
function repsDashValidRoles(): array
{
    return ['admin', 'ops', 'sales', 'business_owner', 'employee', 'individual', 'agent'];
}

function repsDashIsValidRole(string $role): bool
{
    return in_array($role, repsDashValidRoles(), true);
}
