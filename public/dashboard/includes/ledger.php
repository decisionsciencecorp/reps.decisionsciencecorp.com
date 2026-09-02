<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Accepted-hour ledger — 25/25/50 lines (DSC stays; affiliate + capture disburse).
 */

require_once __DIR__ . '/economics.php';

/**
 * @param array{
 *   hour_key: string,
 *   hours: float,
 *   shop_id?: int|null,
 *   operator_id?: int|null,
 *   affiliate_user_id?: int|null,
 *   affiliate_username?: string|null,
 *   has_shop: bool,
 *   has_affiliate: bool,
 *   settlement_id?: int|null,
 *   accepted_at?: string|null
 * } $input
 * @return array{ok: bool, created?: bool, line_ids?: list<int>, error?: string}
 */
function repsLedgerPostAcceptedHour(array $input): array
{
    $hourKey = trim((string) ($input['hour_key'] ?? ''));
    if ($hourKey === '') {
        return ['ok' => false, 'error' => 'missing_hour_key'];
    }
    $pdo = repsDashDb();
    $exists = $pdo->prepare('SELECT id FROM ledger_lines WHERE hour_key = ? LIMIT 1');
    $exists->execute([$hourKey]);
    if ($exists->fetch()) {
        $ids = $pdo->prepare('SELECT id FROM ledger_lines WHERE hour_key = ? ORDER BY id');
        $ids->execute([$hourKey]);
        return [
            'ok' => true,
            'created' => false,
            'line_ids' => array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN) ?: []),
        ];
    }

    $hours = (float) ($input['hours'] ?? 0);
    if (isset($input['gross_cents'])) {
        $split = repsDashSplitGrossCents(
            (int) $input['gross_cents'],
            (bool) ($input['has_shop'] ?? false),
            (bool) ($input['has_affiliate'] ?? false),
            $hours
        );
    } else {
        $rate = isset($input['hourly_rate']) ? (float) $input['hourly_rate'] : null;
        $split = repsDashSplitAcceptedHours(
            $hours,
            (bool) ($input['has_shop'] ?? false),
            (bool) ($input['has_affiliate'] ?? false),
            $rate
        );
    }
    $shopId = isset($input['shop_id']) ? (int) $input['shop_id'] : null;
    $opId = isset($input['operator_id']) ? (int) $input['operator_id'] : null;
    $affUser = isset($input['affiliate_user_id']) ? (int) $input['affiliate_user_id'] : null;
    $affName = isset($input['affiliate_username']) ? (string) $input['affiliate_username'] : null;
    $settlementId = isset($input['settlement_id']) ? (int) $input['settlement_id'] : null;
    $acceptedAt = (string) ($input['accepted_at'] ?? gmdate('Y-m-d H:i:s'));

    $insert = $pdo->prepare(
        'INSERT INTO ledger_lines (
            hour_key, bucket, amount_cents, hours, shop_id, operator_id,
            affiliate_user_id, affiliate_username, capture_payee, settlement_id,
            status, accepted_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $lineIds = [];
    $pdo->beginTransaction();
    try {
        // DSC share — never disbursed via Transfers
        $insert->execute([
            $hourKey, 'dsc', $split['dsc_cents'], $hours, $shopId, $opId,
            $affUser, $affName, $split['capture_payee'], $settlementId,
            'retained', $acceptedAt,
        ]);
        $lineIds[] = (int) $pdo->lastInsertId();

        if ($split['affiliate_cents'] > 0) {
            $insert->execute([
                $hourKey, 'affiliate', $split['affiliate_cents'], $hours, $shopId, $opId,
                $affUser, $affName, $split['capture_payee'], $settlementId,
                'pending', $acceptedAt,
            ]);
            $lineIds[] = (int) $pdo->lastInsertId();
        }

        $insert->execute([
            $hourKey, 'capture', $split['capture_cents'], $hours, $shopId, $opId,
            $affUser, $affName, $split['capture_payee'], $settlementId,
            'pending', $acceptedAt,
        ]);
        $lineIds[] = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return ['ok' => true, 'created' => true, 'line_ids' => $lineIds];
}

/**
 * True when this process is serving (or simulating) production Reps.
 * Hard-blocks demo/mock auto-seed paths that must never invent dollars on multihost.
 */
function repsDashIsProductionHost(): bool
{
    if (getenv('APP_ENV') === 'production') {
        return true;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        $host = (string) (getenv('REPS_PUBLIC_HOST') ?: '');
    }
    $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
    return $host === 'reps.decisionsciencecorp.com';
}

/**
 * Whether Money (and similar) may auto-call repsLedgerSeedFromMockShops().
 *
 * Default: allow only on non-prod hosts when dash.skip_demo_seed is not locked.
 * Explicit override: REPS_DASH_ALLOW_MOCK_LEDGER=1 (dev/demo only — never set on multihost).
 */
function repsDashAllowMockLedgerSeed(): bool
{
    if (getenv('REPS_DASH_ALLOW_MOCK_LEDGER') === '1') {
        return true;
    }
    if (function_exists('repsDashDemoSeedLocked') && repsDashDemoSeedLocked()) {
        return false;
    }
    if (repsDashIsProductionHost()) {
        return false;
    }
    return true;
}

/**
 * Money-page gate: seed mock ledger only when empty, live data off, and mock seed is allowed.
 */
function repsDashShouldSeedMockLedgerOnMoney(): bool
{
    if (function_exists('repsDashLiveDataEnabled') && repsDashLiveDataEnabled()) {
        return false;
    }
    if (!repsDashAllowMockLedgerSeed()) {
        return false;
    }
    try {
        $n = (int) repsDashDb()->query('SELECT COUNT(*) FROM ledger_lines')->fetchColumn();
        return $n === 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Build ledger from mock shop/operator accepted hours (bootstrap until Slice C).
 *
 * @return array{ok: bool, posted: int, skipped: int}
 */
function repsLedgerSeedFromMockShops(): array
{
    $posted = 0;
    $skipped = 0;
    foreach (repsDashMockShops() as $shop) {
        $hours = (float) ($shop['accepted_hours_7d'] ?? 0);
        if ($hours <= 0) {
            continue;
        }
        $hasAff = repsDashShopHasAffiliate($shop);
        $rep = trim((string) ($shop['assigned_sales_rep'] ?? ''));
        $key = 'mock_shop_' . (int) $shop['id'] . '_7d';
        $res = repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => $hours,
            'shop_id' => (int) $shop['id'],
            'has_shop' => true,
            'has_affiliate' => $hasAff,
            'affiliate_username' => $hasAff ? $rep : null,
            'accepted_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!empty($res['created'])) {
            $posted++;
        } else {
            $skipped++;
        }
    }
    foreach (repsDashMockOperators() as $op) {
        if ((int) ($op['shop_id'] ?? 0) > 0) {
            continue; // shop hours already counted on shop
        }
        $hours = (float) ($op['accepted_7d'] ?? 0);
        if ($hours <= 0) {
            continue;
        }
        $rep = trim((string) ($op['assigned_sales_rep'] ?? ''));
        $hasAff = $rep !== '' && strcasecmp($rep, 'unassigned') !== 0;
        $key = 'mock_op_' . (int) $op['id'] . '_7d';
        $res = repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => $hours,
            'operator_id' => (int) $op['id'],
            'has_shop' => false,
            'has_affiliate' => $hasAff,
            'affiliate_username' => $hasAff ? $rep : null,
        ]);
        if (!empty($res['created'])) {
            $posted++;
        } else {
            $skipped++;
        }
    }
    return ['ok' => true, 'posted' => $posted, 'skipped' => $skipped];
}

