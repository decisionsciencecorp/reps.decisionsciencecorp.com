<?php
declare(strict_types=1);
$page_title = 'Reps — Capture work. Get paid.';
$page_description = 'Reps is Decision Science Corp’s capture network. Record everyday work with a headset, upload accepted sessions, and get paid. Companies enroll their teams under the DSC brand.';
$canonical = 'https://reps.decisionsciencecorp.com/';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="Reps by Decision Science Corp">
  <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/reps.css">
</head>
<body>
  <a class="skip" href="#main">Skip to content</a>

  <header class="top">
    <div class="top-inner">
      <a class="brand" href="/" aria-label="Reps home">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-word">Reps</span>
      </a>
      <nav class="top-nav" aria-label="Primary">
        <a href="#quality">Capture quality</a>
        <a href="#how">How it works</a>
        <a href="#companies">For companies</a>
        <a href="#faq">FAQ</a>
      </nav>
      <a class="btn btn-solid top-cta" href="/join.php">Apply now</a>
    </div>
  </header>

  <main id="main">
    <section class="hero" aria-labelledby="hero-brand">
      <div class="hero-media" aria-hidden="true">
        <video
          class="hero-video"
          autoplay
          muted
          loop
          playsinline
          preload="metadata"
          poster="/assets/video/web/hands-poster.jpg"
        >
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
        <h1 class="hero-line">Record what you do.<br>Earn while you work.</h1>
        <p class="hero-sub">Capture everyday tasks with a headset. You get paid for every <em>accepted</em> upload after quality review — through Decision Science Corp.</p>
        <div class="hero-actions">
          <a class="btn btn-solid" href="/join.php" data-track="hero_cta">Apply in 1 minute</a>
          <a class="btn btn-ghost" href="#quality">See real capture</a>
        </div>
        <div class="rec-frame">
          <span class="rec-dot"></span>
          <span class="rec-label">REC</span>
          <span class="rec-timer" data-rec-timer>00:00:00</span>
        </div>
      </div>
    </section>

    <section class="proof-strip" aria-label="How pay works">
      <div class="proof-strip-inner">
        <p><strong>Accepted uploads</strong> — paid on a regular cycle after review</p>
        <p><strong>Headset after eligibility</strong> — gear ships once you’re cleared</p>
        <p><strong>DSC-operated</strong> — Reps is a Decision Science Corp program</p>
      </div>
    </section>

    <section class="trades" aria-labelledby="trades-title">
      <div class="section-head">
        <h2 id="trades-title">Almost anyone qualifies. No experience needed.</h2>
        <p>From kitchens to construction sites, everyday trades are eligible. Record the work you already do — and get paid for it.</p>
      </div>
      <div class="filmstrip" aria-label="Example capture footage">
        <?php
        $strip = [
          ['src' => 'record-0.mp4', 'poster' => 'record-poster.jpg', 'label' => 'Real work'],
          ['src' => 'camera-0.mp4', 'poster' => 'camera-poster.jpg', 'label' => 'Clear view'],
          ['src' => 'hands-1.mp4', 'poster' => 'hands-poster.jpg', 'label' => 'Hands in frame'],
          ['src' => 'task-0.mp4', 'poster' => 'task-poster.jpg', 'label' => 'Active task'],
          ['src' => 'camera-1.mp4', 'poster' => 'camera-poster.jpg', 'label' => 'Steady capture'],
          ['src' => 'hands-2.mp4', 'poster' => 'hands-poster.jpg', 'label' => 'Two-handed work'],
        ];
        foreach ($strip as $clip): ?>
          <figure class="film-cell">
            <video
              class="film-video"
              muted
              loop
              playsinline
              preload="none"
              poster="/assets/video/web/<?= htmlspecialchars($clip['poster'], ENT_QUOTES, 'UTF-8') ?>"
              data-autoplay-on-view
            >
              <source src="/assets/video/web/<?= htmlspecialchars($clip['src'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
            </video>
            <figcaption><?= htmlspecialchars($clip['label'], ENT_QUOTES, 'UTF-8') ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="marquee" data-marquee>
        <div class="marquee-track">
          <?php
          $trades = [
            'Household & Domestic · Laundry',
            'Restaurant & Kitchen · Plating',
            'Restaurant & Kitchen · Prep',
            'Automotive · Diagnostics',
            'Construction · Material handling',
            'Agriculture · Harvesting',
            'Renovation · Surface prep',
            'Industrial · Welding',
            'Facilities · Cleaning',
            'Crafts · Assembly',
            'Warehouse · Picking',
            'Retail · Stocking',
          ];
          foreach (array_merge($trades, $trades) as $t): ?>
            <span class="marquee-item"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="section-cta">
        <a class="btn btn-solid" href="/join.php">Apply now</a>
      </div>
    </section>

    <section class="quality" id="quality" aria-labelledby="quality-title">
      <div class="section-head">
        <h2 id="quality-title">What good capture looks like.</h2>
        <p>Same coaching we use for operators — clear camera, hands visible, continuous real work.</p>
      </div>
      <div class="quality-grid">
        <figure class="quality-shot">
          <video muted loop playsinline preload="none" poster="/assets/video/web/camera-poster.jpg" data-autoplay-on-view>
            <source src="/assets/video/web/camera-0.mp4" type="video/mp4">
          </video>
          <figcaption>
            <strong>Camera quality</strong>
            <span>Bright, sharp, unobstructed view of the work.</span>
          </figcaption>
        </figure>
        <figure class="quality-shot">
          <video muted loop playsinline preload="none" poster="/assets/video/web/hands-poster.jpg" data-autoplay-on-view>
            <source src="/assets/video/web/hands-0.mp4" type="video/mp4">
          </video>
          <figcaption>
            <strong>Hand visibility</strong>
            <span>Both hands in frame as much as possible.</span>
          </figcaption>
        </figure>
        <figure class="quality-shot">
          <video muted loop playsinline preload="none" poster="/assets/video/web/task-poster.jpg" data-autoplay-on-view>
            <source src="/assets/video/web/task-0.mp4" type="video/mp4">
          </video>
          <figcaption>
            <strong>Task quality</strong>
            <span>Continuous, natural work — not idle time.</span>
          </figcaption>
        </figure>
      </div>
    </section>

    <section class="ways" id="ways" aria-labelledby="ways-title">
      <div class="section-head">
        <h2 id="ways-title">Two ways to earn.</h2>
        <p>Both are valid — pick whichever fits your life.</p>
      </div>
      <div class="ways-grid">
        <article class="way">
          <h3>1. Record on the job</h3>
          <p>Work in a trade, kitchen, warehouse, or shop? Your daily professional tasks are exactly what capture buyers want. Earn supplemental income on top of what you already do.</p>
          <a class="text-link" href="/join.php">Apply now</a>
        </article>
        <article class="way">
          <h3>2. Record at home</h3>
          <p>Cooking, cleaning, laundry, repairs — home tasks are just as valuable. No special experience. If it’s part of your routine, it can qualify.</p>
          <a class="text-link" href="/join.php">Apply now</a>
        </article>
      </div>
    </section>

    <section class="how" id="how" aria-labelledby="how-title">
      <div class="section-head">
        <h2 id="how-title">How to join — five simple steps.</h2>
        <p>From sign-up to your first paycheck.</p>
      </div>
      <ol class="steps">
        <li>
          <span class="step-num">01</span>
          <div>
            <h3>Fill out the form</h3>
            <p>Apply in a few minutes — we’ll confirm eligibility and next steps.</p>
          </div>
        </li>
        <li>
          <span class="step-num">02</span>
          <div>
            <h3>Get your headset</h3>
            <p>After eligibility, order the approved recording headset — shipped to your door.</p>
          </div>
        </li>
        <li>
          <span class="step-num">03</span>
          <div>
            <h3>Install the app</h3>
            <p>Set up on Android or iPhone and complete onboarding in minutes.</p>
          </div>
        </li>
        <li>
          <span class="step-num">04</span>
          <div>
            <h3>Start recording</h3>
            <p>Capture everyday tasks at home or at work. The app guides what to film.</p>
          </div>
        </li>
        <li>
          <span class="step-num">05</span>
          <div>
            <h3>Get paid</h3>
            <p>Each upload is quality-reviewed. Accepted sessions pay out on a regular cycle — directly to you. Timing is confirmed in onboarding.</p>
          </div>
        </li>
      </ol>
      <div class="section-cta">
        <a class="btn btn-solid" href="/join.php">Apply now — takes 1 minute</a>
      </div>
    </section>

    <section class="companies" id="companies" aria-labelledby="companies-title">
      <div class="companies-panel">
        <h2 id="companies-title">For companies</h2>
        <p class="companies-lead">Put your workforce on Reps. Decision Science Corp enrolls shops and teams, handles operator onboarding under our brand, and keeps capture flowing to the buyers who pay for real-world work data.</p>
        <ul class="companies-list">
          <li>One partner relationship — DSC runs the affiliate layer</li>
          <li>Your employees capture on the job as Reps operators</li>
          <li>Ops visibility for hours, quality, and team health</li>
        </ul>
        <a class="btn btn-ghost" href="mailto:hello@decisionsciencecorp.com?subject=Reps%20for%20companies" data-track="companies_cta">Talk to DSC about your team</a>
      </div>
    </section>

    <section class="faq" id="faq" aria-labelledby="faq-title">
      <div class="section-head">
        <h2 id="faq-title">Before you apply</h2>
        <p>Quick answers to the questions people ask right before they submit.</p>
      </div>
      <div class="faq-list">
        <details>
          <summary>Is this a job?</summary>
          <p>It’s paid capture work you control. You choose when to record, within quality guidelines. Many people do it alongside an existing job or at home.</p>
        </details>
        <details>
          <summary>When and how do I get paid?</summary>
          <p>You earn for every <strong>accepted</strong> upload after quality review. Payouts run on a regular cycle; exact timing and method are confirmed during onboarding. We don’t quote a fake hourly rate here — acceptance quality drives earnings.</p>
        </details>
        <details>
          <summary>Do I have to buy a headset first?</summary>
          <p>No. Apply first. After eligibility, you’ll order the approved headset and have it shipped. Gear terms are covered in onboarding.</p>
        </details>
        <details>
          <summary>Do I set my own schedule?</summary>
          <p>Yes. Record when the work is happening — on shift or during routine tasks at home.</p>
        </details>
        <details>
          <summary>What am I expected to film?</summary>
          <p>Hands-on, everyday tasks: cooking, cleaning, trades, warehouse work, assembly, and similar real-world activity. The app coaches you on angles and session length.</p>
        </details>
        <details>
          <summary>How is my data protected?</summary>
          <p>Uploads go through secured capture pipelines. Avoid filming faces, screens with personal data, or private documents when possible — the app includes guidance.</p>
        </details>
        <details>
          <summary>Can I stop anytime?</summary>
          <p>Yes. You can pause or leave the program. Return gear per the headset terms if you exit.</p>
        </details>
        <details>
          <summary>What is Reps?</summary>
          <p>Reps is Decision Science Corp’s branded capture program. You record everyday work with a headset and app; accepted uploads are paid. The name came from Athena — short for the reps you put in on the job.</p>
        </details>
      </div>
    </section>

    <section class="apply" id="apply" aria-labelledby="apply-title">
      <div class="apply-panel">
        <h2 id="apply-title">Apply to Reps</h2>
        <p>Takes about a minute. A human from the team will call or text to set expectations before any headset or login.</p>
        <ul class="apply-proof" aria-label="What happens after you apply">
          <li>You apply with real contact info</li>
          <li>We follow up to set expectations</li>
          <li>If it’s a fit, we provision your seat and gear path</li>
        </ul>
        <p style="margin-top:1.25rem">
          <a class="btn btn-solid" href="/join.php" data-track="apply_panel_cta">Continue to application</a>
        </p>
        <p class="apply-alt">Or email <a href="mailto:hello@decisionsciencecorp.com?subject=Reps%20application">hello@decisionsciencecorp.com</a></p>
      </div>
    </section>
  </main>

  <footer class="foot">
    <div class="foot-inner">
      <div class="foot-brand">
        <span class="foot-reps">Reps</span>
        <span class="foot-by">by</span>
        <a class="foot-dsc" href="https://decisionsciencecorp.com/" rel="noopener">
          <img src="/assets/img/dsc-logo-white.svg" alt="Decision Science Corp" width="160" height="28">
        </a>
      </div>
      <p class="foot-copy">© <?= date('Y') ?> Decision Science Corp. Reps is a DSC capture program.</p>
      <p class="foot-copy" style="opacity:0.7;font-size:0.85em"><a href="/join/partner.php">Interested in an affiliate / sales seat?</a></p>
    </div>
  </footer>

  <div class="sticky-apply" data-sticky-apply hidden>
    <a class="btn btn-solid sticky-apply-btn" href="/join.php" data-track="sticky_apply">Apply now</a>
  </div>

  <script src="/assets/js/reps.js" defer></script>
</body>
</html>
