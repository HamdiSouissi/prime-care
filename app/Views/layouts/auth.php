<?php

declare(strict_types=1);

use App\Core\View;

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Connexion';
/** @var string $content */
$content = $content ?? '';
/** @var string $cspNonce */
$cspNonce = $cspNonce ?? View::cspNonce();
?>
<!doctype html>
<html lang="fr" data-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#5E5CE6">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Prime Care">
  <meta name="description" content="Suivi de soins à domicile — by Prime Vision Solutions">
  <meta property="og:title" content="<?= e($pageTitle) ?> — Prime Care">
  <meta property="og:description" content="Suivi de soins à domicile — by Prime Vision Solutions">
  <meta property="og:image" content="/assets/brand/og-image.png">
  <?= csrf_meta() ?>
  <title><?= e($pageTitle) ?> — Prime Care</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" type="image/svg+xml" href="/assets/brand/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/brand/favicon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/brand/apple-touch-icon.png">
  <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/utilities.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
  <?= View::partial('loader') ?>
  <?= View::partial('toast') ?>

  <main class="pc-auth-screen">
    <?= $content ?>

    <footer class="pc-auth-footer">
      <?= View::partial('credit', ['onGradient' => true]) ?>
    </footer>
  </main>

  <script type="module" nonce="<?= e($cspNonce) ?>" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
