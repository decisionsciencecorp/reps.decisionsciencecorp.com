<?php
declare(strict_types=1);

/**
 * Map MicroPS mobile-dashboard JSON → JoinShift hours-feed shape for repsShiftIngestFeed.
 *
 * partnerCode is the JoinShift matching code (C6N9T7), never MicroPS GM code M3WRBU.
 */

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * @return array{0: string, 1: string}
 */
function repsMicropsSplitFullName(string $full): array
{
    $full = trim($full);
    if ($full === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/', $full, 2) ?: [];
    $first = trim((string) ($parts[0] ?? ''));
    $last = trim((string) ($parts[1] ?? ''));
    return [$first, $last];
}

/**
 * @param array<string, mixed>|list<mixed> $body
 * @return list<array<string, mixed>>
 */
function repsMicropsExtractSessionRows($body): array
{
    if (!is_array($body)) {
        return [];
    }
    foreach (['sessions', 'data', 'rows', 'items'] as $key) {
        if (isset($body[$key]) && is_array($body[$key])) {
            $rows = $body[$key];
            $out = [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }
            return $out;
        }
    }
    $looksLikeList = $body !== [] && array_is_list($body);
    if ($looksLikeList) {
        $out = [];
        foreach ($body as $row) {
            if (is_array($row) && (isset($row['session_id']) || isset($row['user_id']))) {
                $out[] = $row;
            }
        }
        return $out;
    }
    return [];
}

/**
 * accepted hours keyed by user_id from /per-user.
 *
 * @param array<string, mixed>|list<mixed> $body
 * @return array<string, float>
 */
function repsMicropsExtractPerUserAccepted($body): array
{
    if (!is_array($body)) {
        return [];
    }
    $rows = [];
    foreach (['users', 'per_user', 'perUser', 'rows', 'items', 'data'] as $key) {
        if (!isset($body[$key]) || !is_array($body[$key])) {
            continue;
        }
        $chunk = $body[$key];
        if ($chunk !== [] && !array_is_list($chunk)) {
            foreach ($chunk as $uid => $row) {
                if (is_array($row)) {
                    $row = $row + ['user_id' => (string) $uid];
                    $rows[] = $row;
                } elseif (is_numeric($row)) {
                    $rows[] = ['user_id' => (string) $uid, 'accepted_hours' => (float) $row];
                }
            }
        } else {
            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }
        break;
    }

    $out = [];
    foreach ($rows as $row) {
        $uid = trim((string) ($row['user_id'] ?? $row['userId'] ?? $row['id'] ?? ''));
        if ($uid === '') {
            continue;
        }
        $accepted = $row['accepted_hours'] ?? $row['acceptedHours'] ?? $row['accepted'] ?? null;
        if ($accepted === null) {
            continue;
        }
        $out[$uid] = (float) $accepted;
    }
    return $out;
}

function repsMicropsRejectionIsReject(?string $reason): bool
{
    $reason = trim((string) $reason);
    if ($reason === '' || strcasecmp($reason, 'null') === 0) {
        return false;
    }
    return true;
}

/**
 * @param array<string, mixed> $dataBody /mobile-dashboard/data
 * @param array<string, mixed> $perUserBody /mobile-dashboard/per-user (may be empty)
 * @return array{partnerCode: string, sessions: list<array<string, mixed>>, bannedUserIds: list<mixed>, source: string}
 */
function repsMicropsMapHoursFeed(array $dataBody, array $perUserBody = [], ?string $partnerCode = null): array
{
    $partner = $partnerCode ?? repsShiftMatchingPartnerCode();
    $rows = repsMicropsExtractSessionRows($dataBody);
    $acceptedByUser = repsMicropsExtractPerUserAccepted($perUserBody);
    $hasPerUser = $acceptedByUser !== [];

    $durations = [];
    foreach ($rows as $i => $row) {
        $uid = trim((string) ($row['user_id'] ?? $row['userId'] ?? ''));
        $reason = $row['rejection_reason'] ?? $row['rejectionReason'] ?? null;
        $reasonStr = $reason === null ? '' : (string) $reason;
        $rejected = repsMicropsRejectionIsReject($reasonStr);
        $seconds = (float) ($row['length_seconds'] ?? $row['lengthSeconds'] ?? 0);
        $duration = $seconds > 0 ? ($seconds / 3600.0) : (float) ($row['duration_hours'] ?? 0);
        if (!$rejected && $uid !== '') {
            $durations[$uid] = ($durations[$uid] ?? 0.0) + $duration;
        }
        $rows[$i]['_uid'] = $uid;
        $rows[$i]['_rejected'] = $rejected;
        $rows[$i]['_duration'] = $duration;
        $rows[$i]['_reason'] = $rejected ? trim($reasonStr) : '';
    }

    $sessions = [];
    foreach ($rows as $row) {
        $sid = trim((string) ($row['session_id'] ?? $row['sessionId'] ?? ''));
        if ($sid === '') {
            continue;
        }
        $uid = (string) ($row['_uid'] ?? '');
        $duration = (float) ($row['_duration'] ?? 0);
        $rejected = !empty($row['_rejected']);
        $full = trim((string) ($row['user_full_name'] ?? $row['userFullName'] ?? ''));
        $first = trim((string) ($row['first_name'] ?? $row['firstName'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? $row['lastName'] ?? ''));
        if ($first === '' && $last === '' && $full !== '') {
            [$first, $last] = repsMicropsSplitFullName($full);
        }
        $created = (string) ($row['date_recorded'] ?? $row['created_at'] ?? $row['createdAt'] ?? '');
        $completed = (string) ($row['uploaded_at'] ?? $row['completed_at'] ?? $row['completedAt'] ?? $created);

        $accepted = 0.0;
        if (!$rejected) {
            if ($hasPerUser) {
                $target = $acceptedByUser[$uid] ?? 0.0;
                $pool = $durations[$uid] ?? 0.0;
                if ($pool > 0.0 && $target > 0.0) {
                    $accepted = $duration * ($target / $pool);
                }
            } else {
                $accepted = $duration;
            }
        }

        $sessions[] = [
            'session_id' => $sid,
            'user_id' => $uid,
            'first_name' => $first,
            'last_name' => $last,
            'user_full_name' => $full !== '' ? $full : trim($first . ' ' . $last),
            'partner_code' => $partner,
            'status' => $rejected ? 'rejected' : 'completed',
            'created_at' => $created,
            'completed_at' => $completed,
            'duration_hours' => $duration,
            'accepted_hours' => $accepted,
            'rejection_reason' => (string) ($row['_reason'] ?? ''),
        ];
    }

    return [
        'partnerCode' => $partner,
        'sessions' => $sessions,
        'bannedUserIds' => [],
        'source' => 'microps',
        'accepted_scaled' => $hasPerUser,
    ];
}

/**
 * Live/fake fetch of MicroPS /data (+ /per-user) mapped to hours-feed JSON.
 *
 * @return array{ok: bool, status?: int, body?: array<string, mixed>, error?: string}
 */
function repsMicropsGetMappedHoursFeed(): array
{
    $dataRes = repsMicropsGetDashboardData();
    if (!($dataRes['ok'] ?? false)) {
        return $dataRes;
    }
    $dataBody = is_array($dataRes['body'] ?? null) ? $dataRes['body'] : [];

    $perUserBody = [];
    $perUserRes = repsMicropsGetPerUser();
    if (($perUserRes['ok'] ?? false) && is_array($perUserRes['body'] ?? null)) {
        $perUserBody = $perUserRes['body'];
    }

    $meRes = repsMicropsGetAuthMe();
    if (($meRes['ok'] ?? false) && is_array($meRes['body'] ?? null)) {
        $gm = repsMicropsExtractGmCode($meRes['body']);
        if ($gm !== '') {
            repsDashAppMetaSet('shift.microps_gm_code', $gm);
        }
    }

    $feed = repsMicropsMapHoursFeed($dataBody, $perUserBody, repsShiftMatchingPartnerCode());
    return ['ok' => true, 'status' => 200, 'body' => $feed];
}
