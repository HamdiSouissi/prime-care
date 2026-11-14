<?php

declare(strict_types=1);

use App\Core\View;

/** @var array<int, array<string, mixed>> $users */
/** @var string $search */
/** @var array<string, string>|null $generatedPassword */

View::section('navbar_right');
?>
<a href="/users/create" class="pc-btn pc-btn--primary pc-btn--sm" aria-label="Créer un membre">
  <?= icon('user-plus', '', 16) ?>
  <span>Ajouter</span>
</a>
<?php View::endSection(); ?>

<!-- Recherche instantanée -->
<div class="pc-search">
  <?= icon('search', 'pc-search__icon', 18) ?>
  <input
    type="search"
    placeholder="<?= e(t('team.search_placeholder')) ?>"
    value="<?= e($search) ?>"
    data-instant-search="#team-list"
    aria-label="<?= e(t('team.search_placeholder')) ?>"
  >
</div>

<!-- Liste groupée de l'équipe -->
<div id="team-list" class="flex flex-col gap-3">
  <?php
  $grouped = [
      'caregiver' => 'Aides-soignantes à domicile',
      'coordinator' => 'Coordination & Planning',
      'admin' => 'Famille & Administration',
      'viewer' => 'Médecin & Lecture seule',
  ];
  ?>
  <?php foreach ($grouped as $roleKey => $groupTitle) : ?>
    <?php
    $members = array_values(array_filter(
        $users,
        static fn(array $u): bool => ($u['role'] ?? '') === $roleKey
    ));
    if ($members === []) {
        continue;
    }
    ?>
    <div>
      <div class="pc-section-header"><?= e($groupTitle) ?> (<?= count($members) ?>)</div>
      <div class="pc-list-group">
        <?php foreach ($members as $member) : ?>
          <?php
          $fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
          $searchText = $fullName . ' ' . ($member['email'] ?? '') . ' ' . ($member['phone'] ?? '') . ' ' . t('roles.' . $roleKey);
          $isActive = (int) ($member['is_active'] ?? 1) === 1;
          ?>
          <a href="/users/<?= (int) $member['id'] ?>" class="pc-list-item" data-search-item="<?= e($searchText) ?>">
            <div class="pc-avatar" style="background-color: <?= e($member['color_hex'] ?? '#5E5CE6') ?>;">
              <?php if (!empty($member['avatar_path'])) : ?>
                <img src="/media/<?= e($member['avatar_path']) ?>" alt="<?= e($fullName) ?>">
              <?php else : ?>
                <?= e(initials((string) ($member['first_name'] ?? ''), (string) ($member['last_name'] ?? ''))) ?>
              <?php endif; ?>
              <span class="pc-avatar__dot" style="background-color: <?= $isActive ? 'var(--ok)' : 'var(--text-3)' ?>;"></span>
            </div>

            <div class="pc-list-body">
              <div class="pc-list-title flex items-center gap-2">
                <span><?= e($fullName) ?></span>
                <?php if (!$isActive) : ?>
                  <span class="pc-badge pc-badge--danger">Inactif</span>
                <?php endif; ?>
              </div>
              <div class="pc-list-subtitle">
                <?= e($member['email'] ?? '') ?>
                <?php if (!empty($member['contract_type'])) : ?>
                  · <?= e((string) $member['contract_type']) ?>
                <?php endif; ?>
              </div>
            </div>

            <span class="pc-list-meta">
              <span class="pc-badge pc-badge--brand"><?= e(t('roles.' . $roleKey)) ?></span>
              <?= icon('chevron-right', 'pc-list-chevron', 18) ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
