<?php

declare(strict_types=1);

?>
<div id="install-pwa-sheet" class="pc-sheet-backdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="install-pwa-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle"></div>
    <div class="pc-sheet__header">
      <h2 id="install-pwa-title" class="pc-sheet__title">Installer Prime Care</h2>
      <button type="button" class="pc-btn pc-btn--icon pc-btn--secondary" data-close-sheet aria-label="Fermer">
        <?= icon('x', '', 20) ?>
      </button>
    </div>

    <div class="flex items-center gap-4 mb-4">
      <img src="/assets/brand/icon-192.png" alt="Prime Care" width="56" height="56" style="border-radius: 14px;">
      <div>
        <div class="text-headline">Prime Care sur votre écran d’accueil</div>
        <div class="text-subhead text-muted">Accès instantané en plein écran, comme une application native.</div>
      </div>
    </div>

    <div class="pc-list-group mb-4">
      <div class="pc-list-item">
        <span class="pc-list-icon" style="background: var(--info);">
          <?= icon('share', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">1. Sur iPhone (Safari)</div>
          <div class="pc-list-subtitle">Appuyez sur le bouton « Partager » en bas de l’écran.</div>
        </div>
      </div>

      <div class="pc-list-item">
        <span class="pc-list-icon" style="background: var(--brand);">
          <?= icon('plus', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">2. Sur l’écran d’accueil</div>
          <div class="pc-list-subtitle">Choisissez « Sur l’écran d’accueil » puis « Ajouter ».</div>
        </div>
      </div>
    </div>

    <button type="button" class="pc-btn pc-btn--primary pc-btn--block" data-close-sheet>
      J’ai compris
    </button>
  </div>
</div>
