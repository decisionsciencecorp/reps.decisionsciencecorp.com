<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Derived stats + dashboard URL helpers (not fixtures, not ACL).
 */

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

/**
 * Shift-shaped worker rollup (Overview / Worker page fidelity).
 *
 * @return array<string, mixed>
 */
function repsDashOperatorDetailStats(int $operatorId, ?float $rate = null): array
{
    $rate ??= repsDashMoneyHourlyRate();
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
