<?php

declare(strict_types=1);

$period = is_array($period ?? null) ? $period : [];
$report = is_array($report ?? null) ? $report : [];
$patient = is_array($report['patient'] ?? null) ? $report['patient'] : [];
$gly = is_array($report['glycemia'] ?? null) ? $report['glycemia'] : [];
$otherVitals = is_array($report['other_vitals'] ?? null) ? $report['other_vitals'] : [];
$obs = is_array($report['observance'] ?? null) ? $report['observance'] : [];
$nutrition = is_array($report['nutrition'] ?? null) ? $report['nutrition'] : [];
$events = is_array($report['events'] ?? null) ? $report['events'] : [];
$userRole = (string) ($userRole ?? 'viewer');
$editorName = (string) ($editorName ?? 'Responsable');
$editedAtLocal = (string) ($editedAtLocal ?? '');

$patientName = trim(($patient['first_name'] ?? 'Henri') . ' ' . ($patient['last_name'] ?? 'Benali'));
$patientAge = isset($patient['birth_date']) ? calculate_age((string) $patient['birth_date']) : 84;
$diabetesType = (string) ($patient['diabetes_type'] ?? 'Diabète de type 2');
$fastingMin = number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.80), 2, ',', ' ');
$fastingMax = number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.30), 2, ',', ' ');
$postMealMax = number_format((float) ($patient['target_glycemia_postmeal_max'] ?? 1.80), 2, ',', ' ');
$hypoLim = number_format((float) ($patient['hypo_threshold_g_l'] ?? 0.70), 2, ',', ' ');
$hyperLim = number_format((float) ($patient['hyper_threshold_g_l'] ?? 2.50), 2, ',', ' ');

$currentPeriod = (string) ($period['period'] ?? '30d');
$fromYmd = (string) ($period['from_ymd'] ?? date('Y-m-d', strtotime('-29 days')));
$toYmd = (string) ($period['to_ymd'] ?? date('Y-m-d'));
?>
<!-- Barre d'outils (masquée à l'impression @media print) -->
<section class="pc-card mb-3 pc-no-print" aria-label="Sélection du rapport et de la période">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div class="pc-segmented">
      <a href="/reports?period=<?= e($currentPeriod) ?>" class="pc-segmented__btn is-active">
        <?= icon('file-text', '', 15) ?>
        <span>Rapport médical</span>
      </a>
      <a href="/reports/exports?period=<?= e($currentPeriod) ?>" class="pc-segmented__btn">
        <?= icon('download', '', 15) ?>
        <span>Exports</span>
      </a>
      <?php if (in_array($userRole, ['admin', 'coordinator'], true)): ?>
        <a href="/attendance/hours" class="pc-segmented__btn">
          <?= icon('clock', '', 15) ?>
          <span>Rapport RH</span>
        </a>
      <?php endif; ?>
    </div>

    <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-print-report>
      <?= icon('printer', '', 15) ?>
      <span>Imprimer / Enregistrer en PDF</span>
    </button>
  </div>

  <!-- Choix rapide de la période : 7 j, 14 j, 30 j, 90 j ou dates libres -->
  <div class="pc-chips-scroll mb-3" role="navigation" aria-label="Période du rapport">
    <?php foreach (['7d' => '7 jours', '14d' => '14 jours', '30d' => '30 jours', '90d' => '90 jours'] as $pKey => $pLbl): ?>
      <a
        href="/reports?period=<?= e($pKey) ?>"
        class="pc-chip <?= $currentPeriod === $pKey ? 'is-active' : '' ?>"
      >
        <span><?= e($pLbl) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="GET" action="/reports" class="pc-grid-3 items-end">
    <input type="hidden" name="period" value="custom">
    <div class="pc-field">
      <label class="pc-label" for="report-from-input">Du (date de début)</label>
      <input type="date" id="report-from-input" name="from" value="<?= e($fromYmd) ?>" class="pc-input font-mono">
    </div>
    <div class="pc-field">
      <label class="pc-label" for="report-to-input">Au (date de fin)</label>
      <input type="date" id="report-to-input" name="to" value="<?= e($toYmd) ?>" class="pc-input font-mono">
    </div>
    <button type="submit" class="pc-btn pc-btn--tinted pc-btn--sm">Appliquer les dates libres</button>
  </form>
</section>

<!-- ======================================================================
     RAPPORT MÉDICAL IMPRIMABLE A4 PORTRAIT (E23, §3.10 / Phase 4 §2.1)
     ====================================================================== -->
