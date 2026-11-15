<?php

declare(strict_types=1);

$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
$program = is_array($program ?? null) ? $program : [];
$moments = $program['moments'] ?? [];
$counts = $program['counts'] ?? ['total' => 0, 'done' => 0, 'late' => 0, 'due' => 0, 'progress_pct' => 0];
$asNeeded = $program['as_needed'] ?? [];
$selectedDate = (string) ($selectedDate ?? date('Y-m-d'));
$prevDate = (string) ($prevDate ?? date('Y-m-d'));
$nextDate = (string) ($nextDate ?? date('Y-m-d'));
$isToday = (bool) ($isToday ?? true);
?>
<!-- Barre de navigation par jour (E04) -->
<section class="pc-card" aria-label="Sélection du jour">
  <div class="flex items-center justify-between gap-2">
    <a href="/today?date=<?= e($prevDate) ?>" class="pc-btn pc-btn--ghost pc-btn--icon" aria-label="Jour précédent">
      <?= icon('chevron-left', '', 20) ?>
    </a>

    <div class="text-center">
      <div class="text-headline">
        <?= $isToday ? 'Aujourd’hui · ' : '' ?><?= e(\App\Services\ScheduleService::utcToLocalParis($selectedDate . ' 12:00:00', 'd/m/Y')) ?>
      </div>
      <div class="text-caption text-muted">
        <?= (int) $counts['done'] ?> sur <?= (int) $counts['total'] ?> actions réalisées
        <?php if (($counts['late'] ?? 0) > 0): ?>
          · <span class="text-danger font-semibold"><?= (int) $counts['late'] ?> en retard</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex items-center gap-1">
      <?php if (!$isToday): ?>
        <a href="/today" class="pc-btn pc-btn--tinted pc-btn--sm">Aujourd’hui</a>
      <?php endif; ?>
      <a href="/today?date=<?= e($nextDate) ?>" class="pc-btn pc-btn--ghost pc-btn--icon" aria-label="Jour suivant">
        <?= icon('chevron-right', '', 20) ?>
      </a>
    </div>
  </div>

  <div class="mt-3">
    <progress class="pc-progress" value="<?= (int) $counts['progress_pct'] ?>" max="100">
      <?= (int) $counts['progress_pct'] ?> %
    </progress>
  </div>
</section>

<!-- Groupes par moment : Matin, Midi, Soir, Coucher -->
<?php foreach ($moments as $momentKey => $moment): ?>
  <?php
  $items = $moment['items'] ?? [];
  if ($items === []) {
      continue;
  }
  ?>
  <section aria-labelledby="moment-heading-<?= e((string) $momentKey) ?>">
    <div class="flex items-center justify-between mb-2">
      <h2 class="pc-section-header mb-0 flex items-center gap-2" id="moment-heading-<?= e((string) $momentKey) ?>">
        <?= icon((string) ($moment['icon'] ?? 'clock'), 'text-brand', 16) ?>
        <span><?= e((string) $moment['label']) ?></span>
      </h2>
      <span class="text-caption text-muted"><?= count($items) ?> élément(s)</span>
    </div>

    <div class="pc-stack gap-2">
      <?php foreach ($items as $item): ?>
        <?php
        $kind = (string) ($item['kind'] ?? 'intake');
        $state = (string) ($item['timeline_state'] ?? 'upcoming');
        $colorClass = $kind === 'intake' ? 'meds' : ($kind === 'measure_expectation' ? 'vitals' : 'meals');
        $iconName = $kind === 'intake'
            ? (!empty($item['insulin_kind']) ? 'syringe' : 'pill')
            : ($kind === 'measure_expectation' ? 'droplet' : 'utensils');
        ?>
        <article class="pc-today-card pc-today-card--<?= e($state) ?>">
          <div class="pc-today-card__main">
            <span class="pc-list-icon pc-list-icon--<?= e($colorClass) ?>">
              <?= icon($iconName, '', 18) ?>
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
              <?php if ($kind === 'intake'): ?>
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
              <?php elseif ($kind === 'measure_expectation'): ?>
                <button
                  type="button"
                  class="pc-btn pc-btn--tinted pc-btn--sm"
                  data-open-measure-sheet="<?= e((string) $item['type_code']) ?>"
                  data-expectation-id="<?= (int) $item['id'] ?>"
                  data-measure-context="<?= e((string) $item['context']) ?>"
                >
                  <?= icon('droplet', '', 15) ?>
                  <span>Mesurer</span>
                </button>
              <?php elseif ($kind === 'meal_slot'): ?>
                <button
                  type="button"
                  class="pc-btn pc-btn--tinted pc-btn--sm"
                  data-open-meal-sheet="<?= e((string) $item['meal_type']) ?>"
                >
                  <?= icon('utensils', '', 15) ?>
                  <span>Saisir</span>
                </button>
              <?php endif; ?>
            </div>
          <?php elseif ($kind === 'meal_slot' && !empty($item['meal_id'])): ?>
            <div class="pc-today-card__actions">
              <a href="/meals/<?= (int) $item['meal_id'] ?>" class="pc-btn pc-btn--ghost pc-btn--sm">Voir fiche</a>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<!-- Traitements « Si besoin » (ex. Paracétamol 1 g avec intervalle minimum) -->
<?php if ($asNeeded !== []): ?>
  <section aria-labelledby="today-as-needed-heading">
    <h2 class="pc-section-header" id="today-as-needed-heading">Médicaments « Si besoin »</h2>
    <div class="pc-stack gap-2">
      <?php foreach ($asNeeded as $an): ?>
        <div class="pc-card flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="text-headline">
              <?= e((string) $an['medication_name']) ?> · <?= number_format((float) $an['dose'], 0, ',', ' ') ?> <?= e((string) $an['unit']) ?>
            </div>
            <div class="text-caption text-muted">
              Intervalle min. <?= (int) ($an['min_interval_h'] ?? 6) ?>h
              <?php if (!empty($an['last_given_local'])): ?>
                · Dernière prise : <?= e((string) $an['last_given_local']) ?> (<?= e((string) $an['last_giver']) ?>)
              <?php else: ?>
                · Aucune prise récente
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
