<?php

declare(strict_types=1);

use App\Services\AlertService;

$pageTitle = (string) ($pageTitle ?? 'Prime Care');
$backUrl = isset($backUrl) ? (string) $backUrl : null;
$backLabel = (string) ($backLabel ?? 'Retour');
$isHome = (bool) ($isHome ?? false);
$navbarActionHtml = View::yieldSection('navbar_action');
$currentUser = auth();
$avatarClass = avatar_color_class((int) ($currentUser['id'] ?? 1));
$openAlertsCount = isset($openAlertsCount) ? (int) $openAlertsCount : AlertService::countOpenAlerts(1);
?>
<header class="pc-navbar" data-navbar>
  <div class="pc-navbar__inner">
    <div class="pc-navbar__left">
      <?php if ($backUrl !== null): ?>
        <a href="<?= e($backUrl) ?>" class="pc-navbar__back" aria-label="<?= e($backLabel) ?>">
          <?= icon('chevron-left', 22) ?>
          <span><?= e($backLabel) ?></span>
        </a>
      <?php elseif ($isHome): ?>
        <a href="/patient" class="pc-navbar__patient-pill" aria-label="Fiche patient">
          <?= icon('heart-pulse', 20) ?>
          <span>M. Benali</span>
        </a>
      <?php endif; ?>
    </div>

    <div class="pc-navbar__center-title" data-navbar-compact-title>
      <?= e($pageTitle) ?>
    </div>

    <div class="pc-navbar__right">
      <a href="/alerts" class="pc-navbar__bell" aria-label="Centre d’alertes (<?= $openAlertsCount ?> ouverte<?= $openAlertsCount > 1 ? 's' : '' ?>)">
        <?= icon('bell', '', 20) ?>
        <?php if ($openAlertsCount > 0): ?>
          <span class="pc-alert-badge" data-alert-badge><?= $openAlertsCount > 99 ? '99+' : $openAlertsCount ?></span>
        <?php endif; ?>
      </a>

      <?php if ($navbarActionHtml !== ''): ?>
        <?= $navbarActionHtml ?>
      <?php elseif ($currentUser !== null): ?>
        <a href="/profile" class="pc-avatar pc-avatar--sm <?= e($avatarClass) ?>" aria-label="Mon profil">
          <?php if (!empty($currentUser['avatar_path'])): ?>
            <img src="/media/<?= e((string) $currentUser['avatar_path']) ?>" alt="<?= e((string) $currentUser['first_name']) ?>">
          <?php else: ?>
            <?= e(initials((string) ($currentUser['first_name'] ?? 'P'), (string) ($currentUser['last_name'] ?? 'C'))) ?>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="pc-large-header" data-navbar-large-header>
  <div class="pc-large-header__title-wrap">
    <?php if ($isHome): ?>
      <span class="pc-large-header__logo" aria-hidden="true">
        <?= brand_svg('prime-care-icone-transparent.svg') ?>
      </span>
    <?php endif; ?>
    <h1 class="pc-large-header__title"><?= e($pageTitle) ?></h1>
  </div>
</div>
