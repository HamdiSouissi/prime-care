<?php

declare(strict_types=1);

$patient = is_array($patient ?? null) ? $patient : [];
$contacts = is_array($contacts ?? null) ? $contacts : [];
$canUpdate = (bool) ($canUpdate ?? can('patient', 'update'));
$canManageContacts = (bool) ($canManageContacts ?? can('patient', 'manage_contacts'));

$firstName = (string) ($patient['first_name'] ?? 'Henri');
$lastName = (string) ($patient['last_name'] ?? 'Benali');
$fullName = trim($firstName . ' ' . $lastName);
$age = isset($patient['birth_date']) ? calculate_age((string) $patient['birth_date']) : 84;
?>
<?php if ($canUpdate): ?>
  <?php View::section('navbar_action'); ?>
    <button type="button" class="pc-btn pc-btn--tinted pc-btn--sm" data-sheet-open="edit-patient-sheet">
      <?= icon('edit-3', 16) ?>
      <span>Modifier</span>
    </button>
  <?php View::endSection(); ?>
<?php endif; ?>

<!-- 1. Carte rouge prioritaire : Protocole d'hypoglycémie (§3.5) -->
<section class="pc-hypo-card" aria-labelledby="hypo-protocol-heading">
  <div class="pc-hypo-card__header">
    <?= icon('alert-triangle', 22) ?>
    <h2 class="pc-hypo-card__title" id="hypo-protocol-heading"><?= e(t('patient.hypoglycemia_protocol')) ?></h2>
  </div>
  <div class="pc-hypo-card__body"><?= e((string) ($patient['hypo_protocol'] ?? '')) ?></div>

  <div class="pc-emergency-grid">
    <a href="tel:15" class="pc-emergency-btn">
      <?= icon('phone', 18) ?>
      <span><?= e(t('patient.call_samu')) ?></span>
    </a>
    <a href="tel:112" class="pc-emergency-btn">
      <?= icon('phone', 18) ?>
      <span><?= e(t('patient.call_112')) ?></span>
    </a>
  </div>
</section>

<!-- 2. Contacts d'urgence & Intervenants (C6.2 : nom complet sans troncature + badge Prioritaire sous le nom) -->
<section aria-labelledby="contacts-heading">
  <div class="flex items-center justify-between mb-2">
    <div class="pc-section-header" id="contacts-heading"><?= e(t('patient.emergency_contacts')) ?></div>
    <?php if ($canManageContacts): ?>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" data-sheet-open="add-contact-sheet">
        <?= icon('plus', 16) ?>
        <span><?= e(t('patient.add_contact')) ?></span>
      </button>
    <?php endif; ?>
  </div>

  <div class="pc-list-group">
    <?php foreach ($contacts as $contact): ?>
      <?php
      $cId = (int) ($contact['id'] ?? 0);
      $cName = (string) ($contact['full_name'] ?? '');
      $cRole = (string) ($contact['relation_role'] ?? '');
      $cPhone = (string) ($contact['phone'] ?? '');
      $cPhoneClean = preg_replace('/\s+/', '', $cPhone) ?? '';
      $cEmail = (string) ($contact['email'] ?? '');
      $isEmergency = (int) ($contact['is_emergency_priority'] ?? 0) === 1;
      ?>
      <div class="pc-list-item pc-contact-row">
        <span class="pc-list-icon <?= $isEmergency ? 'pc-list-icon--danger' : 'pc-list-icon--ok' ?>">
          <?= icon('phone', 16) ?>
        </span>

        <div class="pc-list-body">
          <div class="pc-list-title"><?= e($cName) ?></div>
          <div class="pc-list-subtitle">
            <?= e($cRole) ?> · <span class="text-tabular"><?= e($cPhone) ?></span>
          </div>
          <?php if ($isEmergency): ?>
            <div class="mt-1">
              <span class="pc-badge pc-badge--danger">Prioritaire</span>
            </div>
          <?php endif; ?>
          <?php if (!empty($contact['notes'])): ?>
            <div class="text-caption text-muted mt-1"><?= e((string) $contact['notes']) ?></div>
          <?php endif; ?>
        </div>

        <div class="pc-contact-actions">
          <?php if ($cPhoneClean !== ''): ?>
            <a href="tel:<?= e($cPhoneClean) ?>" class="pc-contact-btn pc-contact-btn--call" aria-label="Appeler <?= e($cName) ?>">
              <?= icon('phone', 18) ?>
            </a>
            <a href="sms:<?= e($cPhoneClean) ?>" class="pc-contact-btn pc-contact-btn--msg" aria-label="Envoyer un SMS à <?= e($cName) ?>">
              <?= icon('message-circle', 18) ?>
            </a>
          <?php endif; ?>
          <?php if ($cEmail !== ''): ?>
            <a href="mailto:<?= e($cEmail) ?>" class="pc-contact-btn pc-contact-btn--mail" aria-label="Envoyer un e-mail à <?= e($cName) ?>">
              <?= icon('mail', 18) ?>
            </a>
          <?php endif; ?>
          <?php if ($canManageContacts): ?>
            <button
              type="button"
              class="pc-contact-btn pc-list-icon--surface"
              data-sheet-open="edit-contact-sheet-<?= $cId ?>"
              aria-label="Modifier <?= e($cName) ?>"
            >
              <?= icon('edit-3', 16) ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 3. Consignes permanentes -->
