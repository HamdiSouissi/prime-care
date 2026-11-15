<?php

declare(strict_types=1);

$viewMode = (string) ($viewMode ?? 'week');
$canManage = !empty($canManage);
$team = is_array($team ?? null) ? $team : [];
$templates = is_array($templates ?? null) ? $templates : [];
$absences = is_array($absences ?? null) ? $absences : [];
$weekDays = is_array($weekDays ?? null) ? $weekDays : [];
$monthCells = is_array($monthCells ?? null) ? $monthCells : [];
$mondayDate = (string) ($mondayDate ?? date('Y-m-d'));
$prevWeek = (string) ($prevWeek ?? $mondayDate);
$nextWeek = (string) ($nextWeek ?? $mondayDate);
$monthParam = (string) ($monthParam ?? date('Y-m'));
$monthLabel = (string) ($monthLabel ?? $monthParam);
$prevMonth = (string) ($prevMonth ?? $monthParam);
$nextMonth = (string) ($nextMonth ?? $monthParam);
$uncoveredCount = isset($uncoveredCount) ? (int) $uncoveredCount : 0;
$replacementNeededCount = isset($replacementNeededCount) ? (int) $replacementNeededCount : 0;
?>
<!-- Barre de navigation Semaine / Mois et période -->
<section class="pc-card mb-3" aria-label="Navigation du planning">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div class="pc-segmented" role="tablist" aria-label="Mode d’affichage du planning">
      <a
        href="/planning?view=week&amp;week=<?= e($mondayDate) ?>"
        class="pc-segmented__btn <?= $viewMode === 'week' ? 'is-active' : '' ?>"
        role="tab"
        aria-selected="<?= $viewMode === 'week' ? 'true' : 'false' ?>"
      >
        <?= icon('calendar-days', '', 15) ?>
        <span>Semaine</span>
      </a>
      <a
        href="/planning?view=month&amp;month=<?= e($monthParam) ?>"
        class="pc-segmented__btn <?= $viewMode === 'month' ? 'is-active' : '' ?>"
        role="tab"
        aria-selected="<?= $viewMode === 'month' ? 'true' : 'false' ?>"
      >
        <?= icon('calendar', '', 15) ?>
        <span>Mois</span>
      </a>
    </div>

    <?php if ($viewMode === 'month'): ?>
      <div class="flex items-center gap-2">
        <a href="/planning?view=month&amp;month=<?= e($prevMonth) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Mois précédent">
          <?= icon('chevron-left', '', 16) ?>
        </a>
        <span class="text-subhead font-bold"><?= e($monthLabel) ?></span>
        <a href="/planning?view=month&amp;month=<?= e($nextMonth) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Mois suivant">
          <?= icon('chevron-right', '', 16) ?>
        </a>
      </div>
    <?php else: ?>
      <div class="flex items-center gap-2">
        <a href="/planning?view=week&amp;week=<?= e($prevWeek) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Semaine précédente">
          <?= icon('chevron-left', '', 16) ?>
        </a>
        <span class="text-subhead font-bold">
          Sem. du <?= e(format_date_fr($mondayDate)) ?>
        </span>
        <a href="/planning?view=week&amp;week=<?= e($nextWeek) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Semaine suivante">
          <?= icon('chevron-right', '', 16) ?>
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Légende de l'équipe & statuts -->
  <div class="pc-planning-legend">
    <?php foreach ($team as $cg): ?>
      <?php $cClass = avatar_color_class((int) $cg['id']); ?>
      <span class="pc-planning-legend__item">
        <span class="pc-avatar pc-avatar--xs <?= e($cClass) ?>">
          <?= e(initials((string) $cg['first_name'], (string) $cg['last_name'])) ?>
        </span>
        <span class="text-caption font-medium"><?= e((string) $cg['first_name']) ?></span>
      </span>
    <?php endforeach; ?>
    <span class="pc-badge pc-badge--danger">
      <?= icon('alert-triangle', '', 12) ?>
      <span>Non couvert (<?= $uncoveredCount ?>)</span>
    </span>
    <?php if ($replacementNeededCount > 0): ?>
      <span class="pc-badge pc-badge--warn">
        <?= icon('user-x', '', 12) ?>
        <span>À remplacer (<?= $replacementNeededCount ?>)</span>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($canManage): ?>
    <div class="pc-planning-actions mt-3">
      <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-sheet-open="shift-create-sheet">
        <?= icon('plus', '', 15) ?>
        <span>Ajouter un créneau</span>
      </button>
      <button type="button" class="pc-btn pc-btn--tinted pc-btn--sm" data-sheet-open="shift-duplicate-sheet">
        <?= icon('copy', '', 15) ?>
        <span>Dupliquer la semaine</span>
      </button>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" data-sheet-open="absence-create-sheet">
        <?= icon('user-x', '', 15) ?>
        <span>Absence</span>
      </button>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" data-sheet-open="templates-sheet">
        <?= icon('sliders', '', 15) ?>
        <span>Créneaux types</span>
      </button>
    </div>
  <?php endif; ?>
