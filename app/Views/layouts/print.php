<?php

declare(strict_types=1);

use App\Core\View;

$pageTitle = (string) ($pageTitle ?? 'Impression — Prime Care');
$content = (string) ($content ?? '');
$cspNonce = View::cspNonce();
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> — Prime Care</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.min.css')) ?>">
</head>
<body class="pc-print-body">
  <?= $content ?>
  <script nonce="<?= e($cspNonce) ?>">
    document.querySelectorAll('[data-print-trigger]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        window.print();
      });
    });
  </script>
</body>
</html>
