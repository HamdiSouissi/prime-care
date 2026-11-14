<?php

declare(strict_types=1);

$member = is_array($member ?? null) ? $member : [];
$memberId = (int) ($member['id'] ?? 0);
$fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
$isActive = (int) ($member['is_active'] ?? 1) === 1;
$role = (string) ($member['role'] ?? 'caregiver');
$canViewRate = (bool) ($canViewHourlyRate ?? can('users', 'view_hourly_rate'));
$initialPassword = isset($initialPassword) && is_string($initialPassword) && $initialPassword !== '' ? $initialPassword : null;
$avatarClass = avatar_color_class(max(1, $memberId));
?>
<?php View::section('navbar_action'); ?>
  <a href="/users/<?= $memberId ?>/edit" class="pc-btn pc-btn--tinted pc-btn--sm">
    <?= icon('edit-3', 16) ?>
    <span>Modifier</span>
  </a>
<?php View::endSection(); ?>

<!-- En-tête membre -->
<section class="pc-card flex flex-col items-center text-center">
  <div class="pc-avatar pc-avatar--xl <?= e($avatarClass) ?> mb-3">
    <?php if (!empty($member['avatar_path'])): ?>
      <img src="/media/<?= e((string) $member['avatar_path']) ?>" alt="<?= e($fullName) ?>">
    <?php else: ?>
      <?= e(initials((string) ($member['first_name'] ?? ''), (string) ($member['last_name'] ?? ''))) ?>
    <?php endif; ?>
    <span class="pc-avatar__dot pc-avatar__dot--lg <?= $isActive ? 'pc-avatar__dot--active' : 'pc-avatar__dot--inactive' ?>"></span>
  </div>

  <h2 class="text-title-2"><?= e($fullName) ?></h2>
  <p class="text-subhead text-muted"><?= e((string) ($member['email'] ?? '')) ?></p>

  <div class="flex items-center gap-2 mt-2">
    <span class="pc-badge pc-badge--brand"><?= e(t('roles.' . $role)) ?></span>
    <span class="pc-badge <?= $isActive ? 'pc-badge--ok' : 'pc-badge--danger' ?>">
      <?= $isActive ? e(t('team.active')) : e(t('team.inactive')) ?>
    </span>
  </div>

  <?php if (!empty($member['phone'])): ?>
    <div class="flex items-center gap-3 mt-4">
      <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $member['phone']) ?? '') ?>" class="pc-btn pc-btn--primary pc-btn--sm">
        <?= icon('phone', 16) ?>
        <span>Appeler (<?= e((string) $member['phone']) ?>)</span>
      </a>
      <a href="sms:<?= e(preg_replace('/\s+/', '', (string) $member['phone']) ?? '') ?>" class="pc-btn pc-btn--secondary pc-btn--sm">
        <?= icon('message-circle', 16) ?>
        <span>SMS</span>
      </a>
    </div>
  <?php endif; ?>
</section>

<!-- Contrat & Heures -->
<section aria-labelledby="member-contract-heading">
  <div class="pc-section-header" id="member-contract-heading">Contrat & Organisation</div>
  <div class="pc-list-group">
    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Type de contrat</div>
        <div class="pc-list-title"><?= e((string) ($member['contract_type'] ?: 'Non renseigné')) ?></div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Volume hebdomadaire prévu</div>
        <div class="pc-list-title text-tabular">
          <?= $member['weekly_hours'] !== null ? e(number_format((float) $member['weekly_hours'], 1, ',', ' ')) . ' h / sem.' : 'Non renseigné' ?>
        </div>
      </div>
    </div>

    <?php if ($canViewRate): ?>
      <div class="pc-list-item pc-list-item--no-icon">
        <div class="pc-list-body">
          <div class="pc-list-subtitle">Taux horaire net (confidentiel Admin)</div>
          <div class="pc-list-title text-tabular">
            <?= $member['hourly_rate'] !== null ? e(number_format((float) $member['hourly_rate'], 2, ',', ' ')) . ' € / h' : 'Non renseigné' ?>
          </div>
        </div>
        <span class="pc-badge pc-badge--brand">Admin seul</span>
      </div>
    <?php endif; ?>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Date de début d’intervention</div>
        <div class="pc-list-title"><?= format_date($member['hired_at'] ?? null, 'd/m/Y') ?: 'Non renseignée' ?></div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Dernière connexion</div>
        <div class="pc-list-title"><?= !empty($member['last_login_at']) ? format_datetime((string) $member['last_login_at']) : 'Jamais connecté' ?></div>
      </div>
    </div>
  </div>
