<?php

declare(strict_types=1);

$user = is_array($user ?? null) ? $user : (auth() ?? []);
$theme = (string) ($user['theme'] ?? 'auto');
$role = (string) ($user['role'] ?? 'caregiver');
$hasPin = !empty($user['pin_hash']);
$trustedDevice = is_array($trustedDevice ?? null) ? $trustedDevice : null;
$authenticatedViaPassword = (bool) ($authenticatedViaPassword ?? false);
$avatarClass = avatar_color_class((int) ($user['id'] ?? 1));
$notificationSettings = is_array($notificationSettings ?? null) ? $notificationSettings : [
    'push_critical' => 1,
    'push_warning' => 1,
    'push_info' => 0,
    'email_critical' => 1,
    'email_warning' => 0,
    'email_info' => 0,
    'daily_digest_email' => 0,
    'quiet_hours_enabled' => 1,
    'quiet_start' => '22:00:00',
    'quiet_end' => '07:00:00',
];
$vapidPublicKey = (string) ($vapidPublicKey ?? '');
?>
<!-- 1. Photo de profil & Identité -->
<section class="pc-card flex flex-col items-center text-center gap-3">
  <div class="pc-avatar pc-avatar--xl <?= e($avatarClass) ?>" id="profile-avatar-preview">
    <?php if (!empty($user['avatar_path'])): ?>
      <img src="/media/<?= e((string) $user['avatar_path']) ?>" alt="<?= e((string) $user['first_name']) ?>">
    <?php else: ?>
      <?= e(initials((string) ($user['first_name'] ?? 'P'), (string) ($user['last_name'] ?? 'C'))) ?>
    <?php endif; ?>
  </div>

  <div>
    <h2 class="text-title-2"><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h2>
    <p class="text-subhead text-muted"><?= e((string) ($user['email'] ?? '')) ?></p>
    <div class="mt-2">
      <span class="pc-badge pc-badge--brand"><?= e(t('roles.' . $role)) ?></span>
    </div>
  </div>

  <form method="POST" action="/profile/avatar" enctype="multipart/form-data" id="profile-avatar-form">
    <?= csrf_field() ?>
    <label class="pc-btn pc-btn--secondary pc-btn--sm cursor-pointer">
      <?= icon('camera', 18) ?>
      <span>Changer ma photo</span>
      <input
        type="file"
        name="avatar"
        id="profile-avatar-input"
        accept="image/jpeg,image/png,image/webp"
        class="hidden"
      >
    </label>
  </form>
</section>

<!-- 2. Thème d'affichage en direct -->
<section class="pc-card">
  <div class="flex items-center justify-between mb-3">
    <div>
      <h2 class="text-headline">Apparence de l’application</h2>
      <p class="text-footnote text-muted">S’applique immédiatement et se synchronise sur votre profil.</p>
    </div>
  </div>
  <div class="pc-segmented" data-theme-segmented role="radiogroup" aria-label="Thème d’affichage">
    <button type="button" class="pc-segmented__btn <?= $theme === 'auto' ? 'is-active' : '' ?>" data-theme-value="auto">
      <?= icon('monitor', 16) ?>
      <span>Automatique</span>
    </button>
    <button type="button" class="pc-segmented__btn <?= $theme === 'light' ? 'is-active' : '' ?>" data-theme-value="light">
      <?= icon('sun', 16) ?>
      <span>Clair</span>
    </button>
    <button type="button" class="pc-segmented__btn <?= $theme === 'dark' ? 'is-active' : '' ?>" data-theme-value="dark">
      <?= icon('moon', 16) ?>
      <span>Sombre</span>
    </button>
  </div>
</section>

