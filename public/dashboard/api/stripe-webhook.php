<?php
declare(strict_types=1);

/**
 * Stripe webhook — platform + Connect events.
 * POST https://reps.decisionsciencecorp.com/dashboard/api/stripe-webhook.php
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'empty_body']);
    exit;
}

$sig = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$secret = repsStripeWebhookSecret(false);
$connectSecret = repsStripeWebhookSecret(true);

$event = repsStripeVerifyWebhook($payload, $sig, $secret);
if ($event === null && $connectSecret !== '' && $connectSecret !== $secret) {
    $event = repsStripeVerifyWebhook($payload, $sig, $connectSecret);
}

// Test / local without secret: only accept when REPS_STRIPE_WEBHOOK_INSECURE=1
if ($event === null && filter_var(getenv('REPS_STRIPE_WEBHOOK_INSECURE') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    $decoded = json_decode($payload, true);
    $event = is_array($decoded) ? $decoded : null;
}

if ($event === null) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}

$eventId = (string) ($event['id'] ?? '');
$type = (string) ($event['type'] ?? '');
$livemode = !empty($event['livemode']) ? 1 : 0;

$pdo = repsDashDb();
if ($eventId !== '') {
    try {
        $pdo->prepare(
            'INSERT INTO stripe_webhook_events (event_id, type, livemode) VALUES (?, ?, ?)'
        )->execute([$eventId, $type, $livemode]);
    } catch (Throwable $e) {
        // Duplicate event_id — already processed
        header('Content-Type: application/json');
        echo json_encode(['received' => true, 'duplicate' => true]);
        exit;
    }
}

$obj = $event['data']['object'] ?? [];
if (!is_array($obj)) {
    $obj = [];
}

switch ($type) {
    case 'account.updated':
        repsStripeMarkPayeeFromAccountObject($obj);
        break;
    case 'balance.available':
        repsSettlementReconcileStripeBalance('webhook_balance_available');
        break;
    case 'transfer.created':
    case 'transfer.updated':
        $trId = (string) ($obj['id'] ?? '');
        if ($trId !== '') {
            repsDisburseMarkTransferFromWebhook($trId, 'created');
        }
        break;
    case 'transfer.reversed':
        $trId = (string) ($obj['id'] ?? '');
        if ($trId !== '') {
            repsDisburseMarkTransferFromWebhook($trId, 'reversed');
        }
        break;
    case 'topup.succeeded':
        $tuId = (string) ($obj['id'] ?? '');
        $amt = (int) ($obj['amount'] ?? 0);
        if ($tuId !== '') {
            repsSettlementRecord('stripe_topup', $tuId, $amt, (string) ($obj['currency'] ?? 'usd'), 'available', [
                'description' => $obj['description'] ?? '',
            ]);
        }
        break;
    default:
        // acknowledged
        break;
}

header('Content-Type: application/json');
echo json_encode(['received' => true, 'type' => $type]);
