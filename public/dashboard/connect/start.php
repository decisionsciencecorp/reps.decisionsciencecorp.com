<?php
declare(strict_types=1);

/**
 * Authenticated entry: create/resume Stripe Express onboarding for this seat.
 * Dashboard only — not join/signup.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = repsDashRequireLogin();
repsDashRequireCsrf();

$role = (string) ($user['role'] ?? '');
if (!in_array($role, ['business_owner', 'individual'], true)) {
    http_response_code(403);
    echo 'Payout setup is for business owners and solo operators.';
    exit;
}

if (!in_array('money', repsDashNavKeysForRole($role), true)) {
    http_response_code(403);
    echo 'Money is not available for this seat.';
    exit;
}

$started = repsStripeStartOnboardingForUser($user);
if (!empty($started['onboarding_url'])) {
    header('Location: ' . $started['onboarding_url']);
    exit;
}

repsDashRenderHeader('Payout setup', 'money');
repsDashRenderPageHeader('Payout setup', 'Could not open Stripe');
?>
<div class="alert alert-warning border-0">
  <?php echo htmlspecialchars((string) ($started['error'] ?? 'Stripe onboarding unavailable.')); ?>
  <?php if (($started['error'] ?? '') === 'stripe_not_configured'): ?>
    <div class="small mt-2">Platform Stripe keys are not loaded yet. Try again after DSC finishes sandbox wiring.</div>
  <?php endif; ?>
</div>
<p><a class="btn btn-primary" href="/dashboard/money.php">Back to My pay</a></p>
<?php repsDashRenderFooter(); ?>
