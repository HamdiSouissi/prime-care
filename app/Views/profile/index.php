<?php

declare(strict_types=1);

/** @var array<string, mixed> $user */
$firstName = (string) ($user['first_name'] ?? '');
$lastName = (string) ($user['last_name'] ?? '');
$currentTheme = (string) ($user['theme'] ?? 'auto');
?>
<!-- 1. Identité & Photo de profil -->
<section class="pc-card">
  <form method="POST" action="/profile" enctype="multipart/form-data" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="theme" id="profile-theme-input" value="<?= e($currentTheme) ?>">

    <div class="flex items-center gap-4">
      <div class="pc-avatar pc-avatar--xl" id="profile-avatar-preview" style="background-color: <?= e($user['color_hex'] ?? '#5E5CE6') ?>;">
        <?php if (!empty($user['avatar_path'])) : ?>
          <img src="/media/<?= e($user['avatar_path']) ?>" alt="Photo de profil">
        <?php else : ?>
          <?= e(initials($firstName, $lastName)) ?>
        <?php endif; ?>
      </div>

      <div class="flex-1">
        <div class="text-title-3"><?= e($firstName . ' ' . $lastName) ?></div>
        <div class="text-footnote text-muted mb-2"><?= e(t('roles.' . ($user['role'] ?? 'caregiver'))) ?> · <?= e($user['email'] ?? '') ?></div>
        <label class="pc-btn pc-btn--secondary pc-btn--sm" style="cursor: pointer;">
          <?= icon('camera', '', 16) ?>
          <span>Changer la photo</span>
          <input
            type="file"
            name="avatar"
            accept="image/jpeg,image/png,image/webp"
            data-avatar-crop="profile-avatar-preview"
            style="display: none;"
          >
        </label>
      </div>
    </div>

    <div class="pc-field">
      <label for="first_name" class="pc-label">Prénom</label>
      <input
        type="text"
        id="first_name"
        name="first_name"
        required
        value="<?= e(old('first_name', $firstName)) ?>"
        class="pc-input <?= errors('first_name') ? 'has-error' : '' ?>"
      >
      <?php if (errors('first_name')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('first_name')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="last_name" class="pc-label">Nom</label>
      <input
        type="text"
        id="last_name"
        name="last_name"
        required
        value="<?= e(old('last_name', $lastName)) ?>"
        class="pc-input <?= errors('last_name') ? 'has-error' : '' ?>"
      >
      <?php if (errors('last_name')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('last_name')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="phone" class="pc-label">Téléphone mobile</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        inputmode="tel"
        value="<?= e(old('phone', (string) ($user['phone'] ?? ''))) ?>"
        placeholder="06 00 00 00 00"
        class="pc-input <?= errors('phone') ? 'has-error' : '' ?>"
      >
    </div>

    <!-- Choix du thème appliqué en direct (§3.3) -->
    <div class="pc-field">
      <span class="pc-label">Thème d’affichage (Auto / Clair / Sombre)</span>
      <div class="pc-segmented" data-segmented data-theme-switcher data-persist-theme="1" role="group" aria-label="Thème">
        <button type="button" class="pc-segmented__btn <?= $currentTheme === 'auto' ? 'is-active' : '' ?>" data-value="auto" aria-pressed="<?= $currentTheme === 'auto' ? 'true' : 'false' ?>">
          <?= icon('monitor', '', 16) ?><span>Auto</span>
        </button>
        <button type="button" class="pc-segmented__btn <?= $currentTheme === 'light' ? 'is-active' : '' ?>" data-value="light" aria-pressed="<?= $currentTheme === 'light' ? 'true' : 'false' ?>">
          <?= icon('sun', '', 16) ?><span>Clair</span>
        </button>
        <button type="button" class="pc-segmented__btn <?= $currentTheme === 'dark' ? 'is-active' : '' ?>" data-value="dark" aria-pressed="<?= $currentTheme === 'dark' ? 'true' : 'false' ?>">
          <?= icon('moon', '', 16) ?><span>Sombre</span>
        </button>
      </div>
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      <?= icon('check', '', 18) ?>
      <span>Enregistrer mon profil</span>
    </button>
  </form>
</section>

<!-- 2. Configuration du code PIN (4 à 6 chiffres, double saisie) -->
<section class="pc-card">
  <div class="flex items-center gap-3 mb-3">
    <span class="pc-list-icon" style="background: var(--brand);">
      <?= icon('key-round', '', 18) ?>
    </span>
    <div>
      <h2 class="text-headline">Code PIN rapide (4 à 6 chiffres)</h2>
      <p class="text-footnote text-muted">Pour déverrouiller l’application à une main en début de garde.</p>
    </div>
  </div>

  <form method="POST" action="/profile/pin" class="flex flex-col gap-3">
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="pin" class="pc-label">Nouveau code PIN</label>
      <input
        type="password"
        id="pin"
        name="pin"
        inputmode="numeric"
        pattern="\d{4,6}"
        maxlength="6"
        required
        placeholder="4 à 6 chiffres"
        class="pc-input <?= errors('pin') ? 'has-error' : '' ?>"
      >
      <?php if (errors('pin')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('pin')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="pin_confirmation" class="pc-label">Confirmer le code PIN</label>
      <input
        type="password"
        id="pin_confirmation"
        name="pin_confirmation"
        inputmode="numeric"
        pattern="\d{4,6}"
        maxlength="6"
        required
        placeholder="Répétez le code PIN"
        class="pc-input"
      >
    </div>

    <button type="submit" class="pc-btn pc-btn--tinted pc-btn--block">
      Enregistrer le code PIN
    </button>
  </form>
</section>

<!-- 3. Changement de mot de passe -->
<section class="pc-card">
  <div class="flex items-center gap-3 mb-3">
    <span class="pc-list-icon" style="background: var(--c-team);">
      <?= icon('lock', '', 18) ?>
    </span>
    <div>
      <h2 class="text-headline">Changer de mot de passe</h2>
      <p class="text-footnote text-muted">Minimum 10 caractères (majuscule, chiffre, symbole).</p>
    </div>
  </div>

  <form method="POST" action="/profile/password" class="flex flex-col gap-3">
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="current_password" class="pc-label">Mot de passe actuel</label>
      <input
        type="password"
        id="current_password"
        name="current_password"
        required
        class="pc-input <?= errors('current_password') ? 'has-error' : '' ?>"
      >
      <?php if (errors('current_password')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('current_password')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="new_password" class="pc-label">Nouveau mot de passe</label>
      <input
        type="password"
        id="new_password"
        name="password"
        required
        minlength="10"
        data-password-strength="profile-pw-strength"
        class="pc-input <?= errors('password') ? 'has-error' : '' ?>"
      >
      <div id="profile-pw-strength" class="pc-help">Minimum 10 caractères.</div>
      <?php if (errors('password')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('password')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="password_confirmation" class="pc-label">Confirmer le nouveau mot de passe</label>
      <input
        type="password"
        id="password_confirmation"
        name="password_confirmation"
        required
        minlength="10"
        class="pc-input"
      >
    </div>

    <button type="submit" class="pc-btn pc-btn--secondary pc-btn--block">
      Mettre à jour le mot de passe
    </button>
  </form>
</section>

<!-- 4. Déconnexion des autres appareils (§3.3) -->
<section class="pc-card">
  <div class="flex items-center gap-3 mb-3">
    <span class="pc-list-icon" style="background: var(--danger);">
      <?= icon('smartphone', '', 18) ?>
    </span>
    <div>
      <h2 class="text-headline">Appareils connectés</h2>
      <p class="text-footnote text-muted">Révoque tous les jetons de connexion rapide sur les autres téléphones.</p>
    </div>
  </div>

  <form method="POST" action="/profile/devices/revoke">
    <?= csrf_field() ?>
    <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
      <?= icon('log-out', '', 18) ?>
      <span><?= e(t('profile.revoke_devices')) ?></span>
    </button>
  </form>
</section>
