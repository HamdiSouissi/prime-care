<?php

declare(strict_types=1);

if (!can('vitals', 'create')) {
    return;
}
?>
<div class="pc-sheet-backdrop" id="quick-add-sheet" role="dialog" aria-modal="true" aria-labelledby="quick-add-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="quick-add-title">Saisie rapide</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', 22) ?>
      </button>
    </div>

    <div class="pc-quick-grid">
      <a href="/coming-soon/glycemia" class="pc-quick-tile">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--vitals">
          <?= icon('droplet', 22) ?>
        </span>
        <div>
          <div>Glycémie</div>
          <div class="text-caption text-muted">Capillaire</div>
        </div>
      </a>

      <a href="/coming-soon/vitals" class="pc-quick-tile">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--vitals">
          <?= icon('heart-pulse', 22) ?>
        </span>
        <div>
          <div>Tension</div>
          <div class="text-caption text-muted">Syst. / Diast.</div>
        </div>
      </a>

      <a href="/coming-soon/vitals" class="pc-quick-tile">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--vitals">
          <?= icon('thermometer', 22) ?>
        </span>
        <div>
          <div>Température</div>
          <div class="text-caption text-muted">SpO2 & Poids</div>
        </div>
      </a>

      <a href="/coming-soon/medication" class="pc-quick-tile">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--meds">
          <?= icon('pill', 22) ?>
        </span>
        <div>
          <div>Médicament</div>
          <div class="text-caption text-muted">Prise / Insuline</div>
        </div>
      </a>

      <a href="/coming-soon/meal" class="pc-quick-tile">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--meals">
          <?= icon('utensils', 22) ?>
        </span>
        <div>
          <div>Repas</div>
          <div class="text-caption text-muted">Photo & Quantité</div>
        </div>
      </a>

      <a href="/coming-soon/journal" class="pc-quick-tile">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--notes">
          <?= icon('notebook-pen', 22) ?>
        </span>
        <div>
          <div>Note / Soin</div>
          <div class="text-caption text-muted">Transmission</div>
        </div>
      </a>
    </div>
  </div>
</div>
