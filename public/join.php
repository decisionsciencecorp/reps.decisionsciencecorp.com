<?php
declare(strict_types=1);

require_once __DIR__ . '/join-handler.php';
repsJoinBootstrap();

$prefRep = strtolower(trim((string) ($_GET['rep'] ?? '')));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = repsJoinHandlePost('operator');
    if (!empty($result['ok'])) {
        header('Location: /join/thanks.php?kind=operator');
        exit;
    }
    $error = $result['error'] ?? 'Could not submit. Try again.';
    $prefRep = strtolower(trim((string) ($_POST['affiliate_code'] ?? $prefRep)));
}

$page_title = 'Join Reps — Apply';
$page_description = 'Apply to capture with Reps. A human will follow up to set expectations before any account or headset.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/reps.css">
</head>
<body>
  <header class="top">
    <div class="top-inner">
      <a class="brand" href="/" aria-label="Reps home">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-word">Reps</span>
      </a>
      <a class="btn btn-solid top-cta" href="/join.php">Apply</a>
    </div>
  </header>
  <main id="main" class="join-shell">
    <section class="apply" aria-labelledby="join-title">
      <div class="apply-panel">
        <h1 id="join-title">Join Reps</h1>
        <p>Not a job offer. Accepted uploads pay after quality review. Someone from the team will contact you before any headset or login.</p>
        <ol class="apply-proof">
          <li>You apply with real contact info</li>
          <li>We call or text to set expectations</li>
          <li>If it’s a fit, we provision your seat and gear path</li>
        </ol>
        <?php if ($error !== ''): ?>
          <p class="apply-alt" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form class="apply-form" method="post" action="/join.php<?= $prefRep !== '' ? '?rep=' . urlencode($prefRep) : '' ?>" novalidate>
          <?= repsDashCsrfField() ?>
          <label>
            <span>Full name</span>
            <input type="text" name="name" required autocomplete="name" maxlength="120" value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label>
            <span>Phone</span>
            <input type="tel" name="phone" required autocomplete="tel" maxlength="40" value="<?= htmlspecialchars((string) ($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label>
            <span>Email</span>
            <input type="email" name="email" required autocomplete="email" maxlength="160" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label>
            <span>How will you capture?</span>
            <select name="path" required>
              <option value="">Select…</option>
              <?php
              $path = (string) ($_POST['path'] ?? '');
              foreach (['on_job' => 'On the job', 'at_home' => 'At home', 'company' => 'I’m enrolling a company / team'] as $val => $label):
              ?>
                <option value="<?= $val ?>"<?= $path === $val ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span>City / metro</span>
            <input type="text" name="metro" maxlength="80" value="<?= htmlspecialchars((string) ($_POST['metro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label>
            <span>Affiliate code (optional)</span>
            <input type="text" name="affiliate_code" maxlength="40" placeholder="e.g. jim" value="<?= htmlspecialchars($prefRep !== '' ? $prefRep : (string) ($_POST['affiliate_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label class="full">
            <span>Anything we should know? (optional)</span>
            <textarea name="notes" rows="3" maxlength="2000"><?= htmlspecialchars((string) ($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </label>
          <label class="full" style="flex-direction:row;align-items:flex-start;gap:0.6rem">
            <input type="checkbox" name="expectations_ack" value="1" required <?= !empty($_POST['expectations_ack']) ? 'checked' : '' ?>>
            <span>I understand pay is only for <em>accepted</em> uploads after review, this is not employment, and someone will contact me before an account is created.</span>
          </label>
          <input type="text" name="company_website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn btn-solid" type="submit">Submit application</button>
        </form>
        <p class="apply-alt"><a href="/">Back to Reps</a></p>
      </div>
    </section>
  </main>
</body>
</html>
