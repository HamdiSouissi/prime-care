<?php

declare(strict_types=1);

$measureType = is_array($measureType ?? null) ? $measureType : [];
$latest = is_array($latest ?? null) ? $latest : null;
$heroEval = is_array($heroEval ?? null) ? $heroEval : null;
$days = (int) ($days ?? 7);
$filterAlert = (string) ($filterAlert ?? 'all');
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 15);
$totalHistory = (int) ($totalHistory ?? 0);
$historyRows = is_array($historyRows ?? null) ? $historyRows : [];
$chartSummaryText = (string) ($chartSummaryText ?? '');
$apexConfigJson = (string) ($apexConfigJson ?? '{}');
$code = (string) ($measureType['code'] ?? 'glycemia');
$isGlycemia = $code === 'glycemia';
$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
$isAdmin = $role === 'admin';

// Compute 5%-stepped gauge class for glycemia 3-zone gauge (zero inline style)
$gaugeStepClass = 'pc-gauge-pos-50';
if ($isGlycemia && $latest !== null) {
    $pct = \App\Services\ThresholdService::computeGaugePercentage((float) $latest['value']);
    $step5 = (int) (round($pct / 5) * 5);
    $step5 = max(5, min(95, $step5));
    $gaugeStepClass = 'pc-gauge-pos-' . $step5;
}
?>
<!-- 1. Bloc héros Bleu nuit (#0A1633) avec dernière valeur XL et jauge 3 zones (§8.2 & §8.3) -->
<section class="pc-card pc-card--hero-navy" aria-label="Dernier relevé de <?= e((string) $measureType['label']) ?>">
  <div class="flex items-center justify-between gap-2 mb-2">
    <div class="flex items-center gap-2 text-white-80 text-subhead font-semibold">
      <?= icon((string) ($measureType['icon_name'] ?: 'activity'), '', 18) ?>
      <span><?= e((string) $measureType['label']) ?> · Dernière valeur</span>
    </div>
    <?php if ($heroEval): ?>
      <span class="pc-badge <?= e((string) $heroEval['badge_class']) ?>">
        <?= e((string) $heroEval['label']) ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($latest): ?>
    <div class="flex items-baseline justify-between gap-3 flex-wrap">
      <div class="pc-hero-metric">
        <span class="pc-hero-metric__val"><?= e((string) $latest['formatted_value']) ?></span>
        <span class="pc-hero-metric__unit"><?= e((string) ($isGlycemia ? ($glycemiaUnit ?? 'g/L') : $measureType['unit'])) ?></span>
      </div>
      <?php if (!$isViewer): ?>
        <button
          type="button"
          class="pc-btn pc-btn--primary pc-btn--sm"
          data-open-measure-sheet="<?= e($code) ?>"
        >
          <?= icon('plus', '', 16) ?>
          <span>Nouveau relevé</span>
        </button>
      <?php endif; ?>
    </div>

    <div class="text-caption text-white-70 mt-1">
      <?= e((string) $latest['context_label']) ?> · <?= e((string) $latest['time_local']) ?>
      · par <?= e((string) ($latest['author_first_name'] ?? 'Équipe')) ?>
    </div>

    <?php if ($isGlycemia): ?>
      <!-- Jauge horizontale 3 zones (Basse < 0,70 | Cible 0,80–1,80 | Haute > 1,80) (§8.2) -->
      <div class="pc-gly-gauge mt-3" role="img" aria-label="Position sur la jauge glycémique : <?= e((string) ($heroEval['label'] ?? 'Normal')) ?>">
        <div class="pc-gly-gauge__track">
          <span class="pc-gly-gauge__zone pc-gly-gauge__zone--low"></span>
          <span class="pc-gly-gauge__zone pc-gly-gauge__zone--ok"></span>
          <span class="pc-gly-gauge__zone pc-gly-gauge__zone--high"></span>
          <span class="pc-gly-gauge__marker <?= e($gaugeStepClass) ?>" aria-hidden="true"></span>
        </div>
        <div class="pc-gly-gauge__labels">
          <span>Hypo (&lt; 0,70)</span>
          <span>Cible (0,80 – 1,80 g/L)</span>
          <span>Hyper (&gt; 1,80)</span>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="py-3 text-white-80">Aucun relevé enregistré pour le moment.</div>
  <?php endif; ?>
</section>

<!-- 2. Courbe d'évolution ApexCharts (7j / 30j / 90j) + résumé textuel accessible (E07) -->
<section class="pc-card" aria-labelledby="vital-chart-heading">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
    <div>
      <h2 class="text-headline" id="vital-chart-heading">Évolution sur <?= $days ?> jours</h2>
      <p class="text-caption text-muted"><?= e($chartSummaryText) ?></p>
    </div>

    <div class="pc-segmented pc-segmented--compact" role="group" aria-label="Période de la courbe">
      <?php foreach ([7 => '7 j', 30 => '30 j', 90 => '90 j'] as $dVal => $dLabel): ?>
        <a
          href="/health/<?= e($code) ?>?days=<?= $dVal ?>&amp;alert=<?= e($filterAlert) ?>"
          class="pc-segmented__btn <?= $days === $dVal ? 'is-active' : '' ?>"
        >
          <?= e($dLabel) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div
    class="pc-chart-box"
    data-pc-chart
    data-chart-config="<?= e($apexConfigJson) ?>"
    role="img"
    aria-label="<?= e($chartSummaryText) ?>"
  ></div>
</section>

<!-- 3. Historique filtrable & paginé avec règle des 30 minutes -->
<section aria-labelledby="vital-history-heading">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
    <div class="pc-section-header mb-0" id="vital-history-heading">
      Historique (<?= $totalHistory ?> relevé<?= $totalHistory > 1 ? 's' : '' ?>)
    </div>

    <div class="pc-chip-group">
      <?php
      $alertFilters = [
          'all' => 'Tous',
          'ok' => 'Normaux',
          'warn' => 'À surveiller',
          'critical' => 'Critiques',
      ];
      foreach ($alertFilters as $aKey => $aLabel):
      ?>
        <a
          href="/health/<?= e($code) ?>?days=<?= $days ?>&amp;alert=<?= e($aKey) ?>"
          class="pc-filter-pill <?= $filterAlert === $aKey ? 'is-active' : '' ?>"
        >
          <?= e($aLabel) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pc-stack gap-2">
    <?php foreach ($historyRows as $hr): ?>
      <?php
      $ev = $hr['eval'];
      $perm = $hr['edit_perm'];
      ?>
      <article class="pc-card">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-mono font-bold text-title-2"><?= e((string) $hr['formatted_value']) ?></span>
              <span class="text-caption text-muted"><?= e((string) ($isGlycemia ? ($glycemiaUnit ?? 'g/L') : $measureType['unit'])) ?></span>
              <span class="pc-badge <?= e((string) $ev['badge_class']) ?>"><?= e((string) $ev['label']) ?></span>
            </div>
            <?php if (!empty($hr['formatted_secondary'])): ?>
              <div class="text-caption text-muted"><?= e((string) $hr['formatted_secondary']) ?></div>
            <?php endif; ?>
            <div class="text-caption text-muted mt-1">
              <?= e((string) $hr['context_label']) ?> · <?= e((string) $hr['time_local']) ?>
              · par <strong><?= e((string) ($hr['author_first_name'] ?? 'Équipe')) ?></strong>
            </div>
            <?php if (!empty($hr['comment'])): ?>
              <p class="text-footnote mt-1"><?= e((string) $hr['comment']) ?></p>
            <?php endif; ?>
            <?php if (!empty($hr['modified_reason'])): ?>
              <p class="text-caption text-warn mt-1">Motif de correction : <?= e((string) $hr['modified_reason']) ?></p>
            <?php endif; ?>
          </div>

          <!-- Actions d'édition (fenêtre 30 min aide-soignante ou admin/référente avec motif) -->
          <?php if (!$isViewer && ($perm['allowed'] ?? false)): ?>
            <details class="pc-inline-details">
              <summary class="pc-btn pc-btn--ghost pc-btn--sm">Corriger</summary>
              <form method="POST" action="/health/measures/<?= (int) $hr['id'] ?>/update" class="pc-inline-edit-form mt-2">
                <?= csrf_field() ?>
                <div class="pc-grid-2">
                  <div class="pc-field">
                    <label class="pc-label">Valeur</label>
                    <input type="text" name="value" value="<?= e((string) $hr['value']) ?>" class="pc-input font-mono" required>
                  </div>
                  <div class="pc-field">
                    <label class="pc-label">Commentaire</label>
                    <input type="text" name="comment" value="<?= e((string) ($hr['comment'] ?? '')) ?>" class="pc-input">
                  </div>
                </div>
                <?php if (!empty($perm['requires_reason'])): ?>
                  <div class="pc-field mt-2">
                    <label class="pc-label">Motif de modification (&gt; 30 min, obligatoire)</label>
                    <input type="text" name="modified_reason" class="pc-input" placeholder="Ex. erreur de retranscription lecteur" required>
                  </div>
                <?php endif; ?>
                <div class="flex gap-2 mt-2">
                  <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">Enregistrer</button>
                </div>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php
  $totalPages = max(1, (int) ceil($totalHistory / $perPage));
  if ($totalPages > 1):
  ?>
    <div class="flex items-center justify-between mt-3">
      <?php if ($page > 1): ?>
        <a href="/health/<?= e($code) ?>?days=<?= $days ?>&amp;alert=<?= e($filterAlert) ?>&amp;page=<?= $page - 1 ?>" class="pc-btn pc-btn--ghost pc-btn--sm">&larr; Précédent</a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
      <span class="text-caption text-muted">Page <?= $page ?> / <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="/health/<?= e($code) ?>?days=<?= $days ?>&amp;alert=<?= e($filterAlert) ?>&amp;page=<?= $page + 1 ?>" class="pc-btn pc-btn--ghost pc-btn--sm">Suivant &rarr;</a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
