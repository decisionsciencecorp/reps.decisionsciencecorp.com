<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

function repsDashSkinAvailableSlugs(): array
{
    return ['hey', 'ledger', 'brutalist', 'obsidian'];
}

function repsDashSkinNormalizeSlug(?string $slug): ?string
{
    $s = strtolower(trim((string) $slug));
    return in_array($s, repsDashSkinAvailableSlugs(), true) ? $s : null;
}

function repsDashSkinMasterSlug(): string
{
    return 'hey';
}

function repsDashSkinUserOverrideSlug(?array $user): ?string
{
    if (!$user) {
        return null;
    }
    $raw = $user['skin_slug'] ?? ($_SESSION['reps_dash_skin'] ?? null);
    if ($raw === null || $raw === '') {
        return null;
    }
    return repsDashSkinNormalizeSlug((string) $raw);
}

function repsDashSkinPreviewSlug(): ?string
{
    if (!isset($_GET['preview_skin'])) {
        return null;
    }
    return repsDashSkinNormalizeSlug((string) $_GET['preview_skin']);
}

function repsDashSkinEffectiveSlug(?array $user = null): string
{
    $preview = repsDashSkinPreviewSlug();
    if ($preview !== null) {
        return $preview;
    }
    $override = repsDashSkinUserOverrideSlug($user);
    if ($override !== null) {
        return $override;
    }
    return repsDashSkinMasterSlug();
}

function repsDashSkinStylesheetHref(string $slug): string
{
    $slug = repsDashSkinNormalizeSlug($slug) ?? 'hey';
    return '/dashboard/assets/css/skins/' . $slug . '.css?v=1';
}
