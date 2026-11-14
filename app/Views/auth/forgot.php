<?php

declare(strict_types=1);

?>
<section class="pc-auth-card" aria-labelledby="forgot-heading">
  <div class="pc-auth-brand">
    <?= brand_svg('logo-complet', '', 200) ?>
  </div>

  <h1 id="forgot-heading" class="text-title-2 text-center mb-2"><?= e(t('auth.forgot.title')) ?></h1>
  <p class="text-subhead text-muted text-center mb-5"><?= e(t('auth.forgot.subtitle')) ?></p>

  <form method="POST" action="/forgot-password" class="flex flex-col gap-4" novalidate>
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="email" class="pc-label"><?= e(t('auth.login.email')) ?></label>
      <input
        type="email"
        id="email"
        name="email"
        inputmode="email"
        autocomplete="email"
        required
        value="<?= e(old('email')) ?>"
        placeholder="prenom@primecare.test"
        class="pc-input <?= errors('email') ? 'has-error' : '' ?>"
      >
      <?php if (errors('email')) : ?>
        <div class="pc-field-error">
          <?= icon('alert-triangle', '', 14) ?>
          <span><?= e(errors('email')) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      <?= e(t('auth.forgot.submit')) ?>
    </button>

    <a href="/login" class="pc-btn pc-btn--ghost pc-btn--block">
      <?= e(t('auth.forgot.back')) ?>
    </a>
  </form>
</section>
