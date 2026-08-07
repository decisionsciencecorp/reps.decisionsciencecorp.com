<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

$payeeId = (int) ($_GET['payee_id'] ?? 0);
$payee = $payeeId > 0 ? repsStripePayeeById($payeeId) : null;

repsDashRenderHeader('Connect', 'money');
repsDashRenderPageHeader('Connect return', 'Stripe onboarding redirect');
?>
<div class="alert alert-info border-0">
  Stripe sent you back here. We do <strong>not</strong> assume onboarding is complete until
  <code>account.updated</code> shows payouts enabled — refresh Money in a minute, or wait for the webhook.
</div>
<?php if ($payee): ?>
  <div class="surface p-3 mb-3">
    <div class="text-muted small">Payee</div>
    <div class="fw-semibold"><?php echo htmlspecialchars((string) $payee['display_name']); ?></div>
    <div class="small">Account: <code><?php echo htmlspecialchars((string) ($payee['stripe_account_id'] ?? '—')); ?></code></div>
    <div class="small">Status: <?php echo htmlspecialchars((string) ($payee['onboarding_status'] ?? '—')); ?>
      · payouts_enabled=<?php echo (int) ($payee['payouts_enabled'] ?? 0); ?></div>
  </div>
<?php else: ?>
  <p class="text-muted">No payee id on return URL.</p>
<?php endif; ?>
<p><a class="btn btn-primary" href="/dashboard/money.php">Money</a></p>
<?php repsDashRenderFooter(); ?>