<section aria-labelledby="instructions-heading">
  <div class="pc-section-header" id="instructions-heading"><?= e(t('patient.permanent_instructions')) ?></div>
  <div class="pc-card">
    <p class="text-body pre-line"><?= e((string) ($patient['permanent_instructions'] ?? '')) ?></p>
  </div>
</section>

<!-- 4. Santé & Diabète -->
<section aria-labelledby="health-heading">
  <div class="pc-section-header" id="health-heading"><?= e(t('patient.health_diabetes')) ?></div>
  <div class="pc-list-group">
    <div class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('droplet', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Pathologie principale & Cibles glycémiques</div>
        <div class="pc-list-title"><?= e((string) ($patient['diabetes_type'] ?? 'Diabète de type 2')) ?></div>
        <div class="pc-list-subtitle text-tabular">
          À jeun : <?= e(number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.80), 2, ',', ' ')) ?> – <?= e(number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.30), 2, ',', ' ')) ?> g/L · Post-repas &lt; <?= e(number_format((float) ($patient['target_glycemia_postmeal_max'] ?? 1.80), 2, ',', ' ')) ?> g/L
        </div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Seuils d’alerte glycémie</div>
        <div class="pc-list-title text-tabular">
          Hypo &lt; <?= e(number_format((float) ($patient['hypo_threshold_g_l'] ?? 0.70), 2, ',', ' ')) ?> g/L · Hyper &gt; <?= e(number_format((float) ($patient['hyper_threshold_g_l'] ?? 2.50), 2, ',', ' ')) ?> g/L
        </div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Groupe sanguin & Anthropométrie</div>
        <div class="pc-list-title text-tabular">
          Groupe <?= e((string) ($patient['blood_group'] ?? '—')) ?> · <?= (int) ($patient['height_cm'] ?? 172) ?> cm · Poids de réf. <?= e(number_format((float) ($patient['reference_weight_kg'] ?? 74.5), 1, ',', ' ')) ?> kg
        </div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Allergies & Intolérances</div>
        <div class="pc-list-title text-danger"><?= e((string) ($patient['allergies'] ?? 'Aucune allergie connue')) ?></div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Antécédents médicaux</div>
        <div class="pc-list-title pre-line"><?= e((string) ($patient['medical_history'] ?? '—')) ?></div>
      </div>
    </div>
  </div>
</section>

