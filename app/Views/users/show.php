<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;

/** @var array<string, mixed> $member */
/** @var array<string, string>|null $generatedPassword */

$isAdmin = Auth::hasRole('admin');
$fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
$isActive = (int) ($member['is_active'] ?? 1) === 1;
$roleKey = (string) ($member['role'] ?? 'caregiver');

View::section('navbar_right');
?>
<a href="/users/<?= (int) $member['id'] ?>/edit" class="pc-btn pc-btn--tinted pc-btn--sm">
  <?= icon('edit-3', '', 16) ?>
  <span>Modifier</span>
</a>
<?php View::endSection(); ?>

<!-- En-tête fiche membre -->
<section class="pc-card flex flex-col items-center text-center py-6">
  <div class="pc-avatar pc-avatar--xl mb-3" style="background-color: <?= e($member['color_hex'] ?? '#5E5CE6') ?>;">
    <?php if (!empty($member['avatar_path'])) : ?>
      <img src="/media/<?= e($member['avatar_path']) ?>" alt="<?= e($fullName) ?>">
    <?php else : ?>
      <?= e(initials((string) ($member['first_name'] ?? ''), (string) ($member['last_name'] ?? ''))) ?>
    <?php endif; ?>
    <span class="pc-avatar__dot" style="background-color: <?= $isActive ? 'var(--ok)' : 'var(--text-3)' ?>; width: 16px; height: 16px;"></span>
  </div>

  <h2 class="text-title-2"><?= e($fullName) ?></h2>
  <p class="text-subhead text-muted mt-1"><?= e($member['email'] ?? '') ?></p>

  <div class="flex items-center gap-2 mt-3">
    <span class="pc-badge pc-badge--brand"><?= e(t('roles.' . $roleKey)) ?></span>
    <span class="pc-badge <?= $isActive ? 'pc-badge--ok' : 'pc-badge--danger' ?>">
      <?= $isActive ? e(t('team.active')) : e(t('team.inactive')) ?>
    </span>
  </div>

  <?php if (!empty($member['phone'])) : ?>
    <div class="flex items-center gap-3 mt-4">
      <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $member['phone']) ?? '') ?>" class="pc-btn pc-btn--secondary pc-btn--sm">
        <?= icon('phone', '', 16) ?>
        <span>Appeler</span>
      </a>
      <a href="mailto:<?= e((string) ($member['email'] ?? '')) ?>" class="pc-btn pc-btn--secondary pc-btn--sm">
        <?= icon('mail', '', 16) ?>
        <span>E-mail</span>
      </a>
    </div>
  <?php endif; ?>
</section>

<!-- Coordonnées & Contrat -->
<div>
  <div class="pc-section-header">Informations & Contrat</div>
  <div class="pc-list-group">
    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Téléphone</div>
        <div class="pc-list-title"><?= e($member['phone'] ?: 'Non renseigné') ?></div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Type de contrat</div>
        <div class="pc-list-title"><?= e($member['contract_type'] ?: 'Non renseigné') ?></div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Date d’embauche</div>
        <div class="pc-list-title"><?= e(!empty($member['hired_at']) ? format_date((string) $member['hired_at'], 'd/m/Y') : 'Non renseignée') ?></div>
      </div>
    </div>

    <?php if ($isAdmin) : ?>
      <div class="pc-list-item">
        <div class="pc-list-body">
          <div class="pc-list-subtitle">Taux horaire net (visible par l’administrateur uniquement)</div>
          <div class="pc-list-title">
            <?= $member['hourly_rate'] !== null ? e(number_format((float) $member['hourly_rate'], 2, ',', ' ') . ' € / h') : 'Non applicable' ?>
          </div>
        </div>
        <span class="pc-badge pc-badge--warn">Confidentiel</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Contact d'urgence du membre -->
