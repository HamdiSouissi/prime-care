<?php

declare(strict_types=1);

$measureTypes = is_array($measureTypes ?? null) ? $measureTypes : [];
$schedules = is_array($schedules ?? null) ? $schedules : [];
?>
<div class="pc-stack gap-4">
  <!-- 1. Configuration des seuils par type de mesure (E21) -->
  <section aria-labelledby="admin-thresholds-heading">
    <div class="pc-section-header" id="admin-thresholds-heading">Seuils d’alerte &amp; Bornes de plausibilité</div>
    <div class="pc-stack gap-3">
      <?php foreach ($measureTypes as $mt): ?>
        <form method="POST" action="/admin/measures/types/<?= (int) $mt['id'] ?>/update" class="pc-card pc-stack gap-2">
          <?= csrf_field() ?>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-headline"><?= e((string) $mt['label']) ?> (<?= e((string) $mt['unit']) ?>)</div>
              <div class="text-caption text-muted">Code : <code><?= e((string) $mt['code']) ?></code><?= !empty($mt['is_custom']) ? ' · Personnalisé' : ' · Natif' ?></div>
            </div>
            <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">Enregistrer</button>
          </div>

          <div class="pc-grid-3">
            <div class="pc-field">
              <label class="pc-label">Critique bas</label>
              <input type="text" name="crit_low" value="<?= e((string) ($mt['crit_low'] ?? '')) ?>" class="pc-input font-mono">
            </div>
            <div class="pc-field">
              <label class="pc-label">Seuil bas</label>
              <input type="text" name="low" value="<?= e((string) ($mt['low'] ?? '')) ?>" class="pc-input font-mono">
            </div>
            <div class="pc-field">
              <label class="pc-label">Seuil haut</label>
              <input type="text" name="high" value="<?= e((string) ($mt['high'] ?? '')) ?>" class="pc-input font-mono">
            </div>
            <div class="pc-field">
              <label class="pc-label">Critique haut</label>
              <input type="text" name="crit_high" value="<?= e((string) ($mt['crit_high'] ?? '')) ?>" class="pc-input font-mono">
            </div>
            <div class="pc-field">
              <label class="pc-label">Min plausible</label>
              <input type="text" name="min_value" value="<?= e((string) $mt['min_value']) ?>" class="pc-input font-mono" required>
            </div>
            <div class="pc-field">
              <label class="pc-label">Max plausible</label>
              <input type="text" name="max_value" value="<?= e((string) $mt['max_value']) ?>" class="pc-input font-mono" required>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 2. Créer un type de mesure personnalisé -->
  <section class="pc-card" aria-labelledby="admin-custom-type-heading">
    <h2 class="text-headline mb-2" id="admin-custom-type-heading">Ajouter un type de mesure personnalisé</h2>
    <form method="POST" action="/admin/measures/types" class="pc-stack gap-2">
      <?= csrf_field() ?>
      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="custom-mt-label">Libellé (ex. Tour de mollet, Débit expiratoire)</label>
          <input type="text" name="label" id="custom-mt-label" class="pc-input" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="custom-mt-unit">Unité (ex. cm, L/min)</label>
          <input type="text" name="unit" id="custom-mt-unit" class="pc-input" required>
        </div>
      </div>

      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label">Décimales (0 à 2)</label>
          <input type="number" name="decimals" value="1" min="0" max="2" class="pc-input">
        </div>
        <div class="pc-field">
          <label class="pc-label">Seuil bas</label>
          <input type="text" name="low" class="pc-input font-mono">
        </div>
        <div class="pc-field">
          <label class="pc-label">Seuil haut</label>
          <input type="text" name="high" class="pc-input font-mono">
        </div>
      </div>

      <input type="hidden" name="is_secondary" value="1">
      <button type="submit" class="pc-btn pc-btn--tinted">Ajouter ce type de mesure</button>
    </form>
  </section>

  <!-- 3. Mesures attendues programmées -->
  <section class="pc-card" aria-labelledby="admin-schedules-heading">
    <h2 class="text-headline mb-2" id="admin-schedules-heading">Horaires des mesures attendues (Aujourd’hui)</h2>
    <div class="pc-stack gap-2 mb-3">
      <?php foreach ($schedules as $sc): ?>
        <div class="flex items-center justify-between py-2 border-b">
          <div>
            <strong><?= e(substr((string) $sc['scheduled_time'], 0, 5)) ?></strong>
            · <?= e((string) ($sc['label'] ?: $sc['type_label'])) ?>
          </div>
          <span class="pc-badge pc-badge--brand"><?= e(\App\Services\TimelineService::contextLabel((string) $sc['context'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="/admin/measures/schedules" class="pc-grid-3 items-end">
      <?= csrf_field() ?>
      <div class="pc-field">
        <label class="pc-label">Constante</label>
        <select name="measure_type_id" class="pc-input">
          <?php foreach ($measureTypes as $mt): ?>
            <option value="<?= (int) $mt['id'] ?>"><?= e((string) $mt['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pc-field">
        <label class="pc-label">Heure (HH:MM)</label>
        <input type="time" name="scheduled_time" value="14:00" class="pc-input" required>
      </div>
      <button type="submit" class="pc-btn pc-btn--tinted">Planifier</button>
    </form>
  </section>
</div>
