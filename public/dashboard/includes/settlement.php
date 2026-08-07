<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Shift settlement discovery + reconcile against Stripe platform balance.
 *
 * LOCKED (Mark 2026-08-07) — Doc #1036:
 *   Partner cash = America/Chicago Mon–Sun accepted_hours × $20, paid next Monday.
 *   No Shift remittance API; match hours-feed week to Monday deposit (bank/Stripe).
 *   Per person = that worker's accepted hours × $20 in the week.
 *   Late Sunday accepts may miss Monday batch → carry forward.
 *   Contract #707 14-day/quality tiers = legal fallback only.
 *
 * Ops import / Stripe balance webhooks book settlement_events; then disburse.
 */

require_once __DIR__ . '/stripe-client.php';
require_once __DIR__ . '/economics.php';

/**
 * Record a settlement event (idempotent on source_key).
 *
 * @param array<string, mixed> $meta
 * @return array{ok: bool, id?: int, created?: bool, error?: string}
 */
function repsSettlementRecord(
    string $source,
    string $sourceKey,
    int $amountCents,
    string $currency = 'usd',
    string $status = 'recorded',
    array $meta = []
): array {
    if ($sourceKey === '' || $amountCents < 0) {
        return ['ok' => false, 'error' => 'invalid_args'];
    }
    $pdo = repsDashDb();
    $existing = $pdo->prepare('SELECT id FROM settlement_events WHERE source = ? AND source_key = ? LIMIT 1');
    $existing->execute([$source, $sourceKey]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return ['ok' => true, 'id' => (int) $row['id'], 'created' => false];
    }
    $pdo->prepare(
        'INSERT INTO settlement_events (source, source_key, amount_cents, currency, status, meta_json)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $source,
        $sourceKey,
        $amountCents,
        strtolower($currency),
        $status,
        $meta === [] ? '{}' : (string) json_encode($meta, JSON_UNESCAPED_SLASHES),
    ]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'created' => true];
}

/**
 * Snapshot platform Stripe balance into settlement_events (source=stripe_balance).
 *
 * @return array{ok: bool, id?: int, available_cents?: int, pending_cents?: int, error?: string}
 */
function repsSettlementReconcileStripeBalance(?string $label = null): array
{
    $bal = repsStripeBalance();
    if (!$bal['ok']) {
        // Soft path when keys missing: still record a discovery stub for ops.
        if (($bal['error'] ?? '') === 'missing_secret_key' || !repsStripeConfigured()) {
            $key = 'discovery_' . gmdate('Y-m-d');
            $rec = repsSettlementRecord(
                'shift_discovery',
                $key,
                0,
                'usd',
                'pending_api',
                [
                    'note' => 'Shift settlement API not wired; Stripe keys empty or balance call failed.',
                    'stripe_error' => $bal['error'] ?? 'not_configured',
                    'label' => $label,
                ]
            );
            return [
                'ok' => (bool) ($rec['ok'] ?? false),
                'id' => $rec['id'] ?? null,
                'available_cents' => 0,
                'pending_cents' => 0,
                'error' => $bal['error'] ?? 'not_configured',
            ];
        }
        return ['ok' => false, 'error' => $bal['error'] ?? 'balance_failed'];
    }

    $key = 'bal_' . gmdate('Y-m-d\TH:i:s\Z') . '_' . $bal['available_cents'];
    $rec = repsSettlementRecord(
        'stripe_balance',
        $key,
        (int) $bal['available_cents'],
        'usd',
        'available',
        [
            'pending_cents' => $bal['pending_cents'],
            'label' => $label,
        ]
    );
    return [
        'ok' => (bool) ($rec['ok'] ?? false),
        'id' => $rec['id'] ?? null,
        'available_cents' => (int) $bal['available_cents'],
        'pending_cents' => (int) $bal['pending_cents'],
    ];
}

/**
 * Import a Shift (or bank) settlement amount once known.
 *
 * @return array{ok: bool, id?: int, created?: bool, error?: string}
 */
function repsSettlementImportShift(string $weekOrId, int $amountCents, array $meta = []): array
{
    return repsSettlementRecord(
        'shift',
        $weekOrId,
        $amountCents,
        'usd',
        'recorded',
        $meta
    );
}

