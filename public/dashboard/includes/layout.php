<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

function repsDashPill(string $kind): string
{
    return match (strtolower($kind)) {
        'active', 'accepted', 'done', 'signed' => 'done',
        'pitched', 'pending', 'doing', 'invited' => 'doing',
        'rejected', 'paused', 'blocked' => 'blocked',
        'prospect', 'todo' => 'todo',
        default => 'info',
    };
}

function repsDashRenderPageHeader(string $title, string $subtitle = '', string $actionsHtml = ''): void
{
    ?>
    <div class="page-header">
      <div class="page-header__title">
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <?php if ($subtitle !== ''): ?>
          <div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div>
        <?php endif; ?>
      </div>
      <?php if ($actionsHtml !== ''): ?>
        <div class="page-header__actions"><?php echo $actionsHtml; ?></div>
      <?php endif; ?>
    </div>
    <?php
}

function repsDashRenderDevModeBar(?array $user): void
{
    if (!repsDashIsDevMode() || !$user) {
        return;
    }
    $accounts = repsDashDemoAccounts();
    $return = $_SERVER['REQUEST_URI'] ?? '/dashboard/';
    ?>
  <div class="rd-dev-bar" role="region" aria-label="Dev Mode role picker">
    <form method="post" action="/dashboard/switch-role.php" class="rd-dev-bar__form">
      <input type="hidden" name="return" value="<?php echo htmlspecialchars($return); ?>">
      <span class="rd-dev-bar__label"><i class="bi bi-tools me-1"></i>Dev Mode</span>
      <label class="rd-dev-bar__select-wrap">
        <span class="visually-hidden">Switch demo role</span>
        <select name="username" class="form-select form-select-sm rd-dev-bar__select" onchange="this.form.submit()" aria-label="Switch demo seat">
          <?php foreach ($accounts as $acct): ?>
            <option value="<?php echo htmlspecialchars($acct['username']); ?>"<?php echo $acct['username'] === $user['username'] ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars(repsDashRoleLabel((string) $acct['role']) . ' — ' . $acct['display_name'] . ' (@' . $acct['username'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <a class="rd-dev-bar__link" href="/dashboard/access.php">Views × roles</a>
      <span class="rd-dev-bar__hint d-none d-lg-inline">Switch seat to audit scoping. Slice A only.</span>
    </form>
  </div>
    <?php
}

function repsDashRenderHeader(string $title = '', string $active = 'home'): void
{
    $user = repsDashCurrentUser();
    $safeTitle = htmlspecialchars($title !== '' ? $title . ' · ' . REPS_DASH_NAME : REPS_DASH_NAME);
    $skin = repsDashSkinEffectiveSlug(is_array($user) ? $user : null);
    $navLight = in_array($skin, ['hey', 'ledger'], true);
    $navThemeClass = $navLight ? 'navbar-light' : 'navbar-dark';

    $role = $user ? (string) $user['role'] : '';
    $shopsLabel = $role === 'business_owner' ? 'My shop' : 'Shops';
    $opsLabel = $role === 'business_owner' ? 'Team' : 'Operators';
    $sessionsLabel = in_array($role, ['employee', 'individual'], true) ? 'My sessions' : 'Sessions';
    $moneyLabel = match ($role) {
        'business_owner' => 'My pay',
        'sales' => 'Money',
        default => 'Money',
    };
    $navAll = [
        'home' => ['Home', '/dashboard/', 'bi-speedometer2'],
        'shops' => [$shopsLabel, '/dashboard/shops.php', 'bi-shop'],
        'operators' => [$opsLabel, '/dashboard/operators.php', 'bi-people'],
        'sessions' => [$sessionsLabel, '/dashboard/sessions.php', 'bi-camera-reels'],
        'money' => [$moneyLabel, '/dashboard/money.php', 'bi-cash-coin'],
        'users' => ['Users', '/dashboard/users.php', 'bi-person-gear'],
        'settings' => ['Settings', '/dashboard/settings.php', 'bi-gear'],
    ];
    $allowed = $user ? repsDashNavKeysForRole($role) : ['home'];
    $nav = [];
    foreach ($allowed as $key) {
        if (isset($navAll[$key])) {
            $nav[$key] = $navAll[$key];
        }
    }
    $bodyClass = 'bg-light' . (repsDashIsDevMode() && $user ? ' rd-has-dev-bar' : '');
    ?>
<!DOCTYPE html>
<html lang="en" data-skin-comp="<?php echo htmlspecialchars($skin); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $safeTitle; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/dashboard/assets/css/dashboard.css?v=3" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(repsDashSkinStylesheetHref($skin)); ?>" rel="stylesheet">
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
  <?php repsDashRenderDevModeBar(is_array($user) ? $user : null); ?>
  <nav class="navbar navbar-expand-lg <?php echo htmlspecialchars($navThemeClass); ?> admin-nav<?php echo $navLight ? '' : ' bg-dark'; ?>">
    <div class="container-fluid px-3 px-lg-4">
      <a class="navbar-brand fw-semibold d-inline-flex align-items-center gap-2" href="/dashboard/">
        <i class="bi bi-broadcast-pin"></i>
        <span>Reps</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#rdNavbar" aria-controls="rdNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="rdNavbar">
        <?php if ($user): ?>
        <div class="d-flex flex-column flex-lg-row flex-lg-nowrap gap-2 ms-lg-auto align-items-stretch align-items-lg-center py-3 py-lg-0">
          <?php foreach ($nav as $key => [$label, $href, $icon]): ?>
            <a class="btn btn-outline-light text-center text-lg-start <?php echo $active === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href); ?>">
              <i class="bi <?php echo htmlspecialchars($icon); ?> me-1"></i><?php echo htmlspecialchars($label); ?>
            </a>
          <?php endforeach; ?>
          <span class="navbar-text small px-2 text-nowrap">
            <?php echo htmlspecialchars($user['display_name']); ?>
            <span class="badge text-bg-secondary ms-1"><?php echo htmlspecialchars(repsDashRoleLabel((string) $user['role'])); ?></span>
          </span>
          <a class="btn btn-outline-light" href="/dashboard/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Sign out</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>
  <main class="dk-shell">
    <?php
}

function repsDashRenderFooter(): void
{
    $partner = repsDashCanSeePartnerCode()
        ? ' · Partner code C6N9T7'
        : '';
    ?>
  </main>
  <footer class="container-fluid px-3 px-lg-4 pb-4">
    <p class="text-muted small mb-0">Reps Dashboard · Slice A mock data<?php echo htmlspecialchars($partner); ?> · <a href="/">Marketing site</a></p>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}

function repsDashStatusPill(string $status): void
{
    $pill = repsDashPill($status);
    echo '<span class="status-pill status-pill--' . htmlspecialchars($pill) . '">' . htmlspecialchars($status) . '</span>';
}
