<?php

declare(strict_types=1);

$pageTitle = (string) ($pageTitle ?? 'Erreur');
$nonce = csp_nonce();
?>
<!doctype html>
<html lang="fr" data-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle) ?> — Prime Care</title>
  <link rel="icon" type="image/svg+xml" href="/assets/brand/favicon.svg">
  <link rel="stylesheet" href="/assets/css/tokens.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/base.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/components.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/utilities.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/app.css" nonce="<?= e($nonce) ?>">
</head>
<body>
  <main class="pc-shell pc-shell--centered">
    <?= $content ?>
    <div class="mt-6">
      <?= View::partial('partials/credit') ?>
    </div>
  </main>
</body>
</html>
