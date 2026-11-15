<?php

declare(strict_types=1);

$primaryTypes = is_array($primaryTypes ?? null) ? $primaryTypes : [];
$secondaryTypes = is_array($secondaryTypes ?? null) ? $secondaryTypes : [];
$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
$isAdmin = $role === 'admin';
?>
<!-- En-tête d'actions rapides Santé -->
<div class="flex items-center justify-between gap-2">
  <div>
    <h2 class="text-title-2">Constantes vitales</h2>
    <p class="text-caption text-muted">4 cartes prioritaires + pastilles secondaires · Tap pour courbes &amp; historique</p>
  </div>
  <?php if (!$isViewer): ?>
    <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-open-measure-sheet="glycemia">
      <?= icon('plus', '', 16) ?>
      <span>Mesurer</span>
    </button>
  <?php endif; ?>
</div>

<!-- 1. Grille des 4 constantes prioritaires (Glycémie, Tension, SpO2, Poids) (§8.3) -->
<section aria-label="Constantes prioritaires">
  <div class="pc-module-grid pc-module-grid--2col">
    <?php foreach ($primaryTypes as $mt): ?>
      <?php
      $last = $mt['last_measure'] ?? null;
      $eval = $last['eval'] ?? null;
      ?>
      <a href="/health/<?= e((string) $mt['code']) ?>" class="pc-card pc-vital-card">
        <div class="flex items-center justify-between gap-2">
          <span class="pc-health-card__header text-vitals">
            <?= icon((string) ($mt['icon_name'] ?: 'activity'), '', 17) ?>
            <span><?= e((string) $mt['label']) ?></span>
          </span>
          <?php if ($eval): ?>
            <span class="pc-badge <?= e((string) $eval['badge_class']) ?>">
              <?= e((string) $eval['label']) ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if ($last): ?>
          <div class="my-1">
            <span class="pc-metric-lg"><?= e((string) $last['formatted_value']) ?></span>
            <span class="pc-metric-unit"><?= e((string) $mt['display_unit']) ?></span>
          </div>
          <div class="text-caption text-muted">
            <?= e((string) $last['context_label']) ?> · <?= e((string) $last['time_local']) ?>
          </div>
        <?php else: ?>
          <div class="my-2 text-subhead text-muted">Aucune mesure</div>
          <div class="text-caption text-brand">Enregistrer un relevé &rarr;</div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- 2. Pastilles secondaires (Pouls, Température, Douleur EVA, Hydratation, Bandelette, Mesures custom) (§8.3) -->
<section aria-labelledby="secondary-vitals-heading">
  <div class="pc-section-header" id="secondary-vitals-heading">Autres constantes &amp; Paramètres</div>
  <div class="pc-secondary-pills">
    <?php foreach ($secondaryTypes as $smt): ?>
      <?php
      $sLast = $smt['last_measure'] ?? null;
      $sEval = $sLast['eval'] ?? null;
      ?>
      <a href="/health/<?= e((string) $smt['code']) ?>" class="pc-vital-pill">
        <span class="pc-vital-pill__icon text-vitals">
          <?= icon((string) ($smt['icon_name'] ?: 'activity'), '', 16) ?>
        </span>
        <div class="pc-vital-pill__body">
          <span class="pc-vital-pill__label"><?= e((string) $smt['label']) ?></span>
          <span class="pc-vital-pill__val font-mono">
            <?php if ($sLast): ?>
              <?= e((string) $sLast['formatted_value']) ?> <?= e((string) $smt['display_unit']) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </span>
        </div>
        <?php if ($sEval && $sEval['alert_level'] !== 'ok'): ?>
          <span class="pc-badge <?= e((string) $sEval['badge_class']) ?>"><?= e((string) $sEval['label']) ?></span>
        <?php endif; ?>
        <?= icon('chevron-right', 'text-muted', 15) ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- 3. Accès directs Traitements & Configuration des seuils -->
<section aria-labelledby="health-links-heading">
  <div class="pc-section-header" id="health-links-heading">Traitements &amp; Seuils</div>
  <div class="pc-list-group">
    <a href="/treatments" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meds">
        <?= icon('pill', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Traitements du jour &amp; Grille d’insuline</div>
        <div class="pc-list-subtitle">Valider une prise, consulter la grille de correction et la rotation des sites</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/treatments/history" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meds">
        <?= icon('activity', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Historique des prises &amp; Observance 7j / 30j</div>
        <div class="pc-list-subtitle">Suivi complet des prises données et non prises avec motifs</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <?php if ($isAdmin): ?>
      <a href="/admin/measures" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--vitals">
          <?= icon('sliders', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Configurer les types de mesures &amp; seuils</div>
          <div class="pc-list-subtitle">Seuils critiques, mesures personnalisées, horaires attendus</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    <?php endif; ?>
  </div>
</section>
