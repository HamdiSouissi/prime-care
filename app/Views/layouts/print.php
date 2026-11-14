<?php

declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Rapport médical — Prime Care';
/** @var string $content */
$content = $content ?? '';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
</head>
<body style="background:#FFFFFF; color:#1C1C1E; padding:32px;">
  <header style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E5E5EA; padding-bottom:16px; margin-bottom:24px;">
    <div style="color:#0A1633;">
      <?= brand_svg('logo-complet', '', 180) ?>
    </div>
    <div style="font-size:13px; color:#636366;">Suivi de soins à domicile</div>
  </header>

  <main>
    <?= $content ?>
  </main>

  <footer style="margin-top:32px; padding-top:16px; border-top:1px solid #E5E5EA; font-size:12px; color:#8E8E93; text-align:center;">
    Généré avec Prime Care — <a href="https://primevisionsolutions.fr" target="_blank" rel="noopener">Prime Vision Solutions</a>
  </footer>
</body>
</html>
