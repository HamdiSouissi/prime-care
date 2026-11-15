<?php

declare(strict_types=1);

$users = is_array($users ?? null) ? $users : [];

$grouped = [
    'admin' => [],
    'coordinator' => [],
    'caregiver' => [],
    'viewer' => [],
];

foreach ($users as $u) {
    $r = (string) ($u['role'] ?? 'caregiver');
    $grouped[$r][] = $u;
}

$groupLabels = [
    'admin' => 'Responsable (Admin)',
    'coordinator' => 'Référente & Coordination',
    'caregiver' => 'Aides-soignantes',
    'viewer' => 'Consultation (Famille & Médecins)',
];
?>
<?php View::section('navbar_action'); ?>
  <a href="/users/create" class="pc-btn pc-btn--primary pc-btn--sm" aria-label="Ajouter un membre">
    <?= icon('user-plus', 18) ?>
    <span>Ajouter</span>
  </a>
<?php View::endSection(); ?>

<!-- Barre de recherche instantanée -->
<div class="pc-search">
  <span class="pc-search__icon"><?= icon('search', 18) ?></span>
  <input
    type="search"
    id="team-search-input"
    placeholder="<?= e(t('team.search_placeholder')) ?>"
    aria-label="<?= e(t('team.search_placeholder')) ?>"
  >
</div>

<!-- Liste groupée par rôle (C5 : classes d'avatar .pc-avatar--c1..c8, C6.1 : badge sous le nom + e-mail tronqué) -->
<?php foreach ($grouped as $roleKey => $members): ?>
  <?php if (empty($members)) { continue; } ?>
  <section class="team-role-group" data-role-group="<?= e($roleKey) ?>">
    <div class="pc-section-header">
      <?= e($groupLabels[$roleKey] ?? $roleKey) ?> (<?= count($members) ?>)
    </div>

    <div class="pc-list-group">
      <?php foreach ($members as $member): ?>
        <?php
        $memberId = (int) ($member['id'] ?? 1);
        $fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        $isActive = (int) ($member['is_active'] ?? 1) === 1;
        $roleLabel = t('roles.' . ($member['role'] ?? 'caregiver'));
        $searchHaystack = mb_strtolower($fullName . ' ' . ($member['email'] ?? '') . ' ' . ($member['phone'] ?? '') . ' ' . $roleLabel);
        ?>
        <a
          href="/users/<?= $memberId ?>"
          class="pc-list-item"
          data-team-item
          data-search-text="<?= e($searchHaystack) ?>"
        >
          <div class="pc-avatar <?= e(avatar_color_class($memberId)) ?>">
            <?php if (!empty($member['avatar_path'])): ?>
              <img src="/media/<?= e((string) $member['avatar_path']) ?>" alt="<?= e($fullName) ?>" loading="lazy">
            <?php else: ?>
              <?= e(initials((string) ($member['first_name'] ?? ''), (string) ($member['last_name'] ?? ''))) ?>
            <?php endif; ?>
            <span
              class="pc-avatar__dot <?= $isActive ? 'pc-avatar__dot--active' : 'pc-avatar__dot--inactive' ?>"
              title="<?= $isActive ? e(t('team.active')) : e(t('team.inactive')) ?>"
            ></span>
          </div>

          <div class="pc-list-body">
            <div class="pc-list-title"><?= e($fullName) ?></div>
            <div class="pc-list-subtitle pc-list-subtitle--truncate">
              <?= e((string) ($member['email'] ?? '')) ?>
              <?php if (!empty($member['phone'])): ?>
                · <?= e((string) $member['phone']) ?>
              <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-wrap mt-1">
              <span class="pc-badge pc-badge--brand"><?= e($roleLabel) ?></span>
              <?php if (!$isActive): ?>
                <span class="pc-badge pc-badge--danger">Inactif</span>
              <?php endif; ?>
            </div>
          </div>

          <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
