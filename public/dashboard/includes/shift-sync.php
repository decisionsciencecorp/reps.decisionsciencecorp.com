<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Shift for Business → Reps SQLite sync (Slice C).
 * HTTP: see shift-client.php (CARDINAL: live Partner is prod — writes via fake in tests).
 */

require_once __DIR__ . '/operators.php';
require_once __DIR__ . '/shift-client.php';

function repsDashLiveDataEnabled(): bool
{
    if (getenv('REPS_DASH_FORCE_MOCK') === '1') {
        return false;
    }
    if (repsDashAppMetaGet('shift.live_data', '') === '1') {
        return true;
    }
    try {
        $n = (int) repsDashDb()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
        return $n > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function repsDashSetLiveDataEnabled(bool $on): void
{
    repsDashAppMetaSet('shift.live_data', $on ? '1' : '0');
}

/**
 * Whether an empty hours-feed may replace / advance sync state.
 * Default: refuse when we already hold sessions (upstream outage must not look like a clean sync).
 */
function repsShiftAllowEmptySessionsIngest(?bool $explicit = null): bool
{
    if ($explicit === true) {
        return true;
    }
    if ($explicit === false) {
        return false;
    }
    return filter_var(getenv('REPS_SHIFT_ALLOW_EMPTY_INGEST') ?: '0', FILTER_VALIDATE_BOOLEAN);
}

/**
 * Guard: empty or wrong-partner feeds must not poison a populated book.
 *
 * @param array<string, mixed> $feed
 * @return array{ok: bool, error?: string, partner_code?: string, local_sessions?: int, feed_sessions?: int}|null
 *         null = safe to ingest; array = refuse payload
 */
function repsShiftIngestGuard(array $feed, bool $allowEmptySessions = false): ?array
{
    $partner = (string) ($feed['partnerCode'] ?? '');
    $sessions = $feed['sessions'] ?? null;
    if (!is_array($sessions)) {
        return [
            'ok' => false,
            'error' => 'bad_sessions',
            'partner_code' => $partner,
            'operators_upserted' => 0,
            'sessions_upserted' => 0,
        ];
    }

    $feedCount = count($sessions);
    $localCount = 0;
    try {
        $localCount = (int) repsDashDb()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
    } catch (Throwable $e) {
        $localCount = 0;
    }

    $storedPartner = (string) repsDashAppMetaGet('shift.partner_code', '');
    if ($partner !== '' && $storedPartner !== '' && strcasecmp($partner, $storedPartner) !== 0) {
        repsDashAppMetaSet('shift.last_poll_at', gmdate('c'));
        repsDashAppMetaSet('shift.last_poll_error', 'partner_mismatch');
        return [
            'ok' => false,
            'error' => 'partner_mismatch',
            'partner_code' => $partner,
            'stored_partner_code' => $storedPartner,
            'operators_upserted' => 0,
            'sessions_upserted' => 0,
            'local_sessions' => $localCount,
            'feed_sessions' => $feedCount,
            'refused' => true,
        ];
    }

    if ($feedCount === 0 && $localCount > 0 && !repsShiftAllowEmptySessionsIngest($allowEmptySessions)) {
        // Upstream often returns HTTP 200 + sessions:[] during outages. Keep local rows.
        repsDashAppMetaSet('shift.last_poll_at', gmdate('c'));
        repsDashAppMetaSet('shift.last_poll_error', 'empty_feed_refused');
        repsDashAppMetaSet('shift.last_empty_feed_at', gmdate('c'));
        return [
            'ok' => false,
            'error' => 'empty_feed_refused',
            'partner_code' => $partner,
            'operators_upserted' => 0,
            'sessions_upserted' => 0,
            'local_sessions' => $localCount,
            'feed_sessions' => 0,
            'refused' => true,
            'message' => 'Hours-feed returned zero sessions while local book has data; ingest skipped to avoid poisoning.',
        ];
    }

    return null;
}

/**
 * Ingest hours-feed (+ optional team/workers) into SQLite.
 *
 * @param array<string, mixed> $feed hours-feed JSON
 * @param array<string, mixed>|null $team team/members JSON
 * @param array<string, mixed>|null $workers dashboard/workers JSON
 * @param array{allow_empty_sessions?: bool} $opts
 * @return array{ok: bool, operators_upserted: int, sessions_upserted: int, partner_code: string, error?: string, refused?: bool}
 */
function repsShiftIngestFeed(array $feed, ?array $team = null, ?array $workers = null, array $opts = []): array
{
    $allowEmpty = !empty($opts['allow_empty_sessions']);
    $blocked = repsShiftIngestGuard($feed, $allowEmpty);
    if ($blocked !== null) {
        return $blocked;
    }

    $pdo = repsDashDb();
    $partner = (string) ($feed['partnerCode'] ?? '');
    $sessions = $feed['sessions'] ?? [];
    if (!is_array($sessions)) {
        return ['ok' => false, 'operators_upserted' => 0, 'sessions_upserted' => 0, 'partner_code' => $partner, 'error' => 'bad_sessions'];
    }

    $opUpserted = 0;
    $sessUpserted = 0;

    // 1) Team members → operators (best names/phones)
    if (is_array($team) && isset($team['members']) && is_array($team['members'])) {
        foreach ($team['members'] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $uid = trim((string) ($m['userId'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $name = trim((string) ($m['name'] ?? $uid));
            $phone = trim((string) ($m['phone'] ?? ''));
            $memberId = trim((string) ($m['id'] ?? ''));
            $opId = repsOperatorEnsure($uid, $name);
            if ($opId <= 0) {
                continue;
            }
            $pdo->prepare(
                'UPDATE operators SET phone = CASE WHEN ? != \'\' THEN ? ELSE phone END,
                 team_member_id = CASE WHEN ? != \'\' THEN ? ELSE team_member_id END,
                 partner_code = CASE WHEN ? != \'\' THEN ? ELSE partner_code END,
                 status = \'active\',
                 updated_at = datetime(\'now\')
                 WHERE id = ?'
            )->execute([$phone, $phone, $memberId, $memberId, $partner, $partner, $opId]);
            $opUpserted++;
        }
    }

    // 2) Workers list (names)
    if (is_array($workers) && isset($workers['workers']) && is_array($workers['workers'])) {
        foreach ($workers['workers'] as $w) {
            if (!is_array($w)) {
                continue;
            }
            $uid = trim((string) ($w['userId'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $name = trim((string) ($w['name'] ?? $uid));
            $opId = repsOperatorEnsure($uid, $name);
            if ($opId > 0) {
                if ($partner !== '') {
                    $pdo->prepare(
                        'UPDATE operators SET partner_code = ?, updated_at = datetime(\'now\') WHERE id = ?'
                    )->execute([$partner, $opId]);
                }
                $opUpserted++;
            }
        }
    }

    // 3) Sessions from hours-feed
    $upsertSess = $pdo->prepare(
        'INSERT INTO sessions (
            session_id, operator_id, shop_id, shift_user_id, status, duration_hours,
            accepted_hours, rejection_reason, partner_code, created_at, completed_at, day, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))
         ON CONFLICT(session_id) DO UPDATE SET
            operator_id = excluded.operator_id,
            shop_id = excluded.shop_id,
            shift_user_id = excluded.shift_user_id,
            status = excluded.status,
            duration_hours = excluded.duration_hours,
            accepted_hours = excluded.accepted_hours,
            rejection_reason = excluded.rejection_reason,
            partner_code = excluded.partner_code,
            created_at = excluded.created_at,
            completed_at = excluded.completed_at,
            day = excluded.day,
            updated_at = datetime(\'now\')'
    );

    foreach ($sessions as $s) {
        if (!is_array($s)) {
            continue;
        }
        $sid = trim((string) ($s['session_id'] ?? ''));
        if ($sid === '') {
            continue;
        }
        $opId = repsOperatorEnsureFromShiftSession($s);
        $shopId = null;
        if ($opId > 0) {
            $op = repsOperatorById($opId);
            if ($op && $op['shop_id'] !== null && $op['shop_id'] !== '') {
                $shopId = (int) $op['shop_id'];
            }
            // Inherit shop from matched user
            if (($shopId === null || $shopId === 0) && !empty($op['matched_user_id'])) {
                $u = $pdo->prepare('SELECT shop_id FROM users WHERE id = ? LIMIT 1');
                $u->execute([(int) $op['matched_user_id']]);
                $sidShop = $u->fetchColumn();
                if ($sidShop !== false && $sidShop !== null && (int) $sidShop > 0) {
                    $shopId = (int) $sidShop;
                    $pdo->prepare(
                        'UPDATE operators SET shop_id = ?, updated_at = datetime(\'now\') WHERE id = ?'
                    )->execute([$shopId, $opId]);
                }
            }
        }
        $completed = (string) ($s['completed_at'] ?? $s['created_at'] ?? '');
        $day = '';
        if ($completed !== '') {
            try {
                $dt = new DateTimeImmutable($completed);
                $day = $dt->setTimezone(new DateTimeZone('America/Chicago'))->format('Y-m-d');
            } catch (Throwable $e) {
                $day = substr($completed, 0, 10);
            }
        }
        $status = (string) ($s['status'] ?? '');
        // Map Shift completed → completed for UI vocabulary
        if ($status === 'completed' || $status === 'accepted') {
            $status = 'completed';
        }
        $upsertSess->execute([
            $sid,
            $opId > 0 ? $opId : null,
            $shopId,
            (string) ($s['user_id'] ?? ''),
            $status,
            (float) ($s['duration_hours'] ?? 0),
            (float) ($s['accepted_hours'] ?? 0),
            (string) ($s['rejection_reason'] ?? ''),
            (string) ($s['partner_code'] ?? $partner),
            (string) ($s['created_at'] ?? null),
            $completed !== '' ? $completed : null,
            $day !== '' ? $day : null,
        ]);
        $sessUpserted++;
        if ($opId > 0) {
            $opUpserted++;
        }
    }

    repsShiftRecomputeOperatorRollups();
    repsDashAppMetaSet('shift.last_sync_at', gmdate('c'));
    repsDashAppMetaSet('shift.last_poll_at', gmdate('c'));
    repsDashAppMetaSet('shift.last_poll_error', '');
    repsDashAppMetaSet('shift.partner_code', $partner);
    repsDashSetLiveDataEnabled(true);

    return [
        'ok' => true,
        'operators_upserted' => $opUpserted,
        'sessions_upserted' => $sessUpserted,
        'partner_code' => $partner,
        'feed_sessions' => count($sessions),
    ];
}

function repsShiftRecomputeOperatorRollups(): void
{
    $pdo = repsDashDb();
    $since = (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))
        ->modify('-7 days')
        ->format('Y-m-d');
    $ops = $pdo->query('SELECT id FROM operators')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $accStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(accepted_hours),0) FROM sessions
         WHERE operator_id = ? AND day >= ? AND status = 'completed'"
    );
    $rejStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(duration_hours),0) FROM sessions
         WHERE operator_id = ? AND day >= ? AND status = 'rejected'"
    );
    $lastStmt = $pdo->prepare(
        'SELECT completed_at FROM sessions WHERE operator_id = ? AND completed_at IS NOT NULL
         ORDER BY completed_at DESC LIMIT 1'
    );
    $upd = $pdo->prepare(
        'UPDATE operators SET accepted_7d = ?, rejected_7d = ?, last_session_at = ?, updated_at = datetime(\'now\')
         WHERE id = ?'
    );
    foreach ($ops as $opId) {
        $opId = (int) $opId;
        $accStmt->execute([$opId, $since]);
        $accepted = (float) $accStmt->fetchColumn();
        $rejStmt->execute([$opId, $since]);
        $rejected = (float) $rejStmt->fetchColumn();
        $lastStmt->execute([$opId]);
        $last = $lastStmt->fetchColumn();
        $upd->execute([$accepted, $rejected, $last !== false ? (string) $last : null, $opId]);
    }
}

/**
 * Live poll Shift APIs then ingest.
 *
 * @param array{allow_empty_sessions?: bool} $opts
 * @return array<string, mixed>
 */
function repsShiftPollLive(array $opts = []): array
{
    // Live read-only GETs are approved (CARDINAL). Cookie required only for joinshift host.
    if (repsShiftIsLiveJoinshiftBase() && !is_readable(repsShiftCookieJarPath())) {
        repsDashAppMetaSet('shift.last_poll_at', gmdate('c'));
        repsDashAppMetaSet('shift.last_poll_error', 'missing_cookie_jar');
        return ['ok' => false, 'error' => 'missing_cookie_jar'];
    }
    $feedRes = repsShiftGetHoursFeed();
    if (!($feedRes['ok'] ?? false)) {
        $err = 'hours_feed:' . ($feedRes['error'] ?? 'fail');
        repsDashAppMetaSet('shift.last_poll_at', gmdate('c'));
        repsDashAppMetaSet('shift.last_poll_error', $err);
        return ['ok' => false, 'error' => $err, 'detail' => $feedRes];
    }
    $teamRes = repsShiftGetTeamMembers();
    $workersRes = repsShiftGetWorkers();
    $team = ($teamRes['ok'] ?? false) ? $teamRes['body'] : null;
    $workers = ($workersRes['ok'] ?? false) ? $workersRes['body'] : null;
    // Team/workers optional: never let a failed secondary wipe; empty workers during outage is common.
    $ingested = repsShiftIngestFeed($feedRes['body'], $team, $workers, $opts);
    $ingested['team_ok'] = $team !== null;
    $ingested['workers_ok'] = $workers !== null;
    if (is_array($team) && isset($team['members']) && is_array($team['members'])) {
        $ingested['team_members'] = count($team['members']);
    }
    if (is_array($workers) && isset($workers['workers']) && is_array($workers['workers'])) {
        $ingested['workers_count'] = count($workers['workers']);
    }
    return $ingested;
}

/**
 * Match Shift operator row → Reps user (admin/ops).
 *
 * @return array{ok: bool, error?: string}
 */
function repsOperatorMatchUser(int $operatorId, int $userId, int $actorUserId, string $note = ''): array
{
    if ($operatorId <= 0 || $userId <= 0) {
        return ['ok' => false, 'error' => 'invalid_ids'];
    }
    $pdo = repsDashDb();
    $op = repsOperatorById($operatorId);
    if (!$op) {
        return ['ok' => false, 'error' => 'operator_not_found'];
    }
    $u = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $u->execute([$userId]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['ok' => false, 'error' => 'user_not_found'];
    }
    $role = (string) ($user['role'] ?? '');
    if (!in_array($role, ['individual', 'employee', 'business_owner'], true)) {
        return ['ok' => false, 'error' => 'user_role_not_matchable'];
    }

    // Clear prior match on this operator
    if (!empty($op['matched_user_id']) && (int) $op['matched_user_id'] !== $userId) {
        $pdo->prepare(
            'UPDATE users SET operator_id = NULL, updated_at = datetime(\'now\')
             WHERE id = ? AND operator_id = ?'
        )->execute([(int) $op['matched_user_id'], $operatorId]);
    }

    // Clear this user from any other operator
    $pdo->prepare(
        'UPDATE operators SET matched_user_id = NULL, matched_at = NULL, matched_by_user_id = NULL,
         updated_at = datetime(\'now\')
         WHERE matched_user_id = ? AND id != ?'
    )->execute([$userId, $operatorId]);

    $shopId = isset($user['shop_id']) && $user['shop_id'] !== null && $user['shop_id'] !== ''
        ? (int) $user['shop_id']
        : null;

    $pdo->prepare(
        'UPDATE operators SET matched_user_id = ?, matched_at = datetime(\'now\'), matched_by_user_id = ?,
         shop_id = COALESCE(?, shop_id),
         updated_at = datetime(\'now\')
         WHERE id = ?'
    )->execute([$userId, $actorUserId, $shopId, $operatorId]);

    $pdo->prepare(
        'UPDATE users SET operator_id = ?, updated_at = datetime(\'now\') WHERE id = ?'
    )->execute([$operatorId, $userId]);

    $pdo->prepare(
        'INSERT INTO operator_match_events (operator_id, user_id, actor_user_id, event_type, note)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$operatorId, $userId, $actorUserId, 'match', $note]);

    // Point sessions at this operator's shop when known
    if ($shopId !== null && $shopId > 0) {
        $pdo->prepare(
            'UPDATE sessions SET shop_id = ?, updated_at = datetime(\'now\') WHERE operator_id = ?'
        )->execute([$shopId, $operatorId]);
    }

    return ['ok' => true];
}

/**
 * @return array{ok: bool, error?: string}
 */
function repsOperatorUnmatch(int $operatorId, int $actorUserId, string $note = ''): array
{
    $op = repsOperatorById($operatorId);
    if (!$op) {
        return ['ok' => false, 'error' => 'operator_not_found'];
    }
    $pdo = repsDashDb();
    $uid = (int) ($op['matched_user_id'] ?? 0);
    $pdo->prepare(
        'UPDATE operators SET matched_user_id = NULL, matched_at = NULL, matched_by_user_id = NULL,
         updated_at = datetime(\'now\') WHERE id = ?'
    )->execute([$operatorId]);
    if ($uid > 0) {
        $pdo->prepare(
            'UPDATE users SET operator_id = NULL, updated_at = datetime(\'now\')
             WHERE id = ? AND operator_id = ?'
        )->execute([$uid, $operatorId]);
    }
    $pdo->prepare(
        'INSERT INTO operator_match_events (operator_id, user_id, actor_user_id, event_type, note)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$operatorId, $uid > 0 ? $uid : null, $actorUserId, 'unmatch', $note]);
    return ['ok' => true];
}
