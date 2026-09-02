#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Stripe Connect sandbox harness — sales seat → Account Link → webhook.
 *
 * Default: HTTP-mocked (no network, safe for CI / PHPUnit).
 * Optional: --live-test hits Stripe *test* API with keys from app_meta / pass.
 *
 * Usage:
 *   php tools/stripe-sandbox-smoke.php
 *   php tools/stripe-sandbox-smoke.php --username=jim
 *   php tools/stripe-sandbox-smoke.php --live-test
 *   php tools/stripe-sandbox-smoke.php --no-webhook
 *
 * See docs/STRIPE-SANDBOX.md
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$opts = getopt('', ['username:', 'live-test::', 'mock::', 'no-webhook::', 'help::']);
if (array_key_exists('help', $opts)) {
    fwrite(STDOUT, file_get_contents(__FILE__) !== false
        ? "stripe-sandbox-smoke.php — see docs/STRIPE-SANDBOX.md\n"
        : "help\n");
    exit(0);
}

$username = isset($opts['username']) ? (string) $opts['username'] : 'jim';
$liveTest = array_key_exists('live-test', $opts);
$noWebhook = array_key_exists('no-webhook', $opts);
// Explicit --mock wins; otherwise mock unless --live-test
$mock = array_key_exists('mock', $opts) ? true : !$liveTest;

putenv('REPS_PUBLIC_BASE=' . (getenv('REPS_PUBLIC_BASE') ?: 'https://reps.decisionsciencecorp.com'));

$result = repsStripeSandboxConnectHarness([
    'username' => $username,
    'mock' => $mock,
    'live_test' => $liveTest,
    'simulate_webhook' => !$noWebhook,
]);

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

if (!empty($result['onboarding_url'])) {
    fwrite(STDERR, "onboarding_url=" . $result['onboarding_url'] . "\n");
}

exit(!empty($result['ok']) ? 0 : 1);
