<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('education', $user);
$role = (string) $user['role'];
$articles = repsDashEducationArticlesForRole($role);

$bySection = [];
foreach ($articles as $art) {
    $bySection[$art['section']][] = $art;
}

repsDashRenderHeader('Education Center', 'education');
repsDashRenderPageHeader(
    'Education Center',
    'Open a card for the full article — Shift guides, reject help, and capture video'
);
?>

<div class="alert alert-info border-0 mb-3">
  Cards are teasers. Tap <strong>Read article</strong> for the full write-up plus capture clips from the Reps media library.
  Filtered for your seat (<?php echo htmlspecialchars(repsDashRoleLabel($role)); ?>).
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
          $href = repsDashEducationArticleHref((string) $art['id']);
          $thumb = $art['media'][0] ?? null;
          $teaser = (string) ($art['teaser'] ?? $art['body'] ?? '');
          ?>
          <div class="col-md-6 col-xl-4">
            <a class="rd-edu-card surface h-100 text-decoration-none text-body d-flex flex-column" href="<?php echo htmlspecialchars($href); ?>">
              <?php if (is_array($thumb) && ($thumb['type'] ?? '') === 'video'): ?>
                <div class="rd-edu-card__media">
                  <img
                    src="<?php echo htmlspecialchars((string) ($thumb['poster'] ?? '')); ?>"
                    alt=""
                    loading="lazy"
                    width="640"
                    height="360"
                  >
                  <span class="rd-edu-card__play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
                </div>
              <?php endif; ?>
              <div class="p-3 d-flex flex-column flex-grow-1">
                <h3 class="h6 mb-2"><?php echo htmlspecialchars((string) $art['title']); ?></h3>
                <p class="small mb-3 flex-grow-1"><?php echo htmlspecialchars($teaser); ?></p>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  <?php foreach ($art['tags'] as $tag): ?>
                    <span class="badge text-bg-light border"><?php echo htmlspecialchars((string) $tag); ?></span>
                  <?php endforeach; ?>
                </div>
                <span class="small fw-semibold text-primary">Read article <i class="bi bi-arrow-right"></i></span>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (repsDashUsesLearnerChrome($role)): ?>
  <div class="mt-2 mb-4">
    <form method="post" action="/dashboard/onboarding.php" class="d-inline">
      <?php echo repsDashCsrfField(); ?>
      <input type="hidden" name="action" value="restart">
      <input type="hidden" name="return" value="/dashboard/">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Replay Home wizard tour</button>
    </form>
  </div>
<?php endif; ?>

<?php repsDashRenderFooter(); ?>