<article class="pc-printable-report" id="pc-medical-report">
  <!-- 1. EN-TÊTE DU RAPPORT -->
  <header class="pc-report-header pc-card mb-3">
    <div class="pc-report-header__top">
      <div class="pc-report-header__brand">
        <?= brand_svg('logo-complet', '', 180) ?>
        <div class="text-caption text-muted mt-1">Suivi de soins à domicile · Rapport médical de synthèse</div>
      </div>
      <div class="pc-report-header__meta">
        <div><strong>Période :</strong> <?= e((string) ($period['label'] ?? '')) ?></div>
        <div><strong>Date d’édition :</strong> <?= e($editedAtLocal) ?></div>
        <div><strong>Édité par :</strong> <?= e($editorName) ?></div>
      </div>
    </div>

    <div class="pc-report-patient-banner mt-3">
      <div>
        <div class="text-caption uppercase text-muted">Patient suivi</div>
        <div class="text-title-3 font-bold"><?= e($patientName) ?> · <?= $patientAge ?> ans</div>
        <div class="text-caption text-muted">
          Né le <?= e(format_date_fr((string) ($patient['birth_date'] ?? '1942-05-12'))) ?> · <?= e($diabetesType) ?>
        </div>
      </div>
      <div class="pc-report-targets">
        <div class="text-caption"><strong>Cibles glycémiques :</strong> À jeun <?= e($fastingMin) ?>–<?= e($fastingMax) ?> g/L · Post-prandial &le; <?= e($postMealMax) ?> g/L</div>
        <div class="text-caption"><strong>Seuils critiques :</strong> Hypoglycémie &lt; <?= e($hypoLim) ?> g/L · Hyperglycémie &gt; <?= e($hyperLim) ?> g/L</div>
      </div>
    </div>
  </header>

  <!-- 2. SYNTHÈSE GLYCÉMIQUE -->
  <section class="pc-card pc-report-section mb-3" aria-labelledby="rep-gly-heading">
    <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
      <div>
        <div class="text-caption uppercase font-semibold text-vitals">Section 1 · Équilibre glycémique</div>
        <h2 class="text-headline" id="rep-gly-heading">Synthèse glycémique (<?= (int) ($gly['count'] ?? 0) ?> mesures)</h2>
      </div>
      <span class="pc-badge <?= ($gly['in_target_pct'] ?? 0) >= 70 ? 'pc-badge--ok' : 'pc-badge--warn' ?> font-mono">
        Temps dans la cible : <?= number_format((float) ($gly['in_target_pct'] ?? 0), 1, ',', ' ') ?> %
      </span>
    </div>

    <!-- 5 indicateurs clés glycémiques -->
    <div class="pc-report-kpi-grid mb-3">
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Glycémie moyenne</span>
        <span class="pc-report-kpi__value font-mono">
          <?= $gly['avg'] !== null ? number_format((float) $gly['avg'], 2, ',', ' ') . ' g/L' : '—' ?>
        </span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Min / Max</span>
        <span class="pc-report-kpi__value font-mono">
          <?= $gly['min'] !== null ? number_format((float) $gly['min'], 2, ',', ' ') : '—' ?>
          /
          <?= $gly['max'] !== null ? number_format((float) $gly['max'], 2, ',', ' ') . ' g/L' : '—' ?>
        </span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Temps dans la cible</span>
        <span class="pc-report-kpi__value font-mono text-ok">
          <?= number_format((float) ($gly['in_target_pct'] ?? 0), 1, ',', ' ') ?> %
          <small class="text-caption text-muted">(<?= (int) ($gly['in_target_count'] ?? 0) ?>/<?= (int) ($gly['count'] ?? 0) ?>)</small>
        </span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Hypoglycémies (&lt; <?= e($hypoLim) ?>)</span>
        <span class="pc-report-kpi__value font-mono <?= ($gly['hypo_count'] ?? 0) > 0 ? 'text-danger' : '' ?>">
          <?= (int) ($gly['hypo_count'] ?? 0) ?>
        </span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Hyperglycémies (&gt; <?= e($hyperLim) ?>)</span>
        <span class="pc-report-kpi__value font-mono <?= ($gly['hyper_count'] ?? 0) > 0 ? 'text-warn' : '' ?>">
          <?= (int) ($gly['hyper_count'] ?? 0) ?>
        </span>
      </div>
    </div>

    <!-- Moyenne par moment de la journée -->
    <div class="mb-3">
      <h3 class="text-subhead font-bold mb-2">Moyenne par moment de la journée</h3>
      <div class="pc-report-kpi-grid">
        <?php foreach (($gly['by_context'] ?? []) as $ctxKey => $ctxData): ?>
          <div class="pc-report-kpi">
            <span class="pc-report-kpi__label"><?= e((string) $ctxData['label']) ?> (<?= (int) $ctxData['count'] ?>)</span>
            <span class="pc-report-kpi__value font-mono">
              <?= $ctxData['avg'] !== null ? number_format((float) $ctxData['avg'], 2, ',', ' ') . ' g/L' : '—' ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Courbe SVG sur la période avec bandes de seuils -->
    <div class="pc-report-chart-wrap">
      <div class="text-caption text-muted mb-1">
        Courbe glycémique sur la période (bande verte : cible <?= e($fastingMin) ?>–<?= e($postMealMax) ?> g/L · pointillés rouges : seuil hypo <?= e($hypoLim) ?> g/L · pointillés ambre : seuil hyper <?= e($hyperLim) ?> g/L)
      </div>
      <?= $gly['svg_chart'] ?? '' ?>
    </div>
  </section>

  <!-- 3. AUTRES CONSTANTES (Tension, Température, Poids, SpO2) -->
  <section class="pc-card pc-report-section mb-3" aria-labelledby="rep-vitals-heading">
    <div class="mb-2">
      <div class="text-caption uppercase font-semibold text-brand">Section 2 · Suivi hémodynamique et général</div>
      <h2 class="text-headline" id="rep-vitals-heading">Autres constantes vitales</h2>
    </div>

    <div class="pc-table-wrap" tabindex="0" role="region" aria-label="Constantes vitales">
      <table class="pc-table">
        <thead>
          <tr>
            <th>Constante</th>
            <th class="text-right">Moyenne</th>
            <th class="text-right">Min</th>
            <th class="text-right">Max</th>
            <th class="text-right">Dernière valeur</th>
            <th class="text-right">Évolution (courbe)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($otherVitals as $ov): ?>
            <tr>
              <td>
                <div class="font-semibold"><?= e((string) $ov['label']) ?></div>
                <div class="text-caption text-muted"><?= (int) $ov['count'] ?> relevé<?= (int) $ov['count'] > 1 ? 's' : '' ?></div>
              </td>
              <td class="text-right font-mono font-bold"><?= e((string) $ov['avg_formatted']) ?></td>
              <td class="text-right font-mono"><?= e((string) $ov['min_formatted']) ?></td>
              <td class="text-right font-mono"><?= e((string) $ov['max_formatted']) ?></td>
              <td class="text-right font-mono">
                <div><?= e((string) $ov['latest_formatted']) ?></div>
                <div class="text-caption text-muted"><?= e((string) $ov['latest_date']) ?></div>
              </td>
              <td class="text-right pc-report-sparkline-cell">
                <?= $ov['sparkline_svg'] ?? '' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- 4. OBSERVANCE DU TRAITEMENT & INSULINE -->
  <section class="pc-card pc-report-section mb-3" aria-labelledby="rep-obs-heading">
    <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
      <div>
        <div class="text-caption uppercase font-semibold text-meds">Section 3 · Thérapeutique &amp; Insulinothérapie</div>
        <h2 class="text-headline" id="rep-obs-heading">Observance du traitement</h2>
      </div>
      <span class="pc-badge <?= ($obs['global_rate_pct'] ?? 100) >= 90 ? 'pc-badge--ok' : 'pc-badge--warn' ?> font-mono">
        Taux global : <?= number_format((float) ($obs['global_rate_pct'] ?? 100), 1, ',', ' ') ?> %
        (<?= (int) ($obs['total_given'] ?? 0) ?>/<?= (int) ($obs['total_scheduled'] ?? 0) ?>)
      </span>
    </div>

    <div class="pc-table-wrap mb-3" tabindex="0" role="region" aria-label="Observance par médicament">
      <table class="pc-table">
        <thead>
          <tr>
            <th>Médicament</th>
            <th class="text-right">Prises données</th>
            <th class="text-right">Non données</th>
            <th class="text-right">Taux d’observance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($obs['by_medication'] ?? []) as $bm): ?>
            <tr>
              <td>
                <span class="font-semibold"><?= e((string) $bm['medication_name']) ?></span>
                <?php if (!empty($bm['as_needed'])): ?>
                  <span class="pc-badge pc-badge--neutral ml-1">Si besoin</span>
                <?php endif; ?>
              </td>
              <td class="text-right font-mono"><?= (int) $bm['given'] ?> / <?= (int) $bm['total'] ?></td>
              <td class="text-right font-mono"><?= (int) $bm['skipped'] ?></td>
              <td class="text-right font-mono font-bold"><?= number_format((float) $bm['rate_pct'], 1, ',', ' ') ?> %</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Synthèse Insuline (doses données et sites d'injection) -->
    <?php $ins = $obs['insulin'] ?? []; ?>
    <div class="pc-grid-2 mb-3">
      <div class="pc-report-subbox">
        <h3 class="text-subhead font-bold mb-2">Doses d’insuline administrées (<?= (int) ($ins['total_injections'] ?? 0) ?> injections)</h3>
        <ul class="text-caption pc-stack gap-1">
          <?php foreach (($ins['by_kind'] ?? []) as $kData): ?>
            <li>
              <strong><?= e((string) $kData['label']) ?> :</strong>
              <?= (int) $kData['count'] ?> injection<?= (int) $kData['count'] > 1 ? 's' : '' ?>
              · Total <?= number_format((float) $kData['total_units'], 1, ',', ' ') ?> UI
              (moy. <?= number_format((float) $kData['avg_units'], 1, ',', ' ') ?> UI/inj.)
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="pc-report-subbox">
        <h3 class="text-subhead font-bold mb-2">Rotation des sites d’injection</h3>
        <div class="flex items-center gap-2 flex-wrap">
          <?php foreach (($ins['by_site'] ?? []) as $sData): ?>
            <span class="pc-badge pc-badge--neutral font-mono">
              <?= e((string) $sData['label']) ?> : <?= (int) $sData['count'] ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Liste des « Non donné » avec leur motif -->
    <div>
      <h3 class="text-subhead font-bold mb-2">Liste des prises « Non donné » et motifs (<?= count($obs['skipped_list'] ?? []) ?>)</h3>
      <?php if (empty($obs['skipped_list'])): ?>
        <p class="text-caption text-muted">Aucune prise non donnée sur la période.</p>
      <?php else: ?>
        <div class="pc-table-wrap" tabindex="0" role="region" aria-label="Mesures critiques">
          <table class="pc-table">
            <thead>
              <tr>
                <th>Date &amp; Heure</th>
                <th>Médicament</th>
                <th>Motif</th>
                <th>Auteur</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($obs['skipped_list'] as $sk): ?>
                <tr>
                  <td class="font-mono"><?= e((string) $sk['date_local']) ?></td>
                  <td class="font-semibold"><?= e((string) $sk['medication_name']) ?></td>
                  <td><?= e((string) $sk['reason']) ?></td>
                  <td><?= e((string) $sk['author']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- 5. ALIMENTATION -->
  <section class="pc-card pc-report-section mb-3" aria-labelledby="rep-meals-heading">
    <div class="mb-2">
      <div class="text-caption uppercase font-semibold text-meals">Section 4 · Nutrition &amp; Apports</div>
      <h2 class="text-headline" id="rep-meals-heading">Alimentation &amp; Hydratation</h2>
    </div>

    <div class="pc-report-kpi-grid">
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Repas enregistrés</span>
        <span class="pc-report-kpi__value font-mono"><?= (int) ($nutrition['meals_count'] ?? 0) ?></span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Portion moyenne mangée</span>
        <span class="pc-report-kpi__value font-mono">
          <?= $nutrition['avg_portion_pct'] !== null ? number_format((float) $nutrition['avg_portion_pct'], 1, ',', ' ') . ' %' : '—' ?>
        </span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Glucides moyens / repas</span>
        <span class="pc-report-kpi__value font-mono">
          <?= $nutrition['avg_carbs_g'] !== null ? number_format((float) $nutrition['avg_carbs_g'], 1, ',', ' ') . ' g' : '—' ?>
        </span>
      </div>
      <div class="pc-report-kpi">
        <span class="pc-report-kpi__label">Boisson moyenne / repas</span>
        <span class="pc-report-kpi__value font-mono">
          <?= $nutrition['avg_drink_ml'] !== null ? number_format((float) $nutrition['avg_drink_ml'], 0, ',', ' ') . ' mL' : '—' ?>
        </span>
      </div>
    </div>
  </section>

  <!-- 6. ÉVÉNEMENTS NOTABLES -->
  <section class="pc-card pc-report-section mb-3" aria-labelledby="rep-events-heading">
    <div class="mb-2">
      <div class="text-caption uppercase font-semibold text-danger">Section 5 · Vigilance clinique</div>
      <h2 class="text-headline" id="rep-events-heading">Événements notables, incidents &amp; alertes critiques</h2>
    </div>

    <div class="pc-stack gap-3">
      <!-- Valeurs critiques -->
      <div>
        <h3 class="text-subhead font-bold mb-1">Valeurs critiques relevées (<?= count($events['critical_measures'] ?? []) ?>)</h3>
        <?php if (empty($events['critical_measures'])): ?>
          <p class="text-caption text-muted">Aucune valeur critique sur la période.</p>
        <?php else: ?>
          <ul class="text-caption pc-stack gap-1">
            <?php foreach ($events['critical_measures'] as $cm): ?>
              <li>
                <strong class="font-mono"><?= e(\App\Services\ScheduleService::utcToLocalParis((string) $cm['measured_at'], 'd/m/Y H:i')) ?></strong> —
                <?= e((string) $cm['type_label']) ?> :
                <strong><?= number_format((float) $cm['value'], 2, ',', ' ') ?> <?= e((string) ($cm['unit_recorded'] ?: $cm['type_unit'])) ?></strong>
                <?= !empty($cm['comment']) ? ' · ' . e((string) $cm['comment']) : '' ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <!-- Chutes & Incidents -->
      <div class="pc-grid-2">
        <div>
          <h3 class="text-subhead font-bold mb-1">Chutes signalées (<?= count($events['falls'] ?? []) ?>)</h3>
          <?php if (empty($events['falls'])): ?>
            <p class="text-caption text-muted">Aucune chute signalée sur la période.</p>
          <?php else: ?>
            <ul class="text-caption pc-stack gap-1">
              <?php foreach ($events['falls'] as $f): ?>
                <li>
                  <strong class="font-mono"><?= e(\App\Services\ScheduleService::utcToLocalParis((string) $f['created_at'], 'd/m/Y H:i')) ?></strong> —
                  <?= e((string) ($f['title'] ?: $f['content'])) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div>
          <h3 class="text-subhead font-bold mb-1">Incidents &amp; Transmissions urgentes (<?= count($events['incidents'] ?? []) + count($events['urgent_handovers'] ?? []) ?>)</h3>
          <?php $allInc = array_merge($events['incidents'] ?? [], $events['urgent_handovers'] ?? []); ?>
          <?php if ($allInc === []): ?>
            <p class="text-caption text-muted">Aucun incident ni transmission urgente.</p>
          <?php else: ?>
            <ul class="text-caption pc-stack gap-1">
              <?php foreach (array_slice($allInc, 0, 6) as $inc): ?>
                <li>
                  <strong class="font-mono"><?= e(\App\Services\ScheduleService::utcToLocalParis((string) $inc['created_at'], 'd/m/Y H:i')) ?></strong> —
                  <strong><?= e((string) ($inc['title'] ?: 'Note clinique')) ?> :</strong>
                  <?= e((string) $inc['content']) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <!-- Alertes critiques avec leur résolution -->
      <div>
        <h3 class="text-subhead font-bold mb-1">Alertes critiques et leur résolution (<?= count($events['critical_alerts'] ?? []) ?>)</h3>
        <?php if (empty($events['critical_alerts'])): ?>
          <p class="text-caption text-muted">Aucune alerte critique déclenchée sur la période.</p>
        <?php else: ?>
          <ul class="text-caption pc-stack gap-1">
            <?php foreach ($events['critical_alerts'] as $ca): ?>
              <li>
                <strong class="font-mono"><?= e(\App\Services\ScheduleService::utcToLocalParis((string) $ca['created_at'], 'd/m/Y H:i')) ?></strong> —
                <strong><?= e((string) $ca['title']) ?></strong>
                (Statut : <?= $ca['status'] === 'handled' ? 'Traitée' : 'Ouverte' ?>)
                <?= !empty($ca['resolution']) ? ' · Résolution : ' . e((string) $ca['resolution']) : '' ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- 7. PIED DE RAPPORT OBLIGATOIRE (§2.1) -->
  <footer class="pc-report-footer">
    <div class="pc-report-disclaimer">
      <?= icon('info', '', 15) ?>
      <span><strong>Document de suivi, ne remplace pas un avis médical.</strong></span>
    </div>
    <div class="pc-report-print-Signature">
      <span>Généré avec Prime Care — Prime Vision Solutions</span>
      <span class="pc-report-page-number">Page <span class="pc-page-counter">1 / 1</span></span>
    </div>
  </footer>
</article>
