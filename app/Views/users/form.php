<?php

declare(strict_types=1);

$isEdit = (bool) ($isEdit ?? false);
$member = is_array($member ?? null) ? $member : [];
$memberId = (int) ($member['id'] ?? 0);
$actionUrl = $isEdit ? '/users/' . $memberId . '/update' : '/users';
?>
<section class="pc-card">
  <form method="POST" action="<?= e($actionUrl) ?>" class="flex flex-col gap-4" novalidate data-loading-form>
    <?= csrf_field() ?>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="first_name" class="pc-label">Prénom *</label>
        <input
          type="text"
          id="first_name"
          name="first_name"
          value="<?= e((string) old('first_name', $member['first_name'] ?? '')) ?>"
          class="pc-input <?= has_error('first_name') ? 'has-error' : '' ?>"
          required
        >
        <?php if (has_error('first_name')): ?>
          <p class="pc-field-error"><?= e((string) field_error('first_name')) ?></p>
        <?php endif; ?>
      </div>

      <div class="pc-field">
        <label for="last_name" class="pc-label">Nom *</label>
        <input
          type="text"
          id="last_name"
          name="last_name"
          value="<?= e((string) old('last_name', $member['last_name'] ?? '')) ?>"
          class="pc-input <?= has_error('last_name') ? 'has-error' : '' ?>"
          required
        >
        <?php if (has_error('last_name')): ?>
          <p class="pc-field-error"><?= e((string) field_error('last_name')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="pc-field">
      <label for="email" class="pc-label">Adresse e-mail *</label>
      <input
        type="email"
        id="email"
        name="email"
        value="<?= e((string) old('email', $member['email'] ?? '')) ?>"
        class="pc-input <?= has_error('email') ? 'has-error' : '' ?>"
        inputmode="email"
        required
      >
      <?php if (has_error('email')): ?>
        <p class="pc-field-error"><?= e((string) field_error('email')) ?></p>
      <?php endif; ?>
    </div>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="phone" class="pc-label">Téléphone mobile</label>
        <input
          type="tel"
          id="phone"
          name="phone"
          value="<?= e((string) old('phone', $member['phone'] ?? '')) ?>"
          class="pc-input"
          inputmode="tel"
          placeholder="06 12 34 56 78"
        >
      </div>

      <div class="pc-field">
        <label for="role" class="pc-label">Rôle dans l’application *</label>
        <?php $selectedRole = (string) old('role', $member['role'] ?? 'caregiver'); ?>
        <select id="role" name="role" class="pc-select">
          <option value="caregiver" <?= $selectedRole === 'caregiver' ? 'selected' : '' ?>>Aide-soignante</option>
          <option value="coordinator" <?= $selectedRole === 'coordinator' ? 'selected' : '' ?>>Référente (Coordinatrice)</option>
          <option value="viewer" <?= $selectedRole === 'viewer' ? 'selected' : '' ?>>Consultation (Famille / Médecin)</option>
          <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Responsable (Admin)</option>
        </select>
      </div>
    </div>

    <input type="hidden" name="color_hex" value="<?= e((string) old('color_hex', $member['color_hex'] ?? '#5E5CE6')) ?>">

    <hr class="pc-divider">

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="contract_type" class="pc-label">Type de contrat</label>
        <input
          type="text"
          id="contract_type"
          name="contract_type"
          value="<?= e((string) old('contract_type', $member['contract_type'] ?? '')) ?>"
          class="pc-input"
          placeholder="CESU CDI, Prestataire…"
        >
      </div>

      <div class="pc-field">
        <label for="hired_at" class="pc-label">Date de début</label>
        <input
          type="date"
          id="hired_at"
          name="hired_at"
          value="<?= e((string) old('hired_at', $member['hired_at'] ?? '')) ?>"
          class="pc-input"
        >
      </div>
    </div>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="weekly_hours" class="pc-label">Heures / semaine</label>
        <input
          type="number"
          step="0.5"
          min="0"
          max="60"
          id="weekly_hours"
          name="weekly_hours"
          value="<?= e((string) old('weekly_hours', $member['weekly_hours'] ?? '')) ?>"
          class="pc-input"
          placeholder="35"
        >
      </div>

      <div class="pc-field">
        <label for="hourly_rate" class="pc-label">Taux horaire net (€/h)</label>
        <input
          type="number"
          step="0.05"
          min="0"
          max="200"
          id="hourly_rate"
          name="hourly_rate"
          value="<?= e((string) old('hourly_rate', $member['hourly_rate'] ?? '')) ?>"
          class="pc-input"
          placeholder="14.50"
        >
      </div>
    </div>

    <div class="pc-field">
      <label for="member_pin" class="pc-label">Code PIN initial (4 à 6 chiffres, optionnel)</label>
      <input
        type="password"
        id="member_pin"
        name="pin"
        class="pc-input <?= has_error('pin') ? 'has-error' : '' ?>"
        inputmode="numeric"
        pattern="\d{4,6}"
        maxlength="6"
        placeholder="<?= $isEdit ? 'Laisser vide pour conserver le PIN actuel' : 'Ex : 3333' ?>"
      >
      <?php if (has_error('pin')): ?>
        <p class="pc-field-error"><?= e((string) field_error('pin')) ?></p>
      <?php endif; ?>
    </div>

    <hr class="pc-divider">

    <div class="pc-field">
      <label for="emergency_contact_name" class="pc-label">Contact d’urgence du membre</label>
      <input
        type="text"
        id="emergency_contact_name"
        name="emergency_contact_name"
        value="<?= e((string) old('emergency_contact_name', $member['emergency_contact_name'] ?? '')) ?>"
        class="pc-input"
        placeholder="Nom et prénom"
      >
    </div>

    <div class="pc-module-grid pc-module-grid--2col">
      <div class="pc-field">
        <label for="emergency_contact_phone" class="pc-label">Tél. d’urgence</label>
        <input
          type="tel"
          id="emergency_contact_phone"
          name="emergency_contact_phone"
          value="<?= e((string) old('emergency_contact_phone', $member['emergency_contact_phone'] ?? '')) ?>"
          class="pc-input"
          inputmode="tel"
        >
      </div>

      <div class="pc-field">
        <label for="emergency_contact_relation" class="pc-label">Lien de parenté</label>
        <input
          type="text"
          id="emergency_contact_relation"
          name="emergency_contact_relation"
          value="<?= e((string) old('emergency_contact_relation', $member['emergency_contact_relation'] ?? '')) ?>"
          class="pc-input"
          placeholder="Époux, sœur…"
        >
      </div>
    </div>

    <div class="pc-field">
      <label for="notes" class="pc-label">Notes internes</label>
      <textarea id="notes" name="notes" class="pc-textarea"><?= e((string) old('notes', $member['notes'] ?? '')) ?></textarea>
    </div>

    <div class="flex items-center gap-3 pt-2">
      <a href="<?= $isEdit ? '/users/' . $memberId : '/users' ?>" class="pc-btn pc-btn--secondary">Annuler</a>
      <button type="submit" class="pc-btn pc-btn--primary flex-1">
        <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le compte membre' ?>
      </button>
    </div>
  </form>
</section>
