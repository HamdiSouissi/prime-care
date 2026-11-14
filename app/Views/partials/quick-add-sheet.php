<?php

declare(strict_types=1);

?>
<div id="quick-add-sheet" class="pc-sheet-backdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="quick-add-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle"></div>
    <div class="pc-sheet__header">
      <h2 id="quick-add-title" class="pc-sheet__title">Ajouter une saisie</h2>
      <button type="button" class="pc-btn pc-btn--icon pc-btn--secondary" data-close-sheet aria-label="Fermer">
        <?= icon('x', '', 20) ?>
      </button>
    </div>

    <div class="pc-quick-grid">
      <a href="/coming-soon/glycemia" class="pc-quick-tile">
        <span class="pc-quick-tile__icon" style="background: var(--c-vitals-soft); color: var(--c-vitals);">
          <?= icon('droplet', '', 22) ?>
        </span>
        <div>
          <div>Glycémie</div>
          <div class="text-caption text-muted">Capillaire</div>
        </div>
      </a>

      <a href="/coming-soon/blood-pressure" class="pc-quick-tile">
        <span class="pc-quick-tile__icon" style="background: var(--c-vitals-soft); color: var(--c-vitals);">
          <?= icon('heart-pulse', '', 22) ?>
        </span>
        <div>
          <div>Tension</div>
          <div class="text-caption text-muted">Sys / Dia</div>
        </div>
      </a>

      <a href="/coming-soon/temperature" class="pc-quick-tile">
        <span class="pc-quick-tile__icon" style="background: var(--c-vitals-soft); color: var(--c-vitals);">
          <?= icon('thermometer', '', 22) ?>
        </span>
        <div>
          <div>Température</div>
          <div class="text-caption text-muted">Corporelle</div>
        </div>
      </a>

      <a href="/coming-soon/medication" class="pc-quick-tile">
        <span class="pc-quick-tile__icon" style="background: var(--c-meds-soft); color: var(--c-meds);">
          <?= icon('pill', '', 22) ?>
        </span>
        <div>
          <div>Médicament</div>
          <div class="text-caption text-muted">Prise / Insuline</div>
        </div>
      </a>

      <a href="/coming-soon/meal" class="pc-quick-tile">
        <span class="pc-quick-tile__icon" style="background: var(--c-meals-soft); color: var(--c-meals);">
          <?= icon('utensils', '', 22) ?>
        </span>
        <div>
          <div>Repas</div>
          <div class="text-caption text-muted">Photo & Glucides</div>
        </div>
      </a>

      <a href="/coming-soon/journal" class="pc-quick-tile">
        <span class="pc-quick-tile__icon" style="background: var(--c-notes-soft); color: var(--c-notes);">
          <?= icon('notebook-pen', '', 22) ?>
        </span>
        <div>
          <div>Note</div>
          <div class="text-caption text-muted">Transmission</div>
        </div>
      </a>
    </div>
  </div>
</div>
