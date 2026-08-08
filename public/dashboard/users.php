<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('users', $user);

if (!repsDashIsAdmin($user)) {
    header('Location: /dashboard/');
    exit;
}

$flash = '';
$flashErr = '';
$flashKey = '';
$roles = repsDashValidRoles();

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
        } else {
            $flashErr = $result['error'] ?? 'Could not create user.';
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['user_id'] ?? 0);
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
        $result = repsDashSetUserPassword($id, (string) ($_POST['password'] ?? ''));
        if ($result['ok']) {
            $flash = 'Password reset.';
        } else {
            $flashErr = $result['error'] ?? 'Could not reset password.';
        }
    } elseif ($action === 'create_api_key') {
        $id = (int) ($_POST['user_id'] ?? 0);
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
        $result = repsApiRevokeKey($keyId, (int) $user['id']);
        if ($result['ok']) {
            $flash = 'API key revoked.';
        } else {
            $flashErr = $result['error'] ?? 'Could not revoke API key.';
        }
    }
}

$accounts = repsDashListUsers(false);

repsDashRenderHeader('Users', 'users');
repsDashRenderPageHeader('Users', 'Provision seats — roles, shop/operator scope, password reset');
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

<div class="surface p-3 mb-4">
  <h2 class="h5 mb-3">Create user</h2>
  <form method="post" class="row g-3">
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
  <p class="small text-muted mt-2 mb-0">Shop ID / Operator ID tie seats to mock (then live) Shift scope — same keys as Slice A.</p>
</div>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Scope</th>
          <th>Active</th>
          <th>Reset password</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $acct): ?>
        <tr>
          <td colspan="7" class="p-3 border-bottom">
            <form method="post" class="row g-2 align-items-end">
              <?php echo repsDashCsrfField(); ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="user_id" value="<?php echo (int) $acct['id']; ?>">
              <div class="col-md-2">
                <label class="form-label small mb-0">Display name</label>
                <input class="form-control form-control-sm" name="display_name" value="<?php echo htmlspecialchars($acct['display_name']); ?>" required>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-0">Email</label>
                <input class="form-control form-control-sm" name="email" value="<?php echo htmlspecialchars($acct['email']); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-0">Role</label>
                <select class="form-select form-select-sm" name="role">
                  <?php foreach ($roles as $r): ?>
                    <option value="<?php echo htmlspecialchars($r); ?>"<?php echo $acct['role'] === $r ? ' selected' : ''; ?>>
                      <?php echo htmlspecialchars(repsDashRoleLabel($r)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-1">
                <label class="form-label small mb-0">Shop</label>
                <input class="form-control form-control-sm" type="number" name="shop_id" value="<?php echo isset($acct['shop_id']) ? (int) $acct['shop_id'] : ''; ?>">
              </div>
              <div class="col-md-1">
                <label class="form-label small mb-0">Op</label>
                <input class="form-control form-control-sm" type="number" name="operator_id" value="<?php echo isset($acct['operator_id']) ? (int) $acct['operator_id'] : ''; ?>">
              </div>
              <div class="col-md-1">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="is_active" id="active-<?php echo (int) $acct['id']; ?>" <?php echo !empty($acct['is_active']) ? 'checked' : ''; ?>>
                  <label class="form-check-label small" for="active-<?php echo (int) $acct['id']; ?>">On</label>
                </div>
              </div>
              <div class="col-md-2">
                <span class="small text-muted d-block">@<?php echo htmlspecialchars($acct['username']); ?></span>
                <button type="submit" class="btn btn-sm btn-outline-primary mt-1">Save</button>
              </div>
            </form>
            <form method="post" class="row g-2 align-items-end mt-2">
              <?php echo repsDashCsrfField(); ?>
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="user_id" value="<?php echo (int) $acct['id']; ?>">
              <div class="col-md-4">
                <label class="form-label small mb-0">New password</label>
                <input class="form-control form-control-sm" type="password" name="password" minlength="<?php echo (int) REPS_DASH_PASSWORD_MIN; ?>" required autocomplete="new-password">
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Reset password</button>
              </div>
            </form>
            <form method="post" class="row g-2 align-items-end mt-2">
              <?php echo repsDashCsrfField(); ?>
              <input type="hidden" name="action" value="create_api_key">
              <input type="hidden" name="user_id" value="<?php echo (int) $acct['id']; ?>">
              <div class="col-md-4">
                <label class="form-label small mb-0">API key name</label>
                <input class="form-control form-control-sm" type="text" name="key_name" value="default" maxlength="80">
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-dark">Create API key</button>
              </div>
            </form>
            <?php
            $keys = repsApiListKeysForUser((int) $acct['id'], false);
            if ($keys !== []):
            ?>
              <ul class="small text-muted mb-0 mt-2">
                <?php foreach ($keys as $k): ?>
                  <li class="d-flex flex-wrap gap-2 align-items-center">
                    <code><?php echo htmlspecialchars((string) $k['key_preview']); ?></code>
                    <span><?php echo htmlspecialchars((string) $k['key_name']); ?></span>
                    <form method="post" class="m-0">
                      <?php echo repsDashCsrfField(); ?>
                      <input type="hidden" name="action" value="revoke_api_key">
                      <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                      <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Revoke</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-3 mb-0">API keys authenticate <code>/dashboard/api/</code> (Slice D). Prefer keys on the <strong>agent</strong> seat for automation. Seed seats use the shared demo password until you reset them.</p>

<?php repsDashRenderFooter(); ?>
