<?php

declare(strict_types=1);

$patient = is_array($patient ?? null) ? $patient : [];
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
    'late_tolerance_min' => 15,
    'missing_alert_delay_min' => 15,
    'night_surcharge_pct' => 25,
    'sunday_surcharge_pct' => 50,
    'holiday_surcharge_pct' => 100,
];
$alertDefaults = is_array($alertDefaults ?? null) ? $alertDefaults : [
    'escalation_delay_min' => 30,
    'repeat_interval_min' => 15,
    'default_quiet_start' => '22:00',
    'default_quiet_end' => '07:00',
];
$smtpSettings = is_array($smtpSettings ?? null) ? $smtpSettings : [
    'host' => '127.0.0.1',
    'port' => 1025,
    'username' => '',
    'encryption' => 'tls',
    'from_email' => 'alertes@prime-care.fr',
    'from_name' => 'Prime Care',
];
$appSettings = is_array($appSettings ?? null) ? $appSettings : [
    'app_name' => 'Prime Care',
    'timezone' => 'Europe/Paris',
    'date_format' => 'DD/MM/YYYY HH:mm',
];
$retentionSettings = is_array($retentionSettings ?? null) ? $retentionSettings : [
    'medical_auto_purge' => false,
    'attendances_years' => 5,
    'tech_logs_months' => 12,
    'handled_alerts_months' => 24,
    'audit_logs_years' => 6,
];

$qrToken = is_array($activeQrToken ?? ($qrToken ?? null)) ? ($activeQrToken ?? $qrToken) : [];
$qrPayload = (string) ($qrToken['qr_payload'] ?? ($qrPayload ?? ''));
$qrSvg = (string) ($homeQrSvg ?? ($qrSvg ?? ''));
?>

