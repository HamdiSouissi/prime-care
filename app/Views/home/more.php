<?php

declare(strict_types=1);

use App\Core\Auth;

$user = Auth::user() ?? [];
$isAdmin = Auth::hasRole('admin');
$currentTheme = (string) ($user['theme'] ?? 'auto');
?>
<!-- Carte utilisateur résumée -->
<a href="/profile" class="pc-card flex items-center gap-4">
  <div class="pc-avatar pc-avatar--lg" style="background-color: <?= e($user['color_hex'] ?? '#5E5CE6') ?>;">
    <?php if (!empty($user['avatar_path'])) : ?>
      <img src="/media/<?= e($user['avatar_path']) ?>" alt="<?= e($user['first_name'] ?? '') ?>">
    <?php else : ?>
      <?= e(initials((string) ($user['first_name'] ?? 'P'), (string) ($user['last_name'] ?? 'C'))) ?>
    <?php endif; ?>
  </div>
  <div class="flex-1" style="min-width: 0;">
    <div class="text-title-3"><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
    <div class="text-subhead text-muted"><?= e($user['email'] ?? '') ?></div>
    <span class="pc-badge pc-badge--brand mt-1"><?= e(t('roles.' . ($user['role'] ?? 'caregiver'))) ?></span>
  </div>
  <span class="pc-list-chevron"><?= icon('chevron-right', '', 20) ?></span>
</a>

<!-- Liste groupée principale (§3.2) -->
<div>
  <div class="pc-section-header">Dossier & Compte</div>
  <div class="pc-list-group">
    <a href="/patient" class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--c-vitals);">
        <?= icon('heart-pulse', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Patient</div>
        <div class="pc-list-subtitle">Henri Benali · Contacts d’urgence & Protocole hypo</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/profile" class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--brand);">
        <?= icon('user', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Mon profil</div>
        <div class="pc-list-subtitle">Photo, mot de passe, code PIN, thème</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/coming-soon/documents" class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--c-docs);">
        <?= icon('file-text', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Documents médicaux</div>
        <div class="pc-list-subtitle">Ordonnances et comptes rendus (Phase 4)</div>
      </div>
      <span class="pc-list-meta">
        <span class="pc-badge pc-badge--info">Bientôt</span>
        <?= icon('chevron-right', 'pc-list-chevron', 18) ?>
      </span>
    </a>
  </div>
</div>

<?php if ($isAdmin) : ?>
  <div>
    <div class="pc-section-header">Administration</div>
    <div class="pc-list-group">
      <a href="/users" class="pc-list-item">
        <span class="pc-list-icon" style="background: var(--c-team);">
          <?= icon('users', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Équipe</div>
          <div class="pc-list-subtitle">Comptes aides-soignantes, rôles, contrats</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/settings" class="pc-list-item">
        <span class="pc-list-icon" style="background: #636366;">
          <?= icon('settings', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Réglages</div>
          <div class="pc-list-subtitle">Seuils d’alertes, notifications, audit (Phase 4)</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/design" class="pc-list-item">
        <span class="pc-list-icon" style="background: #AF52DE;">
          <?= icon('palette', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Design system</div>
          <div class="pc-list-subtitle">Vitrine des composants & Identité Prime Care</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    </div>
  </div>
<?php endif; ?>

<!-- Apparence rapide (3 clics : Plus -> Thème) -->
<div>
  <div class="pc-section-header">Apparence & Application</div>
  <div class="pc-card mb-3">
    <div class="flex items-center justify-between mb-2">
      <span class="text-subhead" style="font-weight: 600;">Thème d’affichage</span>
      <span class="text-caption text-muted">Application immédiate</span>
    </div>
    <div class="pc-segmented" data-segmented data-theme-switcher data-persist-theme="1" role="group" aria-label="Choix du thème">
      <button type="button" class="pc-segmented__btn <?= $currentTheme === 'auto' ? 'is-active' : '' ?>" data-value="auto" aria-pressed="<?= $currentTheme === 'auto' ? 'true' : 'false' ?>">
        <?= icon('monitor', '', 16) ?>
        <span>Auto</span>
      </button>
      <button type="button" class="pc-segmented__btn <?= $currentTheme === 'light' ? 'is-active' : '' ?>" data-value="light" aria-pressed="<?= $currentTheme === 'light' ? 'true' : 'false' ?>">
        <?= icon('sun', '', 16) ?>
        <span>Clair</span>
      </button>
      <button type="button" class="pc-segmented__btn <?= $currentTheme === 'dark' ? 'is-active' : '' ?>" data-value="dark" aria-pressed="<?= $currentTheme === 'dark' ? 'true' : 'false' ?>">
        <?= icon('moon', '', 16) ?>
        <span>Sombre</span>
      </button>
    </div>
  </div>

  <div class="pc-list-group">
    <button type="button" class="pc-list-item" data-install-pwa>
      <span class="pc-list-icon" style="background: var(--ok);">
        <?= icon('download', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Installer l’application</div>
        <div class="pc-list-subtitle">Ajouter Prime Care sur l’écran d’accueil</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </button>

    <a href="/about" class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--info);">
        <?= icon('info', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">À propos</div>
        <div class="pc-list-subtitle">Prime Care · by Prime Vision Solutions</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>
  </div>
</div>

<!-- Déconnexion -->
<form method="POST" action="/logout">
  <?= csrf_field() ?>
  <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
    <?= icon('log-out', '', 20) ?>
    <span>Se déconnecter</span>
  </button>
</form>
