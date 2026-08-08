<?php
declare(strict_types=1);

/**
 * Derived Partner views from hours-feed / SQLite (Doc #818 — no dedicated Shift APIs).
 */

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * @return list<array<string, mixed>>
 */
function repsShiftDerivedIssues(?array $user = null): array
{
    $sessions = $user ? repsDashSessionsForUser(repsApiDataUser($user)) : repsDashAllSessions();
    $byOp = [];
    foreach ($sessions as $s) {
        $oid = (int) ($s['operator_id'] ?? 0);
        if ($oid <= 0) {
            continue;
        }
        if (!isset($byOp[$oid])) {
            $byOp[$oid] = [
                'operator_id' => $oid,
                'operator' => (string) ($s['operator'] ?? ''),
                'completed' => 0,
                'rejected' => 0,
                'accepted_hours' => 0.0,
                'last_day' => '',
            ];
        }
        if (($s['status'] ?? '') === 'completed') {
            $byOp[$oid]['completed']++;
            $byOp[$oid]['accepted_hours'] += (float) ($s['accepted_hours'] ?? 0);
        } elseif (($s['status'] ?? '') === 'rejected') {
            $byOp[$oid]['rejected']++;
        }
        $day = (string) ($s['day'] ?? '');
        if ($day !== '' && $day > $byOp[$oid]['last_day']) {
            $byOp[$oid]['last_day'] = $day;
        }
    }

    $issues = [];
    $today = (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format('Y-m-d');
    foreach ($byOp as $row) {
        $total = $row['completed'] + $row['rejected'];
        $rate = $total > 0 ? $row['completed'] / $total : 1.0;
        if ($total >= 3 && $rate < 0.5) {
            $issues[] = [
                'id' => 'low-acceptance-' . $row['operator_id'],
                'type' => 'low-acceptance',
                'operator_id' => $row['operator_id'],
                'operator' => $row['operator'],
                'acceptance_rate' => round($rate, 3),
                'sessions' => $total,
            ];
        }
        if ($row['last_day'] !== '' && $row['last_day'] < (new DateTimeImmutable($today))->modify('-14 days')->format('Y-m-d')) {
            $issues[] = [
                'id' => 'inactive-' . $row['operator_id'],
                'type' => 'inactive',
                'operator_id' => $row['operator_id'],
                'operator' => $row['operator'],
                'last_day' => $row['last_day'],
            ];
        }
    }
    return $issues;
}

/**
 * @return array<string, mixed>|null
 */
function repsShiftDerivedDay(string $day, ?int $operatorId = null, ?array $user = null): ?array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return null;
    }
    $sessions = $user ? repsDashSessionsForUser(repsApiDataUser($user)) : repsDashAllSessions();
    $rows = [];
    $accepted = 0.0;
    $duration = 0.0;
    $completed = 0;
    $rejected = 0;
    foreach ($sessions as $s) {
        if ((string) ($s['day'] ?? '') !== $day) {
            continue;
        }
        if ($operatorId !== null && (int) ($s['operator_id'] ?? 0) !== $operatorId) {
            continue;
        }
        $rows[] = $s;
        $duration += (float) ($s['duration_hours'] ?? 0);
        $accepted += (float) ($s['accepted_hours'] ?? 0);
        if (($s['status'] ?? '') === 'completed') {
            $completed++;
        } elseif (($s['status'] ?? '') === 'rejected') {
            $rejected++;
        }
    }
    $den = $accepted + ($duration - $accepted);
    return [
        'day' => $day,
        'operator_id' => $operatorId,
        'sessions' => array_values($rows),
        'session_count' => count($rows),
        'completed' => $completed,
        'rejected' => $rejected,
        'duration_hours' => round($duration, 3),
        'accepted_hours' => round($accepted, 3),
        'acceptance_rate' => ($completed + $rejected) > 0
            ? round($completed / ($completed + $rejected), 3)
            : null,
        'earnings_estimate' => round($accepted * repsDashMoneyHourlyRate(), 2),
    ];
}