</section>

<?php if ($viewMode === 'month'): ?>
  <!-- ======================================================================
       VUE MOIS (E16)
       ====================================================================== -->
  <section class="pc-card" aria-label="Calendrier mensuel des gardes">
    <div class="pc-month-grid">
      <?php foreach ($monthCells as $dayInfo): ?>
        <?php $ymd = (string) ($dayInfo['date'] ?? ''); ?>
        <div class="pc-month-cell <?= !empty($dayInfo['is_today']) ? 'pc-month-cell--today' : '' ?>">
          <div class="pc-month-cell__header">
            <span class="pc-month-cell__day"><?= e((string) ($dayInfo['day_number'] ?? '')) ?></span>
            <?php if ($canManage): ?>
              <button
                type="button"
                class="pc-month-cell__add"
                data-open-create-shift-date="<?= e($ymd) ?>"
                aria-label="Ajouter un créneau le <?= e($ymd) ?>"
              >+</button>
            <?php endif; ?>
          </div>
          <div class="pc-month-cell__shifts">
            <?php foreach (($dayInfo['shifts'] ?? []) as $s): ?>
              <?php
              $isUncovered = !empty($s['is_uncovered']);
              $hasAbs = !empty($s['needs_replacement']);
              $uid = isset($s['user_id']) && $s['user_id'] !== null ? (int) $s['user_id'] : 0;
              $pillClass = $isUncovered
                  ? 'pc-shift-pill--uncovered'
                  : ($hasAbs ? 'pc-shift-pill--warn' : 'pc-shift-pill--c' . ((($uid - 1) % 8) + 1));
              ?>
              <div class="pc-shift-pill <?= e($pillClass) ?>">
                <span class="font-mono"><?= e((string) $s['local_start_time']) ?></span>
                <span class="truncate">
                  <?= $isUncovered ? 'Non couvert' : e((string) ($s['first_name'] ?? 'Équipe')) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <!-- ======================================================================
       VUE SEMAINE PAR DÉFAUT (E16)
       ====================================================================== -->
  <div class="pc-stack gap-3">
    <?php foreach ($weekDays as $ymd => $day): ?>
      <?php $dayLabel = trim(($day['day_name'] ?? '') . ' ' . ($day['day_num'] ?? $ymd)); ?>
      <section class="pc-card <?= !empty($day['is_today']) ? 'pc-planning-day--today' : '' ?>" aria-label="Créneaux du <?= e($dayLabel) ?>">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2 flex-wrap">
            <h2 class="text-headline"><?= e($dayLabel) ?></h2>
            <?php if (!empty($day['is_today'])): ?>
              <span class="pc-badge pc-badge--brand">Aujourd’hui</span>
            <?php endif; ?>
            <?php if (!empty($day['holiday_label'])): ?>
              <span class="pc-badge pc-badge--warn"><?= e((string) $day['holiday_label']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($canManage): ?>
            <button
              type="button"
              class="pc-btn pc-btn--ghost pc-btn--sm"
              data-open-create-shift-date="<?= e((string) $ymd) ?>"
            >
              <?= icon('plus', '', 14) ?>
              <span>Créneau</span>
            </button>
          <?php endif; ?>
        </div>

        <?php if (empty($day['shifts'])): ?>
          <div class="pc-empty-inline">
            <span class="text-caption text-muted">Aucun créneau planifié sur cette journée.</span>
          </div>
        <?php else: ?>
          <div class="pc-stack gap-2">
            <?php foreach ($day['shifts'] as $s): ?>
              <?php
              $isUncovered = !empty($s['is_uncovered']);
              $hasAbs = !empty($s['needs_replacement']);
              $uid = isset($s['user_id']) && $s['user_id'] !== null ? (int) $s['user_id'] : 0;
              $avClass = $uid > 0 ? avatar_color_class($uid) : 'pc-avatar--c4';
              $cardMod = $isUncovered
                  ? 'pc-shift-card--uncovered'
                  : ($hasAbs ? 'pc-shift-card--warn' : 'pc-shift-card--covered');
              $spansNextDay = !empty($s['spans_next_day']);
              ?>
              <article class="pc-shift-card <?= e($cardMod) ?>">
                <div class="pc-shift-card__main">
                  <?php if ($isUncovered): ?>
                    <span class="pc-list-icon pc-list-icon--vitals">
                      <?= icon('alert-triangle', '', 18) ?>
                    </span>
                  <?php else: ?>
                    <span class="pc-avatar pc-avatar--sm <?= e($avClass) ?>">
                      <?= e(initials((string) ($s['first_name'] ?? 'A'), (string) ($s['last_name'] ?? 'S'))) ?>
                    </span>
                  <?php endif; ?>

                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-subhead font-bold">
                        <?= $isUncovered
                            ? 'Créneau non couvert'
                            : e(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?>
                      </span>

                      <?php if ($isUncovered): ?>
                        <span class="pc-badge pc-badge--danger">
                          <?= icon('alert-triangle', '', 12) ?>
                          <span>Non couvert</span>
                        </span>
                      <?php elseif ($hasAbs): ?>
                        <span class="pc-badge pc-badge--warn">
                          <?= icon('user-x', '', 12) ?>
                          <span>À remplacer</span>
                        </span>
                      <?php else: ?>
                        <span class="pc-badge pc-badge--ok">
                          <?= icon('check', '', 12) ?>
                          <span>Planifié</span>
                        </span>
                      <?php endif; ?>

                      <?php if (!empty($s['replaced_first_name'])): ?>
                        <span class="pc-badge pc-badge--brand">
                          <?= icon('refresh-cw', '', 12) ?>
                          <span>Remplace <?= e((string) $s['replaced_first_name']) ?></span>
                        </span>
                      <?php endif; ?>
                    </div>

                    <div class="text-caption text-muted mt-1">
                      <strong class="font-mono"><?= e((string) $s['local_start_time']) ?> &rsaquo; <?= e((string) $s['local_end_time']) ?><?= $spansNextDay ? ' (+1j)' : '' ?></strong>
                      · <?= number_format((float) ($s['duration_hours'] ?? 12), 1, ',', '') ?> h
                      <?php if (!empty($s['template_label'])): ?>
                        · <?= e((string) $s['template_label']) ?>
                      <?php endif; ?>
                      <?php if (!empty($s['notes'])): ?>
                        · <em><?= e((string) $s['notes']) ?></em>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <?php if ($canManage): ?>
                  <div class="pc-shift-card__actions">
                    <button
                      type="button"
                      class="pc-btn pc-btn--ghost pc-btn--sm"
                      data-open-edit-shift="<?= (int) $s['id'] ?>"
                      data-shift-user-id="<?= $uid > 0 ? $uid : '' ?>"
                      data-shift-replaced-id="<?= isset($s['replaced_user_id']) && $s['replaced_user_id'] !== null ? (int) $s['replaced_user_id'] : '' ?>"
                      data-shift-template-id="<?= isset($s['template_id']) && $s['template_id'] !== null ? (int) $s['template_id'] : '' ?>"
                      data-shift-start-date="<?= e((string) $s['local_date']) ?>"
                      data-shift-start-time="<?= e((string) $s['local_start_time']) ?>"
                      data-shift-end-date="<?= e((string) ($s['local_end_date'] ?? $s['local_date'])) ?>"
                      data-shift-end-time="<?= e((string) $s['local_end_time']) ?>"
                      data-shift-notes="<?= e((string) ($s['notes'] ?? '')) ?>"
                    >
                      <?= icon('edit-3', '', 14) ?>
                      <span><?= $isUncovered || $hasAbs ? 'Affecter / Remplacer' : 'Modifier' ?></span>
                    </button>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Absences déclarées sur la période -->
<?php if (!empty($absences)): ?>
  <section class="pc-card mt-3" aria-labelledby="absences-heading">
    <h2 class="text-headline mb-2" id="absences-heading">Absences sur la période</h2>
    <div class="pc-stack gap-2">
      <?php foreach ($absences as $abs): ?>
        <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-surface-alt">
          <div>
            <div class="text-subhead font-semibold">
              <?= e(trim(($abs['first_name'] ?? '') . ' ' . ($abs['last_name'] ?? ''))) ?>
              <span class="pc-badge pc-badge--warn"><?= e((string) ($abs['reason'] ?? 'Absence')) ?></span>
            </div>
            <div class="text-caption text-muted">
              Du <?= e((string) ($abs['start_date'] ?? '')) ?> au <?= e((string) ($abs['end_date'] ?? '')) ?>
              <?php if (!empty($abs['notes'])): ?>
                · <?= e((string) $abs['notes']) ?>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($canManage): ?>
            <form method="POST" action="/planning/absences/<?= (int) $abs['id'] ?>/delete">
              <?= csrf_field() ?>
              <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--sm" aria-label="Supprimer l’absence">
                <?= icon('trash-2', '', 14) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($canManage): ?>
  <!-- 1. Bottom-sheet Création d'un créneau -->
  <div class="pc-sheet-backdrop" id="shift-create-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="shift-create-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="shift-create-title">Planifier un créneau de garde</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/planning/shifts" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/planning?view=<?= e($viewMode) ?>&amp;week=<?= e($mondayDate) ?>">

        <div>
          <label class="pc-label" for="create-shift-template">Créneau type rapide</label>
          <select id="create-shift-template" name="template_id" class="pc-input" data-shift-template-select>
            <option value="">Personnalisé</option>
            <?php foreach ($templates as $tpl): ?>
              <option
                value="<?= (int) $tpl['id'] ?>"
                data-start="<?= e(substr((string) $tpl['start_time'], 0, 5)) ?>"
                data-end="<?= e(substr((string) $tpl['end_time'], 0, 5)) ?>"
                data-spans="<?= (string) $tpl['end_time'] <= (string) $tpl['start_time'] ? '1' : '0' ?>"
              >
                <?= e((string) $tpl['label']) ?> (<?= e(substr((string) $tpl['start_time'], 0, 5)) ?>–<?= e(substr((string) $tpl['end_time'], 0, 5)) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="pc-label" for="create-shift-user">Aide-soignante affectée</label>
          <select id="create-shift-user" name="user_id" class="pc-input">
            <option value="">— Créneau non couvert —</option>
            <?php foreach ($team as $cg): ?>
              <option value="<?= (int) $cg['id'] ?>">
                <?= e(trim($cg['first_name'] . ' ' . $cg['last_name'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="pc-label" for="create-shift-replaced">En remplacement de (optionnel)</label>
          <select id="create-shift-replaced" name="replaced_user_id" class="pc-input">
            <option value="">Aucun remplacement</option>
            <?php foreach ($team as $cg): ?>
              <option value="<?= (int) $cg['id'] ?>">
                <?= e(trim($cg['first_name'] . ' ' . $cg['last_name'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div>
            <label class="pc-label" for="create-shift-start-date">Date du créneau</label>
            <input type="date" id="create-shift-start-date" name="date" class="pc-input" value="<?= e($mondayDate) ?>" required>
          </div>
          <div>
            <label class="pc-label" for="create-shift-start-time">Heure de début</label>
            <input type="time" id="create-shift-start-time" name="start_time" class="pc-input" value="08:00" required>
          </div>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div>
            <label class="pc-label" for="create-shift-end-date">Date de fin (indicative)</label>
            <input type="date" id="create-shift-end-date" name="end_date" class="pc-input" value="<?= e($mondayDate) ?>">
          </div>
          <div>
            <label class="pc-label" for="create-shift-end-time">Heure de fin</label>
            <input type="time" id="create-shift-end-time" name="end_time" class="pc-input" value="20:00" required>
          </div>
        </div>

        <div>
          <label class="pc-label" for="create-shift-notes">Consigne / Note (optionnel)</label>
          <input type="text" id="create-shift-notes" name="notes" class="pc-input" placeholder="Ex. : Garde de jour, RDV podologue...">
        </div>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('check', '', 18) ?>
          <span>Enregistrer le créneau</span>
        </button>
      </form>
    </div>
  </div>

  <!-- 2. Bottom-sheet Modification / Remplacement d'un créneau -->
  <div class="pc-sheet-backdrop" id="shift-edit-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="shift-edit-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="shift-edit-title">Modifier / Remplacer le créneau</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/planning/shifts/0/update" id="shift-edit-form" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/planning?view=<?= e($viewMode) ?>&amp;week=<?= e($mondayDate) ?>">
        <input type="hidden" name="template_id" id="edit-shift-template" value="">

        <div>
          <label class="pc-label" for="edit-shift-user">Aide-soignante affectée</label>
          <select id="edit-shift-user" name="user_id" class="pc-input">
            <option value="">— Créneau non couvert —</option>
            <?php foreach ($team as $cg): ?>
              <option value="<?= (int) $cg['id'] ?>">
                <?= e(trim($cg['first_name'] . ' ' . $cg['last_name'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="pc-label" for="edit-shift-replaced">En remplacement de (si applicable)</label>
          <select id="edit-shift-replaced" name="replaced_user_id" class="pc-input">
            <option value="">Aucun remplacement</option>
            <?php foreach ($team as $cg): ?>
              <option value="<?= (int) $cg['id'] ?>">
                <?= e(trim($cg['first_name'] . ' ' . $cg['last_name'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div>
            <label class="pc-label" for="edit-shift-start-date">Date du créneau</label>
            <input type="date" id="edit-shift-start-date" name="date" class="pc-input" required>
          </div>
          <div>
            <label class="pc-label" for="edit-shift-start-time">Heure de début</label>
            <input type="time" id="edit-shift-start-time" name="start_time" class="pc-input" required>
          </div>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div>
            <label class="pc-label" for="edit-shift-end-date">Date de fin</label>
            <input type="date" id="edit-shift-end-date" name="end_date" class="pc-input">
          </div>
          <div>
            <label class="pc-label" for="edit-shift-end-time">Heure de fin</label>
            <input type="time" id="edit-shift-end-time" name="end_time" class="pc-input" required>
          </div>
        </div>

        <div>
          <label class="pc-label" for="edit-shift-notes">Consigne / Note</label>
          <input type="text" id="edit-shift-notes" name="notes" class="pc-input">
        </div>

        <div class="flex items-center gap-2">
          <button type="submit" class="pc-btn pc-btn--primary flex-1">
            <?= icon('check', '', 16) ?>
            <span>Enregistrer</span>
          </button>
        </div>
      </form>
      <form method="POST" action="/planning/shifts/0/delete" id="shift-delete-form" class="px-4 pb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/planning?view=<?= e($viewMode) ?>&amp;week=<?= e($mondayDate) ?>">
        <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
          <?= icon('trash-2', '', 16) ?>
          <span>Supprimer ce créneau</span>
        </button>
      </form>
    </div>
  </div>

  <!-- 3. Bottom-sheet Duplication de la semaine sur N semaines -->
  <div class="pc-sheet-backdrop" id="shift-duplicate-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="shift-dup-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="shift-dup-title">Dupliquer cette semaine</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/planning/duplicate" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="week_start" value="<?= e($mondayDate) ?>">
        <input type="hidden" name="redirect_to" value="/planning?view=week&amp;week=<?= e($mondayDate) ?>">

        <p class="text-subhead text-muted">
          Copie automatiquement tous les créneaux de la semaine du <strong><?= e(format_date_fr($mondayDate)) ?></strong> sur les semaines suivantes (en ignorant les éventuels chevauchements).
        </p>

        <div>
          <label class="pc-label" for="dup-weeks-count">Nombre de semaines suivantes à générer</label>
          <select id="dup-weeks-count" name="weeks_count" class="pc-input">
            <option value="1">1 semaine suivante</option>
            <option value="2" selected>2 semaines suivantes</option>
            <option value="4">4 semaines suivantes (1 mois)</option>
          </select>
        </div>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('copy', '', 18) ?>
          <span>Dupliquer le planning</span>
        </button>
      </form>
    </div>
  </div>

  <!-- 4. Bottom-sheet Déclaration d'absence (maladie, congé, formation) -->
  <div class="pc-sheet-backdrop" id="absence-create-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="absence-create-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="absence-create-title">Enregistrer une absence</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/planning/absences" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/planning?view=<?= e($viewMode) ?>&amp;week=<?= e($mondayDate) ?>">

        <div>
          <label class="pc-label" for="abs-user">Membre de l’équipe</label>
          <select id="abs-user" name="user_id" class="pc-input" required>
            <?php foreach ($team as $cg): ?>
              <option value="<?= (int) $cg['id'] ?>">
                <?= e(trim($cg['first_name'] . ' ' . $cg['last_name'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="pc-label" for="abs-reason-select">Motif d’absence</label>
          <select id="abs-reason-select" name="reason" class="pc-input">
            <option value="maladie">Arrêt maladie</option>
            <option value="conge">Congés payés</option>
            <option value="formation">Formation</option>
            <option value="autre">Autre indisponibilité</option>
          </select>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div>
            <label class="pc-label" for="abs-start-date">Du</label>
            <input type="date" id="abs-start-date" name="start_date" class="pc-input" value="<?= e($mondayDate) ?>" required>
          </div>
          <div>
            <label class="pc-label" for="abs-end-date">Au</label>
            <input type="date" id="abs-end-date" name="end_date" class="pc-input" value="<?= e($mondayDate) ?>" required>
          </div>
        </div>

        <div>
          <label class="pc-label" for="abs-notes">Précision</label>
          <input type="text" id="abs-notes" name="notes" class="pc-input" placeholder="Les créneaux concernés passeront « À remplacer »">
        </div>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('check', '', 18) ?>
          <span>Enregistrer l’absence</span>
        </button>
      </form>
    </div>
  </div>

  <!-- 5. Bottom-sheet Créneaux types modifiables (Jour 8h–20h, Nuit 20h–8h) -->
  <div class="pc-sheet-backdrop" id="templates-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="templates-sheet-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="templates-sheet-title">Créneaux types modifiables</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <div class="pc-sheet__body pc-stack gap-3">
        <?php foreach ($templates as $tpl): ?>
          <form method="POST" action="/planning/templates" class="pc-card pc-stack gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $tpl['id'] ?>">
            <input type="hidden" name="label" value="<?= e((string) $tpl['label']) ?>">
            <div class="text-subhead font-bold"><?= e((string) $tpl['label']) ?></div>
            <div class="pc-module-grid pc-module-grid--2col">
              <div>
                <label class="pc-label">Début</label>
                <input type="time" name="start_time" class="pc-input" value="<?= e(substr((string) $tpl['start_time'], 0, 5)) ?>" required>
              </div>
              <div>
                <label class="pc-label">Fin</label>
                <input type="time" name="end_time" class="pc-input" value="<?= e(substr((string) $tpl['end_time'], 0, 5)) ?>" required>
              </div>
            </div>
            <button type="submit" class="pc-btn pc-btn--tinted pc-btn--sm">Mettre à jour ce modèle</button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
