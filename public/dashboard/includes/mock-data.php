<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/** @return list<array<string, mixed>> */
function repsDashMockShops(): array
{
    return [
        [
            'id' => 101,
            'name' => 'Empanada Empire — Richardson',
            'status' => 'active',
            'assigned_sales_rep' => 'mark',
            'contact_name' => 'Kitchen lead',
            'contact_phone' => '(214) 555-0101',
            'agreed_shop_split' => 0.0,
            'notes' => 'Internal DSC lane — 100% DSC economics (lorem).',
            'operators' => 3,
            'accepted_hours_7d' => 42.5,
            'reject_rate' => 0.08,
        ],
        [
            'id' => 102,
            'name' => 'Chuck’s Detail Garage',
            'status' => 'pitched',
            'assigned_sales_rep' => 'chuck',
            'contact_name' => 'Chuck',
            'contact_phone' => '(469) 555-0144',
            'agreed_shop_split' => 0.5,
            'notes' => 'Waiting on MicroAGI business paperwork (lorem).',
            'operators' => 0,
            'accepted_hours_7d' => 0.0,
            'reject_rate' => 0.0,
        ],
        [
            'id' => 103,
            'name' => 'Seven Mobile Detail',
            'status' => 'signed',
            'assigned_sales_rep' => 'seven',
            'contact_name' => 'Seven Stone',
            'contact_phone' => '(972) 555-0199',
            'agreed_shop_split' => 0.5,
            'notes' => 'Onboarding Moto G 5G + spare phone (lorem).',
            'operators' => 1,
            'accepted_hours_7d' => 6.2,
            'reject_rate' => 0.22,
        ],
        [
            'id' => 104,
            'name' => 'North Texas Fleet Wash',
            'status' => 'active',
            'assigned_sales_rep' => 'jim',
            'contact_name' => 'Maria Lopez',
            'contact_phone' => '(817) 555-0177',
            'agreed_shop_split' => 0.5,
            'notes' => 'Two headsets live; third delayed (lorem).',
            'operators' => 4,
            'accepted_hours_7d' => 61.0,
            'reject_rate' => 0.11,
        ],
        [
            'id' => 105,
            'name' => 'Lake Highlands Auto Spa',
            'status' => 'prospect',
            'assigned_sales_rep' => 'jim',
            'contact_name' => 'Dee Patel',
            'contact_phone' => '(214) 555-0120',
            'agreed_shop_split' => 0.5,
            'notes' => 'Warm intro from Jim — pitch Friday (lorem).',
            'operators' => 0,
            'accepted_hours_7d' => 0.0,
            'reject_rate' => 0.0,
        ],
        [
            'id' => 106,
            'name' => 'Unassigned Pool Shop',
            'status' => 'prospect',
            'assigned_sales_rep' => null,
            'contact_name' => 'TBD',
            'contact_phone' => '',
            'agreed_shop_split' => 0.5,
            'notes' => 'Inbound apply lead — not yet assigned (lorem).',
            'operators' => 0,
            'accepted_hours_7d' => 0.0,
            'reject_rate' => 0.0,
        ],
    ];
}

