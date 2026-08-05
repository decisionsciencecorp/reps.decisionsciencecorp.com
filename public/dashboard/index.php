<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
$role = (string) $user['role'];
$pulse = repsDashPulseForUser($user);
$shops = repsDashShopsForUser($user);
$sessions = repsDashSessionsForUser($user);
$blocks = repsDashHomeBlocksForRole($role);
$wizard = repsDashIsWizardHome($user);
$steps = $wizard ? repsDashWizardStepsForRole($role) : [];

$subtitle = match ($role) {
    'employee', 'individual' => 'Your personal pulse · mock data',
    'business_owner' => 'Your shop pulse · mock data',
    'agent' => 'Service principal · not a human desk',
    default => 'Pulse for your seat · mock Shift sync'
        . (repsDashCanSeePartnerCode($user) ? ' · Partner ' . $pulse['partner_code'] : ''),
};

if ($wizard) {
    $subtitle = 'First-run tour for your seat · finish anytime to open normal Home';
}

repsDashRenderHeader('Home', 'home');
repsDashRenderPageHeader('Home', $subtitle);
?>

<?php if ($wizard): ?>
<div class="rd-wizard" id="rdWizard" data-step-count="<?php echo count($steps); ?>">
  <div class="alert alert-primary border-0 mb-3">
    <strong>Wizard mode.</strong>
    New learner seats (sales, owner, employee, individual) start here.
    Admin and ops skip straight to the normal desk.
  </div>

  <div class="rd-wizard__progress mb-3" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo count($steps); ?>" aria-valuenow="1">
    <div class="rd-wizard__progress-bar" id="rdWizardBar" style="width: <?php echo count($steps) > 0 ? round(100 / count($steps)) : 100; ?>%;"></div>
  </div>
  <p class="small text-muted mb-3" id="rdWizardMeta">Step 1 of <?php echo count($steps); ?></p>

  <?php foreach ($steps as $i => $step): ?>
    <div class="rd-wizard__step surface p-4<?php echo $i === 0 ? '' : ' d-none'; ?>" data-step="<?php echo (int) $i; ?>"<?php echo $i === 0 ? '' : ' hidden'; ?>>
      <div class="text-muted small mb-1">Step <?php echo $i + 1; ?></div>
      <h2 class="h4 mb-3"><?php echo htmlspecialchars($step['title']); ?></h2>
      <p class="mb-3"><?php echo htmlspecialchars($step['body']); ?></p>
      <?php if (!empty($step['href']) && !empty($step['cta'])): ?>
        <a class="btn btn-outline-primary btn-sm mb-3" href="<?php echo htmlspecialchars($step['href']); ?>">
          <?php echo htmlspecialchars($step['cta']); ?>
        </a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
    <button type="button" class="btn btn-outline-secondary" id="rdWizardPrev" disabled>Back</button>
    <button type="button" class="btn btn-primary" id="rdWizardNext">Next</button>
    <form method="post" action="/dashboard/onboarding.php" class="ms-auto m-0" id="rdWizardFinishForm">
      <?php echo repsDashCsrfField(); ?>
      <input type="hidden" name="action" value="finish">
      <button type="submit" class="btn btn-success" id="rdWizardFinish" hidden>Finish tour · open Home</button>
    </form>
    <form method="post" action="/dashboard/onboarding.php" class="m-0">
      <?php echo repsDashCsrfField(); ?>
      <input type="hidden" name="action" value="finish">
      <button type="submit" class="btn btn-link btn-sm text-muted">Skip tour</button>
    </form>
  </div>
