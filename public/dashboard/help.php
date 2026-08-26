<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('help', $user);

/**
 * @return array<string, array{label:string,deck:string,icon:string}>
 */
function repsDashHelpPageMeta(): array
{
    return [
        'overview' => [
            'label' => 'Overview',
            'deck' => 'What Reps is and how this desk fits Partner hours + money',
            'icon' => 'bi-house',
        ],
        'desks' => [
            'label' => 'Desks & screens',
            'deck' => 'Home, Shops, Leads, Money, affiliate page, Education',
            'icon' => 'bi-layout-text-sidebar-reverse',
        ],
        'affiliate-page' => [
            'label' => 'Affiliate landing page',
            'deck' => 'Your public link, apply URL, affiliate code, and how to configure them',
            'icon' => 'bi-link-45deg',
        ],
        'roles' => [
            'label' => 'Roles & access',
            'deck' => 'Who sees what — seats from admin to agent',
            'icon' => 'bi-shield-lock',
        ],
        'users' => [
            'label' => 'Users & seats',
            'deck' => 'Provisioning roster, passwords, API keys',
            'icon' => 'bi-person-gear',
        ],
        'shift' => [
            'label' => 'Partner sync & match',
            'deck' => 'Ingest hours, match workers, invite rules',
            'icon' => 'bi-link-45deg',
        ],
        'money' => [
            'label' => 'Money & Stripe',
            'deck' => 'Role-specific Money views and Connect / webhooks',
            'icon' => 'bi-cash-coin',
        ],
        'api' => [
            'label' => 'HTTP API',
            'deck' => 'Auth, shops, operators, sessions, money, keys',
            'icon' => 'bi-code-slash',
        ],
        'api-shift' => [
            'label' => 'Partner API',
            'deck' => 'Proxy routes for hours, workers, team, and account',
            'icon' => 'bi-broadcast',
        ],
        'api-session' => [
            'label' => 'API for your seat',
            'deck' => 'Session cookie calls scoped to your role',
            'icon' => 'bi-key',
        ],
        'sync' => [
            'label' => 'Settings & sync',
            'deck' => 'Live data, skins, platform notes',
            'icon' => 'bi-gear',
        ],
        'troubleshooting' => [
            'label' => 'Troubleshooting',
            'deck' => 'Common failures and how to read them',
            'icon' => 'bi-tools',
        ],
    ];
}

$metaAll = repsDashHelpPageMeta();
$allowedSlugs = repsDashHelpPagesForRole((string) $user['role']);
$page = (string) ($_GET['page'] ?? 'overview');
if (!in_array($page, $allowedSlugs, true) || !isset($metaAll[$page])) {
    $page = 'overview';
}
$meta = $metaAll[$page];

repsDashRenderHeader('Help · ' . $meta['label'], 'help');
repsDashRenderPageHeader(
    'Help',
    $meta['deck']
);
?>

<div class="row g-3 align-items-start rd-help">
  <div class="col-12 col-lg-3">
    <div class="surface surface-pad">
      <div class="section-title mb-2"><i class="bi bi-compass me-1"></i>Help map</div>
      <div class="list-group list-group-flush rd-help__nav">
        <?php foreach ($allowedSlugs as $slug): ?>
          <?php if (!isset($metaAll[$slug])) {
              continue;
          } ?>
          <?php $m = $metaAll[$slug]; ?>
          <a class="list-group-item list-group-item-action<?php echo $slug === $page ? ' active' : ''; ?>"
             href="/dashboard/help.php?page=<?php echo urlencode($slug); ?>">
            <i class="bi <?php echo htmlspecialchars($m['icon']); ?> me-2"></i><?php echo htmlspecialchars($m['label']); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="small text-muted mt-3 mb-0">Your role (<strong><?php echo htmlspecialchars(repsDashRoleLabel((string) $user['role'])); ?></strong>) gates which chapters appear. Full API and Partner proxy docs are for admin, ops, and agent.</p>
    </div>
  </div>
  <div class="col-12 col-lg-9">
    <div class="surface surface-pad rd-help__body documentation-content">
      <?php
      $helpFile = __DIR__ . '/includes/help/' . $page . '.php';
      if (is_readable($helpFile)) {
          include $helpFile;
      } else {
          echo '<p class="text-danger">Help page missing.</p>';
      }
      ?>
    </div>
  </div>
</div>

<p class="text-muted small mt-3 mb-0">
  <?php if (in_array((string) $user['role'], ['admin', 'ops', 'agent'], true)): ?>
    <a href="/dashboard/api/">API index</a>
  <?php endif; ?>
</p>

<?php repsDashRenderFooter(); ?>
