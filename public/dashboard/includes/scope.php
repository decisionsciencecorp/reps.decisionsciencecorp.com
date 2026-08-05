<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * ACL / seat scoping — permanent policy layer.
 * Reads only through repository.php (not mock-data.php directly).
 */

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

    $shopIds = array_map('intval', array_column(repsDashShopsForUser($user), 'id'));
    if ($shopIds === []) {
        return [];
    }
    return array_values(array_filter(
        $sessions,
        static fn(array $s): bool => in_array((int) $s['shop_id'], $shopIds, true)
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
    // Sales has no Operators nav but may open drill-down from Money.
    if (($user['role'] ?? '') === 'sales') {
        $op = repsDashFindOperator($operatorId);
        if (!$op) {
            return false;
        }
        $shopIds = array_map('intval', array_column(repsDashShopsForUser($user), 'id'));
        return in_array((int) $op['shop_id'], $shopIds, true);
    }
    return false;
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
