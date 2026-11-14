<?php

declare(strict_types=1);

$trustedProfiles = is_array($trustedProfiles ?? null) ? $trustedProfiles : [];
$deviceUser = is_array($deviceUser ?? null) ? $deviceUser : ($trustedProfiles[0] ?? []);
$firstName = (string) ($deviceUser['first_name'] ?? '');
$selector = (string) ($deviceUser['selector'] ?? '');
?>
<section class="pc-auth-card" aria-labelledby="pin-heading">
  <div class="pc-auth-brand">
    <?= brand_svg('prime-care-logo-complet.svg') ?>
    <p class="pc-auth-slogan"><?= e(t('auth.login.subtitle')) ?></p>
  </div>

  <div class="pc-who-section">
    <h2 class="pc-who-title" id="pin-heading"><?= e(t('auth.pin.who_are_you')) ?></h2>
    <div class="pc-who-grid" role="listbox" aria-label="<?= e(t('auth.pin.who_are_you')) ?>">
      <?php foreach ($trustedProfiles as $profile): ?>
        <?php
        $pUid = (int) ($profile['user_id'] ?? 1);
        $pFirst = (string) ($profile['first_name'] ?? '');
        $pLast = (string) ($profile['last_name'] ?? '');
        $pRole = (string) ($profile['role'] ?? 'caregiver');
        $pSel = (string) ($profile['selector'] ?? '');
        $pLocked = (bool) ($profile['pin_locked'] ?? false);
        ?>
        <button
          type="button"
          class="pc-who-card <?= $pSel === $selector ? 'is-selected' : '' ?>"
          data-pin-profile-selector="<?= e($pSel) ?>"
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

  <div class="text-center mb-2">
    <div class="text-headline" id="pc-pin-greeting">
      <?= e(t('auth.pin.greeting', ['name' => $firstName])) ?>
    </div>
    <p class="text-footnote text-muted"><?= e(t('auth.pin.subtitle')) ?></p>
  </div>

  <form method="POST" action="/login/pin" id="pc-pin-form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="selector" value="<?= e($selector) ?>">
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
</section>