<div class="pc-stack gap-4">

  <!-- 1. Groupe iOS : Dossier Patient & Traitements (Raccourcis directs) -->
  <div>
    <h2 class="pc-section-header">Patient &amp; Traitements</h2>
    <div class="pc-group">
      <a href="/patient" class="pc-group__row">
        <span class="pc-list-icon pc-list-icon--brand">
          <?= icon('user', '', 20) ?>
        </span>
        <div class="pc-group__body">
          <div class="pc-group__label">Fiche patient — <?= e(trim((string) ($patient['first_name'] ?? 'Henri') . ' ' . (string) ($patient['last_name'] ?? 'Benali'))) ?></div>
          <div class="pc-group__sub">Identité, protocole d’urgence, contacts médicaux et familiaux</div>
        </div>
        <span class="pc-group__chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/treatments" class="pc-group__row">
        <span class="pc-list-icon pc-list-icon--meds">
          <?= icon('pill', '', 20) ?>
        </span>
        <div class="pc-group__body">
          <div class="pc-group__label">Traitements &amp; Ordonnances</div>
          <div class="pc-group__sub">Catalogue de médicaments, posologies, insuline et ordonnances jointes</div>
        </div>
        <span class="pc-group__chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    </div>
  </div>

  <!-- 2. Mesures et seuils (hypoglycémie, hyperglycémie, cibles par moment de la journée) -->
  <section class="pc-card" aria-labelledby="settings-thresholds-heading">
    <h2 class="text-headline mb-1" id="settings-thresholds-heading">Mesures et seuils glycémiques</h2>
    <p class="text-caption text-muted mb-3">
      Seuils d’hypoglycémie, d’hyperglycémie et cibles selon le moment de la journée (à jeun vs post-prandial).
    </p>
    <form method="POST" action="/settings/thresholds" class="pc-stack gap-3">
      <?= csrf_field() ?>
      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label" for="th-hypo-crit">Hypoglycémie critique (g/L)</label>
          <input id="th-hypo-crit" type="number" step="0.01" name="threshold_hypo_critical" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['threshold_hypo_critical'] ?? 0.54), 2, '.', '')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="th-hypo">Seuil d’hypoglycémie (g/L)</label>
          <input id="th-hypo" type="number" step="0.01" name="threshold_hypo" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['threshold_hypo'] ?? 0.70), 2, '.', '')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="th-fast-min">Cible à jeun min. (g/L)</label>
          <input id="th-fast-min" type="number" step="0.01" name="target_glycemia_fasting_min" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.80), 2, '.', '')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="th-fast-max">Cible à jeun max. (g/L)</label>
          <input id="th-fast-max" type="number" step="0.01" name="target_glycemia_fasting_max" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.30), 2, '.', '')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="th-post-max">Cible post-prandiale max. (g/L)</label>
          <input id="th-post-max" type="number" step="0.01" name="target_glycemia_postprandial_max" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['target_glycemia_postprandial_max'] ?? 1.80), 2, '.', '')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="th-hyper">Seuil d’hyperglycémie (g/L)</label>
          <input id="th-hyper" type="number" step="0.01" name="threshold_hyper" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['threshold_hyper'] ?? 2.50), 2, '.', '')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="th-hyper-crit">Hyperglycémie critique (g/L)</label>
          <input id="th-hyper-crit" type="number" step="0.01" name="threshold_hyper_critical" class="pc-input font-mono" value="<?= e(number_format((float) ($patient['threshold_hyper_critical'] ?? 3.00), 2, '.', '')) ?>" required>
        </div>
      </div>
      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm self-start">Enregistrer les seuils</button>
    </form>
  </section>

  <!-- 3. Soins rapides (ajout, ordre, activation) -->
  <section class="pc-card" aria-labelledby="settings-care-heading">
    <h2 class="text-headline mb-2" id="settings-care-heading">Soins rapides (1 tap)</h2>
    <div class="pc-stack gap-2 mb-3">
      <?php foreach ($careItems as $ci): ?>
        <?php $isActive = (int) ($ci['is_active'] ?? 1) === 1; ?>
        <div class="flex items-center justify-between py-2 border-b gap-2 flex-wrap">
          <div class="flex items-center gap-2">
            <?= icon((string) ($ci['icon_name'] ?: 'check-circle'), 'text-notes', 18) ?>
            <span>
              <strong><?= e((string) $ci['label']) ?></strong>
              <span class="text-caption text-muted"> · Ordre #<?= (int) ($ci['sort_order'] ?? 10) ?></span>
            </span>
            <span class="pc-badge pc-badge--<?= $isActive ? 'ok' : 'neutral' ?>">
              <?= $isActive ? 'Actif' : 'Inactif' ?>
            </span>
          </div>
          <div class="flex items-center gap-2">
            <form method="POST" action="/settings/care-items/<?= (int) $ci['id'] ?>/toggle" class="flex items-center gap-1">
              <?= csrf_field() ?>
              <input type="number" name="sort_order" value="<?= (int) ($ci['sort_order'] ?? 10) ?>" min="1" max="999" class="pc-input pc-input--sm font-mono" aria-label="Ordre d’affichage">
              <button type="submit" class="pc-btn pc-btn--secondary pc-btn--sm">
                <?= $isActive ? 'Désactiver' : 'Activer' ?>
              </button>
            </form>
            <form method="POST" action="/settings/care-items/<?= (int) $ci['id'] ?>/delete">
              <?= csrf_field() ?>
              <button type="submit" class="pc-btn pc-btn--ghost pc-btn--sm text-danger">Retirer</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="/settings/care-items" class="pc-grid-3 items-end">
      <?= csrf_field() ?>
      <div class="pc-field">
        <label class="pc-label" for="new-care-label">Nouveau soin rapide</label>
        <input id="new-care-label" type="text" name="label" class="pc-input" placeholder="Ex. Prévention escarres" required>
      </div>
      <div class="pc-field">
        <label class="pc-label" for="new-care-cat">Catégorie &amp; Ordre</label>
        <div class="flex gap-2">
          <select id="new-care-cat" name="category" class="pc-input">
            <option value="hygiene">Hygiène</option>
            <option value="confort">Confort</option>
            <option value="mobilite">Mobilité</option>
            <option value="medical">Médical</option>
          </select>
          <input type="number" name="sort_order" value="20" min="1" max="999" class="pc-input font-mono" aria-label="Ordre">
        </div>
      </div>
      <button type="submit" class="pc-btn pc-btn--tinted">Ajouter le soin</button>
    </form>
  </section>

  <!-- 4. Pointage (GPS, rayon, tolérance retard, délai alerte non-pointage, majorations nuit/dimanche/férié, QR code) -->
  <section class="pc-card" aria-labelledby="settings-attendance-heading">
    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
      <div>
        <h2 class="text-headline" id="settings-attendance-heading">Pointage &amp; Règles RH</h2>
        <p class="text-caption text-muted">
          Coordonnées GPS du domicile, rayon, tolérance de retard, délai d’alerte de non-pointage, majorations et QR code.
        </p>
      </div>
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

      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label" for="att-late-tol">Tolérance de retard (min)</label>
          <input id="att-late-tol" type="number" name="late_tolerance_min" min="0" max="120" class="pc-input font-mono" value="<?= (int) ($attendanceSettings['late_tolerance_min'] ?? 15) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="att-missing-delay">Délai alerte non-pointage (min)</label>
          <input id="att-missing-delay" type="number" name="missing_alert_delay_min" min="5" max="120" class="pc-input font-mono" value="<?= (int) ($attendanceSettings['missing_alert_delay_min'] ?? 15) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="att-night-pct">Majoration nuit 21h–06h (%)</label>
          <input id="att-night-pct" type="number" name="night_surcharge_pct" min="0" max="200" class="pc-input font-mono" value="<?= (int) ($attendanceSettings['night_surcharge_pct'] ?? 25) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="att-sun-pct">Majoration dimanche (%)</label>
          <input id="att-sun-pct" type="number" name="sunday_surcharge_pct" min="0" max="200" class="pc-input font-mono" value="<?= (int) ($attendanceSettings['sunday_surcharge_pct'] ?? 50) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="att-hol-pct">Majoration jour férié (%)</label>
          <input id="att-hol-pct" type="number" name="holiday_surcharge_pct" min="0" max="200" class="pc-input font-mono" value="<?= (int) ($attendanceSettings['holiday_surcharge_pct'] ?? 100) ?>">
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
      <input type="text" name="label" class="pc-input pc-input--sm" placeholder="Libellé du nouveau QR (ex. Entrée principale)" aria-label="Libellé du nouveau QR code">
      <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--sm">
        <?= icon('refresh-cw', '', 14) ?>
        <span>Révoquer et régénérer le QR code</span>
      </button>
    </form>
  </section>

  <!-- 5. Alertes (délais de relance, heures calmes par défaut) -->
  <section class="pc-card" aria-labelledby="settings-alerts-heading">
    <h2 class="text-headline mb-1" id="settings-alerts-heading">Alertes &amp; Escalades</h2>
    <p class="text-caption text-muted mb-3">Délais de relance automatique et plage d’heures calmes par défaut (les alertes critiques ignorent toujours les heures calmes).</p>
    <form method="POST" action="/settings/alerts" class="pc-stack gap-3">
      <?= csrf_field() ?>
      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="alert-esc-delay">Délai d’escalade vers le responsable (min)</label>
          <input id="alert-esc-delay" type="number" name="escalation_delay_min" min="5" max="240" class="pc-input font-mono" value="<?= (int) ($alertDefaults['escalation_delay_min'] ?? 30) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="alert-rep-int">Délai de relance si non acquittée (min)</label>
          <input id="alert-rep-int" type="number" name="repeat_interval_min" min="5" max="120" class="pc-input font-mono" value="<?= (int) ($alertDefaults['repeat_interval_min'] ?? 15) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="alert-quiet-start">Heures calmes par défaut — Début</label>
          <input id="alert-quiet-start" type="time" name="default_quiet_start" class="pc-input font-mono" value="<?= e((string) ($alertDefaults['default_quiet_start'] ?? '22:00')) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="alert-quiet-end">Heures calmes par défaut — Fin</label>
          <input id="alert-quiet-end" type="time" name="default_quiet_end" class="pc-input font-mono" value="<?= e((string) ($alertDefaults['default_quiet_end'] ?? '07:00')) ?>">
        </div>
      </div>
      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm self-start">Enregistrer les réglages d’alertes</button>
    </form>
  </section>

  <!-- 6. E-mail (SMTP & bouton « Envoyer un e-mail de test ») -->
  <section class="pc-card" aria-labelledby="settings-smtp-heading">
    <h2 class="text-headline mb-1" id="settings-smtp-heading">E-mail (SMTP)</h2>
    <p class="text-caption text-muted mb-3">Configuration du serveur d’envoi des alertes critiques, synthèses quotidiennes et réinitialisations de mot de passe.</p>
    <form method="POST" action="/settings/smtp" class="pc-stack gap-3 mb-3">
      <?= csrf_field() ?>
      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label" for="smtp-host">Serveur SMTP</label>
          <input id="smtp-host" type="text" name="host" class="pc-input font-mono" value="<?= e((string) ($smtpSettings['host'] ?? '127.0.0.1')) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="smtp-port">Port</label>
          <input id="smtp-port" type="number" name="port" class="pc-input font-mono" value="<?= (int) ($smtpSettings['port'] ?? 587) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="smtp-enc">Chiffrement</label>
          <select id="smtp-enc" name="encryption" class="pc-input">
            <option value="tls" <?= ($smtpSettings['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (Recommandé)</option>
            <option value="ssl" <?= ($smtpSettings['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL / TLS</option>
            <option value="none" <?= ($smtpSettings['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Aucun (local)</option>
          </select>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="smtp-user">Identifiant SMTP</label>
          <input id="smtp-user" type="text" name="username" class="pc-input" value="<?= e((string) ($smtpSettings['username'] ?? '')) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="smtp-from-email">Adresse d’expédition</label>
          <input id="smtp-from-email" type="email" name="from_email" class="pc-input" value="<?= e((string) ($smtpSettings['from_email'] ?? 'alertes@prime-care.fr')) ?>">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="smtp-from-name">Nom d’expéditeur</label>
          <input id="smtp-from-name" type="text" name="from_name" class="pc-input" value="<?= e((string) ($smtpSettings['from_name'] ?? 'Prime Care')) ?>">
        </div>
      </div>
      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm self-start">Enregistrer la configuration SMTP</button>
    </form>

    <form method="POST" action="/settings/smtp/test" class="flex items-center gap-2 flex-wrap pt-2 border-t">
      <?= csrf_field() ?>
      <input type="email" name="test_email" class="pc-input pc-input--sm" placeholder="Destinataire du test (optionnel)" aria-label="Adresse e-mail de test">
      <button type="submit" class="pc-btn pc-btn--secondary pc-btn--sm">
        <?= icon('mail', '', 15) ?>
        <span>Envoyer un e-mail de test</span>
      </button>
    </form>
  </section>

  <!-- 7. Application (nom, fuseau horaire, format de date, fenêtre de prise, unité glycémie) -->
  <section class="pc-card" aria-labelledby="settings-general-heading">
    <h2 class="text-headline mb-2" id="settings-general-heading">Application &amp; Préférences d’affichage</h2>
    <form method="POST" action="/settings/general" class="pc-stack gap-3">
      <?= csrf_field() ?>
      <div class="pc-grid-3">
        <div class="pc-field">
          <label class="pc-label" for="setting-app-name">Nom de l’application</label>
          <input id="setting-app-name" type="text" name="app_name" class="pc-input" value="<?= e((string) ($appSettings['app_name'] ?? 'Prime Care')) ?>" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="setting-tz">Fuseau horaire</label>
          <select id="setting-tz" name="timezone" class="pc-input">
            <option value="Europe/Paris" <?= ($appSettings['timezone'] ?? 'Europe/Paris') === 'Europe/Paris' ? 'selected' : '' ?>>Europe/Paris (CET/CEST)</option>
            <option value="UTC" <?= ($appSettings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
          </select>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="setting-date-fmt">Format de date</label>
          <select id="setting-date-fmt" name="date_format" class="pc-input">
            <option value="DD/MM/YYYY HH:mm" <?= ($appSettings['date_format'] ?? '') === 'DD/MM/YYYY HH:mm' ? 'selected' : '' ?>>JJ/MM/AAAA HH:MM (France)</option>
            <option value="YYYY-MM-DD HH:mm" <?= ($appSettings['date_format'] ?? '') === 'YYYY-MM-DD HH:mm' ? 'selected' : '' ?>>AAAA-MM-JJ HH:MM (ISO)</option>
          </select>
        </div>
      </div>

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

  <!-- 8. Conservation des données & RGPD (§5.2) -->
  <section class="pc-card" aria-labelledby="settings-retention-heading">
    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
      <div>
        <h2 class="text-headline" id="settings-retention-heading">Conservation des données &amp; RGPD</h2>
        <p class="text-caption text-muted">
          La tâche planifiée <code class="font-mono">retention:purge</code> effectue une suppression logique puis physique après 30 jours, tracée dans l’audit.
        </p>
      </div>
      <span class="pc-badge pc-badge--ok">Données médicales protégées</span>
    </div>

    <form method="POST" action="/settings/retention" class="pc-stack gap-3 mb-4">
      <?= csrf_field() ?>

      <div class="pc-field">
        <label class="pc-label" for="ret-med-readonly">Données médicales (constantes, prises, repas, journal, soins, documents)</label>
        <input id="ret-med-readonly" type="text" class="pc-input" value="Jamais purgées automatiquement (conservation médicale intégrale)" disabled readonly>
      </div>

      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="ret-att">Pointages &amp; présence (années)</label>
          <input id="ret-att" type="number" name="attendances_years" min="1" max="20" class="pc-input font-mono" value="<?= (int) ($retentionSettings['attendances_years'] ?? 5) ?>" required>
          <span class="text-caption text-muted">Valeur réglementaire par défaut : 5 ans.</span>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="ret-tech">Journaux techniques &amp; tentatives de connexion (mois)</label>
          <input id="ret-tech" type="number" name="tech_logs_months" min="1" max="120" class="pc-input font-mono" value="<?= (int) ($retentionSettings['tech_logs_months'] ?? 12) ?>" required>
          <span class="text-caption text-muted">Valeur par défaut : 12 mois.</span>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="ret-alerts">Alertes traitées (mois)</label>
          <input id="ret-alerts" type="number" name="handled_alerts_months" min="1" max="120" class="pc-input font-mono" value="<?= (int) ($retentionSettings['handled_alerts_months'] ?? 24) ?>" required>
          <span class="text-caption text-muted">Valeur par défaut : 24 mois.</span>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="ret-audit">Journal d’audit médico-légal (années)</label>
          <input id="ret-audit" type="number" name="audit_logs_years" min="1" max="20" class="pc-input font-mono" value="<?= (int) ($retentionSettings['audit_logs_years'] ?? 6) ?>" required>
          <span class="text-caption text-muted">Valeur par défaut : 6 ans.</span>
        </div>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm self-start">Enregistrer les durées de conservation</button>
    </form>

    <div class="pt-3 border-t flex items-center justify-between gap-2 flex-wrap">
      <div>
        <div class="font-semibold">Portabilité intégrale du dossier patient (RGPD Art. 20)</div>
        <div class="text-caption text-muted">Télécharge une archive JSON complète contenant l’ensemble des données médicales et administratives du patient.</div>
      </div>
      <a href="/settings/export-patient-data" class="pc-btn pc-btn--secondary pc-btn--sm">
        <?= icon('download', '', 16) ?>
        <span>Exporter toutes les données du patient</span>
      </a>
    </div>
  </section>

  <!-- 9. Aliments favoris configurables -->
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
        <label class="pc-label" for="new-fav-label">Composition du menu favori</label>
        <input id="new-fav-label" type="text" name="label" class="pc-input" placeholder="Ex. Soupe de légumes + yaourt nature" required>
      </div>
      <div class="pc-grid-3 items-end">
        <div class="pc-field">
          <label class="pc-label" for="new-fav-carbs">Glucides estimés (g)</label>
          <input id="new-fav-carbs" type="number" name="default_carbs_g" class="pc-input font-mono" placeholder="30">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="new-fav-drink">Boisson par défaut</label>
          <input id="new-fav-drink" type="text" name="default_drink" class="pc-input" placeholder="Tisane sans sucre">
        </div>
        <button type="submit" class="pc-btn pc-btn--tinted">Ajouter aux favoris</button>
      </div>
    </form>
  </section>

</div>
