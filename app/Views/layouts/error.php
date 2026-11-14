<?php

declare(strict_types=1);

use App\Core\View;

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Erreur';
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
  <meta name="theme-color" content="#F2F2F7" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
  <title><?= e($pageTitle) ?> — Prime Care</title>
  <link rel="icon" type="image/svg+xml" href="/assets/brand/favicon.svg">
  <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/utilities.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
  <main class="pc-shell" style="justify-content: center; padding: 24px 20px;">
    <?= $content ?>
    <footer class="mt-6">
      <?= View::partial('credit') ?>
    </footer>
  </main>
  <script type="module" nonce="<?= e($cspNonce) ?>" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
