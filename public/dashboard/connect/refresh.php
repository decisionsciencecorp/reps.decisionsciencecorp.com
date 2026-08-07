<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

$payeeId = (int) ($_GET['payee_id'] ?? 0);
$payee = $payeeId > 0 ? repsStripePayeeById($payeeId) : null;
if (!$payee) {
    http_response_code(404);
    echo 'Unknown payee.';
    exit;
}

$email = (string) ($payee['email'] ?? '');
$name = (string) ($payee['display_name'] ?? '');
$ensured = repsStripeEnsurePayee(
    (string) $payee['entity_type'],
    (int) $payee['entity_id'],
    $email !== '' ? $email : (string) ($user['email'] ?? 'payee@example.com'),
    $name !== '' ? $name : (string) ($user['display_name'] ?? 'Payee')
);

if (!empty($ensured['onboarding_url'])) {
    header('Location: ' . $ensured['onboarding_url']);
    exit;
}

repsDashRenderHeader('Connect', 'money');
repsDashRenderPageHeader('Connect onboarding', 'Could not refresh Stripe link');
?>
<div class="alert alert-warning">
  <?php echo htmlspecialchars((string) ($ensured['error'] ?? 'Stripe onboarding link unavailable. Check API keys.')); ?>
</div>
<p><a href="/dashboard/money.php">Back to Money</a></p>
<?php repsDashRenderFooter(); ?>
