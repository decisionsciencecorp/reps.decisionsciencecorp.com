<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Locked pie of partner payout: DSC 25% · Affiliate 25% · Capture 50%.
 * Capture payee is shop XOR operator (never both).
 *
 * Dollar totals follow whatever Shift actually paid (or a stored estimate
 * when the hours feed only has hours). Never bake a $20 floor into the split.
 */

function repsDashMoneyShareDsc(): float
{
    return 0.25;
}

function repsDashMoneyShareAffiliate(): float
{
    return 0.25;
}

function repsDashMoneyShareCapture(): float
{
    return 0.50;
}

function repsDashMoneyPieCaption(): string
{
    $d = (int) round(repsDashMoneyShareDsc() * 100);
    $a = (int) round(repsDashMoneyShareAffiliate() * 100);
    $c = (int) round(repsDashMoneyShareCapture() * 100);
    return $d . '/' . $a . '/' . $c . ' of partner payout';
}

/**
 * Estimate only — used when a row has hours but no dollar amount from Shift.
 * Override with app_meta `money.partner_hourly_cents` (e.g. 3000 if a window is $30).
 */
function repsDashMoneyHourlyRate(): float
{
    try {
        $cents = (int) repsDashAppMetaGet('money.partner_hourly_cents', '0');
        if ($cents > 0) {
            return $cents / 100.0;
        }
    } catch (Throwable $e) {
        // schema not ready
    }
    $env = repsDashEnvOrDefault('REPS_MONEY_PARTNER_HOURLY', '');
    if ($env !== null && $env !== '' && is_numeric($env)) {
        $n = (float) $env;
        if ($n > 0) {
            return $n;
        }
    }
    return 20.0;
}

/**
 * Locked fixed cents per accepted hour (Doc #1236). Do not grow with real rate;
 * excess over $20/hr is booked at settlement, not here.
 */
function repsDashMoneyCaptureCentsPerHour(): int
{
    return 1000; // $10
}

function repsDashMoneyAffiliateCentsPerHour(bool $chuckTree = false): int
{
    return $chuckTree ? 300 : 500; // $3 Chuck-tree · $5 standard
}

function repsDashMoneyDscCentsPerHour(): int
{
    return 500; // $5
}

function repsDashMoneyChuckHoldbackCentsPerHour(): int
{
    return 200; // $2 — DSC accounting only
}

/**
 * Split a known gross (cents) into ledger buckets (legacy percentage path).
 * Prefer repsDashSplitAcceptedHours() for accrual (fixed $/hr).
 *
 * @return array{
 *   hours: float,
 *   gross_cents: int,
 *   dsc_cents: int,
 *   affiliate_cents: int,
 *   capture_cents: int,
 *   chuck_holdback_cents: int,
 *   capture_payee: 'shop'|'operator',
 *   affiliate_to_dsc: bool,
 *   chuck_tree: bool,
 *   hourly_rate: float|null
 * }
 */
function repsDashSplitGrossCents(
    int $grossCents,
    bool $hasShop,
    bool $hasAffiliate,
    float $hours = 0.0,
    ?float $hourlyRate = null,
    bool $chuckTree = false
): array {
    $grossCents = max(0, $grossCents);
    $dsc = (int) floor($grossCents * repsDashMoneyShareDsc());
    $aff = (int) floor($grossCents * repsDashMoneyShareAffiliate());
    $capture = $grossCents - $dsc - $aff;
    $holdback = 0;
    $affiliateToDsc = !$hasAffiliate;
    if ($affiliateToDsc) {
        $dsc += $aff;
        $aff = 0;
    } elseif ($chuckTree && $aff > 0) {
        // Map 25% affiliate slice into $3 equiv + $2 holdback by ratio of locked rates.
        $holdback = (int) floor($aff * 2 / 5);
        $aff = $aff - $holdback;
    }

    return [
        'hours' => $hours,
        'gross_cents' => $grossCents,
        'dsc_cents' => $dsc,
        'affiliate_cents' => $aff,
        'capture_cents' => $capture,
        'chuck_holdback_cents' => $holdback,
        'capture_payee' => $hasShop ? 'shop' : 'operator',
        'affiliate_to_dsc' => $affiliateToDsc,
        'chuck_tree' => $chuckTree && $hasAffiliate,
        'hourly_rate' => $hourlyRate,
    ];
}

/**
 * Split accepted hours using locked fixed $/hr (preferred accrual path).
 *
 * @return array{
 *   hours: float,
 *   gross_cents: int,
 *   dsc_cents: int,
 *   affiliate_cents: int,
 *   capture_cents: int,
 *   chuck_holdback_cents: int,
 *   capture_payee: 'shop'|'operator',
 *   affiliate_to_dsc: bool,
 *   chuck_tree: bool,
 *   hourly_rate: float|null
 * }
 */
