<?php

declare(strict_types=1);

$yearMonth = (string) ($monthParam ?? date('Y-m'));
$prevMonth = (string) ($prevMonth ?? $yearMonth);
$nextMonth = (string) ($nextMonth ?? $yearMonth);
$summary = is_array($summary ?? null) ? $summary : [];
$monthLabel = (string) ($summary['month_label'] ?? $yearMonth);
$report = is_array($summary['caregivers'] ?? null) ? $summary['caregivers'] : [];
$canExport = !empty($canValidate);
?>
<!-- Barre de navigation E17 / E18 & Export CSV -->
<section class="pc-card mb-3" aria-label="Navigation récapitulatif mensuel">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div class="pc-segmented">
      <a href="/attendance" class="pc-segmented__btn">
        <?= icon('clock', '', 15) ?>
        <span>Pointages (Semaine)</span>
      </a>
      <a href="/attendance/hours?month=<?= e($yearMonth) ?>" class="pc-segmented__btn is-active">
        <?= icon('file-spreadsheet', '', 15) ?>
        <span>Heures du mois &amp; CSV</span>
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

<!-- Tableau récapitulatif mensuel des heures (E18) -->
<section class="pc-card" aria-labelledby="hours-table-heading">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div>
      <h2 class="text-headline" id="hours-table-heading">Récapitulatif des heures · <?= e($monthLabel) ?></h2>
      <p class="text-caption text-muted">
        Découpage exact aux passages de minuit, changements d’heure été/hiver (Europe/Paris) et 11 jours fériés français.
      </p>
    </div>

    <?php if ($canExport): ?>
      <a href="/attendance/export-csv?month=<?= e($yearMonth) ?>" class="pc-btn pc-btn--primary pc-btn--sm">
        <?= icon('download', '', 15) ?>
        <span>Exporter CSV paie (; UTF-8)</span>
      </a>
    <?php endif; ?>
  </div>

  <div class="pc-table-wrap">
    <table class="pc-table">
      <thead>
        <tr>
          <th>Membre de l’équipe</th>
          <th class="text-right">Planifiées</th>
          <th class="text-right">Pointées (valides)</th>
          <th class="text-right">Nuit (21h–6h)</th>
          <th class="text-right">Dimanche</th>
          <th class="text-right">Jours fériés</th>
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
            <td class="text-right font-mono"><?= number_format((float) $row['planned_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono font-bold"><?= number_format((float) $row['punched_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= number_format((float) $row['night_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= number_format((float) $row['sunday_hours'], 2, ',', ' ') ?> h</td>
            <td class="text-right font-mono"><?= number_format((float) $row['holiday_hours'], 2, ',', ' ') ?> h</td>
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
