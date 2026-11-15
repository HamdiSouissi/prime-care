<?php

declare(strict_types=1);

$yearMonth = (string) ($monthParam ?? date('Y-m'));
$prevMonth = (string) ($prevMonth ?? $yearMonth);
$nextMonth = (string) ($nextMonth ?? $yearMonth);
$summary = is_array($summary ?? null) ? $summary : [];
$monthLabel = (string) ($summary['month_label'] ?? $yearMonth);
$report = is_array($summary['caregivers'] ?? null) ? $summary['caregivers'] : [];
$canExport = !empty($canValidate);

$totals = [
    'planned_hours' => 0.0,
    'completed_planned_hours' => 0.0,
    'remaining_planned_hours' => 0.0,
    'punched_hours' => 0.0,
    'night_hours' => 0.0,
    'sunday_hours' => 0.0,
    'holiday_hours' => 0.0,
    'diff_hours' => 0.0,
    'late_arrivals_count' => 0,
    'early_departures_count' => 0,
    'pending_count' => 0,
    'absences_count' => 0,
    'absence_days' => 0,
];
foreach ($report as $r) {
    $totals['planned_hours'] += (float) ($r['planned_hours'] ?? 0.0);
    $totals['completed_planned_hours'] += (float) ($r['completed_planned_hours'] ?? 0.0);
    $totals['remaining_planned_hours'] += (float) ($r['remaining_planned_hours'] ?? 0.0);
    $totals['punched_hours'] += (float) ($r['punched_hours'] ?? 0.0);
    $totals['night_hours'] += (float) ($r['night_hours'] ?? 0.0);
    $totals['sunday_hours'] += (float) ($r['sunday_hours'] ?? 0.0);
    $totals['holiday_hours'] += (float) ($r['holiday_hours'] ?? 0.0);
    $totals['diff_hours'] += (float) ($r['diff_hours'] ?? 0.0);
    $totals['late_arrivals_count'] += (int) ($r['late_arrivals_count'] ?? 0);
    $totals['early_departures_count'] += (int) ($r['early_departures_count'] ?? 0);
    $totals['pending_count'] += (int) ($r['pending_count'] ?? 0);
    $totals['absences_count'] += (int) ($r['absences_count'] ?? 0);
    $totals['absence_days'] += (int) ($r['absence_days'] ?? 0);
}
$totalDiffSign = $totals['diff_hours'] > 0 ? '+' : '';
$totalDiffBadge = abs($totals['diff_hours']) < 0.25 ? 'pc-badge--ok' : ($totals['diff_hours'] < 0 ? 'pc-badge--warn' : 'pc-badge--brand');
?>
<!-- Barre de navigation E17 / E18 & Export CSV -->
<section class="pc-card mb-3 pc-no-print" aria-label="Navigation récapitulatif mensuel">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div class="pc-segmented">
      <a href="/attendance" class="pc-segmented__btn">
        <?= icon('clock', '', 15) ?>
        <span>Pointages (Semaine)</span>
      </a>
      <a href="/attendance/hours?month=<?= e($yearMonth) ?>" class="pc-segmented__btn is-active">
        <?= icon('file-spreadsheet', '', 15) ?>
        <span>Rapport RH &amp; Heures</span>
      </a>
    </div>

    <div class="flex items-center gap-2">
      <a href="/attendance/hours?month=<?= e($prevMonth) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Mois précédent">
        <?= icon('chevron-left', '', 16) ?>
      </a>
      <span class="text-subhead font-bold"><?= e($monthLabel) ?></span>
      <a href="/attendance/hours?month=<?= e($nextMonth) ?>" class="pc-btn pc-btn--ghost pc-btn--sm" aria-label="Mois suivant">
        <?= icon('chevron-right', '', 16) ?>
      </a>
    </div>
  </div>
</section>

