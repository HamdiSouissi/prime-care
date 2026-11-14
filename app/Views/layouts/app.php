<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Prime Care';
/** @var string $content */
$content = $content ?? '';
/** @var string $cspNonce */
$cspNonce = $cspNonce ?? View::cspNonce();
/** @var bool $wideLayout */
$wideLayout = $wideLayout ?? false;

$userTheme = (string) (Auth::user()['theme'] ?? 'auto');
?>
<!doctype html>
<html lang="fr" data-theme="<?= e($userTheme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#F2F2F7" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
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

  <div class="pc-app-layout">
    <?= View::partial('sidebar', ['activeTab' => $activeTab ?? 'home']) ?>

    <div class="pc-shell <?= $wideLayout ? 'pc-shell--wide' : '' ?>">
      <?= View::partial('navbar', [
          'pageTitle' => $pageTitle,
          'backUrl' => $backUrl ?? null,
          'backLabel' => $backLabel ?? 'Précédent',
          'showNavbarLogo' => $showNavbarLogo ?? false,
          'rightActionHtml' => $rightActionHtml ?? null,
      ]) ?>

      <main class="pc-main">
        <?= $content ?>

        <footer class="pc-page-footer">
          <?= View::partial('credit') ?>
        </footer>
      </main>

      <?= View::partial('tabbar', ['activeTab' => $activeTab ?? 'home']) ?>
    </div>
  </div>

  <?= View::partial('quick-add-sheet') ?>
  <?= View::partial('install-sheet') ?>

  <script type="module" nonce="<?= e($cspNonce) ?>" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
