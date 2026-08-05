<?php
declare(strict_types=1);

$kind = strtolower(trim((string) ($_GET['kind'] ?? 'operator')));
$isPartner = $kind === 'partner' || $kind === 'affiliate';
$page_title = $isPartner ? 'Thanks — affiliate request received' : 'Thanks — application received';
$page_description = 'Someone from the Reps team will contact you. No login yet.';
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
    </div>
  </header>
  <main id="main" class="join-shell">
    <section class="apply" aria-labelledby="thanks-title">
      <div class="apply-panel">
        <h1 id="thanks-title">You’re in the queue</h1>
        <?php if ($isPartner): ?>
          <p>We received your affiliate / sales seat request. Someone from ops will call or text you. There is <strong>no login yet</strong> — seats are provisioned only after that conversation.</p>
        <?php else: ?>
          <p>We received your application. Someone from the team will call or text to set expectations. There is <strong>no login yet</strong> — accounts are created only after that human touch.</p>
        <?php endif; ?>
        <p class="apply-alt"><a href="/">Back to Reps</a></p>
      </div>
    </section>
  </main>
</body>
</html>
