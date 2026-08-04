<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = repsDashSkinNormalizeSlug((string) ($_POST['skin_slug'] ?? ''));
    if ($slug !== null) {
        $_SESSION['reps_dash_skin'] = $slug;
    }
    header('Location: /dashboard/settings.php');
    exit;
}

$skin = repsDashSkinEffectiveSlug($user);
$pulse = repsDashPulseForUser($user);

repsDashRenderHeader('Settings', 'settings');
repsDashRenderPageHeader('Settings', 'Skin lab + sync placeholders');
?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="surface p-3">
      <h2 class="h5 mb-3">UI skin</h2>
      <p class="text-muted small">Same four skins as Docket / Tasks / CRM. Choice stored in this demo session only.</p>
      <form method="post" class="d-grid gap-2">
        <?php foreach (repsDashSkinAvailableSlugs() as $slug): ?>
          <label class="border rounded p-2 d-flex align-items-center gap-2">
            <input type="radio" name="skin_slug" value="<?php echo htmlspecialchars($slug); ?>" <?php echo $skin === $slug ? 'checked' : ''; ?>>
            <span class="fw-semibold"><?php echo htmlspecialchars($slug); ?></span>
            <a class="small ms-auto" href="?preview_skin=<?php echo urlencode($slug); ?>">preview</a>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary mt-2">Save skin for this session</button>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-2">Shift sync</h2>
      <dl class="row mb-0 small">
        <dt class="col-5">Partner code</dt><dd class="col-7"><code><?php echo htmlspecialchars($pulse['partner_code']); ?></code></dd>
        <dt class="col-5">Last sync</dt><dd class="col-7"><?php echo htmlspecialchars($pulse['last_sync']); ?></dd>
        <dt class="col-5">Worker</dt><dd class="col-7 text-muted">Not built (Slice C)</dd>
        <dt class="col-5">Re-auth</dt><dd class="col-7 text-muted">OTP runbook placeholder</dd>
      </dl>
    </div>
    <div class="surface p-3">
      <h2 class="h5 mb-2">Platform stubs</h2>
      <ul class="small mb-0">
        <li><code>/dashboard/api/</code> — Slice D</li>
        <li><code>reps_sdk/</code> — Slice E</li>
        <li><code>smcp_plugin/</code> — Slice E</li>
      </ul>
    </div>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
