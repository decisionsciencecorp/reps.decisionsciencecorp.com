<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/join-handler.php';
repsJoinBootstrap();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = repsJoinHandlePost('partner');
    if (!empty($result['ok'])) {
        header('Location: /join/thanks.php?kind=partner');
        exit;
    }
    $error = $result['error'] ?? 'Could not submit. Try again.';
}

$page_title = 'Partner with Reps — Affiliate seat';
$page_description = 'Apply for a Reps sales / affiliate seat. Someone from ops will follow up — this is not operator capture pay.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="noindex,follow">
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
      <a class="btn btn-ghost top-cta" href="/join.php">Operator apply</a>
    </div>
  </header>
  <main id="main" class="join-shell">
    <section class="apply" aria-labelledby="partner-title">
      <div class="apply-panel">
        <h1 id="partner-title">Affiliate / sales seat</h1>
        <p>This path is for people who want to <strong>book operators and shops</strong> — not for recording sessions yourself. Ops reviews every affiliate application; seats are not auto-created.</p>
        <ol class="apply-proof">
          <li>Tell us who you are and where you recruit</li>
          <li>Ops follows up (call or text)</li>
          <li>If approved, you get a sales login — separate from any operator seat</li>
        </ol>
        <?php if ($error !== ''): ?>
          <p class="apply-alt" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form class="apply-form" method="post" action="/join/partner.php" novalidate>
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
            <span>Territory / metro</span>
            <input type="text" name="metro" maxlength="80" value="<?= htmlspecialchars((string) ($_POST['metro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label class="full">
            <span>Network notes (who you know, shops, intros)</span>
            <textarea name="notes" rows="4" maxlength="2000"><?= htmlspecialchars((string) ($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </label>
          <label class="full" style="flex-direction:row;align-items:flex-start;gap:0.6rem">
            <input type="checkbox" name="expectations_ack" value="1" required <?= !empty($_POST['expectations_ack']) ? 'checked' : '' ?>>
            <span>I understand this is an affiliate/sales seat application (book, not operator pay), and someone will contact me before any login is created.</span>
          </label>
          <input type="text" name="company_website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn btn-solid" type="submit">Request affiliate review</button>
        </form>
        <p class="apply-alt"><a href="/">Back to Reps</a> · Looking to capture instead? <a href="/join.php">Operator apply</a></p>
      </div>
    </section>
  </main>
</body>
</html>
