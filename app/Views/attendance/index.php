<?php

declare(strict_types=1);

$canValidate = !empty($canValidate);
$weeklyGrid = is_array($weeklyGrid ?? null) ? $weeklyGrid : [];
$pendingList = is_array($pendingAttendances ?? null) ? $pendingAttendances : [];
$mondayYmd = (string) ($weeklyGrid['week_start'] ?? ($todayYmd ?? date('Y-m-d')));
$prevMonday = (string) ($weeklyGrid['prev_week'] ?? $mondayYmd);
$nextMonday = (string) ($weeklyGrid['next_week'] ?? $mondayYmd);
$yearMonth = substr($mondayYmd, 0, 7);
$days = is_array($weeklyGrid['days'] ?? null) ? $weeklyGrid['days'] : [];
$gridRows = is_array($weeklyGrid['grid'] ?? null) ? $weeklyGrid['grid'] : [];
$monthSummary = is_array($weeklyGrid['month_summary'] ?? null) ? $weeklyGrid['month_summary'] : [];

$monthHoursByUser = [];
foreach (($monthSummary['caregivers'] ?? []) as $row) {
    $monthHoursByUser[(int) $row['user_id']] = $row;
}
?>
<!-- Navigation E17 (Contrôle hebdomadaire) / E18 (Récapitulatif mensuel) -->
<section class="pc-card mb-3" aria-label="Navigation pointages et heures">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div class="pc-segmented">
      <a href="/attendance?week=<?= e($mondayYmd) ?>" class="pc-segmented__btn is-active">
        <?= icon('clock', '', 15) ?>
        <span>Pointages (Semaine)</span>
      </a>
      <a href="/attendance/hours?month=<?= e($yearMonth) ?>" class="pc-segmented__btn">
        <?= icon('file-spreadsheet', '', 15) ?>
        <span>Heures du mois &amp; CSV</span>
      </a>
    </div>

    <div class="flex items-center gap-2">
      <a href="/attendance?week=<?= e($prevMonday) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Semaine précédente">
        <?= icon('chevron-left', '', 16) ?>
      </a>
      <span class="text-subhead font-bold">Sem. du <?= e(format_date_fr($mondayYmd)) ?></span>
      <a href="/attendance?week=<?= e($nextMonday) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Semaine suivante">
        <?= icon('chevron-right', '', 16) ?>
      </a>
    </div>
  </div>
</section>

<?php if ($canValidate): ?>
  <!-- ======================================================================
       SECTION 1 : POINTAGES À VALIDER (pending : oublis déclarés, hors zone GPS)
       ====================================================================== -->
  <section class="pc-card mb-3" aria-labelledby="pending-att-heading">
    <div class="flex items-center justify-between mb-2">
      <h2 class="text-headline" id="pending-att-heading">
        Pointages à valider (<?= count($pendingList) ?>)
      </h2>
      <span class="pc-badge <?= count($pendingList) > 0 ? 'pc-badge--warn' : 'pc-badge--ok' ?>">
        <?= count($pendingList) > 0 ? count($pendingList) . ' en attente' : 'À jour' ?>
      </span>
    </div>

    <?php if (empty($pendingList)): ?>
      <p class="text-caption text-muted">Aucun pointage en attente de validation.</p>
    <?php else: ?>
      <div class="pc-stack gap-3">
        <?php foreach ($pendingList as $att): ?>
          <?php
          $localDt = trim(($att['effective_local_date'] ?? '') . ' à ' . ($att['effective_local_time'] ?? ''));
          $isIn = ($att['type'] ?? 'in') === 'in';
          ?>
          <article class="pc-pending-att-card">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="pc-avatar pc-avatar--sm <?= e(avatar_color_class((int) $att['user_id'])) ?>">
                  <?= e(initials((string) $att['first_name'], (string) $att['last_name'])) ?>
                </span>
                <div>
                  <div class="text-subhead font-bold">
                    <?= e(trim($att['first_name'] . ' ' . $att['last_name'])) ?>
                    · <span class="text-brand"><?= $isIn ? 'Arrivée' : 'Départ' ?></span>
                  </div>
                  <div class="text-caption text-muted">
                    Heure : <strong class="font-mono"><?= e($localDt) ?></strong>
                    · Méthode : <?= e(strtoupper((string) $att['method'])) ?>
                  </div>
                </div>
              </div>

              <span class="pc-badge pc-badge--warn">
                <?= icon('alert-triangle', '', 12) ?>
                <span>À valider</span>
              </span>
            </div>

            <?php if (!empty($att['reason'])): ?>
              <div class="text-caption mt-2">
                <strong>Motif :</strong> <?= e((string) $att['reason']) ?>
                <?php if ($att['distance_m'] !== null): ?>
                  (distance : <?= (int) $att['distance_m'] ?> m)
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="pc-pending-att-card__actions mt-3 flex flex-col gap-2">
              <div class="flex items-center gap-2 flex-wrap">
                <form method="POST" action="/attendance/<?= (int) $att['id'] ?>/validate">
                  <?= csrf_field() ?>
                  <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">
                    <?= icon('check', '', 14) ?>
                    <span>Valider le pointage</span>
                  </button>
                </form>
              </div>

              <form method="POST" action="/attendance/<?= (int) $att['id'] ?>/reject" class="flex items-center gap-2 w-full">
                <?= csrf_field() ?>
                <input type="text" name="rejection_reason" class="pc-input pc-input--sm flex-1" placeholder="Motif du refus..." value="Pointage non conforme">
                <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--sm">
                  <?= icon('x', '', 14) ?>
                  <span>Refuser</span>
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<!-- ======================================================================
     SECTION 2 : GRILLE HEBDOMADAIRE (Jours × Aides-soignantes + Total mois)
     ====================================================================== -->
