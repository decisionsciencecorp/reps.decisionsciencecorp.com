<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('leads', $user);

if (!repsDashCanManageApplyLeads($user)) {
    header('Location: /dashboard/');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$lead = $id > 0 ? repsDashFindApplyLead($id) : null;

if ($lead === null) {
    http_response_code(404);
    repsDashRenderHeader('Lead', 'leads');
    echo '<div class="alert alert-danger">Lead not found.</div>';
    echo '<a class="btn btn-outline-primary" href="/dashboard/leads.php">Back to leads</a>';
    repsDashRenderFooter();
    exit;
}

// Sales may only open assigned operator/shop leads. Affiliate leads = admin/ops only.
$role = (string) $user['role'];
if (!repsDashCanViewLead($user, $lead)) {
    http_response_code(403);
    repsDashRenderHeader('Lead', 'leads');
    if (($lead['join_kind'] ?? '') === 'affiliate') {
        echo '<div class="alert alert-warning">Affiliate partner leads are visible to admin and ops only.</div>';
    } else {
        echo '<div class="alert alert-warning">This lead is in another rep’s queue.</div>';
    }
    echo '<a class="btn btn-outline-primary" href="/dashboard/leads.php">Back to leads</a>';
    repsDashRenderFooter();
    exit;
}

$flash = '';
$flashErr = '';
$tempPasswordShown = null;
$salesPool = repsDashSalesUsernames();
if ($salesPool === []) {
    $salesPool = ['jim', 'seven', 'chuck'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    repsDashRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $result = ['ok' => false, 'error' => 'Unknown action.'];

    if ($action === 'claim' && $role === 'sales') {
        $result = repsDashUpdateApplyLead($id, [
            'status' => 'claimed',
            'assigned_sales_rep' => (string) $user['username'],
            'notes' => (string) ($_POST['notes'] ?? $lead['notes']),
        ]);
        if (!empty($result['ok'])) {
            repsDashAddLeadEvent($id, 'assigned', 'Claimed by @' . $user['username'], (int) $user['id']);
        }
    } elseif ($action === 'note') {
        $body = trim((string) ($_POST['event_body'] ?? ''));
        if ($body === '') {
            $result = ['ok' => false, 'error' => 'Note cannot be empty.'];
        } else {
            $result = repsDashAddLeadEvent($id, 'note', $body, (int) $user['id']);
        }
    } elseif ($action === 'called') {
        $body = trim((string) ($_POST['event_body'] ?? 'Called / texted'));
        $result = repsDashAddLeadEvent($id, 'called', $body !== '' ? $body : 'Called / texted', (int) $user['id']);
    } elseif ($action === 'graduate') {
        $g = repsDashGraduateLeadToUser($id, $user);
        if (!empty($g['ok'])) {
            $result = ['ok' => true];
            if (!empty($g['temp_password'])) {
                $tempPasswordShown = [
                    'username' => $g['user']['username'] ?? '',
                    'password' => $g['temp_password'],
                    'role' => $g['user']['role'] ?? '',
                ];
            } else {
                $flash = 'Already graduated — temp password was shown once.';
            }
        } else {
            $result = $g;
        }
    } elseif ($action === 'save') {
        $status = (string) ($_POST['status'] ?? $lead['status']);
        $rep = trim((string) ($_POST['assigned_sales_rep'] ?? ''));
        $prevStatus = (string) $lead['status'];
        $prevRep = (string) ($lead['assigned_sales_rep'] ?? '');
        if ($role === 'sales') {
            if ($status === 'claimed') {
                $rep = (string) $user['username'];
            }
            if ($status === 'open') {
                $rep = '';
            }
            if ($prevRep !== '' && $prevRep !== (string) $user['username'] && $status !== 'open') {
                $result = ['ok' => false, 'error' => 'This lead is claimed by another rep.'];
            } else {
                $result = repsDashUpdateApplyLead($id, [
                    'status' => $status,
                    'assigned_sales_rep' => $rep,
                    'notes' => (string) ($_POST['notes'] ?? ''),
                ]);
            }
        } else {
            $result = repsDashUpdateApplyLead($id, [
                'status' => $status,
                'assigned_sales_rep' => $rep,
                'notes' => (string) ($_POST['notes'] ?? ''),
            ]);
        }
        if (!empty($result['ok'])) {
            if ($status !== $prevStatus) {
                repsDashAddLeadEvent($id, 'status', 'Status → ' . $status, (int) $user['id']);
            }
            if ($rep !== '' && $rep !== $prevRep) {
                repsDashAddLeadEvent($id, 'assigned', 'Assigned to @' . $rep . ' (manual)', (int) $user['id']);
            }
        }
    }

    if (!empty($result['ok'])) {
        if ($flash === '' && $tempPasswordShown === null) {
            $flash = 'Lead updated.';
        }
        $lead = repsDashFindApplyLead($id) ?? $lead;
    } else {
        $flashErr = $result['error'] ?? 'Could not update lead.';
    }
}

$events = repsDashListLeadEvents($id);
$pathLabels = [
    'on_job' => 'On the job',
    'at_home' => 'At home',
    'company' => 'Company / team',
    'affiliate' => 'Affiliate seat',
];
$kindLabels = [
    'operator' => 'Operator',
    'shop' => 'Shop',
    'affiliate' => 'Affiliate',
];
$canGraduate = repsDashCanGraduateLead($user, $lead);

repsDashRenderHeader($lead['name'], 'leads');
?>
<p class="mb-3">
  <a class="small text-decoration-none" href="/dashboard/leads.php">
    <i class="bi bi-arrow-left"></i> Back to leads
  </a>
</p>
<?php
repsDashRenderPageHeader(
    $lead['name'],
    ($kindLabels[$lead['join_kind']] ?? $lead['join_kind']) . ' · ' . ($pathLabels[$lead['path']] ?? $lead['path'])
);
?>

<?php if ($tempPasswordShown !== null): ?>
  <div class="alert alert-warning">
    <strong>Seat created — copy this password now</strong> (shown once).<br>
    Username: <code><?php echo htmlspecialchars($tempPasswordShown['username']); ?></code>
    · Role: <code><?php echo htmlspecialchars($tempPasswordShown['role']); ?></code><br>
    Temp password: <code><?php echo htmlspecialchars($tempPasswordShown['password']); ?></code>
  </div>
<?php endif; ?>
<?php if ($flash !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <?php repsDashStatusPill($lead['status']); ?>
  <span class="badge text-bg-secondary"><?php echo htmlspecialchars($kindLabels[$lead['join_kind']] ?? $lead['join_kind']); ?></span>
  <span class="badge text-bg-light border"><?php echo htmlspecialchars($lead['assign_source']); ?></span>
  <span class="small text-muted">Submitted <?php echo htmlspecialchars($lead['created_at']); ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-3">Contact</h2>
      <dl class="row mb-0 small">
        <dt class="col-4">Phone</dt><dd class="col-8"><?php echo htmlspecialchars($lead['phone']); ?></dd>
        <dt class="col-4">Email</dt><dd class="col-8"><?php echo htmlspecialchars($lead['email']); ?></dd>
        <dt class="col-4">Metro</dt><dd class="col-8"><?php echo htmlspecialchars($lead['metro'] !== '' ? $lead['metro'] : '—'); ?></dd>
        <dt class="col-4">Path</dt><dd class="col-8"><?php echo htmlspecialchars($pathLabels[$lead['path']] ?? $lead['path']); ?></dd>
        <dt class="col-4">Assigned</dt>
        <dd class="col-8"><?php echo htmlspecialchars((string) ($lead['assigned_sales_rep'] ?? '— unassigned')); ?></dd>
        <?php if (!empty($lead['graduated_user_id'])): ?>
          <dt class="col-4">User seat</dt>
          <dd class="col-8">#<?php echo (int) $lead['graduated_user_id']; ?> (graduated)</dd>
        <?php endif; ?>
      </dl>
      <?php if ($role === 'sales' && ($lead['status'] === 'open' || ($lead['assigned_sales_rep'] ?? null) === null)): ?>
        <form method="post" class="mt-3">
          <?php echo repsDashCsrfField(); ?>
          <input type="hidden" name="action" value="claim">
          <input type="hidden" name="notes" value="<?php echo htmlspecialchars($lead['notes']); ?>">
          <button type="submit" class="btn btn-primary btn-sm">Claim for me (@<?php echo htmlspecialchars((string) $user['username']); ?>)</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-3">Log touch</h2>
      <form method="post" class="d-grid gap-2 mb-2">
        <?php echo repsDashCsrfField(); ?>
        <input type="hidden" name="action" value="note">
        <textarea class="form-control" name="event_body" rows="2" maxlength="1000" placeholder="Add a note…"></textarea>
        <button type="submit" class="btn btn-sm btn-outline-primary">Add note</button>
      </form>
      <form method="post" class="d-inline">
        <?php echo repsDashCsrfField(); ?>
        <input type="hidden" name="action" value="called">
        <input type="hidden" name="event_body" value="Called / texted">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Log called / texted</button>
      </form>
    </div>

    <?php if ($canGraduate): ?>
    <div class="surface p-3">
      <h2 class="h5 mb-2">Graduate to Users</h2>
      <p class="small text-muted">Creates a dashboard seat (<?php
        echo htmlspecialchars(repsDashGraduateRoleForJoinKind((string) $lead['join_kind']));
      ?>) and closes this lead. Temp password shown once.</p>
      <form method="post" onsubmit="return confirm('Graduate this lead to a Users seat?');">
        <?php echo repsDashCsrfField(); ?>
        <input type="hidden" name="action" value="graduate">
        <button type="submit" class="btn btn-success btn-sm">Graduate</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-7">
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-3">Pipeline</h2>
      <form method="post" class="d-grid gap-3">
        <?php echo repsDashCsrfField(); ?>
        <input type="hidden" name="action" value="save">
        <div>
          <label class="form-label" for="status">Status</label>
          <select class="form-select" name="status" id="status">
            <?php foreach (['open', 'claimed', 'closed'] as $st): ?>
              <option value="<?php echo $st; ?>"<?php echo $lead['status'] === $st ? ' selected' : ''; ?>>
                <?php echo ucfirst($st); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="assigned_sales_rep">Assigned sales rep</label>
          <?php if ($role === 'sales'): ?>
            <input class="form-control" type="text" name="assigned_sales_rep" id="assigned_sales_rep"
              value="<?php echo htmlspecialchars((string) ($lead['assigned_sales_rep'] ?? $user['username'])); ?>"
              readonly>
            <div class="form-text">Sales can claim for themselves; admin/ops can reassign.</div>
          <?php else: ?>
            <select class="form-select" name="assigned_sales_rep" id="assigned_sales_rep">
              <option value="">— unassigned —</option>
              <?php foreach ($salesPool as $rep): ?>
                <option value="<?php echo htmlspecialchars($rep); ?>"<?php echo ($lead['assigned_sales_rep'] ?? '') === $rep ? ' selected' : ''; ?>>
                  @<?php echo htmlspecialchars($rep); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div>
          <label class="form-label" for="notes">Sticky notes</label>
          <textarea class="form-control" name="notes" id="notes" rows="4" maxlength="4000"><?php echo htmlspecialchars($lead['notes']); ?></textarea>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">Save lead</button>
        </div>
      </form>
    </div>

    <div class="surface p-3">
      <h2 class="h5 mb-3">Activity</h2>
      <?php if ($events === []): ?>
        <p class="small text-muted mb-0">No events yet.</p>
      <?php else: ?>
        <ul class="list-unstyled mb-0">
          <?php foreach ($events as $ev): ?>
            <li class="border-bottom py-2 small">
              <span class="badge text-bg-light border"><?php echo htmlspecialchars((string) $ev['event_type']); ?></span>
              <?php echo htmlspecialchars((string) $ev['body']); ?>
              <div class="text-muted"><?php echo htmlspecialchars((string) $ev['created_at']); ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
