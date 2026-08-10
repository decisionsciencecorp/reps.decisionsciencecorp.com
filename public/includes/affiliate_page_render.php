<?php
declare(strict_types=1);

require_once __DIR__ . '/affiliate_pages.php';

/**
 * Render a public affiliate landing page for a sales slug.
 * Exits after output. Returns false if slug unknown / not sales.
 */
function reps_affiliate_render_page(string $slug): bool
{
    $slug = strtolower(trim($slug));
    if (!reps_affiliate_slug_valid($slug)) {
        return false;
    }

    if (!defined('REPS_DASH_LOADED')) {
        define('REPS_DASH_LOADED', true);
    }
    require_once dirname(__DIR__) . '/dashboard/includes/config.php';
    require_once dirname(__DIR__) . '/dashboard/includes/db.php';
    try {
        repsDashDb();
    } catch (Throwable $e) {
        http_response_code(503);
        echo 'Affiliate page unavailable.';
        exit;
    }

    $user = reps_affiliate_resolve_sales_user($slug);
    if ($user === null) {
        return false;
    }

    $name = trim((string) ($user['display_name'] ?? $slug));
    if ($name === '') {
        $name = $slug;
    }
    $joinUrl = reps_affiliate_join_url($slug);
    $canonical = reps_affiliate_canonical_url($slug);
    $apex = 'https://' . reps_affiliate_apex_host() . '/';
    $pageTitle = 'Reps with ' . $name . ' — Capture work. Get paid.';
    $pageDescription = $name . ' invites you to join Reps by Decision Science Corp. Record everyday work with a headset and get paid for accepted uploads.';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars('Reps · ' . $name, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/reps.css">
</head>
<body class="aff-body">
  <a class="skip" href="#main">Skip to content</a>

  <header class="top">
    <div class="top-inner">
      <a class="brand" href="<?= htmlspecialchars($apex, ENT_QUOTES, 'UTF-8') ?>" aria-label="Reps home">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-word">Reps</span>
      </a>
      <nav class="top-nav" aria-label="Primary">
        <a href="#how">How it works</a>
        <a href="#faq">FAQ</a>
      </nav>
      <a class="btn btn-solid top-cta" href="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>">Apply now</a>
    </div>
  </header>

  <main id="main">
    <section class="hero aff-hero" aria-labelledby="hero-brand">
      <div class="hero-media" aria-hidden="true">
        <video class="hero-video" autoplay muted loop playsinline preload="metadata" poster="/assets/video/web/hands-poster.jpg">
          <source src="/assets/video/web/hands-0.mp4" type="video/mp4">
        </video>
        <div class="hero-media-shade"></div>
      </div>
      <div class="hero-atmosphere" aria-hidden="true">
        <div class="hero-grid"></div>
        <div class="hero-bloom"></div>
      </div>
      <div class="hero-stage">
        <p id="hero-brand" class="hero-brand">Reps</p>
        <p class="aff-byline">Invited by <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <h1 class="hero-line">Record what you do.<br>Earn while you work.</h1>
        <p class="hero-sub"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?> is with Decision Science Corp’s capture network. Apply here and your intro is credited to them — headset path and pay for <em>accepted</em> uploads after review.</p>
        <div class="hero-actions">
          <a class="btn btn-solid" href="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>" data-track="aff_hero_cta">Apply with <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
          <a class="btn btn-ghost" href="#how">How it works</a>
        </div>
      </div>
    </section>

    <section class="proof-strip" aria-label="How pay works">
      <div class="proof-strip-inner">
        <p><strong>Accepted uploads</strong> — paid on a regular cycle after review</p>
        <p><strong>Your affiliate</strong> — <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?> (<code><?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?></code>)</p>
        <p><strong>Not employment</strong> — independent capture under DSC / Reps</p>
      </div>
    </section>

    <section class="how" id="how" aria-labelledby="how-title">
      <div class="section-head">
        <h2 id="how-title">How to join</h2>
        <p>Same path as the main Reps site — credited to <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>.</p>
      </div>
      <ol class="steps">
        <li>
          <span class="step-num">01</span>
          <div>
            <h3>Apply on this page</h3>
            <p>One short form. We follow up before any headset or login.</p>
          </div>
        </li>
        <li>
          <span class="step-num">02</span>
          <div>
            <h3>Get set up</h3>
            <p>After eligibility: headset, app, and onboarding.</p>
          </div>
        </li>
        <li>
          <span class="step-num">03</span>
          <div>
            <h3>Capture and get paid</h3>
            <p>Accepted uploads pay on a regular cycle after quality review.</p>
          </div>
        </li>
      </ol>
      <div class="section-cta">
        <a class="btn btn-solid" href="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>">Apply now — takes 1 minute</a>
      </div>
    </section>

    <section class="faq" id="faq" aria-labelledby="faq-title">
      <div class="section-head">
        <h2 id="faq-title">Before you apply</h2>
        <p>Quick answers about this affiliate page.</p>
      </div>
      <div class="faq-list">
        <details>
          <summary>Why this link?</summary>
          <p>This is <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>’s Reps intro page. Applying from here attributes your application to them — same idea as an affiliate code, without typing one.</p>
        </details>
        <details>
          <summary>Do I need a code?</summary>
          <p>No. This page already carries the affiliate. The join form will show their code filled in.</p>
        </details>
        <details>
          <summary>Is this a job?</summary>
          <p>No. Pay is for accepted capture uploads after quality review, through Decision Science Corp.</p>
        </details>
      </div>
    </section>
  </main>

  <footer class="foot">
    <div class="foot-inner">
      <p><a href="<?= htmlspecialchars($apex, ENT_QUOTES, 'UTF-8') ?>">Reps</a> by Decision Science Corp · Page for <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </footer>
</body>
</html>
    <?php
    exit;
}

/**
 * Front-door dispatch for /a/{slug}/ stubs or ?affiliate= preview.
 */
function reps_affiliate_try_dispatch(?string $forcedSlug = null): bool
{
    $slug = $forcedSlug ?? reps_affiliate_detect_slug();
    if ($slug === null) {
        return false;
    }
    if (!reps_affiliate_render_page($slug)) {
        http_response_code(404);
        echo 'Affiliate page not found.';
        exit;
    }
    return true;
}
