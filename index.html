<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/api.php';

wabyu_install_error_handlers(false);
wabyu_security_headers();
header('Cache-Control: no-store');

$secrets = wabyu_secrets();
if (!$secrets) {
    http_response_code(503); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>WABYU setup</title>
<link rel="stylesheet" href="<?= wabyu_asset('assets/css/reset.css') ?>"><link rel="stylesheet" href="<?= wabyu_asset('assets/css/variables.css') ?>"><link rel="stylesheet" href="<?= wabyu_asset('assets/css/typography.css') ?>"><link rel="stylesheet" href="<?= wabyu_asset('assets/css/layout.css') ?>"></head>
<body class="setup-page"><main class="setup-card"><h1 class="wordmark">WABYU</h1><p>One quick step is left: create your passwords.</p>
<p class="muted">Double-click <strong>setup.bat</strong> in the project folder (or run <code>php setup.php</code>), then refresh this page.</p></main></body></html>
<?php exit; }

wabyu_session_start();
wabyu_init_storage();
$authed = wabyu_is_authenticated();
$csrf = wabyu_csrf_token();
$base = wabyu_base_url();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#080808">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= wabyu_h($csrf) ?>">
  <title>WABYU</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&amp;family=Sora:wght@300;400;600&amp;display=swap">
  <?php foreach (['reset', 'variables', 'typography', 'layout', 'components', 'animations', 'responsive'] as $css): ?>
  <link rel="stylesheet" href="<?= wabyu_asset("assets/css/$css.css") ?>">
  <?php endforeach; ?>
  <script type="module" src="<?= wabyu_asset('assets/js/app.js') ?>"></script>
</head>
<body data-auth="<?= $authed ? '1' : '0' ?>" data-base="<?= wabyu_h($base) ?>">
  <canvas class="particles" id="particles" aria-hidden="true"></canvas>
  <noscript><p class="noscript">WABYU needs JavaScript to run.</p></noscript>

  <section class="login" id="login-view" aria-labelledby="login-brand" <?= $authed ? 'hidden' : '' ?>>
    <div class="login-panel" id="login-panel">
      <h1 class="login-brand" id="login-brand">WABYU</h1>
      <form id="login-form" novalidate>
        <label class="login-label" for="password">Password</label>
        <div class="password-wrap">
          <input class="field" id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="login-error">
          <button class="btn btn-icon toggle-vis" type="button" id="login-toggle" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
        <p class="field-error" id="login-error" role="alert"></p>
        <button class="btn btn-primary btn-block" type="submit" id="login-submit">Enter</button>
      </form>
    </div>
  </section>

  <section class="intro" id="intro" hidden aria-live="polite">
    <h1 class="intro-brand" id="intro-brand" aria-label="WABYU"></h1>
    <div class="intro-continue" id="intro-continue" hidden>
      <p class="intro-message">Enjoy the Album</p>
      <button class="intro-hint" type="button" id="intro-btn"><em>Click to continue</em></button>
    </div>
  </section>

  <div class="app" id="app" <?= $authed ? '' : 'hidden' ?>><?php if ($authed) { define('WABYU_EMBEDDED', true); include __DIR__ . '/gallery.php'; } ?></div>
</body>
</html>
