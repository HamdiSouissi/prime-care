<?php

declare(strict_types=1);
?>
<div class="pc-sheet-backdrop" id="install-pwa-sheet" role="dialog" aria-modal="true" aria-labelledby="install-pwa-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="install-pwa-title">Installer Prime Care</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', 22) ?>
      </button>
    </div>

    <div class="flex items-center gap-3 mb-4">
      <img src="/assets/brand/icon-192.png" alt="Prime Care" width="56" height="56">
      <div>
        <div class="text-headline">Prime Care sur votre écran d’accueil</div>
        <div class="text-footnote text-muted">Accès instantané en plein écran, comme une application native.</div>
      </div>
    </div>

    <div class="pc-list-group mb-4">
      <div class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--info">
          <?= icon('share', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Sur iPhone / iPad (Safari)</div>
          <div class="pc-list-subtitle">Touchez le bouton <strong>Partager</strong> en bas de Safari, puis choisissez <strong>« Sur l’écran d’accueil »</strong>.</div>
        </div>
      </div>

      <div class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--brand">
          <?= icon('smartphone', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Sur Android (Chrome)</div>
          <div class="pc-list-subtitle">Touchez le menu <strong>⋮</strong> en haut à droite, puis <strong>« Installer l’application »</strong>.</div>
        </div>
      </div>
    </div>

    <button type="button" class="pc-btn pc-btn--primary pc-btn--block" data-pwa-install-trigger data-sheet-close>
      J’ai compris
    </button>
  </div>
</div>
