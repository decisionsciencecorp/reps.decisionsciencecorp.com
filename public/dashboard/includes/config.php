<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Dashboard config — DB path, session, Dev Mode gate.
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

/**
 * Dev Mode (role switcher / seat picker). Off unless explicitly enabled.
 * Set REPS_DASH_DEV_MODE=1 in the process env, or define before bootstrap.
 */
if (!defined('REPS_DASH_DEV_MODE')) {
    $dev = repsDashEnvOrDefault('REPS_DASH_DEV_MODE', '0');
    define('REPS_DASH_DEV_MODE', filter_var((string) $dev, FILTER_VALIDATE_BOOLEAN));
}

/** Shared seed password for Slice B demo seats (change via Users). */
if (!defined('REPS_DASH_SEED_PASSWORD')) {
    define('REPS_DASH_SEED_PASSWORD', (string) (repsDashEnvOrDefault('REPS_DASH_SEED_PASSWORD', 'reps-demo') ?? 'reps-demo'));
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
