<?php
declare(strict_types=1);

/**
 * Stripe webhook — platform + Connect events.
 * POST https://reps.decisionsciencecorp.com/dashboard/api/stripe-webhook.php
 *
 * REPS_STRIPE_WEBHOOK_INSECURE=1 accepts unsigned JSON — **dev/tests only**.
 * Never set on multihost prod.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$payload = file_get_contents('php://input');
if ($payload === false) {
    $payload = '';
}
$sig = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$accepted = repsStripeAcceptWebhookPayload($payload, $sig);
if (!($accepted['ok'] ?? false)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $accepted['error'] ?? 'invalid_signature']);
    exit;
}

$result = repsStripeHandleWebhookEvent($accepted['event'] ?? []);
header('Content-Type: application/json');
echo json_encode($result);
