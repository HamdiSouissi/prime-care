<?php

declare(strict_types=1);

$observance = is_array($observance ?? null) ? $observance : [];
$byDay = is_array($byDay ?? null) ? $byDay : [];
$medications = is_array($medications ?? null) ? $medications : [];
$medFilter = (int) ($medFilter ?? 0);
$daysFilter = (int) ($daysFilter ?? 14);
$obs7 = $observance['days_7'] ?? ['rate' => 100.0, 'given' => 0, 'skipped' => 0, 'total' => 0];
$obs30 = $observance['days_30'] ?? ['rate' => 100.0, 'given' => 0, 'skipped' => 0, 'total' => 0];
?>
<!-- 1. Taux d'observance sur 7 et 30 jours (E09) -->
<section class="pc-module-grid pc-module-grid--2col" aria-label="Taux d’observance 7 et 30 jours">
  <div class="pc-card">
    <div class="flex items-center justify-between mb-1">
      <span class="text-subhead font-semibold text-muted">Observance · 7 jours</span>
      <span class="pc-badge <?= $obs7['rate'] >= 90 ? 'pc-badge--ok' : 'pc-badge--warn' ?>">
        <?= number_format((float) $obs7['rate'], 1, ',', '') ?> %
      </span>
    </div>
    <div class="pc-metric-lg">
      <?= (int) $obs7['given'] ?>/<?= (int) $obs7['total'] ?>
      <span class="pc-metric-unit">données</span>
    </div>
    <progress class="pc-progress mt-2" value="<?= (int) round((float) $obs7['rate']) ?>" max="100"></progress>
    <div class="text-caption text-muted mt-1"><?= (int) $obs7['skipped'] ?> non prise(s)</div>
  </div>

  <div class="pc-card">
    <div class="flex items-center justify-between mb-1">
      <span class="text-subhead font-semibold text-muted">Observance · 30 jours</span>
      <span class="pc-badge <?= $obs30['rate'] >= 90 ? 'pc-badge--ok' : 'pc-badge--warn' ?>">
        <?= number_format((float) $obs30['rate'], 1, ',', '') ?> %
      </span>
    </div>
    <div class="pc-metric-lg">
      <?= (int) $obs30['given'] ?>/<?= (int) $obs30['total'] ?>
      <span class="pc-metric-unit">données</span>
    </div>
    <progress class="pc-progress mt-2" value="<?= (int) round((float) $obs30['rate']) ?>" max="100"></progress>
    <div class="text-caption text-muted mt-1"><?= (int) $obs30['skipped'] ?> non prise(s)</div>
  </div>
</section>

<!-- 2. Filtres par médicament et période -->
<form method="GET" action="/treatments/history" class="pc-card flex items-end gap-2 flex-wrap">
  <div class="pc-field flex-1">
    <label class="pc-label" for="hist-med-filter">Médicament</label>
    <select name="medication_id" id="hist-med-filter" class="pc-input">
      <option value="0">Tous les médicaments</option>
      <?php foreach ($medications as $m): ?>
        <option value="<?= (int) $m['id'] ?>" <?= $medFilter === (int) $m['id'] ? 'selected' : '' ?>>
          <?= e((string) $m['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="pc-field">
    <label class="pc-label" for="hist-days-filter">Période</label>
    <select name="days" id="hist-days-filter" class="pc-input">
      <option value="7" <?= $daysFilter === 7 ? 'selected' : '' ?>>7 jours</option>
      <option value="14" <?= $daysFilter === 14 ? 'selected' : '' ?>>14 jours</option>
      <option value="30" <?= $daysFilter === 30 ? 'selected' : '' ?>>30 jours</option>
    </select>
  </div>

  <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">Filtrer</button>
</form>

<!-- 3. Historique par jour -->
<?php foreach ($byDay as $dayStr => $rows): ?>
  <section aria-label="Prises du <?= e($dayStr) ?>">
    <div class="pc-section-header">
      <?= e(\App\Services\ScheduleService::utcToLocalParis($dayStr . ' 12:00:00', 'd/m/Y')) ?>
    </div>
    <div class="pc-stack gap-2">
      <?php foreach ($rows as $r): ?>
        <?php $disp = $r['disp']; ?>
        <article class="pc-card flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-mono font-bold text-subhead"><?= e((string) $r['time_local']) ?></span>
              <span class="text-headline"><?= e((string) $r['medication_name']) ?></span>
            </div>
            <div class="text-caption text-muted mt-1">
              <?php if ((string) $r['status'] === 'given'): ?>
                Dose administrée : <strong><?= number_format((float) ($r['given_dose'] ?? ($r['dose_given'] ?? $r['rx_dose'])), 0, ',', ' ') ?> <?= e((string) ($r['dose_unit'] ?? $r['rx_unit'])) ?></strong>
                à <?= e((string) ($r['given_at_local'] ?? $r['time_local'])) ?>
                par <strong><?= e((string) ($r['giver_first_name'] ?? 'Équipe')) ?></strong>
                <?php if (!empty($r['injection_site'])): ?>
                  · Site : <?= e(\App\Services\InsulinService::INJECTION_SITES[(string) $r['injection_site']] ?? (string) $r['injection_site']) ?>
                <?php endif; ?>
                <?php if (($r['linked_glycemia_value'] ?? null) !== null): ?>
                  · Glycémie liée : <?= number_format((float) $r['linked_glycemia_value'], 2, ',', ' ') ?> g/L
                <?php endif; ?>
              <?php elseif ((string) $r['status'] === 'skipped'): ?>
                <?php $skipReasonKey = (string) ($r['reason'] ?? ($r['skip_reason'] ?? '')); ?>
                Non pris · Motif : <strong><?= e(\App\Services\IntakeService::SKIP_REASONS[$skipReasonKey] ?? $skipReasonKey) ?></strong>
                (<?= e((string) ($r['giver_first_name'] ?? 'Équipe')) ?>)
              <?php else: ?>
                Prévu : <?= number_format((float) $r['rx_dose'], 0, ',', ' ') ?> <?= e((string) $r['rx_unit']) ?>
              <?php endif; ?>
            </div>
            <?php if (!empty($r['comment'])): ?>
              <p class="text-footnote mt-1"><?= e((string) $r['comment']) ?></p>
            <?php endif; ?>
          </div>

          <span class="pc-badge <?= e((string) $disp['badge_class']) ?>"><?= e((string) $disp['label']) ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
