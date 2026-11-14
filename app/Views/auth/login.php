<?php

declare(strict_types=1);

$trustedProfiles = is_array($trustedProfiles ?? null) ? $trustedProfiles : [];
$forcePasswordMode = (bool) ($forcePasswordMode ?? false);
$showPinPad = $trustedProfiles !== [] && !$forcePasswordMode;
$activeProfile = $trustedProfiles[0] ?? null;
?>
<section class="pc-auth-card" aria-labelledby="login-heading">
  <div class="pc-auth-brand">
    <?= brand_svg('prime-care-logo-complet.svg') ?>
    <p class="pc-auth-slogan" id="login-heading"><?= e(t('auth.login.subtitle')) ?></p>
  </div>

  <?php if ($showPinPad && is_array($activeProfile)): ?>
    <!-- Partie 1 : Qui êtes-vous ? (Profils de confiance sur cet appareil — C1 & Additif B §9.6) -->
    <div class="pc-who-section">
      <h2 class="pc-who-title"><?= e(t('auth.pin.who_are_you')) ?></h2>
      <div class="pc-who-grid" role="listbox" aria-label="<?= e(t('auth.pin.who_are_you')) ?>">
        <?php foreach ($trustedProfiles as $idx => $profile): ?>
          <?php
          $pUid = (int) ($profile['user_id'] ?? 1);
          $pFirst = (string) ($profile['first_name'] ?? '');
          $pLast = (string) ($profile['last_name'] ?? '');
          $pRole = (string) ($profile['role'] ?? 'caregiver');
          $pSelector = (string) ($profile['selector'] ?? '');
          $pLocked = (bool) ($profile['pin_locked'] ?? false);
          ?>
          <button
            type="button"
            class="pc-who-card <?= $idx === 0 ? 'is-selected' : '' ?>"
            data-pin-profile-selector="<?= e($pSelector) ?>"
            data-pin-profile-name="<?= e($pFirst) ?>"
            data-pin-profile-locked="<?= $pLocked ? '1' : '0' ?>"
          >
            <span class="pc-avatar <?= e(avatar_color_class($pUid)) ?>">
              <?php if (!empty($profile['avatar_path'])): ?>
                <img src="/media/<?= e((string) $profile['avatar_path']) ?>" alt="<?= e($pFirst) ?>">
              <?php else: ?>
                <?= e(initials($pFirst, $pLast)) ?>
              <?php endif; ?>
            </span>
            <span class="pc-who-card__name"><?= e($pFirst) ?></span>
            <span class="pc-who-card__role"><?= e(t('roles.' . $pRole)) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Partie 2 : Clavier PIN (soumet uniquement le selector du jeton d'appareil) -->
    <div class="text-center mb-2">
      <div class="text-headline" id="pc-pin-greeting">
        <?= e(t('auth.pin.greeting', ['name' => (string) $activeProfile['first_name']])) ?>
      </div>
      <p class="text-footnote text-muted"><?= e(t('auth.pin.subtitle')) ?></p>
    </div>

    <form method="POST" action="/login/pin" id="pc-pin-form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="selector" value="<?= e((string) $activeProfile['selector']) ?>">
      <input type="hidden" name="pin" value="">

      <div class="pc-pin-dots" id="pc-pin-dots" aria-hidden="true">
        <span class="pc-pin-dot"></span>
        <span class="pc-pin-dot"></span>
        <span class="pc-pin-dot"></span>
        <span class="pc-pin-dot"></span>
      </div>

      <div class="pc-pin-pad" role="group" aria-label="Clavier numérique">
        <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit): ?>
          <button type="button" class="pc-pin-key" data-pin-digit="<?= $digit ?>" aria-label="Chiffre <?= $digit ?>">
            <?= $digit ?>
          </button>
        <?php endforeach; ?>

        <a href="/login?mode=password" class="pc-pin-key pc-pin-key--ghost" aria-label="<?= e(t('auth.pin.use_password')) ?>">
          Mot de passe
        </a>

        <button type="button" class="pc-pin-key" data-pin-digit="0" aria-label="Chiffre 0">
          0
        </button>

        <button type="button" class="pc-pin-key pc-pin-key--ghost" data-pin-backspace aria-label="Effacer un chiffre">
          <?= icon('delete', 24) ?>
        </button>
      </div>
    </form>
  <?php else: ?>
    <!-- Formulaire e-mail + mot de passe (seul affiché si aucun appareil de confiance n'est activé) -->
    <form method="POST" action="/login" class="flex flex-col gap-4" novalidate data-loading-form>
      <?= csrf_field() ?>

      <div class="pc-field">
        <label for="email" class="pc-label"><?= e(t('auth.login.email')) ?></label>
        <div class="pc-input-wrap">
          <input
            type="email"
            id="email"
            name="email"
            value="<?= e((string) old('email', '')) ?>"
            class="pc-input <?= has_error('email') ? 'has-error' : '' ?>"
            placeholder="prenom@primecare.test"
            autocomplete="email"
            inputmode="email"
            required
          >
        </div>
        <?php if (has_error('email')): ?>
          <p class="pc-field-error" role="alert">
            <?= icon('alert-triangle', 15) ?>
            <span><?= e((string) field_error('email')) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="pc-field">
        <div class="flex items-center justify-between">
          <label for="password" class="pc-label"><?= e(t('auth.login.password')) ?></label>
        </div>
        <div class="pc-input-wrap">
          <input
            type="password"
            id="password"
            name="password"
            class="pc-input pc-input--with-eye <?= has_error('password') ? 'has-error' : '' ?>"
            placeholder="••••••••••"
            autocomplete="current-password"
            required
          >
          <button
            type="button"
            class="pc-input-eye"
            data-toggle-password="password"
            aria-label="Afficher ou masquer le mot de passe"
          >
            <?= icon('eye', 20) ?>
          </button>
        </div>
        <?php if (has_error('password')): ?>
          <p class="pc-field-error" role="alert">
            <?= icon('alert-triangle', 15) ?>
            <span><?= e((string) field_error('password')) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="flex items-center justify-between py-1 min-h-touch">
        <label for="remember" class="text-subhead cursor-pointer"><?= e(t('auth.login.remember')) ?></label>
        <label class="pc-switch">
          <input type="checkbox" id="remember" name="remember" value="1" checked>
          <span class="pc-switch__track">
            <span class="pc-switch__thumb"></span>
          </span>
        </label>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
        <span><?= e(t('auth.login.submit')) ?></span>
        <?= icon('arrow-right', 18) ?>
      </button>

      <div class="pc-auth-links">
        <?php if ($trustedProfiles !== []): ?>
          <a href="/login" class="pc-auth-link">
            <?= icon('key-round', 16) ?>
            <span>Profils PIN de cet appareil</span>
          </a>
        <?php endif; ?>
        <a href="/forgot-password" class="pc-auth-link pc-auth-link--muted">
          <?= e(t('auth.login.forgot')) ?>
        </a>
      </div>
    </form>
  <?php endif; ?>
</section>
