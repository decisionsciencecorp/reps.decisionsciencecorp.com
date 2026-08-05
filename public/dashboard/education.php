<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('education', $user);
$role = (string) $user['role'];
$articles = repsDashEducationArticlesForRole($role);
$focus = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['topic'] ?? ''))) ?: '';

$bySection = [];
foreach ($articles as $art) {
    $bySection[$art['section']][] = $art;
}

repsDashRenderHeader('Education Center', 'education');
repsDashRenderPageHeader(
    'Education Center',
    'Shift app guides + DSC field coaching — setup, capture, rejects, and your Reps seat'
);
?>

<div class="alert alert-info border-0 mb-3">
  Pulled from Shift for Business (FAQ, Get started, reject help catalog) and Mark’s field coaching
  (including Seven’s teach-back: learn the headset so you can train the next person).
  Admin and ops stay on the live desk without this tab.
</div>

<?php if ($bySection === []): ?>
  <div class="surface p-3"><p class="mb-0 text-muted">No articles for this seat.</p></div>
<?php else: ?>
  <nav class="rd-edu-toc mb-4" aria-label="Education sections">
    <?php foreach (array_keys($bySection) as $sec): ?>
      <?php $sid = 'sec-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($sec)); ?>
      <a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="#<?php echo htmlspecialchars($sid); ?>">
        <?php echo htmlspecialchars($sec); ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php foreach ($bySection as $section => $rows): ?>
    <?php $sid = 'sec-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($section)); ?>
    <section class="mb-4" id="<?php echo htmlspecialchars($sid); ?>">
      <h2 class="h5 mb-3"><?php echo htmlspecialchars($section); ?></h2>
      <div class="row g-3">
        <?php foreach ($rows as $art): ?>
          <?php
          $hilite = ($focus !== '' && $focus === $art['id']);
          ?>
          <div class="col-md-6" id="<?php echo htmlspecialchars($art['id']); ?>">
            <div class="surface p-3 h-100<?php echo $hilite ? ' rd-edu-focus' : ''; ?>">
              <h3 class="h6 mb-2"><?php echo htmlspecialchars($art['title']); ?></h3>
              <p class="small mb-2"><?php echo htmlspecialchars($art['body']); ?></p>
              <div class="d-flex flex-wrap gap-1 mb-2">
                <?php foreach ($art['tags'] as $tag): ?>
                  <span class="badge text-bg-light border"><?php echo htmlspecialchars($tag); ?></span>
                <?php endforeach; ?>
              </div>
              <div class="text-muted small"><?php echo htmlspecialchars($art['source']); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (repsDashUsesLearnerChrome($role)): ?>
  <div class="mt-2 mb-4">
    <form method="post" action="/dashboard/onboarding.php" class="d-inline">
      <input type="hidden" name="action" value="restart">
      <input type="hidden" name="return" value="/dashboard/">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Replay Home wizard tour</button>
    </form>
  </div>
<?php endif; ?>

<?php if ($focus !== ''): ?>
<script>
(function () {
  var el = document.getElementById(<?php echo json_encode($focus); ?>);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
})();
</script>
<?php endif; ?>

<?php repsDashRenderFooter(); ?>
