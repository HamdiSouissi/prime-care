<?php

declare(strict_types=1);

use App\Core\View;

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Prime Care';
/** @var string|null $backUrl */
$backUrl = $backUrl ?? null;
/** @var string $backLabel */
$backLabel = $backLabel ?? 'Précédent';
/** @var bool $showNavbarLogo */
$showNavbarLogo = $showNavbarLogo ?? false;
/** @var string|null $rightActionHtml */
$rightActionHtml = $rightActionHtml ?? (View::yieldSection('navbar_right') !== '' ? View::yieldSection('navbar_right') : null);
?>
<header class="pc-navbar">
  <div class="pc-navbar__inner">
    <div class="pc-navbar__left">
      <?php if ($backUrl !== null) : ?>
        <a href="<?= e($backUrl) ?>" class="pc-navbar__back">
          <?= icon('chevron-left', '', 22) ?>
          <span><?= e($backLabel) ?></span>
        </a>
      <?php elseif ($showNavbarLogo) : ?>
        <a href="/patient" class="inline-flex items-center gap-2 text-brand" style="min-height: 44px;" aria-label="Fiche patient">
          <?= icon('heart-pulse', '', 18) ?>
          <span class="text-footnote" style="font-weight: 600;">M. Benali</span>
        </a>
      <?php endif; ?>
    </div>

    <div class="pc-navbar__center-title"><?= e($pageTitle) ?></div>

    <div class="pc-navbar__right">
      <?php if ($rightActionHtml !== null) : ?>
        <?= $rightActionHtml ?>
      <?php else : ?>
        <a href="/profile" class="pc-avatar" style="width: 34px; height: 34px; font-size: 13px; background-color: <?= e(auth()['color_hex'] ?? '#5E5CE6') ?>;" aria-label="Mon profil">
          <?php if (!empty(auth()['avatar_path'])) : ?>
            <img src="/media/<?= e(auth()['avatar_path']) ?>" alt="<?= e(auth()['first_name'] ?? '') ?>">
          <?php else : ?>
            <?= e(initials((string) (auth()['first_name'] ?? 'P'), (string) (auth()['last_name'] ?? 'C'))) ?>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="pc-large-header">
  <div class="pc-large-header__title-wrap">
    <?php if ($showNavbarLogo) : ?>
      <span class="pc-large-header__logo">
        <?= brand_svg('icone-transparent', '', 28) ?>
      </span>
    <?php endif; ?>
    <h1 class="pc-large-header__title"><?= e($pageTitle) ?></h1>
  </div>
</div>
