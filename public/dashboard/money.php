<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('money', $user);
$mode = repsDashMoneyModeForRole((string) $user['role']);
if ($mode === 'none') {
    header('Location: /dashboard/');
    exit;
}

$shops = repsDashShopsForUser($user);
$rate = 20.0; // mock $/accepted hour display

// ── Sales: affiliate book Money (operators live here, not on Operators nav) ──
if ($mode === 'affiliate_book') {
    $opsByShop = [];
    foreach (repsDashOperatorsForUser($user) as $op) {
        $sid = (int) $op['shop_id'];
        $opsByShop[$sid][] = $op;
    }

    $bookHours = 0.0;
    $yourEarn = 0.0;
    $shopKeep = 0.0;
    $activeOps = 0;
    $producingShops = 0;
    $shopBlocks = [];

    foreach ($shops as $shop) {
        $hours = (float) $shop['accepted_hours_7d'];
        $gross = $hours * $rate;
        $shopShare = (float) $shop['agreed_shop_split'];
        // Mock: shop keeps agreed split; remaining is partner/affiliate lane.
        // Sales display cut = half of that lane (lorem until #1570 locks real rules).
        $shopPay = $gross * $shopShare;
        $partnerLane = $gross - $shopPay;
        $you = $partnerLane * 0.5;
        $bookHours += $hours;
        $yourEarn += $you;
        $shopKeep += $shopPay;
        if ($hours > 0) {
            $producingShops++;
        }
        $ops = $opsByShop[(int) $shop['id']] ?? [];
        foreach ($ops as $op) {
            if (($op['status'] ?? '') === 'active') {
                $activeOps++;
            }
        }
        $shopBlocks[] = [
            'shop' => $shop,
            'hours' => $hours,
            'gross' => $gross,
            'your_earn' => $you,
            'shop_keep' => $shopPay,
            'ops' => $ops,
        ];
    }

    repsDashRenderHeader('Money', 'money');
    repsDashRenderPageHeader(
        'Money',
        'Your book — earnings and who’s producing (mock). Not a session inbox; not payroll.'
    );
    ?>

<div class="alert alert-secondary border-0 mb-3">
  Affiliate view: shop pipeline economics + operator rollups for shops in your book.
  Individual capture rows and video stay off this seat. Split math is lorem until #1570 / Slice C+.
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Est. your earnings (7d)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($yourEarn, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Book hours (7d)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) round($bookHours, 1)); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Shops producing</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $producingShops; ?><span class="fs-6 text-muted"> / <?php echo count($shops); ?></span></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Active operators</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $activeOps; ?></div>
    </div>
  </div>
</div>

<?php if ($shopBlocks === []): ?>
  <div class="surface p-3 text-muted">No shops in your book yet.</div>
<?php endif; ?>

<?php foreach ($shopBlocks as $block):
    $shop = $block['shop'];
    ?>
  <div class="surface p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1"><?php echo htmlspecialchars($shop['name']); ?></h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php repsDashStatusPill($shop['status']); ?>
          <span class="small text-muted"><?php echo (int) count($block['ops']); ?> operators</span>
        </div>
      </div>
      <div class="text-md-end small">
        <div><span class="text-muted">Hours 7d</span> <strong><?php echo htmlspecialchars((string) $block['hours']); ?></strong></div>
        <div><span class="text-muted">Est. your $</span> <strong>$<?php echo number_format($block['your_earn'], 2); ?></strong></div>
        <div><span class="text-muted">Shop keeps</span> $<?php echo number_format($block['shop_keep'], 2); ?></div>
      </div>
    </div>

    <?php if ($block['ops'] === []): ?>
      <p class="small text-muted mb-0">No operators on this shop yet — onboarding gap.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Operator</th>
              <th>Status</th>
              <th>Accepted 7d</th>
              <th>Rejected 7d</th>
              <th>Last active</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($block['ops'] as $op): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($op['name']); ?></td>
              <td><?php repsDashStatusPill($op['status']); ?></td>
              <td><?php echo htmlspecialchars((string) $op['accepted_7d']); ?></td>
              <td><?php echo htmlspecialchars((string) $op['rejected_7d']); ?></td>
              <td class="small text-muted"><?php echo htmlspecialchars($op['last_session']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

    <?php
    repsDashRenderFooter();
    exit;
}

// ── Admin / ops / owner Money (ledger-style) ──
$rows = [];
$totalDsc = 0.0;
$totalShop = 0.0;
$totalGross = 0.0;
foreach ($shops as $shop) {
    $hours = (float) $shop['accepted_hours_7d'];
    $gross = $hours * $rate;
    $shopShare = (float) $shop['agreed_shop_split'];
    $shopPay = $gross * $shopShare;
    $dscPay = $gross - $shopPay;
    $totalDsc += $dscPay;
    $totalShop += $shopPay;
    $totalGross += $gross;
    $rows[] = [
        'name' => $shop['name'],
        'hours' => $hours,
        'gross' => $gross,
        'dsc' => $dscPay,
        'shop' => $shopPay,
        'lane' => $shopShare <= 0.001 ? 'internal (100% DSC)' : 'affiliate 50/50 display',
    ];
}

$showDsc = $mode === 'dsc_full';
$showLane = $mode === 'dsc_full';
$shopColLabel = $mode === 'owner_shop' ? 'Your pay' : 'Shop share';
$subtitle = match ($mode) {
    'dsc_full' => 'DSC + shop economics (mock $20/hr · last-7d) — not payroll',
    'owner_shop' => 'Your shop’s hours and pay (mock) — not the DSC ledger',
    default => 'Money',
};

repsDashRenderHeader('Money', 'money');
repsDashRenderPageHeader('Money', $subtitle);
?>

<div class="alert alert-secondary">Lorem economics for audit only. Real rules lock to #1570 / Slice C+.</div>

<div class="row g-3 mb-3">
  <?php if ($showDsc): ?>
  <div class="col-md-4">
    <div class="surface p-3">
      <div class="text-muted small">DSC share (sample)</div>
      <div class="fs-4 fw-semibold">$<?php echo number_format($totalDsc, 2); ?></div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-md-4">
    <div class="surface p-3">
      <div class="text-muted small"><?php echo htmlspecialchars($shopColLabel); ?> (sample)</div>
      <div class="fs-4 fw-semibold">$<?php echo number_format($totalShop, 2); ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="surface p-3">
      <div class="text-muted small">Gross (sample)</div>
      <div class="fs-4 fw-semibold">$<?php echo number_format($totalGross, 2); ?></div>
    </div>
  </div>
</div>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Shop</th>
          <?php if ($showLane): ?><th>Lane</th><?php endif; ?>
          <th>Hours</th>
          <th>Gross</th>
          <?php if ($showDsc): ?><th>DSC</th><?php endif; ?>
          <th><?php echo htmlspecialchars($shopColLabel); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="text-muted p-3">No shops in money scope for this seat.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['name']); ?></td>
          <?php if ($showLane): ?>
            <td class="small text-muted"><?php echo htmlspecialchars($r['lane']); ?></td>
          <?php endif; ?>
          <td><?php echo htmlspecialchars((string) $r['hours']); ?></td>
          <td>$<?php echo number_format($r['gross'], 2); ?></td>
          <?php if ($showDsc): ?>
            <td>$<?php echo number_format($r['dsc'], 2); ?></td>
          <?php endif; ?>
          <td>$<?php echo number_format($r['shop'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
