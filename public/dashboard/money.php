<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('money', $user);
$shops = repsDashShopsForUser($user);
$rate = 20.0; // mock $/accepted hour display

$rows = [];
$totalDsc = 0.0;
$totalShop = 0.0;
foreach ($shops as $shop) {
    $hours = (float) $shop['accepted_hours_7d'];
    $gross = $hours * $rate;
    $shopShare = (float) $shop['agreed_shop_split'];
    $shopPay = $gross * $shopShare;
    $dscPay = $gross - $shopPay;
    $totalDsc += $dscPay;
    $totalShop += $shopPay;
    $rows[] = [
        'name' => $shop['name'],
        'hours' => $hours,
        'gross' => $gross,
        'dsc' => $dscPay,
        'shop' => $shopPay,
        'lane' => $shopShare <= 0.001 ? 'internal (100% DSC)' : 'affiliate 50/50 display',
    ];
}

repsDashRenderHeader('Money', 'money');
repsDashRenderPageHeader('Money', 'Simple period display (mock $20/hr · last-7d hours) — not payroll');
?>

<div class="alert alert-secondary">Lorem economics for audit only. Real rules lock to #1570 / Slice C+.</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="surface p-3">
      <div class="text-muted small">DSC share (sample)</div>
      <div class="fs-4 fw-semibold">$<?php echo number_format($totalDsc, 2); ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="surface p-3">
      <div class="text-muted small">Shop share (sample)</div>
      <div class="fs-4 fw-semibold">$<?php echo number_format($totalShop, 2); ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="surface p-3">
      <div class="text-muted small">Gross (sample)</div>
      <div class="fs-4 fw-semibold">$<?php echo number_format($totalDsc + $totalShop, 2); ?></div>
    </div>
  </div>
</div>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Shop</th><th>Lane</th><th>Hours</th><th>Gross</th><th>DSC</th><th>Shop</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['name']); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars($r['lane']); ?></td>
          <td><?php echo htmlspecialchars((string) $r['hours']); ?></td>
          <td>$<?php echo number_format($r['gross'], 2); ?></td>
          <td>$<?php echo number_format($r['dsc'], 2); ?></td>
          <td>$<?php echo number_format($r['shop'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
