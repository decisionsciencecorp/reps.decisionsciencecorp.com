<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

$payeeId = (int) ($_GET['payee_id'] ?? 0);
$payee = $payeeId > 0 ? repsStripePayeeById($payeeId) : null;

// Refresh readiness from Stripe when possible (webhook may lag).
if ($payee && repsStripeConfigured() && !empty($payee['stripe_account_id'])) {
    $acct = repsStripeRequest('GET', '/v1/accounts/' . rawurlencode((string) $payee['stripe_account_id']));
    if ($acct['ok'] ?? false) {
        repsStripeMarkPayeeFromAccountObject($acct['body']);
        $payee = repsStripePayeeById($payeeId);
    }
}

repsDashRenderHeader('Connect', 'money');
repsDashRenderPageHeader('Payout setup', 'Back from Stripe');
?>
<div class="alert alert-info border-0">
  Stripe sent you back here. We mark payouts ready when Stripe reports
  <code>payouts_enabled</code> (this page refresh and/or the <code>account.updated</code> webhook).
</div>
<?php if ($payee): ?>
  <div class="surface p-3 mb-3">
    <div class="text-muted small">Payee</div>
    <div class="fw-semibold"><?php echo htmlspecialchars((string) $payee['display_name']); ?></div>
    <div class="small">Account: <code><?php echo htmlspecialchars((string) ($payee['stripe_account_id'] ?? '—')); ?></code></div>
    <div class="small">Status: <?php echo htmlspecialchars((string) ($payee['onboarding_status'] ?? '—')); ?>
      · payouts_enabled=<?php echo (int) ($payee['payouts_enabled'] ?? 0); ?></div>
  </div>
  <?php if ((int) ($payee['payouts_enabled'] ?? 0) !== 1): ?>
  <form method="post" action="/dashboard/connect/start.php" class="mb-3">
    <?php echo repsDashCsrfField(); ?>
    <button type="submit" class="btn btn-primary btn-sm">Continue payout setup</button>
  </form>
  <?php endif; ?>
<?php else: ?>
  <p class="text-muted">No payee id on return URL.</p>
<?php endif; ?>
<p><a class="btn btn-outline-primary" href="/dashboard/money.php">Back to My pay</a></p>
<?php repsDashRenderFooter(); ?>
