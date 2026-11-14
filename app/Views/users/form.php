<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $member */
$isEdit = $member !== null;
$actionUrl = $isEdit ? '/users/' . (int) $member['id'] : '/users';

$roleVal = (string) old('role', (string) ($member['role'] ?? 'caregiver'));
$colorVal = (string) old('color_hex', (string) ($member['color_hex'] ?? '#FF9500'));
$palette = ['#FF9500', '#34C759', '#5E5CE6', '#AF52DE', '#007AFF', '#FF2D55', '#5AC8FA'];
?>
<section class="pc-card">
  <form method="POST" action="<?= e($actionUrl) ?>" class="flex flex-col gap-4" novalidate>
    <?= csrf_field() ?>

    <div class="pc-field">
      <label for="first_name" class="pc-label">Prénom *</label>
      <input
        type="text"
        id="first_name"
        name="first_name"
        required
        value="<?= e(old('first_name', (string) ($member['first_name'] ?? ''))) ?>"
        class="pc-input <?= errors('first_name') ? 'has-error' : '' ?>"
      >
      <?php if (errors('first_name')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('first_name')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="last_name" class="pc-label">Nom *</label>
      <input
        type="text"
        id="last_name"
        name="last_name"
        required
        value="<?= e(old('last_name', (string) ($member['last_name'] ?? ''))) ?>"
        class="pc-input <?= errors('last_name') ? 'has-error' : '' ?>"
      >
      <?php if (errors('last_name')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('last_name')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="email" class="pc-label">Adresse e-mail *</label>
      <input
        type="email"
        id="email"
        name="email"
        inputmode="email"
        required
        value="<?= e(old('email', (string) ($member['email'] ?? ''))) ?>"
        placeholder="prenom@primecare.test"
        class="pc-input <?= errors('email') ? 'has-error' : '' ?>"
      >
      <?php if (errors('email')) : ?>
        <div class="pc-field-error"><?= icon('alert-triangle', '', 14) ?><span><?= e(errors('email')) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="pc-field">
      <label for="phone" class="pc-label">Téléphone mobile</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        inputmode="tel"
        value="<?= e(old('phone', (string) ($member['phone'] ?? ''))) ?>"
        placeholder="06 00 00 00 00"
        class="pc-input"
      >
    </div>

    <div class="pc-field">
      <label for="role" class="pc-label">Rôle dans l’équipe *</label>
      <select id="role" name="role" class="pc-select">
        <option value="caregiver" <?= $roleVal === 'caregiver' ? 'selected' : '' ?>>Aide-soignante (Saisie des soins & Pointage)</option>
        <option value="coordinator" <?= $roleVal === 'coordinator' ? 'selected' : '' ?>>Coordinatrice (Planning & Traitements)</option>
        <option value="admin" <?= $roleVal === 'admin' ? 'selected' : '' ?>>Administrateur (Famille — Accès complet)</option>
        <option value="viewer" <?= $roleVal === 'viewer' ? 'selected' : '' ?>>Lecture seule (Médecin / Infirmier)</option>
      </select>
    </div>

    <div class="pc-field">
      <span class="pc-label">Couleur d’identification (Planning & Avatar)</span>
      <div class="flex items-center gap-3 mt-1">
        <?php foreach ($palette as $hex) : ?>
          <label style="cursor: pointer;">
            <input
              type="radio"
              name="color_hex"
              value="<?= e($hex) ?>"
              <?= strtoupper($colorVal) === strtoupper($hex) ? 'checked' : '' ?>
              style="position: absolute; opacity: 0; pointer-events: none;"
            >
            <span style="display: inline-block; width: 34px; height: 34px; border-radius: 999px; background: <?= e($hex) ?>; border: 3px solid <?= strtoupper($colorVal) === strtoupper($hex) ? 'var(--text-1)' : 'transparent' ?>;"></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--separator); margin: 4px 0;">

    <div class="pc-field">
      <label for="contract_type" class="pc-label">Type de contrat</label>
      <select id="contract_type" name="contract_type" class="pc-select">
        <?php $ct = (string) old('contract_type', (string) ($member['contract_type'] ?? 'CESU')); ?>
        <option value="" <?= $ct === '' ? 'selected' : '' ?>>Non applicable (Famille / Médecin)</option>
        <option value="CESU" <?= $ct === 'CESU' ? 'selected' : '' ?>>CESU</option>
        <option value="CDI" <?= $ct === 'CDI' ? 'selected' : '' ?>>CDI</option>
        <option value="CDD" <?= $ct === 'CDD' ? 'selected' : '' ?>>CDD</option>
        <option value="Prestataire" <?= $ct === 'Prestataire' ? 'selected' : '' ?>>Prestataire / Libéral</option>
      </select>
    </div>

    <div class="pc-field">
      <label for="hired_at" class="pc-label">Date d’embauche</label>
      <input
        type="date"
        id="hired_at"
        name="hired_at"
        value="<?= e(old('hired_at', (string) ($member['hired_at'] ?? ''))) ?>"
        class="pc-input"
      >
    </div>

    <div class="pc-field">
      <label for="hourly_rate" class="pc-label">Taux horaire net (€ / h — visible uniquement par l’admin)</label>
      <input
        type="number"
        step="0.01"
        min="0"
        id="hourly_rate"
        name="hourly_rate"
        inputmode="decimal"
        value="<?= e(old('hourly_rate', (string) ($member['hourly_rate'] ?? ''))) ?>"
        placeholder="15.50"
        class="pc-input"
      >
    </div>

    <div class="pc-field">
      <label for="pin" class="pc-label">Code PIN rapide (4 à 6 chiffres, optionnel)</label>
      <input
        type="password"
        id="pin"
        name="pin"
        inputmode="numeric"
        pattern="\d{4,6}"
        maxlength="6"
        placeholder="<?= $isEdit ? 'Laisser vide pour conserver le PIN actuel' : 'Ex : 3333' ?>"
        class="pc-input"
      >
    </div>

    <hr style="border: 0; border-top: 1px solid var(--separator); margin: 4px 0;">

    <div class="pc-field">
      <label for="emergency_contact_name" class="pc-label">Nom du contact d’urgence du membre</label>
      <input
        type="text"
        id="emergency_contact_name"
        name="emergency_contact_name"
        value="<?= e(old('emergency_contact_name', (string) ($member['emergency_contact_name'] ?? ''))) ?>"
        placeholder="Ex : Mamadou Diallo (Époux)"
        class="pc-input"
      >
    </div>

    <div class="pc-field">
      <label for="emergency_contact_phone" class="pc-label">Téléphone du contact d’urgence</label>
      <input
        type="tel"
        id="emergency_contact_phone"
        name="emergency_contact_phone"
        inputmode="tel"
        value="<?= e(old('emergency_contact_phone', (string) ($member['emergency_contact_phone'] ?? ''))) ?>"
        placeholder="06 00 00 00 00"
        class="pc-input"
      >
    </div>

    <div class="flex flex-col gap-2 mt-2">
      <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
        <?= icon('check', '', 18) ?>
        <span><?= $isEdit ? 'Enregistrer les modifications' : 'Créer le compte et générer le mot de passe' ?></span>
      </button>
      <a href="<?= $isEdit ? '/users/' . (int) $member['id'] : '/users' ?>" class="pc-btn pc-btn--secondary pc-btn--block">
        Annuler
      </a>
    </div>
  </form>
</section>
