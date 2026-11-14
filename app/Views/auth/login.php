<?php

declare(strict_types=1);

?>
<section class="pc-auth-card" aria-labelledby="login-heading">
  <div class="pc-auth-brand">
    <?= brand_svg('logo-complet', '', 220) ?>
    <p id="login-heading" class="pc-auth-slogan"><?= e(t('app.slogan')) ?></p>
  </div>

  <form method="POST" action="/login" class="flex flex-col gap-4" novalidate>
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

    <div class="pc-field">
      <div class="flex items-center justify-between">
        <label for="password" class="pc-label"><?= e(t('auth.login.password')) ?></label>
      </div>
      <div class="pc-input-wrap">
        <input
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          required
          placeholder="••••••••••"
          class="pc-input <?= errors('password') ? 'has-error' : '' ?>"
          style="padding-right: 48px;"
        >
        <button type="button" class="pc-input-eye" data-toggle-password aria-label="Afficher le mot de passe">
          <?= icon('eye', '', 20) ?>
        </button>
      </div>
      <?php if (errors('password')) : ?>
        <div class="pc-field-error">
          <?= icon('alert-triangle', '', 14) ?>
          <span><?= e(errors('password')) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="flex items-center justify-between py-1" style="min-height: 44px;">
      <span class="text-subhead"><?= e(t('auth.login.remember')) ?></span>
      <label class="pc-switch" aria-label="<?= e(t('auth.login.remember')) ?>">
        <input type="checkbox" name="remember" value="1" checked>
        <span class="pc-switch__track"><span class="pc-switch__thumb"></span></span>
      </label>
    </div>

    <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
      <?= e(t('auth.login.submit')) ?>
    </button>

    <div class="flex items-center justify-between mt-1">
      <a href="/login/pin" class="text-subhead text-brand" style="display:inline-flex; align-items:center; gap:5px; min-height:44px; font-weight:600;">
        <?= icon('key-round', '', 16) ?>
        <span>Code PIN rapide</span>
      </a>
      <a href="/forgot-password" class="text-subhead text-muted" style="display:inline-flex; align-items:center; min-height:44px;">
        <?= e(t('auth.login.forgot')) ?>
      </a>
    </div>
  </form>
</section>