<!-- 2 bis. Notifications Web Push VAPID, Emails & Heures calmes (§3.8 / Phase 3) -->
<section class="pc-card" id="notifications-section" aria-labelledby="profile-notif-heading">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div class="flex items-center gap-3">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('bell', 18) ?>
      </span>
      <div>
        <h2 class="text-headline" id="profile-notif-heading">Notifications &amp; Heures calmes</h2>
        <p class="text-footnote text-muted">Web Push VAPID, alertes par e-mail et plage de tranquillité</p>
      </div>
    </div>
    <span class="pc-badge pc-badge--brand" id="pc-push-permission-badge">Web Push</span>
  </div>

  <!-- Abonnement Web Push navigateur & Bouton de test -->
  <div class="pc-card pc-card--subtle mb-4 pc-stack gap-2">
    <div class="text-subhead font-semibold">Notifications push sur cet appareil</div>
    <p class="text-caption text-muted">
      Recevez immédiatement les alertes médicales et de garde sur votre téléphone (y compris écran verrouillé une fois la PWA installée).
    </p>
    <div class="flex items-center gap-2 flex-wrap">
      <button
        type="button"
        class="pc-btn pc-btn--primary pc-btn--sm"
        id="pc-enable-push-btn"
        data-enable-web-push
        data-vapid-public-key="<?= e($vapidPublicKey) ?>"
      >
        <?= icon('bell', 15) ?>
        <span>Activer les notifications push</span>
      </button>

      <form method="POST" action="/profile/push-test">
        <?= csrf_field() ?>
        <button type="submit" class="pc-btn pc-btn--tinted pc-btn--sm">
          <?= icon('send', 15) ?>
          <span>Envoyer une notification de test</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Préférences par canal et par gravité + Heures calmes -->
  <form method="POST" action="/profile/notifications" class="pc-stack gap-3">
    <?= csrf_field() ?>

    <div class="pc-notif-prefs-grid">
      <div class="pc-notif-pref-row">
        <div>
          <div class="text-subhead font-semibold">Alertes critiques (Hypo/Hyper sévère, escalade)</div>
          <div class="text-caption text-muted">Ignorent toujours les heures calmes pour la sécurité du patient.</div>
        </div>
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-1 text-caption font-semibold">
            <input type="checkbox" name="push_critical" value="1" <?= !empty($notificationSettings['push_critical']) ? 'checked' : '' ?>>
            <span>Push</span>
          </label>
          <label class="flex items-center gap-1 text-caption font-semibold">
            <input type="checkbox" name="email_critical" value="1" <?= !empty($notificationSettings['email_critical']) ? 'checked' : '' ?>>
            <span>E-mail</span>
          </label>
        </div>
      </div>

      <div class="pc-notif-pref-row">
        <div>
          <div class="text-subhead font-semibold">Alertes d’attention (Retards, pointage absent, garde &lt; 24h)</div>
          <div class="text-caption text-muted">Respectent vos heures calmes.</div>
        </div>
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-1 text-caption font-semibold">
            <input type="checkbox" name="push_warning" value="1" <?= !empty($notificationSettings['push_warning']) ? 'checked' : '' ?>>
            <span>Push</span>
          </label>
          <label class="flex items-center gap-1 text-caption font-semibold">
            <input type="checkbox" name="email_warning" value="1" <?= !empty($notificationSettings['email_warning']) ? 'checked' : '' ?>>
            <span>E-mail</span>
          </label>
        </div>
      </div>

      <div class="pc-notif-pref-row">
        <div>
          <div class="text-subhead font-semibold">Informations &amp; Récapitulatif quotidien (20h)</div>
          <div class="text-caption text-muted">Bilan journalier si l’observance est inférieure à 90 % ou en cas d’alerte.</div>
        </div>
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-1 text-caption font-semibold">
            <input type="checkbox" name="push_info" value="1" <?= !empty($notificationSettings['push_info']) ? 'checked' : '' ?>>
            <span>Push</span>
          </label>
          <label class="flex items-center gap-1 text-caption font-semibold">
            <input type="checkbox" name="daily_digest_email" value="1" <?= !empty($notificationSettings['daily_digest_email']) ? 'checked' : '' ?>>
            <span>Bilan 20h</span>
          </label>
        </div>
      </div>
    </div>

    <div class="pc-card pc-card--subtle pc-stack gap-2">
      <div class="flex items-center justify-between">
        <div>
          <label for="quiet-hours-enabled" class="text-subhead font-semibold cursor-pointer">
            Activer les heures calmes
          </label>
          <div class="text-caption text-muted">Suspend les notifications non critiques la nuit (les alertes critiques passent toujours).</div>
        </div>
        <label class="pc-switch">
          <input
            type="checkbox"
            id="quiet-hours-enabled"
            name="quiet_hours_enabled"
            value="1"
            <?= !empty($notificationSettings['quiet_hours_enabled']) ? 'checked' : '' ?>
          >
          <span class="pc-switch__track">
            <span class="pc-switch__thumb"></span>
          </span>
        </label>
      </div>

      <div class="pc-module-grid pc-module-grid--2col mt-1">
        <div>
          <label class="pc-label" for="quiet-start">Début des heures calmes</label>
          <input
            type="time"
            id="quiet-start"
            name="quiet_start"
            class="pc-input font-mono"
            value="<?= e(substr((string) ($notificationSettings['quiet_start'] ?? '22:00:00'), 0, 5)) ?>"
          >
        </div>
        <div>
          <label class="pc-label" for="quiet-end">Fin des heures calmes</label>
          <input
            type="time"
            id="quiet-end"
            name="quiet_end"
            class="pc-input font-mono"
            value="<?= e(substr((string) ($notificationSettings['quiet_end'] ?? '07:00:00'), 0, 5)) ?>"
          >
        </div>
      </div>
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      Enregistrer mes préférences de notifications
    </button>
  </form>
