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
    'affiliate_book' => 'Your book’s hours and shop pay (mock) — DSC take hidden from this seat',
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
