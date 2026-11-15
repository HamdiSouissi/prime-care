<?php

declare(strict_types=1);

$program = is_array($program ?? null) ? $program : [];
$observance = is_array($observance ?? null) ? $observance : [];
$rotation = is_array($rotation ?? null) ? $rotation : ['suggested_label' => 'Abdomen gauche', 'last_label' => 'Aucun'];
$recentGlycemias = is_array($recentGlycemias ?? null) ? $recentGlycemias : [];
$flatItems = $program['flat'] ?? [];
$asNeeded = $program['as_needed'] ?? [];
$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
$isAdmin = $role === 'admin';
?>
<!-- 1. Synthèse d'observance & rotation d'insuline -->
<section class="pc-module-grid pc-module-grid--2col" aria-label="Observance et Rotation d’insuline">
  <a href="/treatments/history" class="pc-card flex flex-col justify-between gap-2">
    <div class="flex items-center justify-between">
      <span class="pc-health-card__header text-meds">
        <?= icon('pill', '', 17) ?>
        <span>Observance 7j</span>
      </span>
      <span class="pc-badge pc-badge--ok">
        <?= number_format((float) ($observance['days_7']['rate'] ?? 100), 1, ',', '') ?> %
      </span>
    </div>
    <div class="pc-metric-lg">
      <?= (int) ($observance['days_7']['given'] ?? 0) ?>/<?= (int) ($observance['days_7']['total'] ?? 0) ?>
      <span class="pc-metric-unit">prises</span>
    </div>
    <div class="text-caption text-muted">Voir l’historique 7j / 30j &rsaquo;</div>
  </a>

  <div class="pc-card flex flex-col justify-between gap-2">
    <div class="flex items-center justify-between">
      <span class="pc-health-card__header text-meds">
        <?= icon('syringe', '', 17) ?>
        <span>Rotation insuline</span>
      </span>
      <span class="pc-badge pc-badge--brand">Conseillé</span>
    </div>
    <div>
      <div class="text-headline"><?= e((string) ($rotation['suggested_label'] ?? 'Abdomen gauche')) ?></div>
      <div class="text-caption text-muted">Dernier : <?= e((string) ($rotation['last_label'] ?? 'Aucun')) ?></div>
    </div>
  </div>
</section>

<?php if ($isAdmin): ?>
  <div class="flex justify-end">
    <a href="/admin/treatments" class="pc-btn pc-btn--tinted pc-btn--sm">
      <?= icon('settings', '', 16) ?>
      <span>Gérer le plan de traitement &amp; les ordonnances</span>
    </a>
  </div>
<?php endif; ?>

<!-- 2. Prises programmées du jour (E08) -->
<section aria-labelledby="treatments-today-heading">
  <div class="pc-section-header" id="treatments-today-heading">Prises médicamenteuses du jour</div>
  <div class="pc-stack gap-2">
    <?php foreach ($flatItems as $item): ?>
      <?php
      if (($item['kind'] ?? '') !== 'intake') {
          continue;
      }
      $state = (string) ($item['timeline_state'] ?? 'upcoming');
      ?>
      <article class="pc-today-card pc-today-card--<?= e($state) ?>">
        <div class="pc-today-card__main">
          <span class="pc-list-icon pc-list-icon--meds">
            <?= icon(!empty($item['insulin_kind']) ? 'syringe' : 'pill', '', 18) ?>
          </span>
          <div class="pc-today-card__body">
            <div class="pc-today-card__meta">
              <span class="font-mono font-bold text-subhead"><?= e((string) $item['time']) ?></span>
              <span class="pc-badge <?= e((string) $item['badge_class']) ?>"><?= e((string) $item['status_label']) ?></span>
            </div>
            <h3 class="pc-today-card__title"><?= e((string) $item['title']) ?></h3>
            <p class="pc-today-card__subtitle"><?= e((string) $item['subtitle']) ?></p>
          </div>
        </div>

        <?php if (!$isViewer && $state !== 'done'): ?>
          <div class="pc-today-card__actions">
            <?php if (!empty($item['is_variable_insulin'])): ?>
              <button
                type="button"
                class="pc-btn pc-btn--primary pc-btn--sm"
                data-open-insulin-give="<?= (int) $item['id'] ?>"
                data-insulin-title="<?= e((string) $item['title']) ?>"
              >
                <?= icon('syringe', '', 15) ?>
                <span>Grille &amp; Injecter</span>
              </button>
            <?php else: ?>
              <button
                type="button"
                class="pc-btn pc-btn--primary pc-btn--sm"
                data-quick-give-id="<?= (int) $item['id'] ?>"
              >
                <?= icon('check', '', 15) ?>
                <span>Donné</span>
              </button>
            <?php endif; ?>
            <button
              type="button"
              class="pc-btn pc-btn--ghost pc-btn--sm"
              data-open-skip-id="<?= (int) $item['id'] ?>"
              data-skip-title="<?= e((string) $item['title']) ?>"
            >
              <span>Non pris</span>
            </button>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- 3. Tableau de correction d'insuline rapide (Lecture seule pour l'équipe) -->
<section class="pc-card" aria-labelledby="insulin-table-heading">
  <div class="flex items-center justify-between mb-2">
    <div>
      <h2 class="text-headline" id="insulin-table-heading">Grille d’insuline rapide (Humalog / Novorapid)</h2>
      <p class="text-caption text-muted">Dose selon le tableau du responsable · Glycémie pré-prandiale obligatoire</p>
    </div>
    <span class="pc-badge pc-badge--meds">Protocole</span>
  </div>

  <div class="pc-corr-table">
    <div class="pc-corr-row">
      <span>&lt; 0,70 g/L</span>
      <strong>0 UI · Ne pas injecter (resucrage immédiat)</strong>
    </div>
    <div class="pc-corr-row">
      <span>0,70 – 1,30 g/L</span>
      <strong>4 UI (dose de base)</strong>
    </div>
    <div class="pc-corr-row">
      <span>1,31 – 1,80 g/L</span>
      <strong>6 UI (base 4 UI + 2 UI)</strong>
    </div>
    <div class="pc-corr-row">
      <span>1,81 – 2,50 g/L</span>
      <strong>8 UI (base 4 UI + 4 UI)</strong>
    </div>
    <div class="pc-corr-row">
      <span>&gt; 2,50 g/L</span>
      <strong>10 UI (base 4 UI + 6 UI · contrôler cétonurie)</strong>
    </div>
  </div>
</section>

<!-- 4. Médicaments « Si besoin » -->
<?php if ($asNeeded !== []): ?>
  <section aria-labelledby="treatments-as-needed-heading">
    <div class="pc-section-header" id="treatments-as-needed-heading">Médicaments « Si besoin »</div>
    <div class="pc-stack gap-2">
      <?php foreach ($asNeeded as $an): ?>
        <div class="pc-card flex items-center justify-between gap-3">
          <div>
            <div class="text-headline">
              <?= e((string) $an['medication_name']) ?> · <?= number_format((float) $an['dose'], 0, ',', ' ') ?> <?= e((string) $an['unit']) ?>
            </div>
            <div class="text-caption text-muted">
              Intervalle minimum : <?= (int) ($an['min_interval_h'] ?? 6) ?>h
              <?php if (!empty($an['last_given_local'])): ?>
                · Dernière prise : <?= e((string) $an['last_given_local']) ?> par <?= e((string) $an['last_giver']) ?>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!$isViewer): ?>
            <button type="button" class="pc-btn pc-btn--tinted pc-btn--sm" data-sheet-open="sheet-as-needed">
              Donner
            </button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
