<?php

declare(strict_types=1);

$qrToken = is_array($activeQrToken ?? ($qrToken ?? null)) ? ($activeQrToken ?? $qrToken) : [];
$qrPayload = (string) ($qrToken['qr_payload'] ?? ($qrPayload ?? ''));
$qrSvg = (string) ($qrSvg ?? '');
?>
<main class="pc-qr-print-sheet">
  <div class="pc-qr-print-toolbar no-print">
    <a href="/settings" class="pc-btn pc-btn--ghost pc-btn--sm">
      <?= icon('chevron-left', '', 16) ?>
      <span>Retour aux réglages</span>
    </a>
    <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-print-trigger>
      <?= icon('printer', '', 16) ?>
      <span>Imprimer cette affiche A4</span>
    </button>
  </div>

  <article class="pc-qr-print-card">
    <div class="pc-qr-print-brand">
      <?= brand_svg('logo-complet', '', 220) ?>
    </div>

    <h1 class="text-title-1 mt-3">Pointage d’équipe à domicile</h1>
    <p class="text-subhead text-muted">
      Domicile de <strong>M. Henri Benali</strong> · 14 avenue de la République, 94000 Créteil
    </p>

    <div class="pc-qr-print-frame">
      <?= $qrSvg ?>
    </div>

    <p class="text-subhead font-semibold">
      1. Ouvrez Prime Care sur votre téléphone &rsaquo; 2. Appuyez sur « Scanner QR domicile »
    </p>

    <div class="pc-qr-print-fallback">
      <div class="text-caption text-muted">Code de secours signé HMAC :</div>
      <div class="font-mono text-caption break-all mt-1"><?= e($qrPayload) ?></div>
    </div>

    <footer class="pc-qr-print-footer text-caption text-muted">
      Jeton : <?= e((string) ($qrToken['label'] ?? 'Entrée domicile')) ?> · Généré par Prime Care — by Prime Vision Solutions
    </footer>
  </article>
</main>
