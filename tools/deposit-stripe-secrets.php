#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Deposit Stripe secrets into dashboard app_meta (idempotent).
 *
 * Usage:
 *   php tools/deposit-stripe-secrets.php
 *   REPS_STRIPE_PASS_FILE=/path/to.pass php tools/deposit-stripe-secrets.php
 *
 * Reads ~/.ssh/reps-stripe.pass (or REPS_STRIPE_PASS_FILE). Never prints secret values.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

repsStripeLoadPassFile();

$keys = [
    'secret_key' => (string) (getenv('STRIPE_SECRET_KEY') ?: ''),
    'publishable_key' => (string) (getenv('STRIPE_PUBLISHABLE_KEY') ?: ''),
    'webhook_secret' => (string) (getenv('STRIPE_WEBHOOK_SECRET') ?: ''),
    'connect_webhook_secret' => (string) (getenv('STRIPE_CONNECT_WEBHOOK_SECRET') ?: ''),
    'api_base' => (string) (getenv('STRIPE_API_BASE') ?: 'https://api.stripe.com'),
    'mode' => (string) (getenv('STRIPE_MODE') ?: 'test'),
];

if ($keys['secret_key'] === '' || $keys['publishable_key'] === '') {
    fwrite(STDERR, "Missing STRIPE_SECRET_KEY or STRIPE_PUBLISHABLE_KEY in pass file.\n");
    exit(2);
}

$res = repsStripeStoreSecretsInDb($keys);
fwrite(STDOUT, 'ok written_keys=' . count($res['written']) . ' names=' . implode(',', $res['written']) . "\n");
fwrite(STDOUT, 'configured=' . (repsStripeConfigured() ? 'yes' : 'no') . "\n");
fwrite(STDOUT, 'secret_prefix=' . substr(repsStripeSecretKey(), 0, 8) . "\n");
fwrite(STDOUT, 'pk_prefix=' . substr(repsStripePublishableKey(), 0, 8) . "\n");
fwrite(STDOUT, 'wh_set=' . (repsStripeWebhookSecret(false) !== '' ? 'yes' : 'no') . "\n");
fwrite(STDOUT, 'wh_connect_set=' . (repsStripeWebhookSecret(true) !== '' ? 'yes' : 'no') . "\n");
exit($res['ok'] ? 0 : 1);
