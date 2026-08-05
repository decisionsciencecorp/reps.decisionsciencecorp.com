<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (repsDashCurrentUser()) {
    header('Location: /dashboard/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    repsDashRequireCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $devPick = trim((string) ($_POST['dev_username'] ?? ''));

    if ($devPick !== '' && repsDashIsDevMode()) {
        if (repsDashLoginDemo($devPick)) {
            header('Location: /dashboard/');
            exit;
        }
        $error = 'Unknown Dev Mode seat.';
    } elseif ($username !== '' && $password !== '' && repsDashLogin($username, $password)) {
        header('Location: /dashboard/');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$skin = repsDashSkinEffectiveSlug(null);
$navLight = in_array($skin, ['hey', 'ledger'], true);
$devMode = repsDashIsDevMode();
?>
<!DOCTYPE html>
<html lang="en" data-skin-comp="<?php echo htmlspecialchars($skin); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Reps Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/dashboard/assets/css/dashboard.css?v=1" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(repsDashSkinStylesheetHref($skin)); ?>" rel="stylesheet">
</head>
<body class="bg-light">
  <nav class="navbar <?php echo $navLight ? 'navbar-light' : 'navbar-dark bg-dark'; ?> admin-nav">
    <div class="container-fluid px-3 px-lg-4">
      <a class="navbar-brand fw-semibold" href="/"><i class="bi bi-broadcast-pin me-1"></i>Reps</a>
    </div>
  </nav>
  <main class="dk-shell" style="max-width:480px">
    <div class="page-header">
      <div class="page-header__title">
        <h1>Dashboard sign-in</h1>
        <div class="subtitle">Team seats for DSC Partner work. Password required.</div>
      </div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="surface p-3 mb-3">
      <form method="post" class="d-grid gap-3">
        <?php echo repsDashCsrfField(); ?>
        <div>
          <label class="form-label" for="username">Username</label>
          <input class="form-control" type="text" name="username" id="username" autocomplete="username" required>
        </div>
        <div>
          <label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" name="password" id="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Sign in</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Forgot password? Ask an admin to reset it on the Users page.</p>
    </div>

    <?php if ($devMode): ?>
    <div class="surface p-3 mb-3 border border-warning">
      <p class="mb-2 fw-semibold"><i class="bi bi-tools me-1"></i>Dev Mode seat picker</p>
      <p class="small text-muted mb-3">Enabled via <code>REPS_DASH_DEV_MODE=1</code>. Not production login.</p>
      <div class="d-grid gap-2">
        <?php foreach (repsDashDemoAccounts() as $acct): ?>
          <form method="post" class="m-0">
            <?php echo repsDashCsrfField(); ?>
            <input type="hidden" name="dev_username" value="<?php echo htmlspecialchars($acct['username']); ?>">
            <button type="submit" class="btn btn-outline-warning w-100 text-start d-flex justify-content-between align-items-center">
              <span>
                <strong><?php echo htmlspecialchars($acct['display_name']); ?></strong>
                <span class="text-muted small ms-2">@<?php echo htmlspecialchars($acct['username']); ?></span>
              </span>
              <span class="badge text-bg-secondary"><?php echo htmlspecialchars(repsDashRoleLabel((string) $acct['role'])); ?></span>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <p class="small text-muted">Skin preview:
      <?php foreach (repsDashSkinAvailableSlugs() as $slug): ?>
        <a href="?preview_skin=<?php echo urlencode($slug); ?>"><?php echo htmlspecialchars($slug); ?></a>
      <?php endforeach; ?>
    </p>
  </main>
</body>
</html>
