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
/** @var bool $hideNavbar */
$hideNavbar = (bool) ($hideNavbar ?? false);
/** @var string|null $themeColor */
$themeColor = isset($themeColor) && is_string($themeColor) ? $themeColor : null;
/** @var string $statusBarStyle */
$statusBarStyle = isset($statusBarStyle) && is_string($statusBarStyle) ? $statusBarStyle : 'black-translucent';

$userTheme = (string) (Auth::user()['theme'] ?? 'auto');
?>
<!doctype html>
<html lang="fr" data-theme="<?= e($userTheme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?php if ($themeColor !== null): ?>
    <meta name="theme-color" content="<?= e($themeColor) ?>">
  <?php else: ?>
    <meta name="theme-color" content="#0A1633" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#060B1A" media="(prefers-color-scheme: dark)">
  <?php endif; ?>
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="<?= e($statusBarStyle) ?>">
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
  <link rel="apple-touch-startup-image" href="/assets/brand/splash/splash-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)">
  <link rel="apple-touch-startup-image" href="/assets/brand/splash/splash-1170x2532.png" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)">
  <link rel="apple-touch-startup-image" href="/assets/brand/splash/splash-1179x2556.png" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)">
  <link rel="apple-touch-startup-image" href="/assets/brand/splash/splash-1290x2796.png" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)">
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
      <?php if (View::hasSection('hero_header')): ?>
        <?= View::yieldSection('hero_header') ?>
      <?php elseif (!$hideNavbar): ?>
        <?= View::partial('navbar', [
            'pageTitle' => $pageTitle,
            'backUrl' => $backUrl ?? null,
            'backLabel' => $backLabel ?? 'Précédent',
            'showNavbarLogo' => $showNavbarLogo ?? false,
            'rightActionHtml' => $rightActionHtml ?? null,
        ]) ?>
      <?php endif; ?>

      <main class="pc-main <?= View::hasSection('hero_header') ? 'pc-main--after-hero' : '' ?>">
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

  <script nonce="<?= e($cspNonce) ?>" src="<?= e(asset('vendor/apexcharts.min.js')) ?>"></script>
  <script type="module" nonce="<?= e($cspNonce) ?>" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
