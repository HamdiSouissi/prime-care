<?php

declare(strict_types=1);

$selectedDate = (string) ($selectedDate ?? date('Y-m-d'));
$todayDate = (string) ($todayDate ?? date('Y-m-d'));
$prevDate = (string) ($prevDate ?? date('Y-m-d'));
$nextDate = (string) ($nextDate ?? date('Y-m-d'));
$filter = (string) ($filter ?? 'all');
$feed = is_array($feed ?? null) ? $feed : ['items' => [], 'total' => 0, 'page' => 1, 'has_more' => false];
$unreadHandover = is_array($unreadHandover ?? null) ? $unreadHandover : null;
$shiftSummary = is_array($shiftSummary ?? null) ? $shiftSummary : [];
$careItems = is_array($careItems ?? null) ? $careItems : [];
$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
?>
<!-- 1. Bandeau de transmission de fin de poste non lue (E13) -->
<?php if ($unreadHandover !== null): ?>
  <?php
  $hSummary = is_string($unreadHandover['handover_summary'] ?? null)
      ? (json_decode((string) $unreadHandover['handover_summary'], true) ?: [])
      : [];
  $hTime = \App\Services\ScheduleService::utcToLocalParis((string) $unreadHandover['recorded_at'], 'd/m à H:i');
  ?>
  <section class="pc-handover-banner" aria-label="Transmission de fin de poste non lue">
    <div class="pc-handover-banner__header">
      <div class="flex items-center gap-2">
        <span class="pc-list-icon pc-list-icon--notes">
          <?= icon('notebook-pen', '', 18) ?>
        </span>
        <div>
          <div class="text-subhead font-bold">Transmission non lue · <?= e((string) $unreadHandover['author_first_name']) ?></div>
          <div class="text-caption text-muted">Le <?= e($hTime) ?></div>
        </div>
      </div>
      <span class="pc-badge pc-badge--warn">À lire</span>
    </div>

    <p class="pc-handover-banner__body"><?= e((string) $unreadHandover['content']) ?></p>

    <?php if (!empty($hSummary['summary_text'])): ?>
      <div class="pc-handover-banner__auto">
        <?= icon('activity', '', 15) ?>
        <span><?= e((string) $hSummary['summary_text']) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="/journal/notes/<?= (int) $unreadHandover['id'] ?>/read" class="mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="/journal?date=<?= e($selectedDate) ?>">
      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm" data-mark-read-id="<?= (int) $unreadHandover['id'] ?>">
        <?= icon('check', '', 15) ?>
        <span>Marquer comme lu</span>
      </button>
    </form>
  </section>
<?php endif; ?>

