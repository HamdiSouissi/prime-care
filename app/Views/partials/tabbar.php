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
    <a href="/" class="pc-tabbar__item <?= $activeTab === 'home' ? 'is-active' : '' ?>">
      <?= icon('home', '', 22) ?>
      <span>Accueil</span>
    </a>

    <?php if ($isCaregiverOrCoord) : ?>
      <a href="/today" class="pc-tabbar__item <?= $activeTab === 'today' ? 'is-active' : '' ?>">
        <?= icon('calendar-days', '', 22) ?>
        <span>Aujourd’hui</span>
      </a>
    <?php else : ?>
      <a href="/health" class="pc-tabbar__item <?= $activeTab === 'health' ? 'is-active' : '' ?>">
        <?= icon('heart-pulse', '', 22) ?>
        <span>Santé</span>
      </a>
    <?php endif; ?>

    <?php if ($canQuickAdd) : ?>
      <div class="pc-tabbar__item">
        <button type="button" id="pc-tabbar-plus" class="pc-tabbar__plus-btn" aria-label="Ajouter une saisie rapide">
          <?= icon('plus', '', 26) ?>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($isCaregiverOrCoord) : ?>
      <a href="/journal" class="pc-tabbar__item <?= $activeTab === 'journal' ? 'is-active' : '' ?>">
        <?= icon('notebook-pen', '', 22) ?>
        <span>Journal</span>
      </a>
    <?php elseif ($isAdmin) : ?>
      <a href="/users" class="pc-tabbar__item <?= $activeTab === 'team' ? 'is-active' : '' ?>">
        <?= icon('users', '', 22) ?>
        <span>Équipe</span>
      </a>
    <?php else : ?>
      <a href="/patient" class="pc-tabbar__item <?= $activeTab === 'patient' ? 'is-active' : '' ?>">
        <?= icon('user', '', 22) ?>
        <span>Patient</span>
      </a>
    <?php endif; ?>

    <a href="/more" class="pc-tabbar__item <?= $activeTab === 'more' ? 'is-active' : '' ?>">
      <?= icon('more-horizontal', '', 22) ?>
      <span>Plus</span>
    </a>
  </div>
</nav>
