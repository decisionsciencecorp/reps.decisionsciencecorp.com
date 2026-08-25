<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

require_once dirname(__DIR__, 2) . '/includes/affiliate_pages.php';

/**
 * Affiliate landing-page facts for the signed-in sales seat.
 *
 * @return array{
 *   slug: string,
 *   page_url: string,
 *   join_url: string,
 *   affiliate_code: string,
 *   display_name: string,
 *   page_ready: bool
 * }|null
 */
function repsDashAffiliatePageInfo(?array $user): ?array
{
    if (!$user || ($user['role'] ?? '') !== 'sales' || empty($user['is_active'])) {
        return null;
    }
    $slug = strtolower(trim((string) ($user['username'] ?? '')));
    if (!reps_affiliate_slug_valid($slug)) {
        return null;
    }
    $display = trim((string) ($user['display_name'] ?? ''));
    if ($display === '') {
        $display = $slug;
    }
    $stub = dirname(REPS_DASH_ROOT) . '/a/' . $slug . '/index.php';

    return [
        'slug' => $slug,
        'page_url' => reps_affiliate_canonical_url($slug),
        'join_url' => reps_affiliate_join_url($slug),
        'affiliate_code' => $slug,
        'display_name' => $display,
        'page_ready' => is_readable($stub),
    ];
}

/** Sales affiliate: landing page URL, join link, and setup notes. */
function repsDashRenderAffiliatePagePanel(array $user): void
{
    $info = repsDashAffiliatePageInfo($user);
    if ($info === null) {
        return;
    }
    $pageId = 'rd-aff-page-url';
    $joinId = 'rd-aff-join-url';
    $codeId = 'rd-aff-code';
    ?>
<div class="surface p-3 mb-4 border-primary border-opacity-25" id="affiliate-page-panel">
  <h2 class="h5 mb-2">Your affiliate landing page</h2>
  <p class="small text-muted mb-3">
    Send prospects this link. Applications from it are credited to you automatically — no code to type.
    The page headline uses your display name (<strong><?php echo htmlspecialchars($info['display_name']); ?></strong>).
    Ask an admin to update your display name if that should change.
  </p>
  <?php if (!$info['page_ready']): ?>
    <div class="alert alert-warning border-0 py-2 small mb-3">
      Your public page is not published yet. Share the direct apply link below until ops turns the landing page on.
    </div>
  <?php endif; ?>
  <dl class="row small mb-0 g-2">
    <dt class="col-sm-3 col-lg-2">Landing page</dt>
    <dd class="col-sm-9 col-lg-10">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if ($info['page_ready']): ?>
          <a class="text-break" href="<?php echo htmlspecialchars($info['page_url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($info['page_url']); ?></a>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($info['page_url']); ?>" target="_blank" rel="noopener">Open page</a>
        <?php else: ?>
          <span class="text-muted"><?php echo htmlspecialchars($info['page_url']); ?> (pending)</span>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary rd-copy-btn" data-copy-target="<?php echo htmlspecialchars($pageId); ?>">Copy link</button>
      </div>
      <input type="text" class="visually-hidden" id="<?php echo htmlspecialchars($pageId); ?>" value="<?php echo htmlspecialchars($info['page_url']); ?>" readonly tabindex="-1" aria-hidden="true">
    </dd>
    <dt class="col-sm-3 col-lg-2">Direct apply</dt>
    <dd class="col-sm-9 col-lg-10">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="text-break" href="<?php echo htmlspecialchars($info['join_url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($info['join_url']); ?></a>
        <button type="button" class="btn btn-sm btn-outline-secondary rd-copy-btn" data-copy-target="<?php echo htmlspecialchars($joinId); ?>">Copy link</button>
      </div>
      <input type="text" class="visually-hidden" id="<?php echo htmlspecialchars($joinId); ?>" value="<?php echo htmlspecialchars($info['join_url']); ?>" readonly tabindex="-1" aria-hidden="true">
      <div class="text-muted mt-1">Shorter path if you only need the application form with your code filled in.</div>
    </dd>
    <dt class="col-sm-3 col-lg-2">Affiliate code</dt>
    <dd class="col-sm-9 col-lg-10">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <code id="<?php echo htmlspecialchars($codeId); ?>"><?php echo htmlspecialchars($info['affiliate_code']); ?></code>
        <button type="button" class="btn btn-sm btn-outline-secondary rd-copy-btn" data-copy-target="<?php echo htmlspecialchars($codeId); ?>">Copy code</button>
      </div>
      <div class="text-muted mt-1">Used on the main join form when someone applies without your landing page.</div>
    </dd>
  </dl>
</div>
<script>
(function () {
  document.querySelectorAll('#affiliate-page-panel .rd-copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-copy-target');
      var el = id ? document.getElementById(id) : null;
      if (!el) return;
      var text = el.value || el.textContent || '';
      if (!text) return;
      function done(ok) {
        var prev = btn.textContent;
        btn.textContent = ok ? 'Copied' : 'Copy failed';
        setTimeout(function () { btn.textContent = prev; }, 1500);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
        return;
      }
      if (el.tagName === 'INPUT') {
        el.classList.remove('visually-hidden');
        el.select();
        try { done(document.execCommand('copy')); } catch (e) { done(false); }
        el.classList.add('visually-hidden');
      }
    });
  });
})();
</script>
    <?php
}
