<?php

declare(strict_types=1);

$mealsByDay = is_array($mealsByDay ?? null) ? $mealsByDay : [];
$days = (int) ($days ?? 7);
$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
?>
<!-- En-tête Repas & Filtre de période -->
<div class="flex items-center justify-between gap-2 flex-wrap">
  <div>
    <h2 class="text-title-2">Galerie des repas</h2>
    <p class="text-caption text-muted">Photos Avant/Après, portion consommée &amp; glycémies liées</p>
  </div>

  <div class="flex items-center gap-2">
    <div class="pc-segmented pc-segmented--compact" role="group" aria-label="Période des repas">
      <?php foreach ([7 => '7 j', 14 => '14 j', 30 => '30 j'] as $dVal => $dLabel): ?>
        <a href="/meals?days=<?= $dVal ?>" class="pc-segmented__btn <?= $days === $dVal ? 'is-active' : '' ?>">
          <?= e($dLabel) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (!$isViewer): ?>
      <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-sheet-open="sheet-meal">
        <?= icon('plus', '', 16) ?>
        <span>Repas</span>
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Liste par jour (E11) -->
<?php foreach ($mealsByDay as $dayStr => $meals): ?>
  <section aria-label="Repas du <?= e($dayStr) ?>">
    <div class="pc-section-header">
      <?= e(\App\Services\ScheduleService::utcToLocalParis($dayStr . ' 12:00:00', 'd/m/Y')) ?>
    </div>
    <div class="pc-stack gap-2">
      <?php foreach ($meals as $m): ?>
        <?php
        $portion = (int) $m['portion_eaten'];
        $photos = $m['photos'] ?? [];
        $thumb = $photos[0]['thumb_path'] ?? ($photos[0]['photo_path'] ?? null);
        $linked = $m['linked_glycemia'] ?? ['before' => null, 'after' => null];
        ?>
        <a href="/meals/<?= (int) $m['id'] ?>" class="pc-card pc-meal-card">
          <div class="pc-meal-card__row">
            <?php if ($thumb): ?>
              <div class="pc-meal-card__thumb">
                <img src="/media/meals/<?= e(basename((string) $thumb)) ?>" alt="Photo <?= e((string) $m['type_label']) ?>" loading="lazy">
                <?php if (count($photos) > 1): ?>
                  <span class="pc-meal-card__photo-count"><?= count($photos) ?> photos</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="pc-meal-card__thumb pc-meal-card__thumb--empty">
                <?= icon('utensils', 'text-meals', 22) ?>
              </div>
            <?php endif; ?>

            <div class="pc-meal-card__body">
              <div class="flex items-center justify-between gap-2">
                <span class="text-headline"><?= e((string) $m['type_label']) ?> · <?= e((string) $m['time_local']) ?></span>
                <span class="pc-badge <?= $portion >= 75 ? 'pc-badge--ok' : ($portion >= 50 ? 'pc-badge--warn' : 'pc-badge--danger') ?>">
                  <?= $portion ?> %
                </span>
              </div>

              <p class="text-footnote text-muted mt-1"><?= e((string) $m['description']) ?></p>

              <!-- Barre de quantité 4 segments (§8.2) -->
              <div class="pc-portion-bar mt-2" aria-label="Quantité consommée : <?= $portion ?> %">
                <span class="pc-portion-bar__seg <?= $portion >= 25 ? 'is-filled' : '' ?>"></span>
                <span class="pc-portion-bar__seg <?= $portion >= 50 ? 'is-filled' : '' ?>"></span>
                <span class="pc-portion-bar__seg <?= $portion >= 75 ? 'is-filled' : '' ?>"></span>
                <span class="pc-portion-bar__seg <?= $portion >= 100 ? 'is-filled' : '' ?>"></span>
              </div>

              <!-- Glycémies liées Avant / Après repas -->
              <div class="flex items-center gap-2 flex-wrap mt-2 text-caption text-muted">
                <?php if ($m['carbs_g'] !== null): ?>
                  <span>~<?= (int) $m['carbs_g'] ?> g glucides</span>
                <?php endif; ?>
                <?php if ($linked['before'] !== null): ?>
                  <span>· Avant : <strong class="font-mono"><?= number_format((float) $linked['before']['value'], 2, ',', '') ?> g/L</strong></span>
                <?php endif; ?>
                <?php if ($linked['after'] !== null): ?>
                  <span>· +2h : <strong class="font-mono"><?= number_format((float) $linked['after']['value'], 2, ',', '') ?> g/L</strong></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
