<?php

declare(strict_types=1);

$token = (string) ($token ?? '');
$email = (string) ($email ?? '');
?>
<section class="pc-auth-card" aria-labelledby="reset-heading">
  <div class="pc-auth-brand">
    <?= brand_svg('prime-care-logo-complet.svg') ?>
  </div>

  <h1 class="text-title-2 text-center mb-2" id="reset-heading"><?= e(t('auth.reset.title')) ?></h1>
  <p class="text-subhead text-muted text-center mb-6">Compte : <strong><?= e($email) ?></strong></p>

  <form method="POST" action="/reset-password/<?= e($token) ?>" class="flex flex-col gap-4" novalidate data-loading-form>
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="new_password" class="pc-label"><?= e(t('auth.reset.password')) ?></label>
      <div class="pc-input-wrap">
        <input
          type="password"
          id="new_password"
          name="password"
          class="pc-input pc-input--with-eye <?= has_error('password') ? 'has-error' : '' ?>"
          placeholder="10 caractères minimum"
          autocomplete="new-password"
          required
        >
        <button type="button" class="pc-input-eye" data-toggle-password="new_password" aria-label="Afficher le mot de passe">
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

    <div class="pc-field">
      <label for="password_confirmation" class="pc-label"><?= e(t('auth.reset.password_confirmation')) ?></label>
      <input
        type="password"
        id="password_confirmation"
        name="password_confirmation"
        class="pc-input"
        placeholder="Répétez le mot de passe"
        autocomplete="new-password"
        required
      >
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      <span><?= e(t('auth.reset.submit')) ?></span>
    </button>
  </form>
</section>
