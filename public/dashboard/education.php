<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('education', $user);
$role = (string) $user['role'];
$articles = repsDashEducationArticlesForRole($role);

repsDashRenderHeader('Education Center', 'education');
repsDashRenderPageHeader(
    'Education Center',
    'Guides for your seat — capture tips, rejects, and how Reps maps to Shift (mock content)'
);
?>

<div class="alert alert-info border-0 mb-3">
  Available to sales, business owners, employees, and individuals.
  Admin and ops use the live desk without this tab.
</div>

<div class="row g-3">
  <?php foreach ($articles as $art): ?>
    <div class="col-md-6">
      <div class="surface p-3 h-100">
        <h2 class="h5 mb-2"><?php echo htmlspecialchars($art['title']); ?></h2>
        <p class="small mb-2"><?php echo htmlspecialchars($art['body']); ?></p>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($art['tags'] as $tag): ?>
            <span class="badge text-bg-light border"><?php echo htmlspecialchars($tag); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (repsDashUsesLearnerChrome($role)): ?>
  <div class="mt-4">
    <form method="post" action="/dashboard/onboarding.php" class="d-inline">
      <input type="hidden" name="action" value="restart">
      <input type="hidden" name="return" value="/dashboard/">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Replay Home wizard tour</button>
    </form>
  </div>
<?php endif; ?>

<?php repsDashRenderFooter(); ?>
