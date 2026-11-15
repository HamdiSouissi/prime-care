<?php

declare(strict_types=1);

$meal = is_array($meal ?? null) ? $meal : [];
$photosByKind = is_array($photosByKind ?? null) ? $photosByKind : ['avant' => null, 'apres' => null, 'autre' => null];
$linkedGlycemia = is_array($linkedGlycemia ?? null) ? $linkedGlycemia : ['before' => null, 'after' => null];
$editPerm = is_array($editPerm ?? null) ? $editPerm : ['allowed' => false];
$portion = (int) ($meal['portion_eaten'] ?? 100);
$role = (string) (auth()['role'] ?? 'caregiver');
$isViewer = $role === 'viewer';
?>
<!-- 1. Fiche détaillée du repas (E11) -->
<section class="pc-card">
  <div class="flex items-start justify-between gap-2 mb-2">
    <div>
      <h2 class="text-title-2"><?= e((string) $meal['type_label']) ?> · <?= e((string) $meal['time_local']) ?></h2>
      <p class="text-caption text-muted">
        Le <?= e((string) $meal['date_local']) ?> · Enregistré par <strong><?= e((string) $meal['author_first_name']) ?></strong>
      </p>
    </div>
    <span class="pc-badge <?= $portion >= 75 ? 'pc-badge--ok' : ($portion >= 50 ? 'pc-badge--warn' : 'pc-badge--danger') ?>">
      <?= $portion ?> % consommé
    </span>
  </div>

  <p class="text-body my-2"><?= e((string) ($meal['description'] ?: 'Repas photographié')) ?></p>

  <!-- Barre 4 segments (§8.2) -->
  <div class="pc-portion-bar my-2" aria-label="Quantité consommée : <?= $portion ?> %">
    <span class="pc-portion-bar__seg <?= $portion >= 25 ? 'is-filled' : '' ?>"></span>
    <span class="pc-portion-bar__seg <?= $portion >= 50 ? 'is-filled' : '' ?>"></span>
    <span class="pc-portion-bar__seg <?= $portion >= 75 ? 'is-filled' : '' ?>"></span>
    <span class="pc-portion-bar__seg <?= $portion >= 100 ? 'is-filled' : '' ?>"></span>
  </div>

  <div class="pc-grid-2 mt-3">
    <div class="pc-card pc-card--subtle">
      <div class="text-caption text-muted">Glucides estimés</div>
      <div class="text-headline font-mono"><?= $meal['carbs_g'] !== null ? (int) $meal['carbs_g'] . ' g' : 'Non renseigné' ?></div>
    </div>
    <div class="pc-card pc-card--subtle">
      <div class="text-caption text-muted">Boisson / Hydratation</div>
      <div class="text-headline"><?= e((string) ($meal['drink_type'] ?: 'Eau plate')) ?></div>
    </div>
  </div>

  <?php if (!empty($meal['comment'])): ?>
    <p class="text-footnote text-muted mt-2">Observation : <?= e((string) $meal['comment']) ?></p>
  <?php endif; ?>
</section>

<!-- 2. Photos Avant / Après côte à côte (§8.2) -->
<section class="pc-card" aria-labelledby="meal-photos-heading">
  <h3 class="text-headline mb-2" id="meal-photos-heading">Photos du plateau (Avant / Après)</h3>
  <div class="pc-grid-2">
    <?php foreach (['avant' => 'Avant le repas', 'apres' => 'Après le repas'] as $k => $lbl): ?>
      <?php $ph = $photosByKind[$k] ?? null; ?>
      <div class="pc-meal-photo-slot">
        <div class="text-caption font-semibold mb-1"><?= e($lbl) ?></div>
        <?php if ($ph): ?>
          <a href="/media/meals/<?= e(basename((string) $ph['photo_path'])) ?>" target="_blank" class="pc-meal-photo-frame">
            <img src="/media/meals/<?= e(basename((string) $ph['photo_path'])) ?>" alt="<?= e($lbl) ?>">
          </a>
        <?php else: ?>
          <div class="pc-meal-photo-empty">Aucune photo « <?= e($k) ?> »</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 3. Glycémies associées automatiquement (Avant <= 60 min & Après 90–150 min) -->
<section class="pc-card" aria-labelledby="meal-glycemia-heading">
  <h3 class="text-headline mb-2" id="meal-glycemia-heading">Impact glycémique associé</h3>
  <div class="pc-grid-2">
    <div class="pc-card pc-card--subtle">
      <div class="text-caption text-muted">Glycémie pré-prandiale (&le; 60 min avant)</div>
      <?php if ($linkedGlycemia['before']): ?>
        <div class="pc-metric-lg mt-1">
          <?= number_format((float) $linkedGlycemia['before']['value'], 2, ',', ' ') ?>
          <span class="pc-metric-unit">g/L</span>
        </div>
        <div class="text-caption text-muted">à <?= e((string) $linkedGlycemia['before']['time_local']) ?></div>
      <?php else: ?>
        <div class="text-subhead text-muted mt-1">Aucun relevé pré-prandial</div>
      <?php endif; ?>
    </div>

    <div class="pc-card pc-card--subtle">
      <div class="text-caption text-muted">Glycémie post-prandiale (+1h30 à +2h30)</div>
      <?php if ($linkedGlycemia['after']): ?>
        <div class="pc-metric-lg mt-1">
          <?= number_format((float) $linkedGlycemia['after']['value'], 2, ',', ' ') ?>
          <span class="pc-metric-unit">g/L</span>
        </div>
        <div class="text-caption text-muted">à <?= e((string) $linkedGlycemia['after']['time_local']) ?></div>
      <?php else: ?>
        <div class="text-subhead text-muted mt-1">Aucun relevé post-prandial</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 4. Correction du repas (règle des 30 minutes) -->
<?php if (!$isViewer && ($editPerm['allowed'] ?? false)): ?>
  <section class="pc-card" aria-labelledby="meal-edit-heading">
    <h3 class="text-headline mb-2" id="meal-edit-heading">Modifier la fiche repas</h3>
    <form method="POST" action="/meals/<?= (int) $meal['id'] ?>/update" enctype="multipart/form-data" class="pc-stack gap-2">
      <?= csrf_field() ?>
      <div class="pc-field">
        <label class="pc-label">Composition</label>
        <input type="text" name="description" value="<?= e((string) $meal['description']) ?>" class="pc-input" required>
      </div>
      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label">Quantité consommée (%)</label>
          <select name="portion_eaten" class="pc-input">
            <?php foreach ([0, 25, 50, 75, 100] as $p): ?>
              <option value="<?= $p ?>" <?= $portion === $p ? 'selected' : '' ?>><?= $p ?> %</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pc-field">
          <label class="pc-label">Glucides (g)</label>
          <input type="number" name="carbs_g" value="<?= e((string) ($meal['carbs_g'] ?? '')) ?>" class="pc-input font-mono">
        </div>
      </div>

      <?php if (!empty($editPerm['requires_reason'])): ?>
        <div class="pc-field">
          <label class="pc-label">Motif de modification (&gt; 30 min, obligatoire)</label>
          <input type="text" name="modified_reason" class="pc-input" placeholder="Ex. précision sur la portion consommée" required>
        </div>
      <?php endif; ?>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">Enregistrer les modifications</button>
    </form>
  </section>
<?php endif; ?>
