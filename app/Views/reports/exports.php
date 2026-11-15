<?php

declare(strict_types=1);

$period = is_array($period ?? null) ? $period : [];
$availableExports = is_array($availableExports ?? null) ? $availableExports : [];
$userRole = (string) ($userRole ?? 'viewer');
$currentPeriod = (string) ($period['period'] ?? '30d');
$fromYmd = (string) ($period['from_ymd'] ?? date('Y-m-d', strtotime('-29 days')));
$toYmd = (string) ($period['to_ymd'] ?? date('Y-m-d'));
?>
<!-- Barre de navigation Rapports / Exports -->
<section class="pc-card mb-3" aria-label="Navigation Rapports et Exports">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div class="pc-segmented">
      <a href="/reports?period=<?= e($currentPeriod) ?>" class="pc-segmented__btn">
        <?= icon('file-text', '', 15) ?>
        <span>Rapport médical</span>
      </a>
      <a href="/reports/exports?period=<?= e($currentPeriod) ?>" class="pc-segmented__btn is-active">
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
  </div>

  <!-- Filtre de période -->
  <form method="GET" action="/reports/exports" class="pc-grid-3 items-end">
    <input type="hidden" name="period" value="custom">
    <div class="pc-field">
      <label class="pc-label" for="export-from-input">Du (date de début)</label>
      <input type="date" id="export-from-input" name="from" value="<?= e($fromYmd) ?>" class="pc-input font-mono">
    </div>
    <div class="pc-field">
      <label class="pc-label" for="export-to-input">Au (date de fin)</label>
      <input type="date" id="export-to-input" name="to" value="<?= e($toYmd) ?>" class="pc-input font-mono">
    </div>
    <button type="submit" class="pc-btn pc-btn--tinted pc-btn--sm">Filtrer la période</button>
  </form>
  <p class="text-caption text-muted mt-2">
    Format : CSV UTF-8 avec BOM, séparateur point-virgule (<code>;</code>), dates en heure de Paris (<code>JJ/MM/AAAA HH:MM</code>) et nombres avec virgule décimale. Chaque export est tracé dans le journal d’audit.
  </p>
</section>

<div id="pc-export-feedback" class="pc-banner pc-banner--ok mb-3" role="status" aria-live="polite" hidden>
  <?= icon('check-circle', '', 18) ?>
  <span id="pc-export-feedback-text">Export CSV généré et téléchargé avec succès (UTF-8 avec BOM, séparateur ;).</span>
</div>

<!-- Liste des exports CSV disponibles selon le rôle (§3) -->
<section aria-labelledby="exports-list-heading">
  <h2 class="pc-section-header" id="exports-list-heading">
    Exports CSV disponibles · Période du <?= e(format_date_fr($fromYmd)) ?> au <?= e(format_date_fr($toYmd)) ?>
  </h2>

  <div class="pc-stack gap-2">
    <?php foreach ($availableExports as $typeKey => $meta): ?>
      <?php
      $downloadUrl = route('reports.export_csv', ['type' => (string) $typeKey])
          . '?period=custom&from=' . urlencode($fromYmd) . '&to=' . urlencode($toYmd);
      ?>
      <article class="pc-card flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3 flex-1 min-w-0">
          <span class="pc-list-icon pc-list-icon--brand">
            <?= icon((string) $meta['icon'], '', 18) ?>
          </span>
          <div class="min-w-0 flex-1">
            <h3 class="text-subhead font-bold"><?= e((string) $meta['label']) ?></h3>
            <p class="text-caption text-muted mt-1"><?= e((string) $meta['description']) ?></p>
          </div>
        </div>

        <a href="<?= e($downloadUrl) ?>" class="pc-btn pc-btn--primary pc-btn--sm" data-export-csv="<?= e((string) $meta['label']) ?>" data-export-type="<?= e((string) $typeKey) ?>">
          <?= icon('download', '', 15) ?>
          <span>Exporter CSV</span>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