/**
 * Mark settlement reconciled when ledger coverage matches (ops check).
 */
function repsSettlementMarkReconciled(int $settlementId, string $note = ''): bool
{
    $pdo = repsDashDb();
    // Keep meta as opaque JSON string append via replace when note set.
    if ($note !== '') {
        $pdo->prepare(
            'UPDATE settlement_events SET status = ?, meta_json = ?, updated_at = datetime(\'now\') WHERE id = ?'
        )->execute(['reconciled', json_encode(['reconcile_note' => $note]), $settlementId]);
    } else {
        $pdo->prepare(
            'UPDATE settlement_events SET status = ?, updated_at = datetime(\'now\') WHERE id = ?'
        )->execute(['reconciled', $settlementId]);
    }
    return true;
}

/** @return list<array<string, mixed>> */
function repsSettlementList(int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = repsDashDb()->query(
        'SELECT * FROM settlement_events ORDER BY id DESC LIMIT ' . (int) $limit
    );
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/**
 * Compare open ledger owed (affiliate+capture pending) vs Stripe available.
 *
 * @return array{ledger_owed_cents: int, stripe_available_cents: int|null, gap_cents: int|null, stripe_ok: bool}
 */
function repsSettlementCoverage(): array
{
    $pdo = repsDashDb();
    $owed = (int) $pdo->query(
        "SELECT COALESCE(SUM(amount_cents), 0) FROM ledger_lines
         WHERE bucket IN ('affiliate','capture') AND status IN ('pending','owed')"
    )->fetchColumn();

    $stripeOk = false;
    $available = null;
    if (repsStripeConfigured()) {
        $bal = repsStripeBalance();
        $stripeOk = (bool) ($bal['ok'] ?? false);
        if ($stripeOk) {
            $available = (int) $bal['available_cents'];
        }
    }

    return [
        'ledger_owed_cents' => $owed,
        'stripe_available_cents' => $available,
        'gap_cents' => $available === null ? null : ($available - $owed),
        'stripe_ok' => $stripeOk,
    ];
}

/** Locked batch cutoff: Sunday 22:00 America/Chicago (late accepts → next Monday). Doc #1036. */
function repsSettlementBatchCutoffHourCt(): int
{
    return 22;
}

function repsSettlementTz(): DateTimeZone
{
    return new DateTimeZone('America/Chicago');
}

/**
 * Chicago Mon–Sun week containing $when, plus following cash Monday.
 *
 * @return array{
 *   week_start: string,
 *   week_end: string,
 *   cash_monday: string,
 *   week_key: string,
 *   cutoff_local: string
 * }
 */
function repsSettlementChicagoWeekContaining(string $whenIso): array
{
    $tz = repsSettlementTz();
    $dt = new DateTimeImmutable($whenIso);
    $local = $dt->setTimezone($tz);
    // Monday = 1 in ISO; convert: Mon=0 … Sun=6
    $dow = ((int) $local->format('N')) - 1;
    $monday = $local->setTime(0, 0, 0)->modify('-' . $dow . ' days');
    $sunday = $monday->modify('+6 days');
    $cashMonday = $sunday->modify('+1 day');
    $cutoff = $sunday->setTime(repsSettlementBatchCutoffHourCt(), 0, 0);

    return [
        'week_start' => $monday->format('Y-m-d'),
        'week_end' => $sunday->format('Y-m-d'),
        'cash_monday' => $cashMonday->format('Y-m-d'),
        'week_key' => 'chi_w_' . $monday->format('Y-m-d'),
        'cutoff_local' => $cutoff->format(DateTimeInterface::ATOM),
    ];
}

/**
 * Week window paid by a Monday cash deposit (prior Mon–Sun).
 *
 * @return array{week_start: string, week_end: string, cash_monday: string, week_key: string, cutoff_local: string}
 */
function repsSettlementWeekForCashMonday(string $cashMondayYmd): array
{
    $tz = repsSettlementTz();
    $cash = new DateTimeImmutable($cashMondayYmd . ' 12:00:00', $tz);
    $sunday = $cash->modify('-1 day')->setTime(0, 0, 0);
    $monday = $sunday->modify('-6 days');
    $cutoff = $sunday->setTime(repsSettlementBatchCutoffHourCt(), 0, 0);

    return [
        'week_start' => $monday->format('Y-m-d'),
        'week_end' => $sunday->format('Y-m-d'),
        'cash_monday' => $cash->format('Y-m-d'),
        'week_key' => 'chi_w_' . $monday->format('Y-m-d'),
        'cutoff_local' => $cutoff->format(DateTimeInterface::ATOM),
    ];
}

/**
 * Normalize hours-feed session list (top-level or ['sessions'=>…]).
 *
 * @param array<mixed> $feedOrSessions
 * @return list<array<string, mixed>>
 */
function repsSettlementNormalizeSessions(array $feedOrSessions): array
{
    if (isset($feedOrSessions['sessions']) && is_array($feedOrSessions['sessions'])) {
        $feedOrSessions = $feedOrSessions['sessions'];
    }
    $out = [];
    foreach ($feedOrSessions as $row) {
        if (is_array($row)) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * Sessions that settle on $cashMondayYmd (prior week, completed before Sun 22:00 CT).
 *
 * @param list<array<string, mixed>>|array<string, mixed> $feedOrSessions
 * @return array{
 *   ok: bool,
 *   week: array<string, string>,
 *   accepted_hours: float,
 *   amount_cents: int,
 *   by_person: array<string, array{name: string, user_id: string, accepted_hours: float, amount_cents: int}>,
 *   sessions: list<array<string, mixed>>,
 *   carried: list<array<string, mixed>>
 * }
 */
function repsSettlementAccrueForCashMonday(array $feedOrSessions, string $cashMondayYmd): array
{
    $week = repsSettlementWeekForCashMonday($cashMondayYmd);
    $tz = repsSettlementTz();
    $start = new DateTimeImmutable($week['week_start'] . ' 00:00:00', $tz);
    $endDay = new DateTimeImmutable($week['week_end'] . ' 23:59:59', $tz);
    $cutoff = new DateTimeImmutable($week['cutoff_local']);

    $included = [];
    $carried = [];
    $byPerson = [];
    $totalH = 0.0;

    foreach (repsSettlementNormalizeSessions($feedOrSessions) as $s) {
        if (($s['status'] ?? '') !== 'completed') {
            continue;
        }
        $hours = (float) ($s['accepted_hours'] ?? 0);
        if ($hours <= 0) {
            continue;
        }
        $completed = (string) ($s['completed_at'] ?? $s['created_at'] ?? '');
        if ($completed === '') {
            continue;
        }
        $local = (new DateTimeImmutable($completed))->setTimezone($tz);
        if ($local < $start || $local > $endDay) {
            continue;
        }
        $row = $s;
        $row['_local_completed'] = $local->format(DateTimeInterface::ATOM);
        if ($local >= $cutoff) {
            $carried[] = $row;
            continue;
        }
        $included[] = $row;
        $totalH += $hours;
        $uid = (string) ($s['user_id'] ?? '');
        $name = trim((string) ($s['first_name'] ?? '') . ' ' . (string) ($s['last_name'] ?? ''));
        if ($name === '') {
            $name = $uid !== '' ? $uid : 'unknown';
        }
        if (!isset($byPerson[$uid !== '' ? $uid : $name])) {
            $byPerson[$uid !== '' ? $uid : $name] = [
                'name' => $name,
                'user_id' => $uid,
                'accepted_hours' => 0.0,
                'amount_cents' => 0,
            ];
        }
        $key = $uid !== '' ? $uid : $name;
        $byPerson[$key]['accepted_hours'] += $hours;
    }

    foreach ($byPerson as $k => $p) {
        $byPerson[$k]['amount_cents'] = (int) round($p['accepted_hours'] * repsDashMoneyHourlyRate() * 100);
    }

    $amountCents = (int) round($totalH * repsDashMoneyHourlyRate() * 100);

    return [
        'ok' => true,
        'week' => $week,
        'accepted_hours' => round($totalH, 6),
        'amount_cents' => $amountCents,
        'by_person' => $byPerson,
        'sessions' => $included,
        'carried' => $carried,
    ];
}

/**
 * Book Monday bank/Wise cash (idempotent). source_key = monday:YYYY-MM-DD.
 *
 * @param array<string, mixed> $meta
 * @return array{ok: bool, id?: int, created?: bool, error?: string}
 */
function repsSettlementBookMondayCash(string $cashMondayYmd, int $amountCents, array $meta = []): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cashMondayYmd) || $amountCents < 0) {
        return ['ok' => false, 'error' => 'invalid_args'];
    }
    $week = repsSettlementWeekForCashMonday($cashMondayYmd);
    $meta = array_merge([
        'model' => 'chi_mon_sun_accepted_x20',
        'doc' => 1036,
        'week_start' => $week['week_start'],
        'week_end' => $week['week_end'],
        'cutoff_local' => $week['cutoff_local'],
        'cash_monday' => $cashMondayYmd,
    ], $meta);

    return repsSettlementRecord(
        'bank_monday',
        'monday:' . $cashMondayYmd,
        $amountCents,
        'usd',
        'recorded',
        $meta
    );
}