<!-- Carte récapitulative du total du mois en haut (G4 + §2.2 Rapport RH) -->
<section class="pc-card pc-hours-total-card mb-3" aria-labelledby="hours-total-heading">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div>
      <div class="text-caption font-semibold text-brand uppercase">Rapport RH &amp; Cumul mensuel de l’équipe</div>
      <h2 class="text-headline" id="hours-total-heading">Total du mois · <?= e($monthLabel) ?></h2>
    </div>
    <div class="flex items-center gap-2 flex-wrap pc-no-print">
      <button type="button" class="pc-btn pc-btn--tinted pc-btn--sm" data-print-report>
        <?= icon('printer', '', 15) ?>
        <span>Imprimer</span>
      </button>
      <?php if ($canExport): ?>
        <a href="/attendance/export-csv?month=<?= e($yearMonth) ?>" class="pc-btn pc-btn--primary pc-btn--sm">
          <?= icon('download', '', 15) ?>
          <span>Exporter CSV paie (; UTF-8)</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="pc-hours-card__list">
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Heures planifiées (créneaux terminés)</span>
      <span class="pc-hours-card__value font-mono"><?= number_format($totals['completed_planned_hours'], 2, ',', ' ') ?> h</span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Planifié restant ce mois-ci</span>
      <span class="pc-hours-card__value font-mono text-muted"><?= number_format($totals['remaining_planned_hours'], 2, ',', ' ') ?> h</span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Heures pointées (valides)</span>
      <span class="pc-hours-card__value font-mono font-bold"><?= number_format($totals['punched_hours'], 2, ',', ' ') ?> h</span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Dont nuit (21h–6h)</span>
      <span class="pc-hours-card__value font-mono"><?= number_format($totals['night_hours'], 2, ',', ' ') ?> h</span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Dont dimanche</span>
      <span class="pc-hours-card__value font-mono"><?= number_format($totals['sunday_hours'], 2, ',', ' ') ?> h</span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Dont jours fériés</span>
      <span class="pc-hours-card__value font-mono"><?= number_format($totals['holiday_hours'], 2, ',', ' ') ?> h</span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Retards / Départs anticipés / À valider / Absences</span>
      <span class="pc-hours-card__value font-mono">
        <?= $totals['late_arrivals_count'] ?> ret. · <?= $totals['early_departures_count'] ?> dép. ant. · <?= $totals['pending_count'] ?> à val. · <?= $totals['absence_days'] ?> j abs.
      </span>
    </div>
    <div class="pc-hours-card__row">
      <span class="pc-hours-card__label">Écart global (créneaux terminés)</span>
      <span class="pc-hours-card__value">
        <span class="pc-badge <?= e($totalDiffBadge) ?> font-mono">
          <?= e($totalDiffSign . number_format($totals['diff_hours'], 2, ',', ' ')) ?> h
        </span>
      </span>
    </div>
  </div>
</section>

