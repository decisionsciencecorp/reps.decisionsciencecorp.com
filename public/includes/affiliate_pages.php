<?php
declare(strict_types=1);

/**
 * Affiliate pages — path stubs only (Empanada Option C).
 *
 * Canonical URL:
 *   https://reps.decisionsciencecorp.com/a/{slug}/
 *
 * Slug = active sales-seat username (chuck, jim, seven, …).
 * No subdomain / Host routing — no DNS or nginx changes required.
 */

function reps_affiliate_apex_host(): string
{
    return 'reps.decisionsciencecorp.com';
}

/**
 * Path segments that must never become affiliate directories under /a/.
 *
 * @return list<string>
 */
function reps_affiliate_reserved_slugs(): array
{
    return [
        'www', 'mail', 'ftp', 'smtp', 'api', 'dashboard', 'admin', 'join', 'login',
        'logout', 'apply', 'help', 'docs', 'status', 'health', 'app', 'assets',
        'static', 'cdn', 'img', 'images', 'media', 'files', 'dev', 'staging',
        'test', 'partner', 'partners', 'affiliate', 'affiliates', 'reps',
        'a', 'p', 'includes', 'css', 'js', 'lib', 'tools', 'vendor', 'db',
        'uploads', 'cgi-bin',
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

/** Preview: ?affiliate=chuck on apex (dev / smoke). */
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
    return reps_affiliate_slug_from_path() ?? reps_affiliate_slug_from_query();
}

/** Canonical public affiliate page URL (path only). */
function reps_affiliate_canonical_url(string $slug): string
{
    $slug = strtolower(trim($slug));
    return 'https://' . reps_affiliate_apex_host() . '/a/' . rawurlencode($slug) . '/';
}

/** @deprecated alias — use reps_affiliate_canonical_url() */
function reps_affiliate_path_url(string $slug): string
{
    return reps_affiliate_canonical_url($slug);
}

function reps_affiliate_join_url(string $slug): string
{
    $slug = strtolower(trim($slug));
    return 'https://' . reps_affiliate_apex_host() . '/join.php?rep=' . rawurlencode($slug);
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