/** @return list<array<string, mixed>> */
function repsDashMockOperators(): array
{
    return [
        // Fields mirror Shift Team + Worker surfaces (Doc #818 + live walk 2026-08-05).
        ['id' => 1, 'name' => 'Alex Rivera', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1001', 'status' => 'active', 'matched' => true, 'accepted_7d' => 18.5, 'rejected_7d' => 2.0, 'last_session' => '2026-08-04 14:12'],
        ['id' => 2, 'name' => 'Jordan Lee', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1002', 'status' => 'active', 'matched' => true, 'accepted_7d' => 14.0, 'rejected_7d' => 1.5, 'last_session' => '2026-08-04 11:40'],
        ['id' => 3, 'name' => 'Sam Okonkwo', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1003', 'status' => 'active', 'matched' => true, 'accepted_7d' => 16.0, 'rejected_7d' => 3.0, 'last_session' => '2026-08-03 19:05'],
        ['id' => 4, 'name' => 'Casey Nguyen', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1004', 'status' => 'invited', 'matched' => false, 'accepted_7d' => 0.0, 'rejected_7d' => 0.0, 'last_session' => '—'],
        ['id' => 5, 'name' => 'Seven Stone', 'shop_id' => 103, 'shop' => 'Seven Mobile Detail', 'phone' => '(972) 555-0199', 'status' => 'active', 'matched' => true, 'accepted_7d' => 6.2, 'rejected_7d' => 1.8, 'last_session' => '2026-08-04 16:01'],
        ['id' => 6, 'name' => 'Kit Empanada A', 'shop_id' => 101, 'shop' => 'Empanada Empire — Richardson', 'phone' => '(214) 555-2001', 'status' => 'active', 'matched' => true, 'accepted_7d' => 20.0, 'rejected_7d' => 1.0, 'last_session' => '2026-08-04 15:22'],
        ['id' => 7, 'name' => 'Kit Empanada B', 'shop_id' => 101, 'shop' => 'Empanada Empire — Richardson', 'phone' => '(214) 555-2002', 'status' => 'active', 'matched' => true, 'accepted_7d' => 12.5, 'rejected_7d' => 2.0, 'last_session' => '2026-08-04 09:18'],
        ['id' => 8, 'name' => 'Kit Empanada C', 'shop_id' => 101, 'shop' => 'Empanada Empire — Richardson', 'phone' => '(214) 555-2003', 'status' => 'active', 'matched' => true, 'accepted_7d' => 10.0, 'rejected_7d' => 0.5, 'last_session' => '2026-08-03 21:44'],
        ['id' => 9, 'name' => 'Pat Solo', 'shop_id' => 0, 'shop' => '— (individual)', 'phone' => '(469) 555-0188', 'status' => 'active', 'matched' => true, 'accepted_7d' => 9.5, 'rejected_7d' => 1.0, 'last_session' => '2026-08-04 13:55'],
    ];
}

/** @return list<array<string, mixed>> */
function repsDashMockSessions(): array
{
    $rows = [];
    $ops = repsDashMockOperators();
    // Status vocabulary matches Shift hours-feed: completed | rejected (+ pending for in-flight mock).
    $statuses = ['completed', 'completed', 'completed', 'rejected', 'pending'];
    $reasons = ['', '', '', 'REJECTED_HEALTH_TOO_LOW', '', 'REJECTED_REVIEWED_IV', 'QUARANTINED_FRAUD'];
    $n = 1;
    foreach ($ops as $op) {
        if ($op['status'] !== 'active') {
            continue;
        }
        for ($i = 0; $i < 5; $i++) {
            $st = $statuses[($n + $i) % count($statuses)];
            $hours = round(0.4 + (($n + $i) % 5) * 0.35, 2);
            $accepted = $st === 'completed' ? round($hours * (0.55 + (($n + $i) % 4) * 0.1), 2) : 0.0;
            $day = 1 + (($n + $i) % 5);
            $hour = 3 + ($i * 2);
            $rows[] = [
                'session_id' => 'sess_mock_' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'operator_id' => (int) $op['id'],
                'operator' => $op['name'],
                'shop' => $op['shop'],
                'shop_id' => $op['shop_id'],
                'status' => $st,
                'duration_hours' => $hours,
                'accepted_hours' => $accepted,
                'rejection_reason' => $st === 'rejected' ? $reasons[($n + $i) % count($reasons)] ?: 'REJECTED_HEALTH_TOO_LOW' : '',
                'partner_code' => 'C6N9T7',
                'completed_at' => sprintf('2026-08-%02d %02d:%02d', $day, $hour, 10 + $i),
                'day' => sprintf('2026-08-%02d', $day),
            ];
            $n++;
        }
    }
    return $rows;
}

/**
 * Scope shops for the current user.
 * @return list<array<string, mixed>>
 */
function repsDashShopsForUser(array $user): array
{
    $shops = repsDashMockShops();
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
    $ops = repsDashMockOperators();

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
    $sessions = repsDashMockSessions();

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

function repsDashFindOperator(int $id): ?array
{
    foreach (repsDashMockOperators() as $op) {
        if ((int) $op['id'] === $id) {
            return $op;
        }
    }
    return null;
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

/** @return list<array<string, mixed>> */
function repsDashSessionsForOperator(int $operatorId): array
{
    return array_values(array_filter(
        repsDashMockSessions(),
        static fn(array $s): bool => (int) ($s['operator_id'] ?? 0) === $operatorId
    ));
}

/**
 * Shift-shaped worker rollup (Overview / Worker page fidelity).
 *
 * @return array<string, mixed>
 */
function repsDashOperatorDetailStats(int $operatorId, float $rate = 20.0): array
{
    $sessions = repsDashSessionsForOperator($operatorId);
    $accepted = 0.0;
    $recorded = 0.0;
    $rejectedHours = 0.0;
    $completed = 0;
    $rejected = 0;
    $pending = 0;
    $reasons = [];
    $byDay = [];

    foreach ($sessions as $s) {
        $dur = (float) $s['duration_hours'];
        $acc = (float) $s['accepted_hours'];
        $recorded += $dur;
        $accepted += $acc;
        $day = (string) ($s['day'] ?? substr((string) $s['completed_at'], 0, 10));
        if (!isset($byDay[$day])) {
            $byDay[$day] = [
                'day' => $day,
                'total_hours' => 0.0,
                'accepted' => 0.0,
                'sessions' => 0,
                'earnings' => 0.0,
            ];
        }
        $byDay[$day]['total_hours'] += $dur;
        $byDay[$day]['accepted'] += $acc;
        $byDay[$day]['sessions']++;
        $byDay[$day]['earnings'] += $acc * $rate;

        if ($s['status'] === 'completed') {
            $completed++;
        } elseif ($s['status'] === 'rejected') {
            $rejected++;
            $rejectedHours += $dur;
            $reason = (string) ($s['rejection_reason'] ?: 'UNKNOWN');
            if (!isset($reasons[$reason])) {
                $reasons[$reason] = ['reason' => $reason, 'sessions' => 0, 'hours' => 0.0, 'lost' => 0.0];
            }
            $reasons[$reason]['sessions']++;
            $reasons[$reason]['hours'] += $dur;
            $reasons[$reason]['lost'] += $dur * $rate;
        } elseif ($s['status'] === 'pending') {
            $pending++;
        }
    }

    $denom = $accepted + $rejectedHours;
    $acceptance = $denom > 0 ? round(($accepted / $denom) * 100) : null;
    $days = array_values($byDay);
    usort($days, static fn($a, $b) => strcmp($b['day'], $a['day']));
    foreach ($days as &$d) {
        $d['acceptance'] = $d['total_hours'] > 0
            ? round(($d['accepted'] / $d['total_hours']) * 100)
            : 0;
    }
    unset($d);

    return [
        'sessions_count' => count($sessions),
        'completed' => $completed,
        'rejected' => $rejected,
        'pending' => $pending,
        'accepted_hours' => round($accepted, 1),
        'recorded_hours' => round($recorded, 1),
        'rejected_hours' => round($rejectedHours, 1),
        'acceptance_rate' => $acceptance,
        'earnings' => round($accepted * $rate, 0),
        'lost_payouts' => round($rejectedHours * $rate, 0),
        'reasons' => array_values($reasons),
        'by_day' => $days,
        'sessions' => $sessions,
    ];
}

function repsDashOperatorHref(int $id): string
{
    return '/dashboard/operator.php?id=' . $id;
}

function repsDashDayHref(string $day, ?int $operatorId = null): string
{
    $q = 'date=' . rawurlencode($day);
    if ($operatorId !== null) {
        $q .= '&operator_id=' . $operatorId;
    }
    return '/dashboard/day.php?' . $q;
}

/** @return array<string, mixed> */
function repsDashPulseForUser(array $user): array
{
    $sessions = repsDashSessionsForUser($user);
    $accepted = 0.0;
    $rejected = 0;
    $pending = 0;
    foreach ($sessions as $s) {
        $accepted += (float) $s['accepted_hours'];
        if ($s['status'] === 'rejected') {
            $rejected++;
        }
        if ($s['status'] === 'pending') {
            $pending++;
        }
    }
    $shops = repsDashShopsForUser($user);
    $ops = repsDashOperatorsForUser($user);
    $dead = 0;
    foreach ($shops as $shop) {
        if (in_array($shop['status'], ['active', 'signed'], true) && (float) $shop['accepted_hours_7d'] <= 0) {
            $dead++;
        }
    }
    $activeOps = count(array_filter(
        $ops,
        static fn(array $o): bool => ($o['status'] ?? '') === 'active'
    ));
    return [
        'last_sync' => '2026-08-04 18:45 CDT (mock)',
        'partner_code' => 'C6N9T7',
        'accepted_hours_sample' => round($accepted, 1),
        'rejected_sessions' => $rejected,
        'pending_sessions' => $pending,
        'shops_visible' => count($shops),
        'shops_zero_upload' => $dead,
        'operators_active' => $activeOps,
        'apply_leads_open' => in_array($user['role'], ['admin', 'ops', 'sales'], true) ? 3 : 0,
        'demo_banner' => true,
    ];
}
