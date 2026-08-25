<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Money page HTML peers only — rates/economics live in economics.php.
 */

/**
 * Stripe Connect bank setup for business_owner (shop) and individual (solo).
 * Lives on dashboard Money — never on public join/signup.
 */
function repsDashRenderConnectPayoutPanel(array $user): void
{
    $target = repsStripePayeeTargetForUser($user);
    if ($target === null) {
        return;
    }
    $payee = repsStripePayeeForUser($user);
    $ready = $payee && (int) ($payee['payouts_enabled'] ?? 0) === 1;
    $pending = $payee && !$ready && trim((string) ($payee['stripe_account_id'] ?? '')) !== '';
    $stripeOk = repsStripeConfigured();
    $who = match ((string) ($target['audience'] ?? '')) {
        'shop' => 'your shop’s capture payouts',
        'affiliate' => 'your affiliate share (and capture, if you also work)',
        default => 'your solo capture payouts',
    };
    ?>
<div class="surface p-3 mb-4 border-primary border-opacity-25">
  <h2 class="h5 mb-2">Payout bank account</h2>
  <p class="small text-muted mb-3">
    Reps pays <?php echo htmlspecialchars($who); ?> through Stripe.
    Add your bank details here (hosted by Stripe). This is not part of signup.
  </p>
  <?php if ($ready): ?>
    <div class="alert alert-success border-0 mb-3 py-2 small">
      <strong>Ready.</strong> Payouts can be sent to your linked account
      (<code><?php echo htmlspecialchars((string) $payee['stripe_account_id']); ?></code>).
    </div>
    <form method="post" action="/dashboard/connect/start.php" class="d-inline">
      <?php echo repsDashCsrfField(); ?>
      <button type="submit" class="btn btn-sm btn-outline-primary">Update payout details</button>
    </form>
  <?php elseif ($pending): ?>
    <div class="alert alert-warning border-0 mb-3 py-2 small">
      Setup started but not finished yet
      <?php if (!empty($payee['onboarding_status'])): ?>
        (status: <?php echo htmlspecialchars((string) $payee['onboarding_status']); ?>)
      <?php endif; ?>.
      Finish the Stripe form to receive transfers.
    </div>
    <form method="post" action="/dashboard/connect/start.php" class="d-inline">
      <?php echo repsDashCsrfField(); ?>
      <button type="submit" class="btn btn-sm btn-primary"<?php echo $stripeOk ? '' : ' disabled'; ?>>Continue payout setup</button>
    </form>
  <?php else: ?>
    <form method="post" action="/dashboard/connect/start.php" class="d-inline">
      <?php echo repsDashCsrfField(); ?>
      <button type="submit" class="btn btn-sm btn-primary"<?php echo $stripeOk ? '' : ' disabled'; ?>>Set up payouts</button>
    </form>
  <?php endif; ?>
  <?php if (!$stripeOk): ?>
    <p class="small text-muted mt-2 mb-0">Stripe is not configured on this environment yet — the button unlocks when DSC turns on test/live keys.</p>
  <?php endif; ?>
</div>
    <?php
}