</div>
<script>
(function () {
  var root = document.getElementById('rdWizard');
  if (!root) return;
  var steps = root.querySelectorAll('.rd-wizard__step');
  var bar = document.getElementById('rdWizardBar');
  var meta = document.getElementById('rdWizardMeta');
  var prev = document.getElementById('rdWizardPrev');
  var next = document.getElementById('rdWizardNext');
  var finish = document.getElementById('rdWizardFinish');
  var i = 0;
  function show(n) {
    i = Math.max(0, Math.min(n, steps.length - 1));
    steps.forEach(function (el, idx) {
      var on = idx === i;
      el.classList.toggle('d-none', !on);
      if (on) el.removeAttribute('hidden');
      else el.setAttribute('hidden', '');
    });
    var pct = ((i + 1) / steps.length) * 100;
    if (bar) bar.style.width = pct + '%';
    if (meta) meta.textContent = 'Step ' + (i + 1) + ' of ' + steps.length;
    var progress = root.querySelector('.rd-wizard__progress');
    if (progress) progress.setAttribute('aria-valuenow', String(i + 1));
    if (prev) prev.disabled = i === 0;
    var last = i === steps.length - 1;
    if (next) next.classList.toggle('d-none', last);
    if (finish) {
      if (last) finish.removeAttribute('hidden');
      else finish.setAttribute('hidden', '');
    }
  }
  if (prev) prev.addEventListener('click', function () { show(i - 1); });
  if (next) next.addEventListener('click', function () { show(i + 1); });
  show(0);
})();
</script>

<?php else: ?>

<div class="alert alert-warning border-0 mb-3">
  <strong>Slice A — mock data.</strong> Numbers are fake for layout and scoping audit.
  Real Shift polling is Slice C (PRD Doc #990).
  <?php if (repsDashCanSeePartnerCode($user)): ?>
    Last sync shown: <?php echo htmlspecialchars($pulse['last_sync']); ?>.
  <?php endif; ?>
  <?php if (repsDashUsesLearnerChrome($role)): ?>
    <span class="d-block d-md-inline ms-md-1">
      Prefer the tour?
      <form method="post" action="/dashboard/onboarding.php" class="d-inline">
        <?php echo repsDashCsrfField(); ?>
        <input type="hidden" name="action" value="restart">
        <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Replay Home wizard</button>
      </form>
    </span>
  <?php endif; ?>
</div>

<?php if (in_array('shop_metrics', $blocks, true)): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours (sample)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $pulse['accepted_hours_sample']); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small"><?php echo $role === 'sales' ? 'Reject signals (book)' : 'Rejected sessions'; ?></div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['rejected_sessions']; ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small"><?php echo $role === 'business_owner' ? 'Your shop' : 'Shops visible'; ?></div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['shops_visible']; ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small"><?php echo $role === 'business_owner' ? 'Active team' : 'Zero-upload shops'; ?></div>
      <div class="fs-3 fw-semibold"><?php echo (int) ($role === 'business_owner' ? $pulse['operators_active'] : $pulse['shops_zero_upload']); ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (in_array('personal_metrics', $blocks, true)): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Your accepted hours (sample)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $pulse['accepted_hours_sample']); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Rejected</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['rejected_sessions']; ?></div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Pending review</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['pending_sessions']; ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (in_array('agent_stub', $blocks, true)): ?>
