<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * ACL / seat scoping — permanent policy layer.
 * Reads only through repository.php (not mock-data.php directly).
 */

function repsDashIsSoloOperator(array $op): bool
{
    return (int) ($op['shop_id'] ?? -1) === 0;
}

/** @return list<array<string, mixed>> */
function repsDashShopsForUser(array $user): array
{
    $shops = repsDashAllShops();
    $role = (string) ($user['role'] ?? '');

    // Agent is an API principal — no human shop directory.
    if ($role === 'agent') {
        return [];
    }
    if (in_array($role, ['admin', 'ops'], true)) {
        return $shops;
    }
    if ($role === 'sales') {
        return array_values(array_filter(
            $shops,
            static fn(array $s): bool => ($s['assigned_sales_rep'] ?? null) === $user['username']
                || ($s['assigned_sales_rep'] ?? null) === null
        ));
    }
    if ($role === 'business_owner' && isset($user['shop_id'])) {
        $sid = (int) $user['shop_id'];
        return array_values(array_filter(
            $shops,
            static fn(array $s): bool => (int) $s['id'] === $sid
        ));
    }
    // employee / individual: no shop directory (operator-scoped elsewhere)
    return [];
}

/**
 * Solo individuals sourced by this sales username (shop_id 0 + assigned_sales_rep).
 *
 * @return list<array<string, mixed>>
 */
function repsDashIndividualsForSalesUser(array $user): array
{
    if (($user['role'] ?? '') !== 'sales') {
        return [];
    }
    $username = (string) ($user['username'] ?? '');
    if ($username === '') {
        return [];
    }
    return array_values(array_filter(
        repsDashAllOperators(),
        static fn(array $o): bool => repsDashIsSoloOperator($o)
            && ($o['assigned_sales_rep'] ?? null) === $username
    ));
}

/** @return list<array<string, mixed>> */
function repsDashOperatorsForUser(array $user): array
{
    $role = (string) ($user['role'] ?? '');
    $ops = repsDashAllOperators();

    if ($role === 'agent') {
        return [];
    }

    if ($role === 'employee' || $role === 'individual') {
        $oid = (int) ($user['operator_id'] ?? 0);
        return array_values(array_filter(
            $ops,
            static fn(array $o): bool => (int) $o['id'] === $oid
        ));
    }

    // Admin/ops see every shop worker + solo individuals (shop_id 0).
    if (in_array($role, ['admin', 'ops'], true)) {
        return $ops;
    }

    if ($role === 'sales') {
        $shopIds = array_map('intval', array_column(repsDashShopsForUser($user), 'id'));
        $byId = [];
        foreach ($ops as $o) {
            $sid = (int) $o['shop_id'];
            // Shop-book workers (not solos)
            if ($sid > 0 && in_array($sid, $shopIds, true)) {
                $byId[(int) $o['id']] = $o;
            }
        }
        // Affiliate-sourced individuals (no shop)
        foreach (repsDashIndividualsForSalesUser($user) as $o) {
            $byId[(int) $o['id']] = $o;
        }
        return array_values($byId);
    }

    $shopIds = array_map('intval', array_column(repsDashShopsForUser($user), 'id'));
    if ($shopIds === []) {
        return [];
    }
    return array_values(array_filter(
        $ops,
        static fn(array $o): bool => in_array((int) $o['shop_id'], $shopIds, true)
    ));
}

/** @return list<array<string, mixed>> */
function repsDashSessionsForUser(array $user): array
{
    $role = (string) ($user['role'] ?? '');
    $sessions = repsDashAllSessions();

    if ($role === 'agent') {
        return [];
    }

    if ($role === 'employee' || $role === 'individual') {
        $ops = repsDashOperatorsForUser($user);
        $ids = array_map('intval', array_column($ops, 'id'));
        return array_values(array_filter(
            $sessions,
            static fn(array $s): bool => in_array((int) ($s['operator_id'] ?? 0), $ids, true)
        ));
    }

    if (in_array($role, ['admin', 'ops'], true)) {
        return $sessions;
    }

    // Sales + owner: sessions for operators in scope (shops and/or sourced individuals).
    $ops = repsDashOperatorsForUser($user);
    $ids = array_map('intval', array_column($ops, 'id'));
    if ($ids === []) {
        return [];
    }
    return array_values(array_filter(
        $sessions,
        static fn(array $s): bool => in_array((int) ($s['operator_id'] ?? 0), $ids, true)
    ));
}

function repsDashCanViewOperator(array $user, int $operatorId): bool
{
    if (($user['role'] ?? '') === 'agent') {
        return false;
    }
    foreach (repsDashOperatorsForUser($user) as $op) {
        if ((int) $op['id'] === $operatorId) {
            return true;
        }
    }
    return false;
}

function repsDashCanViewShop(array $user, int $shopId): bool
{
    foreach (repsDashShopsForUser($user) as $shop) {
        if ((int) $shop['id'] === $shopId) {
            return true;
        }
    }
    return false;
}

/** Pipeline / operational notes — admin, ops, sales (in book), owner (own shop). */
function repsDashCanEditShopNotes(array $user, int $shopId): bool
{
    $role = (string) ($user['role'] ?? '');
    if (!in_array($role, ['admin', 'ops', 'sales', 'business_owner'], true)) {
        return false;
    }
    return repsDashCanViewShop($user, $shopId);
}

/** Book-wide /day.php?date=… (no operator_id) — not for sales or agent. */
function repsDashCanOpenBookWideDay(array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    return !in_array($role, ['sales', 'agent'], true);
}

/** Human worker desk pages — agent never. */
function repsDashCanOpenOperatorDesk(array $user): bool
{
    return (string) ($user['role'] ?? '') !== 'agent';
}
