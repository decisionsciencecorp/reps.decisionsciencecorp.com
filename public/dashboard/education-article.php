<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('education', $user);
$role = (string) $user['role'];

$id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['id'] ?? ''))) ?: '';
$article = $id !== '' ? repsDashEducationArticleById($id, $role) : null;

if ($article === null) {
    http_response_code(404);
    repsDashRenderHeader('Article not found', 'education');
    repsDashRenderPageHeader('Article not found', 'This guide isn’t available for your seat — or the link is stale.');
    echo '<p><a class="btn btn-outline-primary btn-sm" href="/dashboard/education.php">Back to Education Center</a></p>';
    repsDashRenderFooter();
    exit;
}

$related = [];
foreach (repsDashEducationArticlesForRole($role) as $row) {
    if ($row['id'] === $article['id']) {
        continue;
    }
    if ($row['section'] === $article['section']) {
        $related[] = $row;
    }
    if (count($related) >= 4) {
        break;
    }
}

repsDashRenderHeader((string) $article['title'], 'education');
repsDashRenderPageHeader(
    (string) $article['title'],
    (string) $article['section'] . ' · ' . (string) $article['source']
);
?>

<p class="mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="/dashboard/education.php#sec-<?php echo htmlspecialchars(preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $article['section']))); ?>">
    ← Education Center
  </a>
</p>

<?php
$media = $article['media'] ?? [];
if (is_array($media) && $media !== []) {
    repsDashRenderEducationMedia($media, 'hero');
}
?>

<div class="surface p-4 mb-4 rd-edu-article">
  <p class="lead mb-3"><?php echo htmlspecialchars((string) ($article['teaser'] ?? '')); ?></p>
  <?php repsDashRenderEducationBlocks($article['article'] ?? []); ?>
  <div class="d-flex flex-wrap gap-1 mt-3">
    <?php foreach ($article['tags'] as $tag): ?>
      <span class="badge text-bg-light border"><?php echo htmlspecialchars((string) $tag); ?></span>
    <?php endforeach; ?>
  </div>
  <p class="small text-muted mb-0 mt-3">Source: <?php echo htmlspecialchars((string) $article['source']); ?></p>
</div>

<?php if ($related !== []): ?>
  <h2 class="h5 mb-3">More in <?php echo htmlspecialchars((string) $article['section']); ?></h2>
  <div class="row g-3 mb-4">
    <?php foreach ($related as $rel): ?>
      <div class="col-md-6">
        <a class="surface p-3 d-block h-100 text-decoration-none text-body" href="<?php echo htmlspecialchars(repsDashEducationArticleHref((string) $rel['id'])); ?>">
          <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string) $rel['title']); ?></div>
          <div class="small text-muted"><?php echo htmlspecialchars((string) ($rel['teaser'] ?? '')); ?></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php repsDashRenderFooter(); ?>
