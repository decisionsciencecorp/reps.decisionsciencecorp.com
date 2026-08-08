<?php
declare(strict_types=1);

/**
 * Admin/Ops: match Partner workers to Reps accounts.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$user = repsDashRequireLogin();
repsDashRequireNavKey('shift_match', $user);

$role = (string) ($user['role'] ?? '');
if (!in_array($role, ['admin', 'ops'], true)) {
    header('Location: /dashboard/');
    exit;
}

$flash = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    repsDashRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $actorId = (int) ($user['id'] ?? 0);

    if ($action === 'poll') {
        $res = repsShiftPollLive();
        if ($res['ok'] ?? false) {
            $flash = sprintf(
                'Sync OK — %d session rows, partner %s.',
                (int) ($res['sessions_upserted'] ?? 0),
                (string) ($res['partner_code'] ?? '—')
            );
        } else {
            $flashErr = 'Sync failed: ' . (string) ($res['error'] ?? 'unknown');
        }
    } elseif ($action === 'match') {
        $opId = (int) ($_POST['operator_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $res = repsOperatorMatchUser($opId, $userId, $actorId);
        if ($res['ok'] ?? false) {
            $flash = 'Matched worker to Reps account.';
        } else {
            $flashErr = 'Match failed: ' . (string) ($res['error'] ?? 'unknown');
        }
    } elseif ($action === 'unmatch') {
        $opId = (int) ($_POST['operator_id'] ?? 0);
        $res = repsOperatorUnmatch($opId, $actorId);
        if ($res['ok'] ?? false) {
            $flash = 'Unmatched.';
        } else {
            $flashErr = 'Unmatch failed: ' . (string) ($res['error'] ?? 'unknown');
        }
    } elseif ($action === 'invite') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            $flashErr = 'Invite needs name and phone.';
        } else {
            try {
                $res = repsShiftInviteTeamMember($name, $phone);
                if ($res['ok'] ?? false) {
                    $sync = repsShiftPollLive();
                    $flash = 'Invite sent.'
                        . (($sync['ok'] ?? false) ? ' Local book refreshed.' : ' (re-sync skipped or failed — pull manually)');
                } else {
                    $flashErr = 'Invite failed: ' . (string) ($res['error'] ?? 'unknown');
                }
            } catch (Throwable $e) {
                $flashErr = $e->getMessage();
            }
        }
    }
}

$pdo = repsDashDb();
$workers = $pdo->query(
    "SELECT * FROM operators
     WHERE shift_user_id NOT LIKE 'reps-user-%'
     ORDER BY
       CASE WHEN matched_user_id IS NULL THEN 0 ELSE 1 END,
       display_name COLLATE NOCASE"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$users = $pdo->query(
    "SELECT id, username, display_name, role, shop_id, operator_id
     FROM users
     WHERE is_active = 1 AND role IN ('individual','employee','business_owner')
     ORDER BY display_name COLLATE NOCASE"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$userById = [];
foreach ($users as $u) {
    $userById[(int) $u['id']] = $u;
}

$lastSync = repsDashAppMetaGet('shift.last_sync_at', '');
$partner = repsDashAppMetaGet('shift.partner_code', '');
$live = repsDashLiveDataEnabled();

repsDashRenderHeader('Worker match', 'shift_match');
repsDashRenderPageHeader('Worker match', 'Link Partner workers to dashboard seats');
?>

<div class="alert alert-dark border-0 mb-3">
  <strong>Admin / Ops.</strong> Reps pays the shop owner or solo operator — not shop employees via Stripe.
  Matching tells the ledger and Connect which Partner <code>user_id</code> maps to a Reps seat.
</div>

<?php if ($flash !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div>
<?php endif; ?>

<?php
$shiftBase = repsShiftApiBase();
?>
<div class="surface p-3 mb-4 d-flex flex-wrap gap-3 align-items-center justify-content-between">
  <div class="small">
    <div>Live data: <strong><?php echo $live ? 'on' : 'off (mock)'; ?></strong></div>
    <div>Partner code: <code><?php echo htmlspecialchars($partner !== '' ? $partner : '—'); ?></code></div>
    <div>Last sync: <?php echo htmlspecialchars($lastSync !== '' ? $lastSync : 'never'); ?></div>
    <div>API base: <code><?php echo htmlspecialchars($shiftBase); ?></code></div>
  </div>
  <form method="post" class="m-0">
    <?php echo repsDashCsrfField(); ?>
    <input type="hidden" name="action" value="poll">
    <button type="submit" class="btn btn-sm btn-primary">Pull from Partner now</button>
  </form>
</div>

<div class="surface p-3 mb-4">
  <h2 class="h6 mb-2">Invite worker</h2>
  <p class="small text-muted mb-3">Name + phone → Partner texts them. Same as Team invite on the Partner dashboard.</p>
  <form method="post" class="row g-2 align-items-end">
    <?php echo repsDashCsrfField(); ?>
    <input type="hidden" name="action" value="invite">
    <div class="col-md-4">
      <label class="form-label small mb-0">Name</label>
      <input class="form-control form-control-sm" name="name" required autocomplete="off">
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-0">Phone</label>
      <input class="form-control form-control-sm" name="phone" required placeholder="+1…" autocomplete="off">
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-sm btn-outline-danger">Send invite</button>
    </div>
  </form>
</div>

<?php if ($workers === []): ?>
  <div class="surface p-3 text-muted">
    No workers in the database yet. Run <strong>Pull from Partner now</strong>
    (needs a valid Partner cookie jar on the host),
    or ingest offline JSON via <code>tools/poll-shift.php</code>.
  </div>
<?php else: ?>
  <div class="surface p-0 mb-4">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Worker</th>
            <th>Partner user id</th>
            <th>7d accepted</th>
            <th>Matched Reps seat</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($workers as $w):
            $opId = (int) $w['id'];
            $matchedId = isset($w['matched_user_id']) ? (int) $w['matched_user_id'] : 0;
            $matchedUser = $matchedId > 0 ? ($userById[$matchedId] ?? null) : null;
            ?>
          <tr>
            <td class="fw-semibold"><?php echo htmlspecialchars((string) $w['display_name']); ?>
              <?php if (!empty($w['phone'])): ?>
                <div class="small text-muted"><?php echo htmlspecialchars((string) $w['phone']); ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><code><?php echo htmlspecialchars((string) $w['shift_user_id']); ?></code></td>
            <td><?php echo htmlspecialchars((string) round((float) ($w['accepted_7d'] ?? 0), 2)); ?>h</td>
            <td>
              <?php if ($matchedUser): ?>
                <?php echo htmlspecialchars((string) $matchedUser['display_name']); ?>
                <span class="text-muted small">(@<?php echo htmlspecialchars((string) $matchedUser['username']); ?> · <?php echo htmlspecialchars((string) $matchedUser['role']); ?>)</span>
              <?php else: ?>
                <span class="text-muted">Unmatched</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($matchedUser): ?>
                <form method="post" class="d-inline">
                  <?php echo repsDashCsrfField(); ?>
                  <input type="hidden" name="action" value="unmatch">
                  <input type="hidden" name="operator_id" value="<?php echo $opId; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Unmatch</button>
                </form>
              <?php else: ?>
                <form method="post" class="d-inline-flex gap-1 align-items-center">
                  <?php echo repsDashCsrfField(); ?>
                  <input type="hidden" name="action" value="match">
                  <input type="hidden" name="operator_id" value="<?php echo $opId; ?>">
                  <select name="user_id" class="form-select form-select-sm" required style="min-width:12rem">
                    <option value="">Choose Reps user…</option>
                    <?php foreach ($users as $u): ?>
                      <option value="<?php echo (int) $u['id']; ?>">
                        <?php echo htmlspecialchars((string) $u['display_name'] . ' (@' . $u['username'] . ') — ' . $u['role']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-sm btn-primary">Match</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<p class="small text-muted mb-0">
  Shop employees can be matched for session attribution; capture dollars still go to the shop owner’s Connect account when <code>shop_id</code> is set.
  Solo individuals should be matched so My pay / Connect lands on the right person.
</p>

<?php repsDashRenderFooter(); ?>
