<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
if (!repsDashIsAdmin($user)) {
    http_response_code(403);
    repsDashRenderHeader('Users', 'users');
    echo '<div class="alert alert-danger">Admin only — switch to the Mark demo seat to audit this screen.</div>';
    repsDashRenderFooter();
    exit;
}

$accounts = repsDashDemoAccounts();

repsDashRenderHeader('Users', 'users');
repsDashRenderPageHeader('Users', 'Provision seats for people who join the team (mock list — Slice B makes this real)');
?>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Name</th><th>Username</th><th>Role</th><th>Email</th><th>Skin</th></tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $acct): ?>
        <tr>
          <td class="fw-semibold"><?php echo htmlspecialchars($acct['display_name']); ?></td>
          <td><code><?php echo htmlspecialchars($acct['username']); ?></code></td>
          <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($acct['role']); ?></span></td>
          <td><?php echo htmlspecialchars($acct['email']); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars((string) ($acct['skin_slug'] ?? 'default')); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-3 mb-0">Invite / deactivate / password reset arrive in Slice B. Agent API keys arrive with Slice D/E.</p>

<?php repsDashRenderFooter(); ?>
