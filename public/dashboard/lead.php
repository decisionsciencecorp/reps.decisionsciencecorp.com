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

$flash = '';
$flashErr = '';
$role = (string) $user['role'];
$salesReps = ['jim', 'seven', 'chuck', 'mark'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    repsDashRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'claim' && $role === 'sales') {
        $result = repsDashUpdateApplyLead($id, [
            'status' => 'claimed',
            'assigned_sales_rep' => (string) $user['username'],
            'notes' => (string) ($_POST['notes'] ?? $lead['notes']),
        ]);
    } elseif ($action === 'save') {
        $status = (string) ($_POST['status'] ?? $lead['status']);
        $rep = trim((string) ($_POST['assigned_sales_rep'] ?? ''));
        if ($role === 'sales') {
            // Sales may only assign themselves or leave open/closed on their claim.
            if ($status === 'claimed') {
                $rep = (string) $user['username'];
            }
            if ($status === 'open') {
                $rep = '';
            }
            $existingRep = (string) ($lead['assigned_sales_rep'] ?? '');
            if ($existingRep !== '' && $existingRep !== (string) $user['username'] && $status !== 'open') {
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
    } else {
        $result = ['ok' => false, 'error' => 'Unknown action.'];
    }

    if (!empty($result['ok'])) {
        $flash = 'Lead updated.';
        $lead = repsDashFindApplyLead($id) ?? $lead;
    } else {
        $flashErr = $result['error'] ?? 'Could not update lead.';
    }
}

$pathLabels = [
    'on_job' => 'On the job',
    'at_home' => 'At home',
    'company' => 'Company / team',
];

repsDashRenderHeader($lead['name'], 'leads');
?>
<p class="mb-3">
  <a class="small text-decoration-none" href="/dashboard/leads.php">
    <i class="bi bi-arrow-left"></i> Back to leads
  </a>
</p>
<?php
repsDashRenderPageHeader($lead['name'], 'Inbound apply · path mirrors marketing form fields');
?>

<?php if ($flash !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <?php repsDashStatusPill($lead['status']); ?>
  <span class="small text-muted">Submitted <?php echo htmlspecialchars($lead['created_at']); ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">Contact</h2>
      <dl class="row mb-0 small">
        <dt class="col-4">Phone</dt><dd class="col-8"><?php echo htmlspecialchars($lead['phone']); ?></dd>
        <dt class="col-4">Email</dt><dd class="col-8"><?php echo htmlspecialchars($lead['email']); ?></dd>
        <dt class="col-4">Path</dt><dd class="col-8"><?php echo htmlspecialchars($pathLabels[$lead['path']] ?? $lead['path']); ?></dd>
        <dt class="col-4">Assigned</dt>
        <dd class="col-8"><?php echo htmlspecialchars((string) ($lead['assigned_sales_rep'] ?? '— unassigned')); ?></dd>
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
  </div>
  <div class="col-lg-7">
    <div class="surface p-3">
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
              <?php foreach ($salesReps as $rep): ?>
                <option value="<?php echo htmlspecialchars($rep); ?>"<?php echo ($lead['assigned_sales_rep'] ?? '') === $rep ? ' selected' : ''; ?>>
                  @<?php echo htmlspecialchars($rep); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div>
          <label class="form-label" for="notes">Notes</label>
          <textarea class="form-control" name="notes" id="notes" rows="5" maxlength="4000"><?php echo htmlspecialchars($lead['notes']); ?></textarea>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">Save lead</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
