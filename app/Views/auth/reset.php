<?php

declare(strict_types=1);

/** @var string $token */
/** @var string $email */
?>
<section class="pc-auth-card" aria-labelledby="reset-heading">
  <div class="pc-auth-brand">
    <?= brand_svg('logo-complet', '', 200) ?>
  </div>

  <h1 id="reset-heading" class="text-title-2 text-center mb-1"><?= e(t('auth.reset.title')) ?></h1>
  <p class="text-subhead text-muted text-center mb-4">Compte : <?= e($email) ?></p>

  <form method="POST" action="/reset-password/<?= e($token) ?>" class="flex flex-col gap-4" novalidate>
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="password" class="pc-label"><?= e(t('auth.reset.password')) ?></label>
      <div class="pc-input-wrap">
        <input
          type="password"
          id="password"
          name="password"
          required
          minlength="10"
          data-password-strength="reset-pw-strength"
          class="pc-input <?= errors('password') ? 'has-error' : '' ?>"
          style="padding-right: 48px;"
        >
        <button type="button" class="pc-input-eye" data-toggle-password aria-label="Afficher le mot de passe">
          <?= icon('eye', '', 20) ?>
        </button>
      </div>
      <div id="reset-pw-strength" class="pc-help">Minimum 10 caractères avec majuscule, chiffre et symbole.</div>
      <?php if (errors('password')) : ?>
        <div class="pc-field-error">
          <?= icon('alert-triangle', '', 14) ?>
          <span><?= e(errors('password')) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="password_confirmation" class="pc-label"><?= e(t('auth.reset.password_confirmation')) ?></label>
      <input
        type="password"
        id="password_confirmation"
        name="password_confirmation"
        required
        minlength="10"
        class="pc-input"
      >
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      <?= e(t('auth.reset.submit')) ?>
    </button>
  </form>
</section>
