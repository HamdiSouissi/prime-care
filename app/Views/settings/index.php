<?php

declare(strict_types=1);

$careItems = is_array($careItems ?? null) ? $careItems : [];
$favorites = is_array($favorites ?? null) ? $favorites : [];
$intakeWindowMin = (int) ($intakeWindowMin ?? 30);
$glycemiaUnit = (string) ($glycemiaUnit ?? 'g/L');
?>
<div class="pc-stack gap-4">
  <!-- 1. Réglages généraux médicaux (Fenêtre de prise & Unité de glycémie) -->
  <section class="pc-card" aria-labelledby="settings-general-heading">
    <h2 class="text-headline mb-2" id="settings-general-heading">Paramètres généraux de suivi</h2>
    <form method="POST" action="/settings/general" class="pc-stack gap-3">
      <?= csrf_field() ?>
      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="setting-window-input">Fenêtre de prise « À donner » (&plusmn; minutes)</label>
          <input
            type="number"
            name="intake_window_min"
            id="setting-window-input"
            value="<?= $intakeWindowMin ?>"
            min="5"
            max="180"
            class="pc-input font-mono"
            required
          >
          <span class="text-caption text-muted">Après ce délai, une prise non validée passe au statut « En retard ».</span>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="setting-gly-unit">Unité principale d’affichage de la glycémie</label>
          <select name="glycemia_unit" id="setting-gly-unit" class="pc-input">
            <option value="g/L" <?= $glycemiaUnit === 'g/L' ? 'selected' : '' ?>>g/L (standard France · ex. 1,10 g/L)</option>
            <option value="mg/dL" <?= $glycemiaUnit === 'mg/dL' ? 'selected' : '' ?>>mg/dL (conversion auto &times; 100 · ex. 110 mg/dL)</option>
          </select>
        </div>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm self-start">Enregistrer les paramètres</button>
    </form>
  </section>

  <!-- 2. Liste des soins rapides configurables (1 tap) -->
  <section class="pc-card" aria-labelledby="settings-care-heading">
    <h2 class="text-headline mb-2" id="settings-care-heading">Soins rapides en 1 tap</h2>
    <div class="pc-stack gap-2 mb-3">
      <?php foreach ($careItems as $ci): ?>
        <div class="flex items-center justify-between py-2 border-b">
          <div class="flex items-center gap-2">
            <?= icon((string) ($ci['icon_name'] ?: 'check-circle'), 'text-notes', 18) ?>
            <span><strong><?= e((string) $ci['label']) ?></strong></span>
          </div>
          <form method="POST" action="/settings/care-items/<?= (int) $ci['id'] ?>/delete">
            <?= csrf_field() ?>
            <button type="submit" class="pc-btn pc-btn--ghost pc-btn--sm text-danger">Retirer</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="/settings/care-items" class="pc-grid-3 items-end">
      <?= csrf_field() ?>
      <div class="pc-field">
        <label class="pc-label">Nouveau soin rapide</label>
        <input type="text" name="label" class="pc-input" placeholder="Ex. Prévention escarres" required>
      </div>
      <div class="pc-field">
        <label class="pc-label">Catégorie</label>
        <select name="category" class="pc-input">
          <option value="hygiene">Hygiène</option>
          <option value="confort">Confort</option>
          <option value="mobilite">Mobilité</option>
          <option value="medical">Médical</option>
        </select>
      </div>
      <button type="submit" class="pc-btn pc-btn--tinted">Ajouter le soin</button>
    </form>
  </section>

  <!-- 3. Aliments favoris configurables -->
  <section class="pc-card" aria-labelledby="settings-fav-heading">
    <h2 class="text-headline mb-2" id="settings-fav-heading">Aliments &amp; menus favoris</h2>
    <div class="pc-stack gap-2 mb-3">
      <?php foreach ($favorites as $fav): ?>
        <div class="flex items-center justify-between py-2 border-b gap-2">
          <div>
            <div class="font-semibold"><?= e((string) $fav['label']) ?></div>
            <div class="text-caption text-muted">
              <?= $fav['default_carbs_g'] !== null ? (int) $fav['default_carbs_g'] . ' g glucides' : '' ?>
              <?= !empty($fav['default_drink']) ? ' · ' . e((string) $fav['default_drink']) : '' ?>
            </div>
          </div>
          <form method="POST" action="/settings/favorites/<?= (int) $fav['id'] ?>/delete">
            <?= csrf_field() ?>
            <button type="submit" class="pc-btn pc-btn--ghost pc-btn--sm text-danger">Retirer</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="/settings/favorites" class="pc-stack gap-2">
      <?= csrf_field() ?>
      <div class="pc-field">
        <label class="pc-label">Composition du menu favori</label>
        <input type="text" name="label" class="pc-input" placeholder="Ex. Soupe de légumes + yaourt nature" required>
      </div>
      <div class="pc-grid-3 items-end">
        <div class="pc-field">
          <label class="pc-label">Glucides estimés (g)</label>
          <input type="number" name="default_carbs_g" class="pc-input font-mono" placeholder="30">
        </div>
        <div class="pc-field">
          <label class="pc-label">Boisson par défaut</label>
          <input type="text" name="default_drink" class="pc-input" placeholder="Tisane sans sucre">
        </div>
        <button type="submit" class="pc-btn pc-btn--tinted">Ajouter aux favoris</button>
      </div>
    </form>
  </section>
</div>
