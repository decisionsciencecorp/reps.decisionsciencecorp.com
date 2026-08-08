<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/affiliate_pages.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('users', $user);

if (!repsDashIsAdmin($user)) {
    header('Location: /dashboard/shift-match.php');
    exit;
}

$flash = '';
$flashErr = '';
$flashKey = '';
$roles = repsDashValidRoles();
$expandId = isset($_GET['expand']) ? (int) $_GET['expand'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    repsDashRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = repsDashCreateUser([
            'username' => $_POST['username'] ?? '',
            'display_name' => $_POST['display_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role' => $_POST['role'] ?? '',
            'password' => $_POST['password'] ?? '',
            'shop_id' => $_POST['shop_id'] ?? '',
            'operator_id' => $_POST['operator_id'] ?? '',
        ]);
        if ($result['ok']) {
            $flash = 'User created.';
            $expandId = (int) ($result['id'] ?? 0);
        } else {
            $flashErr = $result['error'] ?? 'Could not create user.';
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $expandId = $id;
        $result = repsDashUpdateUser($id, [
            'display_name' => $_POST['display_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role' => $_POST['role'] ?? '',
            'shop_id' => $_POST['shop_id'] ?? '',
            'operator_id' => $_POST['operator_id'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
        if ($result['ok']) {
            $flash = 'User updated.';
        } else {
            $flashErr = $result['error'] ?? 'Could not update user.';
        }
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $expandId = $id;
        $result = repsDashSetUserPassword($id, (string) ($_POST['password'] ?? ''));
        if ($result['ok']) {
            $flash = 'Password reset.';
        } else {
            $flashErr = $result['error'] ?? 'Could not reset password.';
        }
    } elseif ($action === 'create_api_key') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $expandId = $id;
        $name = trim((string) ($_POST['key_name'] ?? 'default'));
        $result = repsApiCreateKey($id, $name !== '' ? $name : 'default', (int) $user['id']);
        if ($result['ok']) {
            $flash = 'API key created — copy it now; it will not be shown again.';
            $flashKey = (string) ($result['key'] ?? '');
        } else {
            $flashErr = $result['error'] ?? 'Could not create API key.';
        }
    } elseif ($action === 'revoke_api_key') {
        $keyId = (int) ($_POST['key_id'] ?? 0);
        $expandId = (int) ($_POST['user_id'] ?? 0);
        $result = repsApiRevokeKey($keyId, (int) $user['id']);
        if ($result['ok']) {
            $flash = 'API key revoked.';
        } else {
            $flashErr = $result['error'] ?? 'Could not revoke API key.';
        }
    }
}

$accounts = repsDashListUsers(false);
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $needle = strtolower($q);
    $accounts = array_values(array_filter($accounts, static function (array $acct) use ($needle): bool {
        $hay = strtolower(implode(' ', [
            (string) ($acct['display_name'] ?? ''),
            (string) ($acct['username'] ?? ''),
            (string) ($acct['email'] ?? ''),
            (string) ($acct['role'] ?? ''),
        ]));
        return str_contains($hay, $needle);
    }));
}

repsDashRenderHeader('Users', 'users');
repsDashRenderPageHeader(
    'Users',
    'Seat ledger — expand a row to edit scope, password, or API keys'
);
?>

<?php if ($flash !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div>
<?php endif; ?>
<?php if ($flashKey !== ''): ?>
  <div class="alert alert-warning">
    <strong>New API key (copy once):</strong>
    <code class="user-select-all"><?php echo htmlspecialchars($flashKey); ?></code>
  </div>
<?php endif; ?>

<details class="surface surface-pad mb-3 rd-users-create">
  <summary class="fw-semibold user-select-none">
    <i class="bi bi-person-plus me-1"></i>Create user
  </summary>
  <form method="post" class="row g-3 mt-1">
    <?php echo repsDashCsrfField(); ?>
    <input type="hidden" name="action" value="create">
    <div class="col-md-3">
      <label class="form-label">Username</label>
      <input class="form-control" name="username" required pattern="[a-zA-Z0-9._\-]{2,40}" autocomplete="off">
    </div>
    <div class="col-md-3">
      <label class="form-label">Display name</label>
      <input class="form-control" name="display_name" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="email">
    </div>
    <div class="col-md-3">
      <label class="form-label">Role</label>
      <select class="form-select" name="role" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars(repsDashRoleLabel($r)); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Password</label>
      <input class="form-control" type="password" name="password" required minlength="<?php echo (int) REPS_DASH_PASSWORD_MIN; ?>" autocomplete="new-password">
    </div>
    <div class="col-md-2">
      <label class="form-label">Shop ID</label>
      <input class="form-control" type="number" name="shop_id" placeholder="optional">
    </div>
    <div class="col-md-2">
      <label class="form-label">Operator ID</label>
      <input class="form-control" type="number" name="operator_id" placeholder="optional">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary">Create</button>
    </div>
  </form>
  <p class="small text-muted mt-2 mb-0">Shop ID / Operator ID tie seats to Partner scope. Prefer the <strong>agent</strong> seat for automation API keys.</p>
</details>

<form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <label class="visually-hidden" for="users-q">Filter seats</label>
  <input class="form-control form-control-sm" style="max-width:16rem" type="search" name="q" id="users-q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Filter name, username, role…">
  <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
  <?php if ($q !== ''): ?>
    <a class="btn btn-sm btn-link" href="/dashboard/users.php">Clear</a>
  <?php endif; ?>
  <span class="small text-muted ms-auto"><?php echo count($accounts); ?> seat<?php echo count($accounts) === 1 ? '' : 's'; ?></span>
</form>

<div class="surface p-0 rd-ledger">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 rd-ledger__table">
      <thead>
        <tr>
          <th style="width:2rem"></th>
          <th>Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Scope</th>
          <th>Status</th>
          <th class="text-end">Keys</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($accounts === []): ?>
        <tr><td colspan="7" class="text-muted p-3">No seats match.</td></tr>
      <?php endif; ?>
      <?php foreach ($accounts as $acct): ?>
        <?php
        $uid = (int) $acct['id'];
        $open = $expandId === $uid;
        $keys = repsApiListKeysForUser($uid, false);
        $keyCount = count($keys);
        $scopeBits = [];
        if (isset($acct['shop_id']) && $acct['shop_id'] !== null && $acct['shop_id'] !== '') {
            $scopeBits[] = 'shop ' . (int) $acct['shop_id'];
        }
        if (isset($acct['operator_id']) && $acct['operator_id'] !== null && $acct['operator_id'] !== '') {
            $scopeBits[] = 'op ' . (int) $acct['operator_id'];
        }
        $scopeLabel = $scopeBits !== [] ? implode(' · ', $scopeBits) : '—';
        $activeOn = !empty($acct['is_active']);
        $affSlug = strtolower((string) ($acct['username'] ?? ''));
        $showAff = ($acct['role'] ?? '') === 'sales' && $activeOn && reps_affiliate_slug_valid($affSlug);
        ?>
        <tr class="rd-ledger__row<?php echo $open ? ' rd-ledger__row--open' : ''; ?>">
          <td>
            <button type="button" class="btn btn-sm btn-link p-0 rd-ledger__toggle" data-bs-toggle="collapse" data-bs-target="#user-edit-<?php echo $uid; ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="user-edit-<?php echo $uid; ?>">
              <i class="bi bi-chevron-<?php echo $open ? 'down' : 'right'; ?>"></i>
              <span class="visually-hidden">Expand</span>
            </button>
          </td>
          <td class="fw-medium"><?php echo htmlspecialchars((string) $acct['display_name']); ?></td>
          <td><code class="small">@<?php echo htmlspecialchars((string) $acct['username']); ?></code></td>
          <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars(repsDashRoleLabel((string) $acct['role'])); ?></span></td>
          <td class="small text-muted"><?php echo htmlspecialchars($scopeLabel); ?></td>
          <td>
            <?php if ($activeOn): ?>
              <span class="status-pill status-pill--done">active</span>
            <?php else: ?>
              <span class="status-pill status-pill--blocked">off</span>
            <?php endif; ?>
          </td>
          <td class="text-end small text-muted"><?php echo $keyCount > 0 ? (string) $keyCount : '—'; ?></td>
        </tr>
        <tr class="rd-ledger__detail">
          <td colspan="7" class="p-0 border-0">
            <div class="collapse<?php echo $open ? ' show' : ''; ?>" id="user-edit-<?php echo $uid; ?>">
              <div class="rd-ledger__panel p-3">
                <div class="row g-3">
                  <div class="col-lg-7">
                    <h3 class="h6 mb-2">Edit seat</h3>
                    <form method="post" class="row g-2 align-items-end">
                      <?php echo repsDashCsrfField(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                      <div class="col-md-4">
                        <label class="form-label small mb-0">Display name</label>
                        <input class="form-control form-control-sm" name="display_name" value="<?php echo htmlspecialchars((string) $acct['display_name']); ?>" required>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label small mb-0">Email</label>
                        <input class="form-control form-control-sm" name="email" value="<?php echo htmlspecialchars((string) $acct['email']); ?>">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label small mb-0">Role</label>
                        <select class="form-select form-select-sm" name="role">
                          <?php foreach ($roles as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>"<?php echo $acct['role'] === $r ? ' selected' : ''; ?>>
                              <?php echo htmlspecialchars(repsDashRoleLabel($r)); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-0">Shop ID</label>
                        <input class="form-control form-control-sm" type="number" name="shop_id" value="<?php echo isset($acct['shop_id']) && $acct['shop_id'] !== null && $acct['shop_id'] !== '' ? (int) $acct['shop_id'] : ''; ?>">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-0">Operator ID</label>
                        <input class="form-control form-control-sm" type="number" name="operator_id" value="<?php echo isset($acct['operator_id']) && $acct['operator_id'] !== null && $acct['operator_id'] !== '' ? (int) $acct['operator_id'] : ''; ?>">
                      </div>
                      <div class="col-md-3">
                        <div class="form-check mt-4">
                          <input class="form-check-input" type="checkbox" name="is_active" id="active-<?php echo $uid; ?>" <?php echo $activeOn ? 'checked' : ''; ?>>
                          <label class="form-check-label small" for="active-<?php echo $uid; ?>">Active</label>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                      </div>
                    </form>
                    <?php if ($showAff): ?>
                      <div class="small mt-3 p-2 border rounded bg-white">
                        <div class="fw-semibold mb-1">Affiliate page</div>
                        <div><a href="<?php echo htmlspecialchars(reps_affiliate_canonical_url($affSlug)); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(reps_affiliate_canonical_url($affSlug)); ?></a></div>
                        <div class="text-muted">Path fallback: <a href="<?php echo htmlspecialchars(reps_affiliate_path_url($affSlug)); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(reps_affiliate_path_url($affSlug)); ?></a></div>
                        <div class="text-muted mt-1">After adding a sales seat, run <code>php tools/sync_affiliate_page_stubs.php</code>.</div>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="col-lg-5">
                    <h3 class="h6 mb-2">Reset password</h3>
                    <form method="post" class="row g-2 align-items-end mb-3">
                      <?php echo repsDashCsrfField(); ?>
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                      <div class="col-8">
                        <label class="form-label small mb-0">New password</label>
                        <input class="form-control form-control-sm" type="password" name="password" minlength="<?php echo (int) REPS_DASH_PASSWORD_MIN; ?>" required autocomplete="new-password">
                      </div>
                      <div class="col-4">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Reset</button>
                      </div>
                    </form>
                    <h3 class="h6 mb-2">API keys</h3>
                    <form method="post" class="row g-2 align-items-end mb-2">
                      <?php echo repsDashCsrfField(); ?>
                      <input type="hidden" name="action" value="create_api_key">
                      <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                      <div class="col-7">
                        <label class="form-label small mb-0">Key name</label>
                        <input class="form-control form-control-sm" type="text" name="key_name" value="default" maxlength="80">
                      </div>
                      <div class="col-5">
                        <button type="submit" class="btn btn-sm btn-outline-dark">Create key</button>
                      </div>
                    </form>
                    <?php if ($keys !== []): ?>
                      <ul class="small text-muted mb-0 list-unstyled">
                        <?php foreach ($keys as $k): ?>
                          <li class="d-flex flex-wrap gap-2 align-items-center mb-1">
                            <code><?php echo htmlspecialchars((string) $k['key_preview']); ?></code>
                            <span><?php echo htmlspecialchars((string) $k['key_name']); ?></span>
                            <form method="post" class="m-0">
                              <?php echo repsDashCsrfField(); ?>
                              <input type="hidden" name="action" value="revoke_api_key">
                              <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                              <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                              <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Revoke</button>
                            </form>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <p class="small text-muted mb-0">No active keys.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-3 mb-0">
  Worker linking lives under <a href="/dashboard/shift-match.php">Users → Worker match</a>.
  API keys authenticate <code>/dashboard/api/</code> — see <a href="/dashboard/help.php?page=api">Help → HTTP API</a>.
</p>

<script>
(function () {
  document.querySelectorAll('.rd-ledger__toggle').forEach(function (btn) {
    var target = document.querySelector(btn.getAttribute('data-bs-target'));
    if (!target) return;
    target.addEventListener('show.bs.collapse', function () {
      var icon = btn.querySelector('.bi');
      if (icon) { icon.classList.remove('bi-chevron-right'); icon.classList.add('bi-chevron-down'); }
      btn.closest('tr')?.classList.add('rd-ledger__row--open');
    });
    target.addEventListener('hide.bs.collapse', function () {
      var icon = btn.querySelector('.bi');
      if (icon) { icon.classList.remove('bi-chevron-down'); icon.classList.add('bi-chevron-right'); }
      btn.closest('tr')?.classList.remove('rd-ledger__row--open');
    });
  });
})();
</script>

<?php repsDashRenderFooter(); ?>
