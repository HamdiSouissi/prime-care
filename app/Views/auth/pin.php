<?php

declare(strict_types=1);

/** @var array<string, mixed> $deviceUser */
$firstName = (string) ($deviceUser['first_name'] ?? 'Fatou');
$lastName = (string) ($deviceUser['last_name'] ?? '');
$colorHex = (string) ($deviceUser['color_hex'] ?? '#FF9500');
$roleLabel = t('roles.' . ($deviceUser['role'] ?? 'caregiver'));
?>
<section class="pc-auth-card text-center" aria-labelledby="pin-heading">
  <div class="flex flex-col items-center">
    <div class="pc-avatar pc-avatar--lg mb-3" style="background-color: <?= e($colorHex) ?>;">
      <?php if (!empty($deviceUser['avatar_path'])) : ?>
        <img src="/media/<?= e($deviceUser['avatar_path']) ?>" alt="<?= e($firstName) ?>">
      <?php else : ?>
        <?= e(initials($firstName, $lastName)) ?>
      <?php endif; ?>
    </div>

    <span class="pc-badge pc-badge--brand mb-2"><?= e($roleLabel) ?></span>
    <h1 id="pin-heading" class="text-title-1"><?= e(t('auth.pin.greeting', ['name' => $firstName])) ?></h1>
    <p class="text-subhead text-muted mt-1"><?= e(t('auth.pin.subtitle')) ?></p>
  </div>

  <form id="pc-pin-form" method="POST" action="/login/pin" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= e((string) ($deviceUser['id'] ?? 0)) ?>">
    <input type="hidden" name="pin" value="">

    <div id="pc-pin-dots" class="pc-pin-dots" role="status" aria-label="Saisie du code PIN">
      <span class="pc-pin-dot"></span>
      <span class="pc-pin-dot"></span>
      <span class="pc-pin-dot"></span>
      <span class="pc-pin-dot"></span>
    </div>

    <div class="pc-pin-pad">
      <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit) : ?>
        <button type="button" class="pc-pin-key" data-pin-digit="<?= $digit ?>" aria-label="Chiffre <?= $digit ?>">
          <?= $digit ?>
        </button>
      <?php endforeach; ?>

      <a href="/login/switch" class="pc-pin-key" style="background:transparent; font-size:13px; font-weight:600; color:var(--text-2);" aria-label="<?= e(t('auth.pin.switch_user')) ?>">
        Autre
      </a>

      <button type="button" class="pc-pin-key" data-pin-digit="0" aria-label="Chiffre 0">
        0
      </button>

      <button type="button" class="pc-pin-key" style="background:transparent; color:var(--text-2);" data-pin-backspace aria-label="Effacer un chiffre">
        <?= icon('delete', '', 24) ?>
      </button>
    </div>

    <div class="mt-5">
      <a href="/login/switch" class="pc-btn pc-btn--ghost pc-btn--sm">
        <?= e(t('auth.pin.switch_user')) ?>
      </a>
    </div>
  </form>
</section>
