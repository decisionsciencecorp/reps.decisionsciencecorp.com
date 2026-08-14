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
 * Split a known gross (cents) into ledger buckets.
 *
 * @return array{
 *   hours: float,
 *   gross_cents: int,
 *   dsc_cents: int,
 *   affiliate_cents: int,
 *   capture_cents: int,
 *   capture_payee: 'shop'|'operator',
 *   affiliate_to_dsc: bool,
 *   hourly_rate: float|null
 * }
 */
function repsDashSplitGrossCents(int $grossCents, bool $hasShop, bool $hasAffiliate, float $hours = 0.0, ?float $hourlyRate = null): array
{
    $grossCents = max(0, $grossCents);
    $dsc = (int) floor($grossCents * repsDashMoneyShareDsc());
    $aff = (int) floor($grossCents * repsDashMoneyShareAffiliate());
    $capture = $grossCents - $dsc - $aff;
    $affiliateToDsc = !$hasAffiliate;
    if ($affiliateToDsc) {
        $dsc += $aff;
        $aff = 0;
    }

    return [
        'hours' => $hours,
        'gross_cents' => $grossCents,
        'dsc_cents' => $dsc,
        'affiliate_cents' => $aff,
        'capture_cents' => $capture,
        'capture_payee' => $hasShop ? 'shop' : 'operator',
        'affiliate_to_dsc' => $affiliateToDsc,
        'hourly_rate' => $hourlyRate,
    ];
}

/**
 * Split accepted hours. Optional $hourlyRate / omit to use current partner estimate.
 *
 * @return array{
 *   hours: float,
 *   gross_cents: int,
 *   dsc_cents: int,
 *   affiliate_cents: int,
 *   capture_cents: int,
 *   capture_payee: 'shop'|'operator',
 *   affiliate_to_dsc: bool,
 *   hourly_rate: float|null
 * }
 */
function repsDashSplitAcceptedHours(float $hours, bool $hasShop, bool $hasAffiliate, ?float $hourlyRate = null): array
{
    $hours = max(0.0, $hours);
    $rate = $hourlyRate ?? repsDashMoneyHourlyRate();
    $grossCents = (int) round($hours * $rate * 100);
    return repsDashSplitGrossCents($grossCents, $hasShop, $hasAffiliate, $hours, $rate);
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
    $split = repsDashSplitAcceptedHours($hours, true, $hasAff);
    $gross = $split['gross_cents'] / 100.0;
    $capture = $split['capture_cents'] / 100.0;
    $dsc = $split['dsc_cents'] / 100.0;
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
    $split = repsDashSplitAcceptedHours($hours, false, $hasAff);

    return [
        'hours' => $hours,
        'gross' => $split['gross_cents'] / 100.0,
        'your_affiliate' => $split['affiliate_cents'] / 100.0,
        'capture_pay' => $split['capture_cents'] / 100.0,
        'dsc_pay' => $split['dsc_cents'] / 100.0,
    ];
}
