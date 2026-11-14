<?php

declare(strict_types=1);

use App\Core\Auth;

/** @var string $activeTab */
$activeTab = $activeTab ?? 'home';
$role = (string) (Auth::user()['role'] ?? 'caregiver');
$isCaregiverOrCoord = in_array($role, ['caregiver', 'coordinator'], true);
$isAdmin = $role === 'admin';
$canQuickAdd = in_array($role, ['admin', 'coordinator', 'caregiver'], true);
?>
<nav class="pc-tabbar" aria-label="Navigation principale">
  <div class="pc-tabbar__list">
    <a
      href="/"
      class="pc-tabbar__item <?= $activeTab === 'home' ? 'is-active' : '' ?>"
      aria-label="Accueil"
      <?= $activeTab === 'home' ? 'aria-current="page"' : '' ?>
    >
      <?= icon('home', '', 22) ?>
      <span class="pc-tabbar__label">Accueil</span>
    </a>

    <?php if ($isCaregiverOrCoord) : ?>
      <a
        href="/today"
        class="pc-tabbar__item <?= $activeTab === 'today' ? 'is-active' : '' ?>"
        aria-label="Aujourd’hui"
        <?= $activeTab === 'today' ? 'aria-current="page"' : '' ?>
      >
        <?= icon('calendar-days', '', 22) ?>
        <span class="pc-tabbar__label">Aujourd’hui</span>
      </a>
    <?php else : ?>
      <a
        href="/health"
        class="pc-tabbar__item <?= $activeTab === 'health' ? 'is-active' : '' ?>"
        aria-label="Santé"
        <?= $activeTab === 'health' ? 'aria-current="page"' : '' ?>
      >
        <?= icon('heart-pulse', '', 22) ?>
        <span class="pc-tabbar__label">Santé</span>
      </a>
    <?php endif; ?>

    <?php if ($canQuickAdd) : ?>
      <div class="pc-tabbar__plus-slot">
        <button type="button" id="pc-tabbar-plus" class="pc-tabbar__plus-btn" aria-label="Ajouter une saisie rapide">
          <?= icon('plus', '', 24) ?>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($isCaregiverOrCoord) : ?>
      <a
        href="/journal"
        class="pc-tabbar__item <?= $activeTab === 'journal' ? 'is-active' : '' ?>"
        aria-label="Journal"
        <?= $activeTab === 'journal' ? 'aria-current="page"' : '' ?>
      >
        <?= icon('notebook-pen', '', 22) ?>
        <span class="pc-tabbar__label">Journal</span>
      </a>
    <?php elseif ($isAdmin) : ?>
      <a
        href="/users"
        class="pc-tabbar__item <?= $activeTab === 'team' ? 'is-active' : '' ?>"
        aria-label="Équipe"
        <?= $activeTab === 'team' ? 'aria-current="page"' : '' ?>
      >
        <?= icon('users', '', 22) ?>
        <span class="pc-tabbar__label">Équipe</span>
      </a>
    <?php else : ?>
      <a
        href="/patient"
        class="pc-tabbar__item <?= $activeTab === 'patient' ? 'is-active' : '' ?>"
        aria-label="Patient"
        <?= $activeTab === 'patient' ? 'aria-current="page"' : '' ?>
      >
        <?= icon('user', '', 22) ?>
        <span class="pc-tabbar__label">Patient</span>
      </a>
    <?php endif; ?>

    <a
      href="/more"
      class="pc-tabbar__item <?= $activeTab === 'more' ? 'is-active' : '' ?>"
      aria-label="Plus"
      <?= $activeTab === 'more' ? 'aria-current="page"' : '' ?>
    >
      <?= icon('more-horizontal', '', 22) ?>
      <span class="pc-tabbar__label">Plus</span>
    </a>
  </div>
</nav>
