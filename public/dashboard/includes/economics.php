<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/** Mock $/accepted hour — replace when Slice C + #1570 lock real rates. */
function repsDashMoneyHourlyRate(): float
{
    return 20.0;
}

/**
 * @return array{
 *   hours: float,
 *   gross: float,
 *   shop_pay: float,
 *   dsc_pay: float,
 *   partner_lane: float,
 *   your_affiliate: float,
 *   internal: bool
 * }
 */
function repsDashMoneyShopEconomics(array $shop, float $rate): array
{
    $hours = (float) $shop['accepted_hours_7d'];
    $gross = $hours * $rate;
    $shopShare = (float) $shop['agreed_shop_split'];
    $shopPay = $gross * $shopShare;
    $dscPay = $gross - $shopPay;
    // Mock: affiliate display cut = half of non-shop lane when split > 0
    $yourAffiliate = $shopShare <= 0.001 ? 0.0 : $dscPay * 0.5;
    return [
        'hours' => $hours,
        'gross' => $gross,
        'shop_pay' => $shopPay,
        'dsc_pay' => $dscPay,
        'partner_lane' => $dscPay,
        'your_affiliate' => $yourAffiliate,
        'internal' => $shopShare <= 0.001,
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
