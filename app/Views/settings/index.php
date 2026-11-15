<?php

declare(strict_types=1);

$careItems = is_array($careItems ?? null) ? $careItems : [];
$favorites = is_array($favorites ?? null) ? $favorites : [];
$intakeWindowMin = (int) ($intakeWindowMin ?? 30);
$glycemiaUnit = (string) ($glycemiaUnit ?? 'g/L');
$attendanceSettings = is_array($attendanceSettings ?? null) ? $attendanceSettings : [
    'verification_mode' => 'gps',
    'radius_m' => 150,
    'require_clocked_in' => false,
    'home_latitude' => 48.7903600,
    'home_longitude' => 2.4555700,
];
$qrToken = is_array($activeQrToken ?? ($qrToken ?? null)) ? ($activeQrToken ?? $qrToken) : [];
$qrPayload = (string) ($qrToken['qr_payload'] ?? ($qrPayload ?? ''));
$qrSvg = (string) ($homeQrSvg ?? ($qrSvg ?? ''));
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

  <!-- 2. Pointage & Vérification de présence au domicile (E05 / Phase 3) -->
  <section class="pc-card" aria-labelledby="settings-attendance-heading">
    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
      <div>
        <h2 class="text-headline" id="settings-attendance-heading">Pointage &amp; Vérification de présence</h2>
        <p class="text-caption text-muted">
          Contrôle de présence lors du pointage uniquement (aucun suivi GPS en continu). Un pointage hors zone ou refusé n’est jamais bloqué : il passe « À valider ».
        </p>
      </div>
      <span class="pc-badge pc-badge--brand">Phase 3</span>
    </div>

    <form method="POST" action="/settings/attendance" class="pc-stack gap-3 mb-4">
      <?= csrf_field() ?>

      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="att-verification-mode">Mode de vérification à l’arrivée / au départ</label>
          <?php $vMode = (string) ($attendanceSettings['verification_mode'] ?? 'gps'); ?>
          <select name="verification_mode" id="att-verification-mode" class="pc-input">
            <option value="gps" <?= $vMode === 'gps' ? 'selected' : '' ?>>Position GPS au domicile (Haversine)</option>
            <option value="qr" <?= $vMode === 'qr' ? 'selected' : '' ?>>Scan du QR code affiché au domicile</option>
            <option value="both" <?= $vMode === 'both' ? 'selected' : '' ?>>GPS + Scan du QR code du domicile</option>
            <option value="none" <?= $vMode === 'none' ? 'selected' : '' ?>>Aucune vérification (déclaratif horodaté serveur)</option>
          </select>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="att-radius-m">Rayon GPS autorisé autour du domicile (mètres)</label>
          <input
            type="number"
            name="radius_m"
            id="att-radius-m"
            value="<?= (int) ($attendanceSettings['radius_m'] ?? 150) ?>"
            min="30"
            max="2000"
            class="pc-input font-mono"
            required
          >
          <span class="text-caption text-muted">Par défaut : 150 m. Au-delà, le pointage est marqué « Hors du domicile (X m) ».</span>
        </div>
      </div>

      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="att-lat">Latitude GPS du domicile (M. Benali)</label>
          <input
            type="text"
            name="home_latitude"
            id="att-lat"
            value="<?= e(number_format((float) ($attendanceSettings['home_latitude'] ?? 48.79036), 7, '.', '')) ?>"
            class="pc-input font-mono"
            required
          >
        </div>

        <div class="pc-field">
          <label class="pc-label" for="att-lon">Longitude GPS du domicile (M. Benali)</label>
          <input
            type="text"
            name="home_longitude"
            id="att-lon"
            value="<?= e(number_format((float) ($attendanceSettings['home_longitude'] ?? 2.45557), 7, '.', '')) ?>"
            class="pc-input font-mono"
            required
          >
        </div>
      </div>

      <div class="flex items-center justify-between py-2 border-t border-b">
        <div>
          <label for="require-clocked-in" class="text-subhead font-semibold cursor-pointer">
            Exiger d’être pointée en arrivée pour saisir des soins
          </label>
          <div class="text-caption text-muted">
            Si activé, une aide-soignante non pointée en arrivée reçoit « Pointez votre arrivée pour saisir » (403).
          </div>
        </div>
        <label class="pc-switch">
          <input
            type="checkbox"
            id="require-clocked-in"
            name="require_clocked_in"
            value="1"
            <?= !empty($attendanceSettings['require_clocked_in']) ? 'checked' : '' ?>
          >
          <span class="pc-switch__track">
            <span class="pc-switch__thumb"></span>
          </span>
        </label>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm self-start">
        Enregistrer les règles de pointage
      </button>
    </form>

    <!-- Affiche QR Code signé HMAC du domicile -->
    <div class="pc-qr-settings-box">
      <div class="pc-qr-settings-box__svg">
        <?= $qrSvg ?>
      </div>
      <div class="flex-1 min-w-0 pc-stack gap-2">
        <div class="text-subhead font-bold">
          QR code signé du domicile · <?= e((string) ($qrToken['label'] ?? 'Domicile M. Benali')) ?>
        </div>
        <p class="text-caption text-muted">
          À imprimer et afficher dans l’entrée du domicile (Créteil). Signé par HMAC-SHA256 et révocable à tout moment.
        </p>
        <div class="text-caption font-mono break-all bg-surface p-2 rounded">
          <?= e($qrPayload) ?>
        </div>
        <div class="flex items-center gap-2 flex-wrap mt-1">
          <a href="/settings/attendance/qr-print" target="_blank" rel="noopener" class="pc-btn pc-btn--tinted pc-btn--sm">
            <?= icon('printer', '', 15) ?>
            <span>Imprimer l’affiche A4</span>
          </a>
        </div>
      </div>
    </div>

    <form method="POST" action="/settings/attendance/qr-regenerate" class="mt-3 flex items-center gap-2 flex-wrap">
      <?= csrf_field() ?>
      <input type="text" name="label" class="pc-input pc-input--sm" placeholder="Libellé du nouveau QR (ex. Entrée principale)">
      <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--sm">
        <?= icon('refresh-cw', '', 14) ?>
        <span>Révoquer et régénérer le QR code</span>
      </button>
    </form>
  </section>

  <!-- 3. Liste des soins rapides configurables (1 tap) -->
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

  <!-- 4. Aliments favoris configurables -->
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
