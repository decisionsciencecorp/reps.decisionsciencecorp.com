#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Book locked Monday settlement from a Shift hours-feed JSON file.
 *
 * Usage:
 *   php tools/process-shift-monday.php --feed=/path/hours-feed.json --monday=2026-08-03
 *   php tools/process-shift-monday.php --feed=... --monday=2026-07-20 --amount-cents=5500
 *   php tools/process-shift-monday.php --feed=... --mondays=2026-07-20,2026-07-27,2026-08-03
 *
 * Options:
 *   --has-shop=0|1 --has-affiliate=0|1 --shop-id=N --affiliate=username
 *   --dry-run=1  (accrue only; do not write settlement/ledger)
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$opts = getopt('', [
    'feed:',
    'monday:',
    'mondays:',
    'amount-cents:',
    'has-shop:',
    'has-affiliate:',
    'shop-id:',
    'affiliate:',
    'dry-run:',
]);

$feedPath = (string) ($opts['feed'] ?? '');
if ($feedPath === '' || !is_readable($feedPath)) {
    fwrite(STDERR, "Need --feed=/path/to/hours-feed.json\n");
    exit(2);
}

$raw = file_get_contents($feedPath);
$feed = json_decode((string) $raw, true);
if (!is_array($feed)) {
    fwrite(STDERR, "Invalid JSON feed\n");
    exit(2);
}

$mondays = [];
if (!empty($opts['mondays'])) {
    foreach (explode(',', (string) $opts['mondays']) as $m) {
        $m = trim($m);
        if ($m !== '') {
            $mondays[] = $m;
        }
    }
} elseif (!empty($opts['monday'])) {
    $mondays[] = (string) $opts['monday'];
} else {
    fwrite(STDERR, "Need --monday=YYYY-MM-DD or --mondays=a,b,c\n");
    exit(2);
}

$processOpts = [
    'has_shop' => ((string) ($opts['has-shop'] ?? '0')) === '1',
    'has_affiliate' => ((string) ($opts['has-affiliate'] ?? '0')) === '1',
];
if (isset($opts['shop-id'])) {
    $processOpts['shop_id'] = (int) $opts['shop-id'];
}
if (isset($opts['affiliate'])) {
    $processOpts['affiliate_username'] = (string) $opts['affiliate'];
}
if (isset($opts['amount-cents'])) {
    $processOpts['amount_cents'] = (int) $opts['amount-cents'];
}

$dry = ((string) ($opts['dry-run'] ?? '0')) === '1';
$exit = 0;

foreach ($mondays as $monday) {
    if ($dry) {
        $acc = repsSettlementAccrueForCashMonday($feed, $monday);
        fwrite(STDOUT, json_encode([
            'dry_run' => true,
            'monday' => $monday,
            'accepted_hours' => $acc['accepted_hours'],
            'amount_cents' => $acc['amount_cents'],
            'sessions' => count($acc['sessions']),
            'carried' => count($acc['carried']),
            'by_person' => $acc['by_person'],
        ], JSON_UNESCAPED_SLASHES) . "\n");
        continue;
    }

    $res = repsSettlementProcessCashMonday($feed, $monday, $processOpts);
    $line = [
        'monday' => $monday,
        'ok' => (bool) ($res['ok'] ?? false),
        'accepted_hours' => $res['accrual']['accepted_hours'] ?? null,
        'accrued_cents' => $res['accrual']['amount_cents'] ?? null,
        'settlement_id' => $res['settlement']['id'] ?? null,
        'settlement_created' => $res['settlement']['created'] ?? null,
        'ledger_posted' => $res['ledger']['posted'] ?? null,
        'ledger_skipped' => $res['ledger']['skipped'] ?? null,
        'error' => $res['error'] ?? null,
    ];
    fwrite(STDOUT, json_encode($line, JSON_UNESCAPED_SLASHES) . "\n");
    if (!($res['ok'] ?? false)) {
        $exit = 1;
    }
}

exit($exit);