function repsDashSplitAcceptedHours(
    float $hours,
    bool $hasShop,
    bool $hasAffiliate,
    ?float $hourlyRate = null,
    bool $chuckTree = false
): array {
    $hours = max(0.0, $hours);
    $rate = $hourlyRate ?? repsDashMoneyHourlyRate();
    $capture = (int) round($hours * repsDashMoneyCaptureCentsPerHour());
    $dsc = (int) round($hours * repsDashMoneyDscCentsPerHour());
    $aff = 0;
    $holdback = 0;
    $affiliateToDsc = !$hasAffiliate;
    if ($hasAffiliate) {
        $aff = (int) round($hours * repsDashMoneyAffiliateCentsPerHour($chuckTree));
        if ($chuckTree) {
            $holdback = (int) round($hours * repsDashMoneyChuckHoldbackCentsPerHour());
        }
    } else {
        // No affiliate → standard $5/hr affiliate slice stays with DSC (not Chuck holdback).
        $dsc += (int) round($hours * repsDashMoneyAffiliateCentsPerHour(false));
    }
    $grossCents = $dsc + $aff + $capture + $holdback;

    return [
        'hours' => $hours,
        'gross_cents' => $grossCents,
        'dsc_cents' => $dsc,
        'affiliate_cents' => $aff,
        'capture_cents' => $capture,
        'chuck_holdback_cents' => $holdback,
        'capture_payee' => $hasShop ? 'shop' : 'operator',
        'affiliate_to_dsc' => $affiliateToDsc,
        'chuck_tree' => $chuckTree && $hasAffiliate,
        'hourly_rate' => $rate,
    ];
}

function repsDashShopHasAffiliate(array $shop): bool
{
    $rep = trim((string) ($shop['assigned_sales_rep'] ?? ''));
    if ($rep === '' || strcasecmp($rep, 'unassigned') === 0) {
        return false;
    }
    // Legacy mock: agreed_shop_split ≈ 0 meant internal Empanada (no affiliate seat).
    $split = (float) ($shop['agreed_shop_split'] ?? 0.5);
    if ($split <= 0.001) {
        return false;
    }
    return true;
}

/** True when the sales username has the admin-only Chuck-tree flag. */
function repsDashAffiliateIsChuckTree(?string $username): bool
{
    $rep = trim((string) ($username ?? ''));
    if ($rep === '' || strcasecmp($rep, 'unassigned') === 0) {
        return false;
    }
    $u = repsDashFindUserByUsername($rep);
    return is_array($u) && !empty($u['chuck_tree']);
}

/**
 * @return array{
 *   hours: float,
 *   gross: float,
 *   shop_pay: float,
 *   dsc_pay: float,
 *   partner_lane: float,
 *   your_affiliate: float,
 *   internal: bool,
 *   capture_pay: float,
 *   affiliate_pay: float
 * }
 */
function repsDashMoneyShopEconomics(array $shop, float $rate): array
{
    unset($rate); // pie uses current partner estimate unless shop carries gross
    $hours = (float) ($shop['accepted_hours_7d'] ?? 0);
    $hasAff = repsDashShopHasAffiliate($shop);
    $chuck = $hasAff && repsDashAffiliateIsChuckTree((string) ($shop['assigned_sales_rep'] ?? ''));
    $split = repsDashSplitAcceptedHours($hours, true, $hasAff, null, $chuck);
    $gross = $split['gross_cents'] / 100.0;
    $capture = $split['capture_cents'] / 100.0;
    $dsc = ($split['dsc_cents'] + ($split['chuck_holdback_cents'] ?? 0)) / 100.0;
    $aff = $split['affiliate_cents'] / 100.0;

    return [
        'hours' => $hours,
        'gross' => $gross,
        'shop_pay' => $capture,
        'dsc_pay' => $dsc,
        'partner_lane' => $dsc + $aff,
        'your_affiliate' => $aff,
        'internal' => !$hasAff,
        'capture_pay' => $capture,
        'affiliate_pay' => $aff,
    ];
}

/**
 * @param list<array<string, mixed>> $ops
 * @return array<int, list<array<string, mixed>>>
 */
function repsDashMoneyOpsByShopId(array $ops): array
{
    $by = [];
    foreach ($ops as $op) {
        $by[(int) $op['shop_id']][] = $op;
    }
    return $by;
}

/**
 * Solo / individual operator economics (no shop).
 *
 * @return array{hours: float, gross: float, your_affiliate: float, capture_pay: float, dsc_pay: float}
 */
function repsDashMoneyIndividualEconomics(array $op, float $rate): array
{
    unset($rate);
    $hours = (float) ($op['accepted_7d'] ?? 0);
    $rep = trim((string) ($op['assigned_sales_rep'] ?? ''));
    $hasAff = $rep !== '' && strcasecmp($rep, 'unassigned') !== 0;
    $chuck = $hasAff && repsDashAffiliateIsChuckTree($rep);
    $split = repsDashSplitAcceptedHours($hours, false, $hasAff, null, $chuck);

    return [
        'hours' => $hours,
        'gross' => $split['gross_cents'] / 100.0,
        'your_affiliate' => $split['affiliate_cents'] / 100.0,
        'capture_pay' => $split['capture_cents'] / 100.0,
        'dsc_pay' => ($split['dsc_cents'] + ($split['chuck_holdback_cents'] ?? 0)) / 100.0,
    ];
}