</section>

<!-- 3. Coordonnées & Contact d'urgence -->
<section class="pc-card">
  <div class="flex items-center gap-3 mb-4">
    <span class="pc-list-icon pc-list-icon--brand">
      <?= icon('user', 18) ?>
    </span>
    <div>
      <h2 class="text-headline">Mes coordonnées</h2>
      <p class="text-footnote text-muted">Informations de contact pour l’équipe de soins</p>
    </div>
  </div>

  <form method="POST" action="/profile/info" class="flex flex-col gap-4" novalidate data-loading-form>
    <?= csrf_field() ?>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="first_name" class="pc-label">Prénom</label>
        <input
          type="text"
          id="first_name"
          name="first_name"
          value="<?= e((string) old('first_name', $user['first_name'] ?? '')) ?>"
          class="pc-input <?= has_error('first_name') ? 'has-error' : '' ?>"
          required
        >
      </div>

      <div class="pc-field">
        <label for="last_name" class="pc-label">Nom</label>
        <input
          type="text"
          id="last_name"
          name="last_name"
          value="<?= e((string) old('last_name', $user['last_name'] ?? '')) ?>"
          class="pc-input <?= has_error('last_name') ? 'has-error' : '' ?>"
          required
        >
      </div>
    </div>

    <div class="pc-field">
      <label for="phone" class="pc-label">Téléphone mobile</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        value="<?= e((string) old('phone', $user['phone'] ?? '')) ?>"
        class="pc-input"
        inputmode="tel"
        placeholder="06 12 34 56 78"
      >
    </div>

    <div class="pc-field">
      <label for="emergency_contact_name" class="pc-label">Personne à prévenir en cas d’urgence</label>
      <input
        type="text"
        id="emergency_contact_name"
        name="emergency_contact_name"
        value="<?= e((string) old('emergency_contact_name', $user['emergency_contact_name'] ?? '')) ?>"
        class="pc-input"
        placeholder="Nom et prénom"
      >
    </div>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="emergency_contact_phone" class="pc-label">Tél. d’urgence</label>
        <input
          type="tel"
          id="emergency_contact_phone"
          name="emergency_contact_phone"
          value="<?= e((string) old('emergency_contact_phone', $user['emergency_contact_phone'] ?? '')) ?>"
          class="pc-input"
          inputmode="tel"
        >
      </div>

      <div class="pc-field">
        <label for="emergency_contact_relation" class="pc-label">Lien de parenté</label>
        <input
          type="text"
          id="emergency_contact_relation"
          name="emergency_contact_relation"
          value="<?= e((string) old('emergency_contact_relation', $user['emergency_contact_relation'] ?? '')) ?>"
          class="pc-input"
          placeholder="Conjoint, sœur…"
        >
      </div>
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      Enregistrer mes coordonnées
    </button>
  </form>
</section>