<!-- 2. Soins rapides en 1 tap + bouton Transmission de fin de poste -->
<?php if (!$isViewer): ?>
  <section class="pc-card" aria-labelledby="journal-quick-care-heading">
    <div class="flex items-center justify-between gap-2 mb-2">
      <h2 class="text-headline" id="journal-quick-care-heading">Soins rapides (1 tap) &amp; Relève</h2>
      <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-sheet-open="sheet-note">
        <?= icon('plus', '', 15) ?>
        <span>Note / Relève</span>
      </button>
    </div>

    <div class="pc-care-quick-grid">
      <?php foreach ($careItems as $ci): ?>
        <button
          type="button"
          class="pc-care-quick-btn"
          data-quick-care-id="<?= (int) $ci['id'] ?>"
          data-quick-care-label="<?= e((string) $ci['label']) ?>"
        >
          <?= icon((string) ($ci['icon_name'] ?: 'check-circle'), '', 16) ?>
          <span><?= e((string) $ci['label']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- 3. Sélecteur de jour & Filtres par type (Tout / Mesures / Prises / Repas / Notes / Soins) (E12) -->
<section class="pc-card" aria-label="Navigation et filtres du journal">
  <div class="flex items-center justify-between gap-2 mb-3">
    <a href="/journal?date=<?= e($prevDate) ?>&amp;filter=<?= e($filter) ?>" class="pc-btn pc-btn--ghost pc-btn--icon" aria-label="Jour précédent">
      <?= icon('chevron-left', '', 20) ?>
    </a>

    <div class="text-center">
      <div class="text-headline">
        <?= $selectedDate === $todayDate ? 'Aujourd’hui · ' : '' ?><?= e(\App\Services\ScheduleService::utcToLocalParis($selectedDate . ' 12:00:00', 'd/m/Y')) ?>
      </div>
      <div class="text-caption text-muted"><?= (int) ($feed['total'] ?? 0) ?> événement(s) horodaté(s)</div>
    </div>

    <a href="/journal?date=<?= e($nextDate) ?>&amp;filter=<?= e($filter) ?>" class="pc-btn pc-btn--ghost pc-btn--icon" aria-label="Jour suivant">
      <?= icon('chevron-right', '', 20) ?>
    </a>
  </div>

  <div class="pc-chip-group" role="group" aria-label="Filtrer par type d’événement">
    <?php
    $filters = [
        'all' => 'Tout',
        'measures' => 'Mesures',
        'intakes' => 'Prises',
        'meals' => 'Repas',
        'notes' => 'Notes & Relève',
        'care' => 'Soins',
    ];
    foreach ($filters as $fKey => $fLabel):
    ?>
      <a
        href="/journal?date=<?= e($selectedDate) ?>&amp;filter=<?= e($fKey) ?>"
        class="pc-filter-pill <?= $filter === $fKey ? 'is-active' : '' ?>"
      >
        <?= e($fLabel) ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- 4. Fil chronologique unifié (E12) -->
<section aria-label="Fil chronologique du journal">
  <?php if (empty($feed['items'])): ?>
    <div class="pc-card text-center py-4 text-muted">
      Aucun événement enregistré pour ce filtre à cette date.
    </div>
  <?php else: ?>
    <div class="pc-stack gap-2">
      <?php foreach ($feed['items'] as $item): ?>
        <?php
        $mod = (string) ($item['module'] ?? 'notes');
        ?>
        <article class="pc-card pc-journal-entry pc-journal-entry--<?= e($mod) ?>">
          <div class="flex items-start gap-3">
            <span class="pc-list-icon pc-list-icon--<?= e($mod) ?>">
              <?= icon((string) ($item['icon'] ?? 'activity'), '', 18) ?>
            </span>

            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-bold text-subhead"><?= e((string) $item['time_local']) ?></span>
                  <h3 class="text-headline"><?= e((string) $item['title']) ?></h3>
                </div>
                <span class="pc-badge <?= e((string) $item['badge_class']) ?>">
                  <?= e((string) $item['badge_label']) ?>
                </span>
              </div>

              <?php if (!empty($item['subtitle'])): ?>
                <div class="text-caption text-muted mt-1"><?= e((string) $item['subtitle']) ?></div>
              <?php endif; ?>

              <?php if (!empty($item['body'])): ?>
                <p class="text-subhead mt-1"><?= e((string) $item['body']) ?></p>
              <?php endif; ?>

              <?php if (!empty($item['handover_summary']['summary_text'])): ?>
                <div class="pc-handover-banner__auto mt-2">
                  <?= icon('activity', '', 14) ?>
                  <span><?= e((string) $item['handover_summary']['summary_text']) ?></span>
                </div>
              <?php endif; ?>

              <?php if (!empty($item['photos'])): ?>
                <div class="flex gap-2 mt-2">
                  <?php foreach ($item['photos'] as $ph): ?>
                    <a href="/media/meals/<?= e(basename((string) $ph['photo_path'])) ?>" target="_blank" class="pc-journal-thumb">
                      <img src="/media/meals/<?= e(basename((string) ($ph['thumb_path'] ?: $ph['photo_path']))) ?>" alt="Photo repas" loading="lazy">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="text-caption text-muted mt-1">
                Par <strong><?= e((string) ($item['author_name'] ?? 'Équipe')) ?></strong>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