/**
 * Accrue hours-feed → book Monday cash (expected amount) → post ledger lines for included sessions.
 *
 * @param list<array<string, mixed>>|array<string, mixed> $feedOrSessions
 * @param array{has_shop?: bool, has_affiliate?: bool, shop_id?: int|null, affiliate_username?: string|null, amount_cents?: int|null} $opts
 * @return array{
 *   ok: bool,
 *   accrual?: array<string, mixed>,
 *   settlement?: array<string, mixed>,
 *   ledger?: array{posted: int, skipped: int, errors: list<string>},
 *   error?: string
 * }
 */
function repsSettlementProcessCashMonday(
    array $feedOrSessions,
    string $cashMondayYmd,
    array $opts = []
): array {
    require_once __DIR__ . '/ledger.php';

    $accrual = repsSettlementAccrueForCashMonday($feedOrSessions, $cashMondayYmd);
    $amount = isset($opts['amount_cents']) ? (int) $opts['amount_cents'] : (int) $accrual['amount_cents'];
    $book = repsSettlementBookMondayCash($cashMondayYmd, $amount, [
        'accepted_hours' => $accrual['accepted_hours'],
        'accrued_cents' => $accrual['amount_cents'],
        'by_person' => $accrual['by_person'],
        'carried_count' => count($accrual['carried']),
        'session_count' => count($accrual['sessions']),
    ]);
    if (!($book['ok'] ?? false)) {
        return ['ok' => false, 'error' => $book['error'] ?? 'book_failed', 'accrual' => $accrual];
    }

    $settlementId = (int) ($book['id'] ?? 0);
    $hasShop = (bool) ($opts['has_shop'] ?? false);
    $hasAff = (bool) ($opts['has_affiliate'] ?? false);
    $shopId = array_key_exists('shop_id', $opts) ? $opts['shop_id'] : null;
    $affName = $opts['affiliate_username'] ?? null;

    $posted = 0;
    $skipped = 0;
    $errors = [];
    foreach ($accrual['sessions'] as $s) {
        $sid = (string) ($s['session_id'] ?? '');
        if ($sid === '') {
            $errors[] = 'missing_session_id';
            continue;
        }
        $hours = (float) ($s['accepted_hours'] ?? 0);
        $res = repsLedgerPostAcceptedHour([
            'hour_key' => 'shift_sess_' . $sid,
            'hours' => $hours,
            'shop_id' => $shopId !== null ? (int) $shopId : null,
            'has_shop' => $hasShop,
            'has_affiliate' => $hasAff,
            'affiliate_username' => is_string($affName) ? $affName : null,
            'settlement_id' => $settlementId,
            'accepted_at' => (string) ($s['completed_at'] ?? gmdate('Y-m-d H:i:s')),
        ]);
        if (!($res['ok'] ?? false)) {
            $errors[] = $sid . ':' . ($res['error'] ?? 'ledger_fail');
            continue;
        }
        if ($res['created'] ?? false) {
            $posted++;
        } else {
            $skipped++;
        }
    }

    return [
        'ok' => $errors === [],
        'accrual' => $accrual,
        'settlement' => $book,
        'ledger' => [
            'posted' => $posted,
            'skipped' => $skipped,
            'errors' => $errors,
        ],
        'error' => $errors === [] ? null : 'ledger_errors',
    ];
}