function repsDashRenderMoneyAdmin(array $user, array $shops, ?string $repFilter = null): void
{
    $rate = repsDashMoneyHourlyRate();
    $opsBy = repsDashMoneyOpsByShopId(repsDashOperatorsForUser($user));
    $repFilter = $repFilter !== null && $repFilter !== '' ? $repFilter : null;

    $totGross = $totDsc = $totShop = $totHours = 0.0;
    $internalGross = $affiliateGross = 0.0;
    $rows = [];
    $byRepAll = [];

    foreach ($shops as $shop) {
        $e = repsDashMoneyShopEconomics($shop, $rate);
        $rep = (string) ($shop['assigned_sales_rep'] ?? 'unassigned');
        if (!isset($byRepAll[$rep])) {
            $byRepAll[$rep] = ['hours' => 0.0, 'dsc' => 0.0, 'shops' => 0];
        }
        $byRepAll[$rep]['hours'] += $e['hours'];
        $byRepAll[$rep]['dsc'] += $e['dsc_pay'];
        $byRepAll[$rep]['shops']++;

        if ($repFilter !== null && $rep !== $repFilter) {
            continue;
        }
        $totGross += $e['gross'];
        $totDsc += $e['dsc_pay'];
        $totShop += $e['shop_pay'];
        $totHours += $e['hours'];
        if ($e['internal']) {
            $internalGross += $e['gross'];
        } else {
            $affiliateGross += $e['gross'];
        }
        $rows[] = ['shop' => $shop, 'e' => $e, 'ops' => count($opsBy[(int) $shop['id']] ?? [])];
    }
    ksort($byRepAll);
    usort($rows, static fn($a, $b) => $b['e']['gross'] <=> $a['e']['gross']);

    $subtitle = 'Portfolio earnings · ' . repsDashMoneyPieCaption();
    if ($repFilter !== null) {
        $subtitle .= ' · filtered to @' . $repFilter;
    }
    $ledger = repsLedgerTotals();
    $coverage = repsSettlementCoverage();
    $batches = repsDisburseListBatches(5);
    $stripeReady = repsStripeConfigured();

    repsDashRenderHeader('Money', 'money');
    repsDashRenderPageHeader('Money', $subtitle);
    ?>
<div class="alert alert-light border mb-3">
  Split on accepted hours: DSC 25% · Affiliate 25% (unassigned affiliate share stays with DSC) · Capture 50% (shop or solo operator).
  Estimates use $<?php echo number_format($rate, 0); ?>/hr when only hours are available. Click a rep to filter the shop list.
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Ledger owed (affiliate+capture)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($ledger['owed_cents'] / 100, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">DSC retained (ledger)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($ledger['retained_cents'] / 100, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Transferred</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($ledger['transferred_cents'] / 100, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Stripe available</div>
      <div class="fs-3 fw-semibold"><?php
        echo $coverage['stripe_available_cents'] === null
            ? ($stripeReady ? '—' : 'keys pending')
            : '$' . number_format($coverage['stripe_available_cents'] / 100, 2);
      ?></div>
    </div>
  </div>
</div>
<?php if ($stripeReady): ?>
<form method="post" class="mb-3">
  <?php echo repsDashCsrfField(); ?>
  <input type="hidden" name="action" value="disburse_batch">
  <button type="submit" class="btn btn-sm btn-primary">Run disbursement batch</button>
  <span class="text-muted small ms-2">Transfers pending lines to ready Connect payees.</span>
</form>
<?php else: ?>
<div class="alert alert-warning border-0 mb-3">
  Stripe API keys not loaded yet (<code>~/.ssh/reps-stripe.pass</code>). Ledger + UI are live; Transfers wait on test keys.
</div>
<?php endif; ?>
<?php if ($batches !== []): ?>
<div class="surface p-3 mb-4">
  <div class="fw-semibold mb-2">Recent disbursement batches</div>
  <ul class="mb-0 small">
    <?php foreach ($batches as $b): ?>
      <li>#<?php echo (int) $b['id']; ?> · <?php echo htmlspecialchars((string) $b['label']); ?> ·
        <?php echo htmlspecialchars((string) $b['status']); ?> ·
        ok <?php echo (int) $b['transferred_count']; ?> /
        skip <?php echo (int) $b['skipped_count']; ?> /
        fail <?php echo (int) $b['failed_count']; ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php if ($repFilter !== null): ?>
  <p class="mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="/dashboard/money.php">Clear rep filter (@<?php echo htmlspecialchars($repFilter); ?>)</a>
  </p>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">DSC net (7d)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($totDsc, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Paid to shops (7d)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($totShop, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Gross book</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($totGross, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) round($totHours, 1)); ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="surface p-3 h-100">
      <h2 class="h6 mb-3">Lane mix<?php echo $repFilter !== null ? ' (filtered)' : ''; ?></h2>
      <div class="d-flex justify-content-between small mb-2"><span>Internal (100% DSC)</span><strong>$<?php echo number_format($internalGross, 2); ?></strong></div>
      <div class="d-flex justify-content-between small mb-0"><span>Affiliate shops (split)</span><strong>$<?php echo number_format($affiliateGross, 2); ?></strong></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="surface p-3 h-100">
      <h2 class="h6 mb-3">By sales seat</h2>
      <?php if ($byRepAll === []): ?>
        <p class="small text-muted mb-0">No shops.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Rep</th><th>Shops</th><th>Hours</th><th>DSC $</th></tr></thead>
            <tbody>
            <?php foreach ($byRepAll as $rep => $agg): ?>
              <tr<?php echo $repFilter === $rep ? ' class="table-active"' : ''; ?>>
                <td>
                  <a href="<?php echo htmlspecialchars(repsDashMoneyRepHref((string) $rep)); ?>">
                    <code><?php echo htmlspecialchars((string) $rep); ?></code>
                  </a>
                </td>
                <td><?php echo (int) $agg['shops']; ?></td>
                <td><?php echo htmlspecialchars((string) round($agg['hours'], 1)); ?></td>
                <td>$<?php echo number_format($agg['dsc'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Shop</th><th>Status</th><th>Rep</th><th>Lane</th><th>Ops</th>
          <th>Hours</th><th>Gross</th><th>DSC</th><th>Shop</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="9" class="text-muted p-3">No shops for this filter.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r):
          $s = $r['shop'];
          $e = $r['e'];
          $shopOps = $opsBy[(int) $s['id']] ?? [];
          $rep = (string) ($s['assigned_sales_rep'] ?? 'unassigned');
          ?>
        <tr>
          <td class="fw-semibold"><a class="text-decoration-none" href="<?php echo htmlspecialchars(repsDashShopHref((int) $s['id'])); ?>"><?php echo htmlspecialchars($s['name']); ?></a></td>
          <td><?php repsDashStatusPill($s['status']); ?></td>
          <td class="small">
            <a href="<?php echo htmlspecialchars(repsDashMoneyRepHref($rep)); ?>"><code><?php echo htmlspecialchars($rep === 'unassigned' ? '—' : $rep); ?></code></a>
          </td>
          <td class="small text-muted"><?php echo $e['internal'] ? 'internal' : 'affiliate split'; ?></td>
          <td class="small">
            <?php
            echo $shopOps === []
                ? (string) (int) $r['ops']
                : repsDashOperatorLinksHtml($shopOps, 3);
            ?>
          </td>
          <td><?php echo htmlspecialchars((string) $e['hours']); ?></td>
          <td>$<?php echo number_format($e['gross'], 2); ?></td>
          <td>$<?php echo number_format($e['dsc_pay'], 2); ?></td>
          <td>$<?php echo number_format($e['shop_pay'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="small text-muted mt-3 mb-0">
  Drill-down: tap an operator name → worker → day → sessions.
  Solo individuals appear here when <code>shop_id = 0</code>; affiliate attribution uses operator <code>assigned_sales_rep</code> (sales Money shows “Individuals you sourced”).
</p>
    <?php
    repsDashRenderFooter();
}

function repsDashRenderMoneyOps(array $user, array $shops): void
{
    $rate = repsDashMoneyHourlyRate();
    $opsBy = repsDashMoneyOpsByShopId(repsDashOperatorsForUser($user));

    $totHours = $rejectDrag = $totGross = 0.0;
    $zeroUpload = 0;
    $hotReject = [];
    $healthy = [];

    foreach ($shops as $shop) {
        $e = repsDashMoneyShopEconomics($shop, $rate);
        $totHours += $e['hours'];
        $totGross += $e['gross'];
        $rr = (float) $shop['reject_rate'];
        // Est. reject impact ≈ rejected fraction of gross at the hourly rate
        $drag = $e['gross'] * $rr;
        $rejectDrag += $drag;
        $active = in_array($shop['status'], ['active', 'signed'], true);
        if ($active && $e['hours'] <= 0) {
            $zeroUpload++;
        }
        $row = ['shop' => $shop, 'e' => $e, 'drag' => $drag, 'ops' => $opsBy[(int) $shop['id']] ?? []];
        if ($rr >= 0.15 || ($active && $e['hours'] <= 0)) {
            $hotReject[] = $row;
        } else {
            $healthy[] = $row;
        }
    }

    repsDashRenderHeader('Money', 'money');
    repsDashRenderPageHeader('Money', 'Hours health and estimated reject impact');
    ?>
<div class="alert alert-light border mb-3">
  Watch production and reject impact across shops — not the full portfolio ledger or affiliate commission detail.
  Estimates use $<?php echo number_format($rate, 0); ?>/hr.
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours (7d)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) round($totHours, 1)); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Gross at stake</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($totGross, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Est. reject drag</div>
      <div class="fs-3 fw-semibold text-danger">$<?php echo number_format($rejectDrag, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Zero-upload (live shops)</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $zeroUpload; ?></div>
    </div>
  </div>
</div>

<div class="surface p-3 mb-3">
  <h2 class="h5 mb-3">Needs attention</h2>
  <?php if ($hotReject === []): ?>
    <p class="small text-muted mb-0">Nothing flagged right now.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Shop</th><th>Status</th><th>Hours</th><th>Reject %</th><th>Drag $</th><th>Why</th></tr></thead>
        <tbody>
        <?php foreach ($hotReject as $r):
            $s = $r['shop'];
            $why = ((float) $s['accepted_hours_7d'] <= 0 && in_array($s['status'], ['active', 'signed'], true))
                ? 'No uploads'
                : 'High reject rate';
            ?>
          <tr>
            <td class="fw-semibold"><a class="text-decoration-none" href="<?php echo htmlspecialchars(repsDashShopHref((int) $s['id'])); ?>"><?php echo htmlspecialchars($s['name']); ?></a></td>
            <td><?php repsDashStatusPill($s['status']); ?></td>
            <td><?php echo htmlspecialchars((string) $r['e']['hours']); ?></td>
            <td><?php echo htmlspecialchars((string) round((float) $s['reject_rate'] * 100)); ?>%</td>
            <td>$<?php echo number_format($r['drag'], 2); ?></td>
            <td class="small text-muted">
              <?php echo htmlspecialchars($why); ?>
              <?php if (($r['ops'] ?? []) !== []): ?>
                · <?php echo repsDashOperatorLinksHtml($r['ops'], 2); ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="surface p-3">
  <h2 class="h5 mb-3">Rest of book</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Shop</th><th>Hours</th><th>Reject %</th><th>Active ops</th></tr></thead>
      <tbody>
      <?php foreach ($healthy as $r):
          $s = $r['shop'];
          ?>
        <tr>
          <td><a class="text-decoration-none" href="<?php echo htmlspecialchars(repsDashShopHref((int) $s['id'])); ?>"><?php echo htmlspecialchars($s['name']); ?></a></td>
          <td><?php echo htmlspecialchars((string) $r['e']['hours']); ?></td>
          <td><?php echo htmlspecialchars((string) round((float) $s['reject_rate'] * 100)); ?>%</td>
          <td>
            <?php
            $activeList = array_values(array_filter($r['ops'], static fn($o) => ($o['status'] ?? '') === 'active'));
            echo repsDashOperatorLinksHtml($activeList, 2);
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($healthy === []): ?>
        <tr><td colspan="4" class="text-muted">No other shops in scope.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
    <?php
    repsDashRenderFooter();
}

function repsDashRenderMoneySales(array $user, array $shops): void
{
    $rate = repsDashMoneyHourlyRate();
    $opsByShop = repsDashMoneyOpsByShopId(repsDashOperatorsForUser($user));
    $individuals = repsDashIndividualsForSalesUser($user);

    $bookHours = $yourEarn = 0.0;
    $activeOps = $producingShops = 0;
    $shopBlocks = [];
    $individualRows = [];

    foreach ($shops as $shop) {
        $e = repsDashMoneyShopEconomics($shop, $rate);
        $you = $e['your_affiliate'];
        $bookHours += $e['hours'];
        $yourEarn += $you;
        if ($e['hours'] > 0) {
            $producingShops++;
        }
        // Shop workers only (exclude any stray shop_id 0 bucket)
        $ops = array_values(array_filter(
            $opsByShop[(int) $shop['id']] ?? [],
            static fn(array $o): bool => !repsDashIsSoloOperator($o)
        ));
        foreach ($ops as $op) {
            if (($op['status'] ?? '') === 'active') {
                $activeOps++;
            }
        }
        $shopBlocks[] = [
            'shop' => $shop,
            'e' => $e,
            'your_earn' => $you,
            'ops' => $ops,
        ];
    }

    foreach ($individuals as $op) {
        $e = repsDashMoneyIndividualEconomics($op, $rate);
        $bookHours += $e['hours'];
        $yourEarn += $e['your_affiliate'];
        if (($op['status'] ?? '') === 'active') {
            $activeOps++;
        }
        $individualRows[] = [
            'op' => $op,
            'e' => $e,
        ];
    }
    $producingIndividuals = count(array_filter(
        $individualRows,
        static fn(array $r): bool => $r['e']['hours'] > 0
    ));

    repsDashRenderHeader('Money', 'money');
    repsDashRenderPageHeader('Money', 'Your book — shops and sourced operators · ' . repsDashMoneyPieCaption());
    ?>
<?php repsDashRenderAffiliatePagePanel($user); ?>
<div class="alert alert-light border mb-3">
  Earnings estimates for shops you own and operators you sourced.
  Your affiliate share is 25% of partner payout on accepted hours; capture stays with the shop or solo operator.
</div>
<?php repsDashRenderConnectPayoutPanel($user); ?>

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
      <div class="text-muted small">Shops / individuals producing</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $producingShops; ?><span class="fs-6 text-muted"> / <?php echo count($shops); ?></span>
        <span class="fs-6 text-muted">·</span>
        <?php echo (int) $producingIndividuals; ?><span class="fs-6 text-muted"> / <?php echo count($individuals); ?></span>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Active producers</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $activeOps; ?></div>
    </div>
  </div>
</div>

<div class="surface p-3 mb-3">
  <h2 class="h5 mb-2">Individuals you sourced</h2>
  <p class="small text-muted mb-3">
    Solo capture seats (no shop). Attribution is <code>assigned_sales_rep</code> on the operator — same edge Shift/Reps will store when affiliates sign people up directly.
  </p>
  <?php if ($individualRows === []): ?>
    <p class="small text-muted mb-0">None yet — when you sign up an individual, they show here (not only under a shop).</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Individual</th>
            <th>Status</th>
            <th>Accepted 7d</th>
            <th>Rejected 7d</th>
            <th>Est. your $</th>
            <th>Last active</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($individualRows as $row):
            $op = $row['op'];
            $e = $row['e'];
            ?>
          <tr>
            <td class="fw-semibold">
              <?php echo repsDashOperatorLinkHtml((int) $op['id'], (string) $op['name']); ?>
              <div class="small text-muted">Individual · no shop</div>
            </td>
            <td><?php repsDashStatusPill((string) $op['status']); ?></td>
            <td><?php echo htmlspecialchars((string) $op['accepted_7d']); ?></td>
            <td><?php echo htmlspecialchars((string) $op['rejected_7d']); ?></td>
            <td>$<?php echo number_format($e['your_affiliate'], 2); ?></td>
            <td class="small text-muted"><?php echo htmlspecialchars((string) $op['last_session']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($shopBlocks === []): ?>
  <div class="surface p-3 text-muted">No shops in your book yet.</div>
<?php else: ?>
  <h2 class="h5 mb-3">Shops in your book</h2>
<?php endif; ?>

<?php foreach ($shopBlocks as $block):
    $shop = $block['shop'];
    $e = $block['e'];
    ?>
  <div class="surface p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1"><a class="text-decoration-none" href="<?php echo htmlspecialchars(repsDashShopHref((int) $shop['id'])); ?>"><?php echo htmlspecialchars($shop['name']); ?></a></h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php repsDashStatusPill($shop['status']); ?>
          <span class="small text-muted"><?php echo (int) count($block['ops']); ?> operators</span>
        </div>
      </div>
      <div class="text-md-end small">
        <div><span class="text-muted">Hours 7d</span> <strong><?php echo htmlspecialchars((string) $e['hours']); ?></strong></div>
        <div><span class="text-muted">Est. your $</span> <strong>$<?php echo number_format($block['your_earn'], 2); ?></strong></div>
        <div><span class="text-muted">Shop keeps</span> $<?php echo number_format($e['shop_pay'], 2); ?></div>
      </div>
    </div>
    <?php if ($block['ops'] === []): ?>
      <p class="small text-muted mb-0">No operators yet — onboarding gap.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr><th>Operator</th><th>Status</th><th>Accepted 7d</th><th>Rejected 7d</th><th>Last active</th></tr>
          </thead>
          <tbody>
          <?php foreach ($block['ops'] as $op): ?>
            <tr>
              <td class="fw-semibold">
                <?php echo repsDashOperatorLinkHtml((int) $op['id'], (string) $op['name']); ?>
              </td>
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
}

function repsDashRenderMoneyOwner(array $user, array $shops): void
{
    $rate = repsDashMoneyHourlyRate();
    $shop = $shops[0] ?? null;
    $ops = repsDashOperatorsForUser($user);

    if ($shop === null) {
        repsDashRenderHeader('My pay', 'money');
        repsDashRenderPageHeader('My pay', 'Your shop’s earnings');
        echo '<div class="surface p-3 text-muted">No shop linked to this seat yet.</div>';
        repsDashRenderFooter();
        return;
    }

    $e = repsDashMoneyShopEconomics($shop, $rate);
    $active = array_values(array_filter($ops, static fn($o) => ($o['status'] ?? '') === 'active'));
    $invited = array_values(array_filter($ops, static fn($o) => ($o['status'] ?? '') === 'invited'));
    usort($active, static fn($a, $b) => (float) $b['accepted_7d'] <=> (float) $a['accepted_7d']);

    $teamHours = 0.0;
    foreach ($ops as $op) {
        $teamHours += (float) $op['accepted_7d'];
    }

    repsDashRenderHeader('My pay', 'money');
    repsDashRenderPageHeader('My pay', $shop['name'] . ' — your shop’s capture share');
    ?>
<div class="alert alert-light border mb-3">
  Hours your team produced and what this shop keeps (50% of partner payout on accepted hours).
  You won’t see other shops or DSC’s internal ledger here.
</div>
<?php repsDashRenderConnectPayoutPanel($user); ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Your shop keeps (7d)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($e['shop_pay'], 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours (7d)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $e['hours']); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Team active</div>
      <div class="fs-3 fw-semibold"><?php echo count($active); ?><span class="fs-6 text-muted"> / <?php echo count($ops); ?></span></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Reject rate</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) round((float) $shop['reject_rate'] * 100)); ?>%</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">This period</h2>
      <dl class="row small mb-0">
        <dt class="col-6">Shop</dt><dd class="col-6"><a href="<?php echo htmlspecialchars(repsDashShopHref((int) $shop['id'])); ?>"><?php echo htmlspecialchars($shop['name']); ?></a></dd>
        <dt class="col-6">Status</dt><dd class="col-6"><?php repsDashStatusPill($shop['status']); ?></dd>
        <dt class="col-6">Gross at rate</dt><dd class="col-6">$<?php echo number_format($e['gross'], 2); ?></dd>
        <dt class="col-6">Your keep</dt><dd class="col-6"><strong>$<?php echo number_format($e['shop_pay'], 2); ?></strong></dd>
        <dt class="col-6">Capture share</dt><dd class="col-6">50% of accepted hours ($10/hr) to this shop</dd>
        <dt class="col-6">Payout</dt><dd class="col-6">Stripe Connect — use <strong>Payout bank account</strong> above</dd>
      </dl>
      <p class="small text-muted mt-3 mb-0">Contact on file: <?php echo htmlspecialchars($shop['contact_name']); ?>
        <?php if ($shop['contact_phone'] !== ''): ?> · <?php echo htmlspecialchars($shop['contact_phone']); ?><?php endif; ?></p>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">Who produced</h2>
      <?php if ($active === [] && $invited === []): ?>
        <p class="small text-muted mb-0">No team members yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Teammate</th><th>Status</th><th>Hours 7d</th><th>Share of team</th></tr></thead>
            <tbody>
            <?php foreach ($active as $op):
                $share = $teamHours > 0 ? round(((float) $op['accepted_7d'] / $teamHours) * 100) : 0;
                ?>
              <tr>
                <td class="fw-semibold">
                  <?php echo repsDashOperatorLinkHtml((int) $op['id'], (string) $op['name']); ?>
                </td>
                <td><?php repsDashStatusPill($op['status']); ?></td>
                <td><?php echo htmlspecialchars((string) $op['accepted_7d']); ?></td>
                <td class="small text-muted"><?php echo (int) $share; ?>%</td>
              </tr>
            <?php endforeach; ?>
            <?php foreach ($invited as $op): ?>
              <tr class="text-muted">
                <td>
                  <?php echo repsDashOperatorLinkHtml((int) $op['id'], (string) $op['name']); ?>
                </td>
                <td><?php repsDashStatusPill($op['status']); ?></td>
                <td>—</td>
                <td class="small">Waiting on first hours</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-primary mt-3" href="/dashboard/operators.php">Manage team</a>
    </div>
  </div>
</div>
    <?php
    repsDashRenderFooter();
}

function repsDashRenderMoneySolo(array $user): void
{
    $rate = repsDashMoneyHourlyRate();
    $opId = (int) ($user['operator_id'] ?? 0);
    $ops = repsDashOperatorsForUser($user);
    $self = null;
    foreach ($ops as $op) {
        if ((int) ($op['id'] ?? 0) === $opId) {
            $self = $op;
            break;
        }
    }
    // Fallback: economics from empty solo operator shape
    if ($self === null) {
        $self = [
            'id' => $opId,
            'name' => (string) ($user['display_name'] ?? 'You'),
            'accepted_7d' => 0.0,
            'status' => 'active',
            'assigned_sales_rep' => '',
        ];
    }
    $e = repsDashMoneyIndividualEconomics($self, $rate);

    repsDashRenderHeader('My pay', 'money');
    repsDashRenderPageHeader('My pay', 'Your capture earnings');
    ?>
<div class="alert alert-light border mb-3">
  When your sessions are accepted, your capture share (50% of partner payout) is owed to you.
  If you also have an affiliate seat, that share lands on the same payout account — link a bank below.
</div>
<?php repsDashRenderConnectPayoutPanel($user); ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Your capture (7d)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format((float) ($e['capture_pay'] ?? $e['shop_pay'] ?? 0), 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours (7d)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) ($e['hours'] ?? 0)); ?></div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Gross at rate (7d)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format((float) ($e['gross'] ?? 0), 2); ?></div>
    </div>
  </div>
</div>
<p class="small text-muted mb-0">
  Shop employees are paid by their shop outside Reps. You are on the solo path — Connect details stay on this page, not on the public join form.
</p>
    <?php
    repsDashRenderFooter();
}