/**
 * @return array{owed_cents: int, retained_cents: int, transferred_cents: int, pending_lines: int}
 */
function repsLedgerTotals(): array
{
    $pdo = repsDashDb();
    $owed = (int) $pdo->query(
        "SELECT COALESCE(SUM(amount_cents),0) FROM ledger_lines
         WHERE bucket IN ('affiliate','capture') AND status IN ('pending','owed')"
    )->fetchColumn();
    $retained = (int) $pdo->query(
        "SELECT COALESCE(SUM(amount_cents),0) FROM ledger_lines WHERE bucket = 'dsc'"
    )->fetchColumn();
    $transferred = (int) $pdo->query(
        "SELECT COALESCE(SUM(amount_cents),0) FROM ledger_lines WHERE status = 'transferred'"
    )->fetchColumn();
    $pendingLines = (int) $pdo->query(
        "SELECT COUNT(*) FROM ledger_lines WHERE status IN ('pending','owed')"
    )->fetchColumn();

    return [
        'owed_cents' => $owed,
        'retained_cents' => $retained,
        'transferred_cents' => $transferred,
        'pending_lines' => $pendingLines,
    ];
}

/** @return list<array<string, mixed>> */
function repsLedgerListPending(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $stmt = repsDashDb()->query(
        "SELECT * FROM ledger_lines WHERE bucket IN ('affiliate','capture')
         AND status IN ('pending','owed') ORDER BY id ASC LIMIT " . (int) $limit
    );
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/** @return list<array<string, mixed>> */
function repsLedgerListRecent(int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = repsDashDb()->query(
        'SELECT * FROM ledger_lines ORDER BY id DESC LIMIT ' . (int) $limit
    );
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}