<!-- 5. Identité & Accès domicile -->
<section aria-labelledby="identity-heading">
  <div class="pc-section-header" id="identity-heading"><?= e(t('patient.identity')) ?></div>
  <div class="pc-list-group">
    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Identité du patient</div>
        <div class="pc-list-title"><?= e($fullName) ?> (<?= $age ?> ans)</div>
        <div class="pc-list-subtitle">Né le <?= format_date($patient['birth_date'] ?? null, 'd/m/Y') ?> · N° SS <?= e((string) ($patient['ssn_masked'] ?? '')) ?></div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Adresse du domicile</div>
        <div class="pc-list-title"><?= e((string) ($patient['address'] ?? '')) ?></div>
        <div class="pc-list-subtitle"><?= e((string) ($patient['postal_code'] ?? '')) ?> <?= e((string) ($patient['city'] ?? '')) ?></div>
      </div>
    </div>

    <div class="pc-list-item pc-list-item--no-icon">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Accès secours (digicode, étage, boîte à clés)</div>
        <div class="pc-list-title"><?= e((string) ($patient['access_code'] ?? $patient['access_code_notes'] ?? '')) ?></div>
      </div>
    </div>
  </div>
</section>

<?php if ($canUpdate): ?>
  <!-- Feuille modale : Modifier la fiche patient -->
  <div class="pc-sheet-backdrop" id="edit-patient-sheet" role="dialog" aria-modal="true" aria-labelledby="edit-patient-title">
    <div class="pc-sheet">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="edit-patient-title">Modifier la fiche patient</h2>
        <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
          <?= icon('x', 22) ?>
        </button>
      </div>

      <form method="POST" action="/patient" class="flex flex-col gap-4" data-loading-form>
        <?= csrf_field() ?>

        <div class="pc-module-grid pc-module-grid--2col">
          <div class="pc-field">
            <label class="pc-label" for="p_first_name">Prénom</label>
            <input type="text" id="p_first_name" name="first_name" class="pc-input" value="<?= e($firstName) ?>" required>
          </div>
          <div class="pc-field">
            <label class="pc-label" for="p_last_name">Nom</label>
            <input type="text" id="p_last_name" name="last_name" class="pc-input" value="<?= e($lastName) ?>" required>
          </div>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div class="pc-field">
            <label class="pc-label" for="p_birth_date">Date de naissance</label>
            <input type="date" id="p_birth_date" name="birth_date" class="pc-input" value="<?= e((string) ($patient['birth_date'] ?? '')) ?>" required>
          </div>
          <div class="pc-field">
            <label class="pc-label" for="p_blood_group">Groupe sanguin</label>
            <input type="text" id="p_blood_group" name="blood_group" class="pc-input" value="<?= e((string) ($patient['blood_group'] ?? '')) ?>">
          </div>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_diabetes_type">Type de diabète</label>
          <input type="text" id="p_diabetes_type" name="diabetes_type" class="pc-input" value="<?= e((string) ($patient['diabetes_type'] ?? '')) ?>" required>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_allergies">Allergies</label>
          <textarea id="p_allergies" name="allergies" class="pc-textarea pc-textarea--sm"><?= e((string) ($patient['allergies'] ?? '')) ?></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_permanent_instructions">Consignes permanentes</label>
          <textarea id="p_permanent_instructions" name="permanent_instructions" class="pc-textarea"><?= e((string) ($patient['permanent_instructions'] ?? '')) ?></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_hypo_protocol">Protocole d’hypoglycémie (&lt; 0,70 g/L)</label>
          <textarea id="p_hypo_protocol" name="hypo_protocol" class="pc-textarea" required><?= e((string) ($patient['hypo_protocol'] ?? '')) ?></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_access_code_notes">Accès secours (digicode / clés)</label>
          <input type="text" id="p_access_code_notes" name="access_code" class="pc-input" value="<?= e((string) ($patient['access_code'] ?? $patient['access_code_notes'] ?? '')) ?>">
        </div>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          Enregistrer la fiche patient
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canManageContacts): ?>
  <!-- Feuille modale : Ajouter un contact -->
  <div class="pc-sheet-backdrop" id="add-contact-sheet" role="dialog" aria-modal="true" aria-labelledby="add-contact-title">
    <div class="pc-sheet">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="add-contact-title">Nouveau contact d’urgence</h2>
        <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
          <?= icon('x', 22) ?>
        </button>
      </div>

      <form method="POST" action="/patient/contacts" class="flex flex-col gap-4" data-loading-form>
        <?= csrf_field() ?>
        <div class="pc-field">
          <label class="pc-label" for="c_full_name">Nom complet *</label>
          <input type="text" id="c_full_name" name="full_name" class="pc-input" placeholder="Dr Martin, Pharmacie…" required>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="c_relation_role">Fonction ou lien *</label>
          <input type="text" id="c_relation_role" name="relation_role" class="pc-input" placeholder="Cardiologue, Infirmière IDEL…" required>
        </div>
        <div class="pc-module-grid pc-module-grid--2col">
          <div class="pc-field">
            <label class="pc-label" for="c_phone">Téléphone *</label>
            <input type="tel" id="c_phone" name="phone" class="pc-input" inputmode="tel" required>
          </div>
          <div class="pc-field">
            <label class="pc-label" for="c_email">E-mail</label>
            <input type="email" id="c_email" name="email" class="pc-input" inputmode="email">
          </div>
        </div>
        <div class="flex items-center justify-between py-1">
          <label class="text-subhead" for="c_emergency">Contact prioritaire en cas d’urgence</label>
          <label class="pc-switch">
            <input type="checkbox" id="c_emergency" name="is_emergency_priority" value="1">
            <span class="pc-switch__track"><span class="pc-switch__thumb"></span></span>
          </label>
        </div>
        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">Ajouter ce contact</button>
      </form>
    </div>
  </div>

  <!-- Feuilles modales : Modifier / Supprimer chaque contact -->
  <?php foreach ($contacts as $contact): ?>
    <?php $cId = (int) ($contact['id'] ?? 0); ?>
    <div class="pc-sheet-backdrop" id="edit-contact-sheet-<?= $cId ?>" role="dialog" aria-modal="true" aria-labelledby="edit-contact-title-<?= $cId ?>">
      <div class="pc-sheet">
        <div class="pc-sheet__handle" aria-hidden="true"></div>
        <div class="pc-sheet__header">
          <h2 class="pc-sheet__title" id="edit-contact-title-<?= $cId ?>">Modifier le contact</h2>
          <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
            <?= icon('x', 22) ?>
          </button>
        </div>

        <form method="POST" action="/patient/contacts/<?= $cId ?>/update" class="flex flex-col gap-4 mb-3" data-loading-form>
          <?= csrf_field() ?>
          <div class="pc-field">
            <label class="pc-label" for="ec_name_<?= $cId ?>">Nom complet *</label>
            <input type="text" id="ec_name_<?= $cId ?>" name="full_name" class="pc-input" value="<?= e((string) ($contact['full_name'] ?? '')) ?>" required>
          </div>
          <div class="pc-field">
            <label class="pc-label" for="ec_role_<?= $cId ?>">Fonction ou lien *</label>
            <input type="text" id="ec_role_<?= $cId ?>" name="relation_role" class="pc-input" value="<?= e((string) ($contact['relation_role'] ?? '')) ?>" required>
          </div>
          <div class="pc-module-grid pc-module-grid--2col">
            <div class="pc-field">
              <label class="pc-label" for="ec_phone_<?= $cId ?>">Téléphone *</label>
              <input type="tel" id="ec_phone_<?= $cId ?>" name="phone" class="pc-input" value="<?= e((string) ($contact['phone'] ?? '')) ?>" required>
            </div>
            <div class="pc-field">
              <label class="pc-label" for="ec_email_<?= $cId ?>">E-mail</label>
              <input type="email" id="ec_email_<?= $cId ?>" name="email" class="pc-input" value="<?= e((string) ($contact['email'] ?? '')) ?>">
            </div>
          </div>
          <div class="flex items-center justify-between py-1">
            <label class="text-subhead" for="ec_emerg_<?= $cId ?>">Contact prioritaire d’urgence</label>
            <label class="pc-switch">
              <input type="checkbox" id="ec_emerg_<?= $cId ?>" name="is_emergency_priority" value="1" <?= (int) ($contact['is_emergency_priority'] ?? 0) === 1 ? 'checked' : '' ?>>
              <span class="pc-switch__track"><span class="pc-switch__thumb"></span></span>
            </label>
          </div>
          <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">Enregistrer le contact</button>
        </form>

        <form method="POST" action="/patient/contacts/<?= $cId ?>/delete">
          <?= csrf_field() ?>
          <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
            <?= icon('trash-2', 16) ?>
            <span>Supprimer ce contact</span>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