<div>
  <div class="pc-section-header">Contact en cas d’urgence</div>
  <div class="pc-list-group">
    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-title"><?= e($member['emergency_contact_name'] ?: 'Aucun contact renseigné') ?></div>
        <div class="pc-list-subtitle"><?= e($member['emergency_contact_phone'] ?: '—') ?></div>
      </div>
      <?php if (!empty($member['emergency_contact_phone'])) : ?>
        <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $member['emergency_contact_phone']) ?? '') ?>" class="pc-contact-btn pc-contact-btn--call" aria-label="Appeler le contact d’urgence">
          <?= icon('phone', '', 18) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Activation / Désactivation avec feuille de confirmation iOS (§3.4) -->
<?php if ((int) (Auth::id() ?? 0) !== (int) $member['id']) : ?>
  <div class="mt-2">
    <button
      type="button"
      class="pc-btn <?= $isActive ? 'pc-btn--danger-soft' : 'pc-btn--primary' ?> pc-btn--block"
      data-sheet-open="confirm-toggle-member-sheet"
    >
      <?= icon($isActive ? 'shield-alert' : 'circle-check', '', 18) ?>
      <span><?= $isActive ? 'Désactiver ce compte' : 'Réactiver ce compte' ?></span>
    </button>
  </div>

  <div id="confirm-toggle-member-sheet" class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="toggle-member-title" aria-hidden="true">
    <div class="pc-sheet__backdrop" data-sheet-close></div>
    <div class="pc-sheet__panel">
      <div class="pc-sheet__handle"></div>
      <div class="pc-sheet__header">
        <h2 id="toggle-member-title" class="pc-sheet__title">
          <?= $isActive ? e(t('team.confirm_deactivate_title')) : 'Réactiver ce membre ?' ?>
        </h2>
        <button type="button" class="pc-navbar__btn" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <p class="text-subhead text-muted mb-5">
        <?= $isActive ? e(t('team.confirm_deactivate_body')) : 'Ce membre pourra à nouveau se connecter à Prime Care.' ?>
      </p>

      <div class="flex flex-col gap-2">
        <form method="POST" action="/users/<?= (int) $member['id'] ?>/toggle">
          <?= csrf_field() ?>
          <button type="submit" class="pc-btn <?= $isActive ? 'pc-btn--danger' : 'pc-btn--primary' ?> pc-btn--block">
            <?= $isActive ? 'Confirmer la désactivation' : 'Confirmer la réactivation' ?>
          </button>
        </form>
        <button type="button" class="pc-btn pc-btn--secondary pc-btn--block" data-sheet-close>
          Annuler
        </button>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Feuille de mot de passe initial affiché une seule fois (§3.4) -->
<?php if (!empty($generatedPassword)) : ?>
  <div id="generated-password-sheet" class="pc-sheet is-open" role="dialog" aria-modal="true" aria-labelledby="gen-pw-title" aria-hidden="false">
    <div class="pc-sheet__backdrop" data-sheet-close></div>
    <div class="pc-sheet__panel">
      <div class="pc-sheet__handle"></div>
      <div class="pc-sheet__header">
        <h2 id="gen-pw-title" class="pc-sheet__title"><?= e(t('team.initial_password_title')) ?></h2>
        <button type="button" class="pc-navbar__btn" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>

      <p class="text-subhead text-muted mb-4">
        <?= e(t('team.initial_password_help')) ?>
      </p>

      <div class="pc-card mb-4" style="background: var(--surface-2);">
        <div class="text-caption text-muted mb-1"><?= e($generatedPassword['name'] ?? '') ?> (<?= e($generatedPassword['email'] ?? '') ?>)</div>
        <div class="flex items-center justify-between gap-2">
          <code class="text-title-2 text-tabular" style="color: var(--brand); letter-spacing: 0.04em;">
            <?= e($generatedPassword['password'] ?? '') ?>
          </code>
          <button
            type="button"
            class="pc-btn pc-btn--primary pc-btn--sm"
            data-copy-text="<?= e($generatedPassword['password'] ?? '') ?>"
          >
            <?= icon('copy', '', 16) ?>
            <span><?= e(t('team.copy_password')) ?></span>
          </button>
        </div>
      </div>

      <button type="button" class="pc-btn pc-btn--secondary pc-btn--block" data-sheet-close>
        J’ai copié le mot de passe
      </button>
    </div>
  </div>
<?php endif; ?>