<div class="surface p-4 mb-4">
  <h2 class="h5 mb-2">Agent / API principal</h2>
  <p class="mb-2">This seat is <strong>not</strong> a human ops login. Sync workers and SMCP tools authenticate with an API key against <code>/dashboard/api/</code> (Slice D–E).</p>
  <p class="mb-0 text-muted small">No shops, operators, sessions, or money screens. Use Dev Mode only to confirm agents stay off the human desk.</p>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php if (in_array('shops_attention', $blocks, true)): ?>
  <div class="col-lg-7">
    <div class="surface p-3">
      <h2 class="h5 mb-3"><?php echo $role === 'business_owner' ? 'Your shop' : 'Shops needing attention'; ?></h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Shop</th><th>Status</th><th>7d hours</th><th>Reject</th></tr></thead>
          <tbody>
          <?php if ($shops === []): ?>
            <tr><td colspan="4" class="text-muted">No shops in scope for this seat.</td></tr>
          <?php endif; ?>
          <?php foreach ($shops as $shop): ?>
            <tr>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?php echo htmlspecialchars(repsDashShopHref((int) $shop['id'])); ?>">
                  <?php echo htmlspecialchars($shop['name']); ?>
                </a>
              </td>
              <td><?php repsDashStatusPill($shop['status']); ?></td>
              <td><?php echo htmlspecialchars((string) $shop['accepted_hours_7d']); ?></td>
              <td><?php echo htmlspecialchars((string) round($shop['reject_rate'] * 100)) . '%'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array('recent_sessions', $blocks, true)): ?>
  <div class="col-lg-7">
    <div class="surface p-3">
      <h2 class="h5 mb-3">Your recent sessions</h2>
      <?php
      repsDashRenderSessionTable($sessions, [
          'variant' => 'recent',
          'limit' => 8,
          'empty' => 'No sessions yet (mock).',
      ]);
      ?>
      <a class="btn btn-sm btn-outline-primary mt-3" href="/dashboard/sessions.php">All my sessions</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-lg-5">
    <?php if (in_array('apply_leads', $blocks, true)): ?>
    <div class="surface p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">My lead feed</h2>
        <a class="small" href="/dashboard/leads.php">Open CRM</a>
      </div>
      <p class="mb-2 small text-muted"><?php echo (int) $pulse['apply_leads_open']; ?> open/claimed in system · latest activity on your leads</p>
      <?php
      $feed = repsDashListLeadFeedForUser($user, 8);
      $homeLeads = repsDashListApplyLeadsForUser(
          $user,
          null,
          null,
          ($role === 'sales')
      );
      $homeLeads = array_values(array_filter(
          $homeLeads,
          static fn(array $l): bool => in_array($l['status'], ['open', 'claimed'], true)
      ));
      $homeLeads = array_slice($homeLeads, 0, 4);
      ?>
      <?php if ($feed !== []): ?>
        <ul class="list-unstyled small mb-3">
          <?php foreach ($feed as $ev): ?>
            <li class="border-bottom py-1">
              <a href="<?php echo htmlspecialchars(repsDashLeadHref((int) $ev['lead_id'])); ?>">
                <?php echo htmlspecialchars((string) ($ev['lead_name'] ?? 'Lead')); ?>
              </a>
              · <span class="badge text-bg-light border"><?php echo htmlspecialchars((string) $ev['event_type']); ?></span>
              <?php echo htmlspecialchars((string) $ev['body']); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($homeLeads === []): ?>
        <p class="small text-muted mb-0">No open leads in your queue right now.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Name</th><th>Kind</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($homeLeads as $lead): ?>
              <tr>
                <td>
                  <a href="<?php echo htmlspecialchars(repsDashLeadHref((int) $lead['id'])); ?>">
                    <?php echo htmlspecialchars($lead['name']); ?>
                  </a>
                </td>
                <td class="small"><?php echo htmlspecialchars($lead['join_kind']); ?></td>
                <td><?php repsDashStatusPill($lead['status']); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (in_array('team_pulse', $blocks, true)): ?>
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-2">Team</h2>
      <p class="mb-2"><?php echo (int) $pulse['operators_active']; ?> active operators on your shop (mock).</p>
      <a class="btn btn-sm btn-outline-primary" href="/dashboard/operators.php">Open team roster</a>
    </div>
    <?php endif; ?>

    <?php if (in_array('signed_in', $blocks, true)): ?>
    <div class="surface p-3">
      <h2 class="h5 mb-2">Signed in as</h2>
      <p class="mb-1"><strong><?php echo htmlspecialchars($user['display_name']); ?></strong></p>
      <p class="mb-0 text-muted small">
        Role <strong><?php echo htmlspecialchars(repsDashRoleLabel($role)); ?></strong>
        (<code><?php echo htmlspecialchars($role); ?></code>) —
        <?php echo htmlspecialchars(repsDashScopeBlurb($role)); ?>
        Use Dev Mode to switch seats; see <a href="/dashboard/access.php">Views × roles</a>.
      </p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php repsDashRenderFooter(); ?>
