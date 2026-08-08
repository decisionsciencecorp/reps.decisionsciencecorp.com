<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
$role = (string) $user['role'];
$panels = repsDashSettingsPanelsForRole($role);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array('skin', $panels, true)) {
    repsDashRequireCsrf();
    $slug = repsDashSkinNormalizeSlug((string) ($_POST['skin_slug'] ?? ''));
    if ($slug !== null) {
        $_SESSION['reps_dash_skin'] = $slug;
        repsDashPersistUserSkin((int) $user['id'], $slug);
    }
    header('Location: /dashboard/settings.php');
    exit;
}

$skin = repsDashSkinEffectiveSlug($user);
$pulse = repsDashPulseForUser($user);

repsDashRenderHeader('Settings', 'settings');
repsDashRenderPageHeader('Settings', match ($role) {
    'admin' => 'Skin lab + sync + platform stubs',
    'ops' => 'Skin + Shift sync status',
    'agent' => 'Platform / API stubs for the service principal',
    default => 'Your display preferences',
});
?>

<div class="row g-3">
  <?php if (in_array('skin', $panels, true)): ?>
  <div class="col-lg-6">
    <div class="surface p-3">
      <h2 class="h5 mb-3">UI skin</h2>
      <p class="text-muted small">Same four skins as Docket / Tasks / CRM. Saved on your user record.</p>
      <form method="post" class="d-grid gap-2">
        <?php echo repsDashCsrfField(); ?>
        <?php foreach (repsDashSkinAvailableSlugs() as $slug): ?>
          <label class="border rounded p-2 d-flex align-items-center gap-2">
            <input type="radio" name="skin_slug" value="<?php echo htmlspecialchars($slug); ?>" <?php echo $skin === $slug ? 'checked' : ''; ?>>
            <span class="fw-semibold"><?php echo htmlspecialchars($slug); ?></span>
            <a class="small ms-auto" href="?preview_skin=<?php echo urlencode($slug); ?>">preview</a>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary mt-2">Save skin</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-lg-6">
    <?php if (in_array('sync', $panels, true)): ?>
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-2">Shift sync</h2>
      <dl class="row mb-0 small">
        <dt class="col-5">Partner code</dt><dd class="col-7"><code><?php echo htmlspecialchars($pulse['partner_code']); ?></code></dd>
        <dt class="col-5">Last sync</dt><dd class="col-7"><?php echo htmlspecialchars($pulse['last_sync']); ?></dd>
        <dt class="col-5">Live data</dt><dd class="col-7"><?php echo !empty($pulse['live_data']) ? 'yes' : 'no (fixtures)'; ?></dd>
        <dt class="col-5">Worker</dt><dd class="col-7"><code>tools/poll-shift.php</code> · Shift match UI</dd>
        <dt class="col-5">Re-auth</dt><dd class="col-7 text-muted">Cookie jar / OTP runbook</dd>
      </dl>
    </div>
    <?php endif; ?>

    <?php if (in_array('platform', $panels, true)): ?>
    <div class="surface p-3">
      <h2 class="h5 mb-2">Platform</h2>
      <ul class="small mb-0">
        <li><code>/dashboard/api/</code> — Slice D JSON (see <a href="/dashboard/api/">README</a>)</li>
        <li><code>reps_sdk/</code> — Slice E (next)</li>
        <li><code>smcp_plugin/</code> — Slice E (next)</li>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($panels === ['skin']): ?>
    <div class="surface p-3 mt-0">
      <h2 class="h5 mb-2">Account</h2>
      <p class="small text-muted mb-0">
        <?php echo htmlspecialchars(repsDashScopeBlurb($role)); ?>
        Shop roster invites and password reset land in Slice B.
      </p>
    </div>
    <?php endif; ?>

    <?php if (repsDashUsesLearnerChrome($role)): ?>
    <div class="surface p-3 mt-3">
      <h2 class="h5 mb-2">Home wizard</h2>
      <p class="small text-muted mb-3">
        New learner seats start on a short Home tour. Replay it anytime, or open Education Center for capture tips.
      </p>
      <form method="post" action="/dashboard/onboarding.php" class="d-inline">
        <?php echo repsDashCsrfField(); ?>
        <input type="hidden" name="action" value="restart">
        <input type="hidden" name="return" value="/dashboard/">
        <button type="submit" class="btn btn-outline-primary btn-sm">Replay Home wizard</button>
      </form>
      <a class="btn btn-outline-secondary btn-sm ms-1" href="/dashboard/education.php">Education Center</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
