<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Shared UI fragments — keep session/operator markup in one place.
 */

function repsDashOperatorLinkHtml(int $id, string $name, string $class = ''): string
{
    $cls = $class !== '' ? ' class="' . htmlspecialchars($class) . '"' : '';
    return '<a' . $cls . ' href="' . htmlspecialchars(repsDashOperatorHref($id)) . '">'
        . htmlspecialchars($name) . '</a>';
}

/**
 * Comma-separated operator name links (Money tables).
 *
 * @param list<array<string, mixed>> $ops
 */
function repsDashOperatorLinksHtml(array $ops, int $limit = 3): string
{
    if ($ops === []) {
        return '0';
    }
    $bits = [];
    foreach (array_slice($ops, 0, $limit) as $op) {
        $bits[] = repsDashOperatorLinkHtml((int) $op['id'], (string) $op['name']);
    }
    $html = implode(', ', $bits);
    $extra = count($ops) - $limit;
    if ($extra > 0) {
        $html .= ' <span class="text-muted">+' . $extra . '</span>';
    }
    return $html;
}

/**
 * Hours-feed / day session table.
 *
 * @param list<array<string, mixed>> $sessions
 * @param array{
 *   variant?: 'inbox'|'day'|'recent',
 *   show_operator?: bool,
 *   show_shop?: bool,
 *   shop_dash?: bool,
 *   empty?: string,
 *   limit?: int|null
 * } $opts
 */
