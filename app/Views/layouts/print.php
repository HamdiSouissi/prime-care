<?php

declare(strict_types=1);

$pageTitle = (string) ($pageTitle ?? 'Rapport médical — Prime Care');
$nonce = csp_nonce();
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="/assets/css/tokens.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/base.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/components.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/utilities.css" nonce="<?= e($nonce) ?>">
  <link rel="stylesheet" href="/assets/css/app.css" nonce="<?= e($nonce) ?>">
</head>
<body class="pc-print-body">
  <header class="pc-print-header">
    <div class="text-brand-ink">
      <?= brand_svg('prime-care-logo-complet.svg') ?>
    </div>
    <div class="text-footnote text-muted">Suivi de soins à domicile</div>
  </header>

  <main>
    <?= $content ?>
  </main>

  <footer class="pc-print-footer">
    Document généré par Prime Care — Application créée par Prime Vision Solutions (https://primevisionsolutions.fr)
  </footer>
</body>
</html>
