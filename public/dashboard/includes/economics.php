<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Locked pie: $20 / accepted hour → DSC 25% · Affiliate 25% · Capture 50%.
 * Capture payee is shop XOR operator (never both).
 */

function repsDashMoneyHourlyRate(): float
{
    return 20.0;
}

/**
 * Split accepted hours into ledger buckets (cents).
 *
 * @return array{
 *   hours: float,
 *   gross_cents: int,
 *   dsc_cents: int,
 *   affiliate_cents: int,
 *   capture_cents: int,
 *   capture_payee: 'shop'|'operator',
 *   affiliate_to_dsc: bool
 * }
 */
function repsDashSplitAcceptedHours(float $hours, bool $hasShop, bool $hasAffiliate): array
{
    $hours = max(0.0, $hours);
    $grossCents = (int) round($hours * repsDashMoneyHourlyRate() * 100);
    $dsc = (int) floor($grossCents * 0.25);
    $aff = (int) floor($grossCents * 0.25);
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
    unset($rate); // locked rate via repsDashMoneyHourlyRate()
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