</section>

<!-- Contact d'urgence du membre -->
<section aria-labelledby="member-emergency-heading">
  <div class="pc-section-header" id="member-emergency-heading">Contact d’urgence du membre</div>
  <div class="pc-list-group">
    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Personne à prévenir</div>
        <div class="pc-list-title">
          <?= e((string) ($member['emergency_contact_name'] ?: 'Non renseignée')) ?>
          <?php if (!empty($member['emergency_contact_relation'])): ?>
            <span class="text-muted">(<?= e((string) $member['emergency_contact_relation']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($member['emergency_contact_phone'])): ?>
          <div class="pc-list-subtitle"><?= e((string) $member['emergency_contact_phone']) ?></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($member['emergency_contact_phone'])): ?>
        <a
          href="tel:<?= e(preg_replace('/\s+/', '', (string) $member['emergency_contact_phone']) ?? '') ?>"
          class="pc-contact-btn pc-contact-btn--call"
          aria-label="Appeler le contact d’urgence"
        >
          <?= icon('phone', 18) ?>
        </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($member['notes'])): ?>
      <div class="pc-list-item pc-list-item--no-icon">
        <div class="pc-list-body">
          <div class="pc-list-subtitle">Notes internes</div>
          <div class="pc-list-title"><?= nl2br(e((string) $member['notes'])) ?></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Activation / Désactivation du compte -->
<?php if ($memberId !== (int) (auth()['id'] ?? 0)): ?>
  <section class="mt-2">
    <?php if ($isActive): ?>
      <button type="button" class="pc-btn pc-btn--danger-soft pc-btn--block" data-alert-open="confirm-deactivate-alert">
        <?= icon('user-x', 18) ?>
        <span>Désactiver ce compte</span>
      </button>
    <?php else: ?>
      <form method="POST" action="/users/<?= $memberId ?>/toggle-status" data-loading-form>
        <?= csrf_field() ?>
        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('user-check', 18) ?>
          <span>Réactiver ce compte</span>
        </button>
      </form>
    <?php endif; ?>
  </section>

  <!-- Dialogue de confirmation pour la désactivation -->
  <div class="pc-alert-backdrop" id="confirm-deactivate-alert" role="alertdialog" aria-modal="true" aria-labelledby="deactivate-title">
    <div class="pc-alert-dialog">
      <div class="pc-alert-dialog__body">
        <h3 class="pc-alert-dialog__title" id="deactivate-title"><?= e(t('team.confirm_deactivate_title')) ?></h3>
        <p class="pc-alert-dialog__desc"><?= e(t('team.confirm_deactivate_body')) ?></p>
      </div>
      <div class="pc-alert-dialog__actions">
        <button type="button" class="pc-alert-dialog__btn" data-alert-close>Annuler</button>
        <form method="POST" action="/users/<?= $memberId ?>/toggle-status">
          <?= csrf_field() ?>
          <button type="submit" class="pc-alert-dialog__btn pc-alert-dialog__btn--danger w-full">
            Désactiver
          </button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Feuille modale : Mot de passe initial généré (affiché une seule fois après création) -->
<?php if ($initialPassword !== null): ?>
  <div class="pc-sheet-backdrop is-open" id="initial-password-sheet" role="dialog" aria-modal="true" aria-labelledby="init-pwd-title">
    <div class="pc-sheet">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="init-pwd-title"><?= e(t('team.initial_password_title')) ?></h2>
        <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
          <?= icon('x', 22) ?>
        </button>
      </div>

      <p class="text-subhead text-muted mb-4">
        <?= e(t('team.initial_password_help')) ?>
      </p>

      <div class="pc-card pc-card--subtle mb-4">
        <div class="text-caption text-muted mb-1">Mot de passe temporaire de <?= e($fullName) ?></div>
        <div class="flex items-center justify-between gap-2">
          <code class="text-title-2 text-tabular pc-code-display">
            <?= e($initialPassword) ?>
          </code>
          <button
            type="button"
            class="pc-btn pc-btn--primary pc-btn--sm"
            data-copy-text="<?= e($initialPassword) ?>"
          >
            <?= icon('copy', 16) ?>
            <span><?= e(t('team.copy_password')) ?></span>
          </button>
        </div>
      </div>

      <button type="button" class="pc-btn pc-btn--secondary pc-btn--block" data-sheet-close>
        J’ai bien noté ce mot de passe
      </button>
    </div>
  </div>
<?php endif; ?>