function repsDashRenderSessionTable(array $sessions, array $opts = []): void
{
    $variant = (string) ($opts['variant'] ?? 'inbox');
    $showOperator = (bool) ($opts['show_operator'] ?? false);
    $showShop = (bool) ($opts['show_shop'] ?? false);
    $shopDash = (bool) ($opts['shop_dash'] ?? false);
    $empty = (string) ($opts['empty'] ?? 'No sessions in scope.');
    $limit = $opts['limit'] ?? null;
    if (is_int($limit) && $limit > 0) {
        $sessions = array_slice($sessions, 0, $limit);
    }

    $cols = 1; // when/session
    if ($variant === 'inbox') {
        $cols = 1; // session id
        if ($showOperator) {
            $cols++;
        }
        if ($showShop) {
            $cols++;
        }
        $cols += 5; // status, duration, accepted, reason, completed
    } elseif ($variant === 'day') {
        $cols = 1; // when
        if ($showOperator) {
            $cols++;
        }
        $cols += 4; // duration, accepted, status, reason
    } else { // recent
        $cols = 4;
    }
    ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <?php if ($variant === 'inbox'): ?>
            <th>Session</th>
            <?php if ($showOperator): ?><th>Operator</th><?php endif; ?>
            <?php if ($showShop): ?><th>Shop</th><?php endif; ?>
            <th>Status</th>
            <th>Duration</th>
            <th>Accepted</th>
            <th>Reason</th>
            <th>Completed</th>
          <?php elseif ($variant === 'day'): ?>
            <th>When</th>
            <?php if ($showOperator): ?><th>Operator</th><?php endif; ?>
            <th>Duration</th>
            <th>Accepted</th>
            <th>Status</th>
            <th>Reason</th>
          <?php else: ?>
            <th>When</th>
            <th>Status</th>
            <th>Accepted</th>
            <th>Reason</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if ($sessions === []): ?>
        <tr><td colspan="<?php echo (int) $cols; ?>" class="text-muted p-3"><?php echo htmlspecialchars($empty); ?></td></tr>
      <?php endif; ?>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <?php if ($variant === 'inbox'): ?>
            <td class="small font-monospace"><?php echo htmlspecialchars((string) $s['session_id']); ?></td>
            <?php if ($showOperator): ?>
              <td>
                <?php
                if (!empty($s['operator_id'])) {
                    echo repsDashOperatorLinkHtml((int) $s['operator_id'], (string) $s['operator']);
                } else {
                    echo htmlspecialchars((string) $s['operator']);
                }
                ?>
              </td>
            <?php endif; ?>
            <?php if ($showShop): ?>
              <td><?php echo htmlspecialchars($shopDash ? '—' : (string) $s['shop']); ?></td>
            <?php endif; ?>
            <td><?php repsDashStatusPill((string) $s['status']); ?></td>
            <td><?php echo htmlspecialchars((string) $s['duration_hours']); ?></td>
            <td><?php echo htmlspecialchars((string) $s['accepted_hours']); ?></td>
            <td class="small text-muted"><?php echo htmlspecialchars($s['rejection_reason'] !== '' ? (string) $s['rejection_reason'] : '—'); ?></td>
            <td class="small">
              <?php if (!empty($s['day'])): ?>
                <a href="<?php echo htmlspecialchars(repsDashDayHref((string) $s['day'], !empty($s['operator_id']) ? (int) $s['operator_id'] : null)); ?>">
                  <?php echo htmlspecialchars((string) $s['completed_at']); ?>
                </a>
              <?php else: ?>
                <?php echo htmlspecialchars((string) $s['completed_at']); ?>
              <?php endif; ?>
            </td>
          <?php elseif ($variant === 'day'): ?>
            <td class="small"><?php echo htmlspecialchars(substr((string) $s['completed_at'], 11)); ?></td>
            <?php if ($showOperator): ?>
              <td>
                <?php
                if (!empty($s['operator_id'])) {
                    echo repsDashOperatorLinkHtml((int) $s['operator_id'], (string) $s['operator']);
                } else {
                    echo htmlspecialchars((string) $s['operator']);
                }
                ?>
              </td>
            <?php endif; ?>
            <td><?php echo htmlspecialchars((string) $s['duration_hours']); ?> h</td>
            <td><?php echo htmlspecialchars((string) $s['accepted_hours']); ?> h</td>
            <td><?php repsDashStatusPill((string) $s['status']); ?></td>
            <td class="small text-muted"><?php echo htmlspecialchars($s['rejection_reason'] !== '' ? (string) $s['rejection_reason'] : '—'); ?></td>
          <?php else: ?>
            <td class="small"><?php echo htmlspecialchars((string) $s['completed_at']); ?></td>
            <td><?php repsDashStatusPill((string) $s['status']); ?></td>
            <td><?php echo htmlspecialchars((string) $s['accepted_hours']); ?></td>
            <td class="small text-muted"><?php echo htmlspecialchars($s['rejection_reason'] !== '' ? (string) $s['rejection_reason'] : '—'); ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $operators
 */
function repsDashRenderOperatorRoster(array $operators): void
{
    ?>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Shop</th>
          <th>Status</th>
          <th>Accepted</th>
          <th>Accept rate</th>
          <th>Last session</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($operators === []): ?>
        <tr><td colspan="6" class="text-muted p-3">No operators in scope.</td></tr>
      <?php endif; ?>
      <?php foreach ($operators as $op):
          $st = repsDashOperatorDetailStats((int) $op['id']);
          ?>
        <tr>
          <td>
            <?php echo repsDashOperatorLinkHtml((int) $op['id'], (string) $op['name'], 'fw-semibold text-decoration-none'); ?>
            <div class="small text-muted"><?php echo htmlspecialchars((string) $op['phone']); ?></div>
          </td>
          <td><?php echo htmlspecialchars((string) $op['shop']); ?></td>
          <td>
            <?php repsDashStatusPill((string) $op['status']); ?>
            <?php if (!empty($op['matched'])): ?>
              <span class="badge text-bg-light border ms-1">Matched</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars((string) $st['accepted_hours']); ?> h</td>
          <td><?php echo $st['acceptance_rate'] === null ? '—' : ((int) $st['acceptance_rate'] . '%'); ?></td>
          <td class="small"><?php echo htmlspecialchars((string) $op['last_session']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
    <?php
}
