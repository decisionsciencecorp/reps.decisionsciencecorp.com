<?php
declare(strict_types=1);

/**
 * Affiliate pages — vanity hosts + /a/{slug}/ path stubs (Empanada Option C pattern).
 *
 * Canonical spoken URL (after Ada wildcard DNS/TLS):
 *   https://{slug}.reps.decisionsciencecorp.com/
 * Path fallback (works without DNS):
 *   https://reps.decisionsciencecorp.com/a/{slug}/
 *
 * Slug = active sales-seat username (chuck, jim, seven, …).
 */

function reps_affiliate_apex_host(): string
{
    return 'reps.decisionsciencecorp.com';
}

/**
 * Labels that must never become affiliate subdomains (host left-label or /a/ segment).
 *
 * @return list<string>
 */
function reps_affiliate_reserved_slugs(): array
{
    return [
        // Host / infra
        'www', 'mail', 'ftp', 'smtp', 'pop', 'imap', 'ns', 'ns1', 'ns2', 'mx',
        'cdn', 'static', 'assets', 'img', 'images', 'media', 'files',
        'dev', 'staging', 'stage', 'test', 'local', 'localhost',
        // Product surfaces
        'api', 'dashboard', 'admin', 'join', 'login', 'logout', 'apply',
        'help', 'docs', 'status', 'health', 'app', 'm', 'mobile',
        'partner', 'partners', 'affiliate', 'affiliates', 'reps',
        // Path / deploy safety (mirror Empanada web reserved)
        'a', 'p', 'includes', 'css', 'js', 'lib', 'tools', 'vendor', 'db',
        'carousel', 'uploads', 'cgi-bin',
    ];
}

function reps_affiliate_slug_valid(string $slug): bool
{
    $slug = strtolower(trim($slug));
    if ($slug === '' || strlen($slug) > 40) {
        return false;
    }
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
        return false;
    }
    return !in_array($slug, reps_affiliate_reserved_slugs(), true);
}

/**
 * Parse affiliate slug from HTTP_HOST when on {slug}.reps.decisionsciencecorp.com
 * (or {slug}.localhost for local smoke).
 */
function reps_affiliate_slug_from_host(?string $host = null): ?string
{
    $host = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
    if ($host === '') {
        return null;
    }
    // strip port
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }

    $apex = reps_affiliate_apex_host();
    $suffixes = [
        '.' . $apex,
        '.reps.localhost',
        '.localhost',
    ];
    foreach ($suffixes as $suffix) {
        if (!str_ends_with($host, $suffix)) {
            continue;
        }
        $left = substr($host, 0, -strlen($suffix));
        if ($left === '' || str_contains($left, '.')) {
            // multi-level or empty — not a single-label affiliate host
            return null;
        }
        if (!reps_affiliate_slug_valid($left)) {
            return null;
        }
        return $left;
    }
    return null;
}

/** Path segment from /a/{slug}/… */
function reps_affiliate_slug_from_path(?string $uri = null): ?string
{
    $uri = (string) ($uri ?? ($_SERVER['REQUEST_URI'] ?? ''));
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) {
        return null;
    }
    if (!preg_match('#^/a/([a-z0-9][a-z0-9-]{0,38})/?$#i', $path, $m)) {
        return null;
    }
    $slug = strtolower($m[1]);
    return reps_affiliate_slug_valid($slug) ? $slug : null;
}

/**
 * Preview / override: ?affiliate=chuck on apex (dev + smoke without DNS).
 */
function reps_affiliate_slug_from_query(): ?string
{
    $q = strtolower(trim((string) ($_GET['affiliate'] ?? '')));
    if ($q === '' || !reps_affiliate_slug_valid($q)) {
        return null;
    }
    return $q;
}

function reps_affiliate_detect_slug(): ?string
{
    return reps_affiliate_slug_from_host()
        ?? reps_affiliate_slug_from_path()
        ?? reps_affiliate_slug_from_query();
}

function reps_affiliate_canonical_url(string $slug): string
{
    $slug = strtolower(trim($slug));
    return 'https://' . rawurlencode($slug) . '.' . reps_affiliate_apex_host() . '/';
}

function reps_affiliate_path_url(string $slug): string
{
    $slug = strtolower(trim($slug));
    return 'https://' . reps_affiliate_apex_host() . '/a/' . rawurlencode($slug) . '/';
}

/**
 * Active sales seat for slug, or null.
 *
 * @return array<string, mixed>|null
 */
function reps_affiliate_resolve_sales_user(string $slug): ?array
{
    $slug = strtolower(trim($slug));
    if (!reps_affiliate_slug_valid($slug)) {
        return null;
    }
    if (!function_exists('repsDashFindUserByUsername')) {
        require_once dirname(__DIR__) . '/dashboard/includes/db.php';
    }
    try {
        $user = repsDashFindUserByUsername($slug);
    } catch (Throwable $e) {
        return null;
    }
    if ($user === null) {
        return null;
    }
    if (($user['role'] ?? '') !== 'sales') {
        return null;
    }
    if (empty($user['is_active'])) {
        return null;
    }
    return $user;
}

/**
 * Prefer subdomain when Host already is affiliate; else path; for CTAs to join always use apex + ?rep=.
 */
function reps_affiliate_join_url(string $slug): string
{
    $slug = strtolower(trim($slug));
    return 'https://' . reps_affiliate_apex_host() . '/join.php?rep=' . rawurlencode($slug);
}

/**
 * Asset / link origin: on subdomain pages, prefer apex for canonical brand home,
 * but same-host relative /assets works either way.
 */
function reps_affiliate_is_subdomain_request(): bool
{
    return reps_affiliate_slug_from_host() !== null;
}

/**
 * If Host is a reserved left-label on the reps apex (e.g. dashboard.reps…),
 * bounce to the apex product URL so wildcards cannot shadow real surfaces.
 */
function reps_affiliate_guard_reserved_host(?string $host = null): void
{
    $host = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
    if ($host === '') {
        return;
    }
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }
    $apex = reps_affiliate_apex_host();
    $suffix = '.' . $apex;
    if (!str_ends_with($host, $suffix)) {
        return;
    }
    $left = substr($host, 0, -strlen($suffix));
    if ($left === '' || str_contains($left, '.')) {
        return;
    }
    if (!in_array($left, reps_affiliate_reserved_slugs(), true)) {
        return;
    }
    $target = 'https://' . $apex . '/';
    if ($left === 'dashboard') {
        $target = 'https://' . $apex . '/dashboard/';
    } elseif ($left === 'join') {
        $target = 'https://' . $apex . '/join.php';
    } elseif ($left === 'api') {
        $target = 'https://' . $apex . '/dashboard/api/';
    }
    header('Location: ' . $target, true, 302);
    exit;
}
