<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Data access seam — Slice A reads mock fixtures.
 * Slice C: swap bodies to Shift sync / SQLite / API without rewriting pages or scope.php.
 */

/** @return list<array<string, mixed>> */
function repsDashAllShops(): array
{
    $shops = repsDashMockShops();
    try {
        $overlays = repsDashShopNotesMap();
    } catch (Throwable $e) {
        return $shops;
    }
    if ($overlays === []) {
        return $shops;
    }
    foreach ($shops as &$shop) {
        $id = (int) $shop['id'];
        if (array_key_exists($id, $overlays)) {
            $shop['notes'] = $overlays[$id];
        }
    }
    unset($shop);
    return $shops;
}

/** @return list<array<string, mixed>> */
function repsDashAllOperators(): array
{
    return repsDashMockOperators();
}

/** @return list<array<string, mixed>> */
function repsDashAllSessions(): array
{
    return repsDashMockSessions();
}

function repsDashFindOperator(int $id): ?array
{
    foreach (repsDashAllOperators() as $op) {
        if ((int) $op['id'] === $id) {
            return $op;
        }
    }
    return null;
}

function repsDashFindShop(int $id): ?array
{
    foreach (repsDashAllShops() as $shop) {
        if ((int) $shop['id'] === $id) {
            return $shop;
        }
    }
    return null;
}

function repsDashFindSession(string $sessionId): ?array
{
    foreach (repsDashAllSessions() as $session) {
        if ((string) ($session['session_id'] ?? '') === $sessionId) {
            return $session;
        }
    }
    return null;
}

/** @return list<array<string, mixed>> */
function repsDashSessionsForOperator(int $operatorId): array
{
    return array_values(array_filter(
        repsDashAllSessions(),
        static fn(array $s): bool => (int) ($s['operator_id'] ?? 0) === $operatorId
    ));
}