<!-- Détail par aide-soignante : cartes sous 600 px + tableau à partir de 600 px (E18 / G4 / §2.2) -->
<section class="pc-card" aria-labelledby="hours-table-heading">
  <div class="mb-3">
    <h2 class="text-headline" id="hours-table-heading">Détail par aide-soignante · <?= e($monthLabel) ?></h2>
    <p class="text-caption text-muted">
      L’écart est calculé uniquement sur les créneaux déjà terminés ; le planifié restant ce mois-ci est affiché à part.
    </p>
  </div>

  <!-- Vue mobile (< 600 px) : une carte par aide-soignante avec libellés à gauche et valeurs à droite -->
  <div class="pc-hours-cards" aria-label="Cartes récapitulatives par aide-soignante">
    <?php foreach ($report as $row): ?>
      <?php
      $diff = (float) ($row['diff_hours'] ?? 0.0);
      $diffSign = $diff > 0 ? '+' : '';
      $diffBadge = abs($diff) < 0.25 ? 'pc-badge--ok' : ($diff < 0 ? 'pc-badge--warn' : 'pc-badge--brand');
      ?>
      <article class="pc-hours-card">
        <div class="pc-hours-card__header">
          <div class="flex items-center gap-2 min-w-0">
            <span class="pc-avatar pc-avatar--sm <?= e(avatar_color_class((int) $row['user_id'])) ?>">
              <?= e(initials((string) $row['first_name'], (string) $row['last_name'])) ?>
            </span>
            <div class="min-w-0">
              <div class="text-subhead font-bold truncate"><?= e((string) $row['full_name']) ?></div>
              <div class="text-caption text-muted">
                <?= e((string) ($row['contract_type'] ?? 'CDI')) ?> · <?= number_format((float) ($row['weekly_hours'] ?? 35), 0, ',', ' ') ?> h/sem.
              </div>
            </div>
          </div>
          <span class="pc-badge pc-badge--brand font-mono">
            <?= number_format((float) $row['punched_hours'], 2, ',', ' ') ?> h
          </span>
        </div>

        <div class="pc-hours-card__list">
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Planifiées (terminées)</span>
            <span class="pc-hours-card__value font-mono"><?= number_format((float) ($row['completed_planned_hours'] ?? 0.0), 2, ',', ' ') ?> h</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Planifié restant ce mois-ci</span>
            <span class="pc-hours-card__value font-mono text-muted"><?= number_format((float) ($row['remaining_planned_hours'] ?? 0.0), 2, ',', ' ') ?> h</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Heures pointées</span>
            <span class="pc-hours-card__value font-mono font-bold"><?= number_format((float) $row['punched_hours'], 2, ',', ' ') ?> h</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Nuit (21h–6h)</span>
            <span class="pc-hours-card__value font-mono"><?= number_format((float) $row['night_hours'], 2, ',', ' ') ?> h</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Dimanche</span>
            <span class="pc-hours-card__value font-mono"><?= number_format((float) $row['sunday_hours'], 2, ',', ' ') ?> h</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Jours fériés</span>
            <span class="pc-hours-card__value font-mono"><?= number_format((float) $row['holiday_hours'], 2, ',', ' ') ?> h</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Retards / Dép. anticipés / À valider</span>
            <span class="pc-hours-card__value font-mono">
              <?= (int) ($row['late_arrivals_count'] ?? 0) ?> / <?= (int) ($row['early_departures_count'] ?? 0) ?> / <?= (int) ($row['pending_count'] ?? 0) ?>
            </span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Absences</span>
            <span class="pc-hours-card__value font-mono"><?= (int) ($row['absence_days'] ?? 0) ?> j</span>
          </div>
          <div class="pc-hours-card__row">
            <span class="pc-hours-card__label">Écart (créneaux terminés)</span>
            <span class="pc-hours-card__value">
              <span class="pc-badge <?= e($diffBadge) ?> font-mono">
                <?= e($diffSign . number_format($diff, 2, ',', ' ')) ?> h
              </span>
            </span>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <!-- Vue tablette / bureau (>= 600 px) : tableau -->
  <div class="pc-table-wrap pc-hours-table-desktop">
    <table class="pc-table">
      <thead>
        <tr>
          <th>Membre de l’équipe</th>
          <th class="text-right">Planifiées (terminées)</th>
          <th class="text-right">Planifié restant ce mois-ci</th>
          <th class="text-right">Pointées (valides)</th>
          <th class="text-right">Nuit (21h–6h)</th>
          <th class="text-right">Dimanche</th>
          <th class="text-right">Fériés</th>
          <th class="text-right">Retards / Dép. / À val.</th>
          <th class="text-right">Absences</th>
          <th class="text-right">Écart</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report as $row): ?>
          <?php
          $diff = (float) ($row['diff_hours'] ?? 0.0);
          $diffSign = $diff > 0 ? '+' : '';
          $diffBadge = abs($diff) < 0.25 ? 'pc-badge--ok' : ($diff < 0 ? 'pc-badge--warn' : 'pc-badge--brand');
          ?>
          <tr>
            <td>
              <div class="flex items-center gap-2">
                <span class="pc-avatar pc-avatar--xs <?= e(avatar_color_class((int) $row['user_id'])) ?>">
                  <?= e(initials((string) $row['first_name'], (string) $row['last_name'])) ?>
                </span>
                <div>
                  <div class="font-semibold"><?= e((string) $row['full_name']) ?></div>
                  <div class="text-caption text-muted"><?= e((string) ($row['contract_type'] ?? 'CDI')) ?> · <?= number_format((float) ($row['weekly_hours'] ?? 35), 0, ',', ' ') ?> h/sem.</div>
                </div>
              </div>
            </td>
            <td class="text-right font-mono"><?= number_format((float) ($row['completed_planned_hours'] ?? 0.0), 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono text-muted"><?= number_format((float) ($row['remaining_planned_hours'] ?? 0.0), 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono font-bold"><?= number_format((float) $row['punched_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= number_format((float) $row['night_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= number_format((float) $row['sunday_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= number_format((float) $row['holiday_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= (int) ($row['late_arrivals_count'] ?? 0) ?> / <?= (int) ($row['early_departures_count'] ?? 0) ?> / <?= (int) ($row['pending_count'] ?? 0) ?></td>
            <td class="text-right font-mono"><?= (int) ($row['absence_days'] ?? 0) ?> j</td>
            <td class="text-right">
              <span class="pc-badge <?= e($diffBadge) ?> font-mono">
                <?= e($diffSign . number_format($diff, 2, ',', ' ')) ?> h
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