<!-- 4. Code PIN rapide & Activation sur cet appareil de confiance (C1) -->
<section class="pc-card">
  <div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-3">
      <span class="pc-list-icon pc-list-icon--team">
        <?= icon('key-round', 18) ?>
      </span>
      <div>
        <h2 class="text-headline">Code PIN &amp; Appareil de confiance</h2>
        <p class="text-footnote text-muted">4 à 6 chiffres pour déverrouiller Prime Care à une main</p>
      </div>
    </div>
    <?php if ($trustedDevice !== null): ?>
      <span class="pc-badge pc-badge--ok">Actif sur cet appareil</span>
    <?php elseif ($hasPin): ?>
      <span class="pc-badge pc-badge--brand">PIN défini</span>
    <?php else: ?>
      <span class="pc-badge pc-badge--warn">Non défini</span>
    <?php endif; ?>
  </div>

  <?php if ($trustedDevice !== null): ?>
    <div class="pc-card pc-card--subtle mb-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <div class="text-subhead font-semibold">Connexion par PIN activée sur ce navigateur</div>
          <div class="text-caption text-muted">Appareil : <?= e((string) ($trustedDevice['device_label'] ?? 'Téléphone mobile')) ?> (valable 90 jours)</div>
        </div>
        <form method="POST" action="/profile/trust-device/revoke" data-loading-form>
          <?= csrf_field() ?>
          <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--sm">
            <?= e(t('profile.revoke_pin_device')) ?>
          </button>
        </form>
      </div>
    </div>
  <?php elseif ($hasPin && $authenticatedViaPassword): ?>
    <div class="pc-card pc-card--subtle mb-4">
      <form method="POST" action="/profile/trust-device" class="flex flex-col gap-3" data-loading-form>
        <?= csrf_field() ?>
        <div class="text-subhead font-semibold">Autoriser ce téléphone pour la connexion rapide par PIN</div>
        <p class="text-caption text-muted">
          Votre profil apparaîtra dans l’écran « Qui êtes-vous ? » uniquement sur cet appareil.
        </p>
        <input type="hidden" name="device_label" value="Téléphone mobile">
        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('smartphone', 18) ?>
          <span><?= e(t('profile.enable_pin_device')) ?></span>
        </button>
      </form>
    </div>
  <?php elseif ($hasPin && !$authenticatedViaPassword): ?>
    <div class="pc-card pc-card--subtle mb-4">
      <p class="text-caption text-muted">
        Pour activer la connexion par PIN sur un nouvel appareil, connectez-vous d’abord avec votre mot de passe.
      </p>
    </div>
  <?php endif; ?>

  <form method="POST" action="/profile/pin" class="flex flex-col gap-4" novalidate data-loading-form>
    <?= csrf_field() ?>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="pin" class="pc-label">Nouveau code PIN</label>
        <input
          type="password"
          id="pin"
          name="pin"
          class="pc-input <?= has_error('pin') ? 'has-error' : '' ?>"
          inputmode="numeric"
          pattern="\d{4,6}"
          maxlength="6"
          placeholder="••••"
          required
        >
      </div>

      <div class="pc-field">
        <label for="pin_confirmation" class="pc-label">Confirmer le code PIN</label>
        <input
          type="password"
          id="pin_confirmation"
          name="pin_confirmation"
          class="pc-input"
          inputmode="numeric"
          pattern="\d{4,6}"
          maxlength="6"
          placeholder="••••"
          required
        >
      </div>
    </div>

    <?php if ($authenticatedViaPassword): ?>
      <div class="flex items-center justify-between py-1 min-h-touch">
        <label for="enable_on_device" class="text-subhead cursor-pointer">
          <?= e(t('profile.enable_pin_device')) ?>
        </label>
        <label class="pc-switch">
          <input type="checkbox" id="enable_on_device" name="enable_on_device" value="1" checked>
          <span class="pc-switch__track">
            <span class="pc-switch__thumb"></span>
          </span>
        </label>
      </div>
    <?php endif; ?>

    <button type="submit" class="pc-btn pc-btn--tinted pc-btn--block">
      Enregistrer mon code PIN
    </button>
  </form>
</section>

<!-- 5. Mot de passe & Appareils connectés -->
<section class="pc-card">
  <div class="flex items-center gap-3 mb-4">
    <span class="pc-list-icon pc-list-icon--danger">
      <?= icon('lock', 18) ?>
    </span>
    <div>
      <h2 class="text-headline">Mot de passe &amp; Sécurité</h2>
      <p class="text-footnote text-muted">10 caractères minimum</p>
    </div>
  </div>

  <form method="POST" action="/profile/password" class="flex flex-col gap-4 mb-6" novalidate data-loading-form>
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="current_password" class="pc-label">Mot de passe actuel</label>
      <input
        type="password"
        id="current_password"
        name="current_password"
        class="pc-input <?= has_error('current_password') ? 'has-error' : '' ?>"
        autocomplete="current-password"
        required
      >
      <?php if (has_error('current_password')): ?>
        <p class="pc-field-error" role="alert">
          <?= icon('alert-triangle', 15) ?>
          <span><?= e((string) field_error('current_password')) ?></span>
        </p>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="new_profile_password" class="pc-label">Nouveau mot de passe</label>
      <input
        type="password"
        id="new_profile_password"
        name="password"
        class="pc-input <?= has_error('password') ? 'has-error' : '' ?>"
        autocomplete="new-password"
        placeholder="10 caractères minimum"
        required
      >
      <?php if (has_error('password')): ?>
        <p class="pc-field-error" role="alert">
          <?= icon('alert-triangle', 15) ?>
          <span><?= e((string) field_error('password')) ?></span>
        </p>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="profile_password_confirmation" class="pc-label">Confirmer le nouveau mot de passe</label>
      <input
        type="password"
        id="profile_password_confirmation"
        name="password_confirmation"
        class="pc-input"
        autocomplete="new-password"
        required
      >
    </div>

    <button type="submit" class="pc-btn pc-btn--secondary pc-btn--block">
      Modifier mon mot de passe
    </button>
  </form>

  <form method="POST" action="/profile/devices/revoke" data-loading-form>
    <?= csrf_field() ?>
    <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
      <?= icon('smartphone', 18) ?>
      <span><?= e(t('profile.revoke_devices')) ?></span>
    </button>
  </form>
</section>

<!-- 6. Déconnexion -->
<form method="POST" action="/logout" data-loading-form data-logout-form>
  <?= csrf_field() ?>
  <button type="submit" class="pc-btn pc-btn--danger pc-btn--block">
    <?= icon('log-out', 18) ?>
    <span><?= e(t('auth.logout')) ?></span>
  </button>
</form>