<section class="pc-card" aria-labelledby="weekly-grid-heading">
  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
    <div>
      <h2 class="text-headline" id="weekly-grid-heading">Grille hebdomadaire des présences</h2>
      <p class="text-caption text-muted">Créneaux planifiés, heures d’arrivée/départ pointées et cumul du mois en cours.</p>
    </div>
    <a href="/attendance/hours?month=<?= e($yearMonth) ?>" class="pc-btn pc-btn--tinted pc-btn--sm">
      <?= icon('file-spreadsheet', '', 15) ?>
      <span>Récapitulatif mensuel &amp; CSV</span>
    </a>
  </div>

  <div class="pc-stack gap-3">
    <?php foreach ($gridRows as $uid => $userRow): ?>
      <?php
      $uid = (int) $uid;
      if (!$canValidate && $uid !== (int) (\App\Core\Auth::id() ?? 0)) {
          continue;
      }
      $cg = $userRow['user'] ?? [];
      $cells = $userRow['cells'] ?? [];
      $mRow = $monthHoursByUser[$uid] ?? null;
      $monthWorked = $mRow ? number_format((float) $mRow['punched_hours'], 1, ',', '') : '0,0';
      $monthPlanned = $mRow ? number_format((float) $mRow['planned_hours'], 1, ',', '') : '0,0';
      ?>
      <div class="pc-att-user-block">
        <div class="pc-att-user-block__header">
          <div class="flex items-center gap-2">
            <span class="pc-avatar pc-avatar--sm <?= e(avatar_color_class($uid)) ?>">
              <?= e(initials((string) ($cg['first_name'] ?? 'A'), (string) ($cg['last_name'] ?? 'S'))) ?>
            </span>
            <div>
              <div class="text-subhead font-bold"><?= e(trim(($cg['first_name'] ?? '') . ' ' . ($cg['last_name'] ?? ''))) ?></div>
              <div class="text-caption text-muted">Contrat <?= e((string) ($cg['contract_type'] ?? 'CDI')) ?> · <?= (int) ($cg['weekly_hours'] ?? 35) ?> h/sem.</div>
            </div>
          </div>

          <div class="text-right">
            <span class="pc-badge pc-badge--brand">
              Mois : <?= e($monthWorked) ?> h pointées / <?= e($monthPlanned) ?> h planifiées
            </span>
          </div>
        </div>

        <div class="pc-att-week-days">
          <?php foreach ($days as $ymd => $dayMeta): ?>
            <?php
            $cellItems = $cells[$ymd] ?? [];
            if (empty($cellItems)) {
                continue;
            }
            ?>
            <div class="pc-att-day-row">
              <div class="pc-att-day-row__date font-semibold text-caption">
                <?= e(trim(($dayMeta['short_day'] ?? '') . ' ' . ($dayMeta['day_num'] ?? $ymd))) ?>
              </div>

              <div class="pc-att-day-row__content">
                <?php foreach ($cellItems as $item): ?>
                  <span class="pc-badge pc-badge--neutral">
                    <?= icon('calendar', '', 11) ?>
                    <span>Planifié <?= e((string) $item['shift_start_local']) ?>–<?= e((string) $item['shift_end_local']) ?></span>
                  </span>

                  <?php if (!empty($item['in_local'])): ?>
                    <span class="pc-badge pc-badge--ok">
                      <?= icon('log-in', '', 11) ?>
                      <span>Arrivée <?= e((string) $item['in_local']) ?></span>
                    </span>
                  <?php endif; ?>

                  <?php if (!empty($item['out_local'])): ?>
                    <span class="pc-badge pc-badge--ok">
                      <?= icon('log-out', '', 11) ?>
                      <span>Départ <?= e((string) $item['out_local']) ?></span>
                    </span>
                  <?php endif; ?>

                  <span class="pc-badge pc-badge--<?= e((string) ($item['badge_tone'] ?? 'neutral')) ?>">
                    <span><?= e((string) ($item['status_label'] ?? 'Prévu')) ?></span>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
