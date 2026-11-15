<?php

declare(strict_types=1);

$medications = is_array($medications ?? null) ? $medications : [];
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
?>
<div class="pc-stack gap-4">
  <!-- 1. Prescriptions actives & Grille de correction d'insuline (E20) -->
  <section aria-labelledby="admin-rx-heading">
    <div class="pc-section-header" id="admin-rx-heading">Prescriptions actives &amp; Grille de correction</div>
    <div class="pc-stack gap-3">
      <?php foreach ($prescriptions as $rx): ?>
        <?php
        $timeStrings = array_map(
            static fn (array $t): string => substr((string) $t['scheduled_time'], 0, 5),
            $rx['times'] ?? []
        );
        $corrRows = $rx['correction_rows'] ?? [];
        ?>
        <form method="POST" action="/admin/prescriptions/<?= (int) $rx['id'] ?>/update" class="pc-card pc-stack gap-2">
          <?= csrf_field() ?>
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="text-headline"><?= e((string) $rx['medication_name']) ?></div>
              <div class="text-caption text-muted">
                <?= e((string) ($rx['dci'] ?? '')) ?> · <?= e((string) $rx['form']) ?>
                <?= !empty($rx['as_needed']) ? ' · Si besoin (min ' . (int) $rx['min_interval_h'] . 'h)' : '' ?>
              </div>
            </div>
            <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">Mettre à jour</button>
          </div>

          <div class="pc-grid-3">
            <div class="pc-field">
              <label class="pc-label">Dose de base</label>
              <input type="text" name="dose" value="<?= e((string) $rx['dose']) ?>" class="pc-input font-mono" required>
            </div>
            <div class="pc-field">
              <label class="pc-label">Unité</label>
              <input type="text" name="unit" value="<?= e((string) $rx['unit']) ?>" class="pc-input" required>
            </div>
            <div class="pc-field">
              <label class="pc-label">Horaires (séparés par virgule)</label>
              <input
                type="text"
                name="scheduled_times"
                value="<?= e(implode(', ', $timeStrings)) ?>"
                class="pc-input font-mono"
                placeholder="08:00, 20:00"
                <?= !empty($rx['as_needed']) ? 'disabled' : '' ?>
              >
            </div>
          </div>

          <input type="hidden" name="dose_mode" value="<?= e((string) $rx['dose_mode']) ?>">
          <input type="hidden" name="is_active" value="<?= (int) $rx['is_active'] ?>">

          <?php if ((string) $rx['dose_mode'] === 'tableau'): ?>
            <div class="pc-card pc-card--subtle mt-1">
              <div class="text-footnote font-semibold mb-2">
                Éditeur du tableau de correction d’insuline du responsable (g/L &rsaquo; UI)
              </div>
              <div class="pc-stack gap-2">
                <?php foreach ($corrRows as $cRow): ?>
                  <div class="pc-grid-4">
                    <input type="text" name="corr_min[]" value="<?= e((string) $cRow['min']) ?>" class="pc-input font-mono" placeholder="Min g/L">
                    <input type="text" name="corr_max[]" value="<?= e((string) $cRow['max']) ?>" class="pc-input font-mono" placeholder="Max g/L">
                    <input type="text" name="corr_dose[]" value="<?= e((string) $cRow['dose']) ?>" class="pc-input font-mono" placeholder="Dose UI">
                    <input type="text" name="corr_note[]" value="<?= e((string) ($cRow['note'] ?? '')) ?>" class="pc-input" placeholder="Consigne">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($rx['document_path'])): ?>
            <div class="text-caption">
              Ordonnance jointe :
              <a href="/media/prescriptions/<?= e(basename((string) $rx['document_path'])) ?>" target="_blank" class="text-brand font-semibold">
                <?= e((string) ($rx['document_original_name'] ?: 'Voir l’ordonnance')) ?>
              </a>
            </div>
          <?php endif; ?>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 2. Ajouter une nouvelle prescription avec ordonnance jointe -->
  <section class="pc-card" aria-labelledby="admin-new-rx-heading">
    <h2 class="text-headline mb-2" id="admin-new-rx-heading">Nouvelle prescription</h2>
    <form method="POST" action="/admin/prescriptions" enctype="multipart/form-data" class="pc-stack gap-2">
      <?= csrf_field() ?>
      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label">Médicament</label>
          <select name="medication_id" class="pc-input" required>
            <?php foreach ($medications as $med): ?>
              <option value="<?= (int) $med['id'] ?>"><?= e((string) $med['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pc-field">
          <label class="pc-label">Dose</label>
          <input type="text" name="dose" value="1" class="pc-input font-mono" required>
        </div>
        <div class="pc-field">
          <label class="pc-label">Unité</label>
          <input type="text" name="unit" value="comprimé" class="pc-input" required>
        </div>
      </div>

      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label">Date de début</label>
          <input type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" class="pc-input" required>
        </div>
        <div class="pc-field">
          <label class="pc-label">Horaires (HH:MM, virgules)</label>
          <input type="text" name="scheduled_times" value="08:00, 20:00" class="pc-input font-mono">
        </div>
        <div class="pc-field">
          <label class="pc-label">Ordonnance (PDF / Image)</label>
          <input type="file" name="document" class="pc-input" accept="application/pdf,image/jpeg,image/png,image/webp">
        </div>
      </div>

      <div class="flex items-center gap-4">
        <label class="pc-checkbox-row">
          <input type="checkbox" name="ends_on_rx" value="1" checked>
          <span>Fin d’ordonnance continue</span>
        </label>
        <label class="pc-checkbox-row">
          <input type="checkbox" name="as_needed" value="1">
          <span>Prise « Si besoin »</span>
        </label>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary">Créer la prescription</button>
    </form>
  </section>

  <!-- 3. Ajouter un médicament au catalogue (avec photo de boîte) -->
  <section class="pc-card" aria-labelledby="admin-new-med-heading">
    <h2 class="text-headline mb-2" id="admin-new-med-heading">Ajouter un médicament au catalogue</h2>
    <form method="POST" action="/admin/medications" enctype="multipart/form-data" class="pc-stack gap-2">
      <?= csrf_field() ?>
      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label" for="new-med-name">Nom commercial</label>
          <input type="text" name="name" id="new-med-name" class="pc-input" placeholder="Ex. Kardégic 75 mg" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="new-med-dci">DCI</label>
          <input type="text" name="dci" id="new-med-dci" class="pc-input" placeholder="Acétylsalicylate de DL-lysine">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="new-med-form">Forme galénique</label>
          <select name="form" id="new-med-form" class="pc-input">
            <option value="comprimé">Comprimé</option>
            <option value="gélule">Gélule</option>
            <option value="stylo injectable">Stylo injectable</option>
            <option value="sachet">Sachet</option>
            <option value="solution buvable">Solution buvable</option>
            <option value="collyre">Collyre</option>
          </select>
        </div>
      </div>

      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label">Dosage par défaut</label>
          <input type="text" name="default_dosage" value="75" class="pc-input font-mono">
        </div>
        <div class="pc-field">
          <label class="pc-label">Unité</label>
          <input type="text" name="default_unit" value="mg" class="pc-input">
        </div>
        <div class="pc-field">
          <label class="pc-label">Photo de la boîte (optionnel)</label>
          <input type="file" name="box_photo" class="pc-input" accept="image/jpeg,image/png,image/webp">
        </div>
      </div>

      <button type="submit" class="pc-btn pc-btn--tinted">Ajouter au catalogue</button>
    </form>
  </section>
</div>
