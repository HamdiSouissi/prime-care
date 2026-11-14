<?php

declare(strict_types=1);

use App\Core\View;

/** @var array<string, mixed> $patient */
/** @var array<int, array<string, mixed>> $contacts */
/** @var bool $canEdit */

$fullName = trim(($patient['first_name'] ?? 'Henri') . ' ' . ($patient['last_name'] ?? 'Benali'));
$age = isset($patient['birth_date']) ? calculate_age((string) $patient['birth_date']) : 84;
$hypoProtocol = (string) ($patient['hypo_protocol'] ?? $patient['hypoglycemia_protocol'] ?? '');

if ($canEdit) {
    View::section('navbar_right');
    ?>
    <button type="button" class="pc-btn pc-btn--tinted pc-btn--sm" data-sheet-open="edit-patient-sheet">
      <?= icon('edit-3', '', 16) ?>
      <span>Modifier</span>
    </button>
    <?php
    View::endSection();
}
?>

<!-- 1. Carte rouge d'urgence : Conduite à tenir en cas d'hypoglycémie (§3.5 — mise en avant en haut de page) -->
<section class="pc-hypo-card" aria-labelledby="hypo-title">
  <div class="pc-hypo-card__header">
    <?= icon('alert-triangle', '', 22) ?>
    <h2 id="hypo-title" class="pc-hypo-card__title"><?= e(t('patient.hypoglycemia_protocol')) ?></h2>
  </div>
  <div class="pc-hypo-card__body"><?= e($hypoProtocol) ?></div>

  <!-- Boutons d'appel d'urgence SAMU 15 / 112 -->
  <div class="pc-emergency-grid">
    <a href="tel:15" class="pc-emergency-btn">
      <?= icon('phone', '', 18) ?>
      <span><?= e(t('patient.call_samu')) ?></span>
    </a>
    <a href="tel:112" class="pc-emergency-btn">
      <?= icon('heart-pulse', '', 18) ?>
      <span><?= e(t('patient.call_112')) ?></span>
    </a>
  </div>
</section>

<!-- 2. Contacts d'urgence avec appel direct (tel:) & SMS/E-mail (§3.5) -->
<div>
  <div class="flex items-center justify-between">
    <div class="pc-section-header"><?= e(t('patient.emergency_contacts')) ?></div>
    <?php if ($canEdit) : ?>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" data-sheet-open="add-contact-sheet">
        <?= icon('plus', '', 16) ?>
        <span><?= e(t('patient.add_contact')) ?></span>
      </button>
    <?php endif; ?>
  </div>

  <div class="pc-list-group">
    <?php foreach ($contacts as $contact) : ?>
      <?php
      $cleanPhone = preg_replace('/\s+/', '', (string) ($contact['phone'] ?? '')) ?? '';
      $isEmergency = (int) ($contact['is_emergency_priority'] ?? $contact['is_emergency'] ?? 0) === 1;
      ?>
      <div class="pc-list-item">
        <span class="pc-list-icon" style="background: <?= $isEmergency ? 'var(--danger)' : 'var(--ok)' ?>;">
          <?= icon($isEmergency ? 'phone' : 'user', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title flex items-center gap-2">
            <span><?= e($contact['full_name']) ?></span>
            <?php if ($isEmergency) : ?>
              <span class="pc-badge pc-badge--danger">Prioritaire</span>
            <?php endif; ?>
          </div>
          <div class="pc-list-subtitle"><?= e($contact['relation_role']) ?> · <?= e($contact['phone']) ?></div>
          <?php if (!empty($contact['notes'])) : ?>
            <div class="text-caption text-muted mt-1"><?= e((string) $contact['notes']) ?></div>
          <?php endif; ?>
        </div>

        <div class="pc-contact-actions">
          <a href="tel:<?= e($cleanPhone) ?>" class="pc-contact-btn pc-contact-btn--call" aria-label="Appeler <?= e($contact['full_name']) ?>">
            <?= icon('phone', '', 18) ?>
          </a>
          <a href="sms:<?= e($cleanPhone) ?>" class="pc-contact-btn pc-contact-btn--msg" aria-label="Envoyer un SMS à <?= e($contact['full_name']) ?>">
            <?= icon('message-circle', '', 18) ?>
          </a>
          <?php if (!empty($contact['email'])) : ?>
            <a href="mailto:<?= e((string) $contact['email']) ?>" class="pc-contact-btn pc-contact-btn--mail" aria-label="Envoyer un e-mail à <?= e($contact['full_name']) ?>">
              <?= icon('mail', '', 18) ?>
            </a>
          <?php endif; ?>
          <?php if ($canEdit) : ?>
            <button
              type="button"
              class="pc-contact-btn"
              style="background: var(--surface-3); color: var(--text-2);"
              data-sheet-open="edit-contact-sheet-<?= (int) $contact['id'] ?>"
              aria-label="Modifier <?= e($contact['full_name']) ?>"
            >
              <?= icon('edit-3', '', 16) ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- 3. Consignes permanentes -->
<div>
  <div class="pc-section-header"><?= e(t('patient.permanent_instructions')) ?></div>
  <section class="pc-card">
    <p class="text-body" style="white-space: pre-line;"><?= e((string) ($patient['permanent_instructions'] ?? '')) ?></p>
  </section>
</div>

<!-- 4. Santé & Diabète -->
<div>
  <div class="pc-section-header"><?= e(t('patient.health_diabetes')) ?></div>
  <div class="pc-list-group">
    <div class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--c-vitals);">
        <?= icon('droplet', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Pathologie principale</div>
        <div class="pc-list-title"><?= e((string) ($patient['diabetes_type'] ?? '')) ?></div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Cible glycémique à jeun</div>
        <div class="pc-list-title text-tabular">
          <?= e(number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.8), 2, ',', ' ')) ?> à <?= e(number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.3), 2, ',', ' ')) ?> g/L
        </div>
      </div>
      <span class="pc-badge pc-badge--ok">À jeun</span>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Cible post-prandiale (2h après repas)</div>
        <div class="pc-list-title text-tabular">
          &lt; <?= e(number_format((float) ($patient['target_glycemia_postmeal_max'] ?? 1.8), 2, ',', ' ')) ?> g/L
        </div>
      </div>
      <span class="pc-badge pc-badge--info">Post-repas</span>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Seuils d’alerte critique</div>
        <div class="pc-list-title text-tabular">
          Hypo &lt; <?= e(number_format((float) ($patient['hypo_threshold_g_l'] ?? 0.7), 2, ',', ' ')) ?> g/L · Hyper &gt; <?= e(number_format((float) ($patient['hyper_threshold_g_l'] ?? 2.5), 2, ',', ' ')) ?> g/L
        </div>
      </div>
      <span class="pc-badge pc-badge--danger">Alerte</span>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Allergies connues</div>
        <div class="pc-list-title" style="color: var(--danger);"><?= e((string) ($patient['allergies'] ?? 'Aucune allergie connue')) ?></div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Antécédents médicaux</div>
        <div class="pc-list-title" style="white-space: pre-line;"><?= e((string) ($patient['medical_history'] ?? '—')) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- 5. Identité & Coordonnées du domicile -->
<div>
  <div class="pc-section-header"><?= e(t('patient.identity')) ?></div>
  <div class="pc-list-group">
    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Nom complet & Âge</div>
        <div class="pc-list-title"><?= e($fullName) ?> · <?= $age ?> ans (né le <?= e(format_date((string) ($patient['birth_date'] ?? '1942-03-18'), 'd/m/Y')) ?>)</div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Groupe sanguin · Poids / Taille</div>
        <div class="pc-list-title">
          Groupe <?= e((string) ($patient['blood_group'] ?? 'A+')) ?> · <?= e((string) ($patient['reference_weight_kg'] ?? '74')) ?> kg · <?= e((string) ($patient['height_cm'] ?? '172')) ?> cm
        </div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">N° Sécurité Sociale (NIR)</div>
        <div class="pc-list-title text-tabular"><?= e((string) ($patient['ssn_masked'] ?? '—')) ?></div>
      </div>
    </div>

    <div class="pc-list-item">
      <div class="pc-list-body">
        <div class="pc-list-subtitle">Adresse du domicile & Accès secours</div>
        <div class="pc-list-title"><?= e((string) ($patient['address'] ?? '')) ?></div>
        <div class="text-caption text-muted mt-1"><?= e((string) ($patient['access_code'] ?? '')) ?></div>
      </div>
    </div>
  </div>
</div>

<?php if ($canEdit) : ?>
  <!-- Feuille modale d'édition de la fiche patient (Admin) -->
  <div id="edit-patient-sheet" class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="edit-patient-title" aria-hidden="true">
    <div class="pc-sheet__backdrop" data-sheet-close></div>
    <div class="pc-sheet__panel">
      <div class="pc-sheet__handle"></div>
      <div class="pc-sheet__header">
        <h2 id="edit-patient-title" class="pc-sheet__title">Modifier la fiche patient</h2>
        <button type="button" class="pc-navbar__btn" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>

      <form method="POST" action="/patient" class="flex flex-col gap-3">
        <?= csrf_field() ?>

        <div class="pc-field">
          <label class="pc-label" for="p_first_name">Prénom</label>
          <input type="text" id="p_first_name" name="first_name" required value="<?= e((string) ($patient['first_name'] ?? '')) ?>" class="pc-input">
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_last_name">Nom</label>
          <input type="text" id="p_last_name" name="last_name" required value="<?= e((string) ($patient['last_name'] ?? '')) ?>" class="pc-input">
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_birth_date">Date de naissance</label>
          <input type="date" id="p_birth_date" name="birth_date" required value="<?= e((string) ($patient['birth_date'] ?? '1942-03-18')) ?>" class="pc-input">
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_diabetes_type">Type de diabète</label>
          <input type="text" id="p_diabetes_type" name="diabetes_type" required value="<?= e((string) ($patient['diabetes_type'] ?? '')) ?>" class="pc-input">
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_allergies">Allergies</label>
          <textarea id="p_allergies" name="allergies" class="pc-textarea" style="min-height: 70px;"><?= e((string) ($patient['allergies'] ?? '')) ?></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_permanent_instructions">Consignes permanentes</label>
          <textarea id="p_permanent_instructions" name="permanent_instructions" class="pc-textarea"><?= e((string) ($patient['permanent_instructions'] ?? '')) ?></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_hypo_protocol">Protocole d’hypoglycémie (Carte rouge)</label>
          <textarea id="p_hypo_protocol" name="hypo_protocol" required class="pc-textarea"><?= e($hypoProtocol) ?></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_address">Adresse du domicile</label>
          <input type="text" id="p_address" name="address" value="<?= e((string) ($patient['address'] ?? '')) ?>" class="pc-input">
        </div>

        <div class="pc-field">
          <label class="pc-label" for="p_access_code">Digicode & Accès secours</label>
          <input type="text" id="p_access_code" name="access_code" value="<?= e((string) ($patient['access_code'] ?? '')) ?>" class="pc-input">
        </div>

        <input type="hidden" name="gender" value="<?= e((string) ($patient['gender'] ?? 'M')) ?>">
        <input type="hidden" name="blood_group" value="<?= e((string) ($patient['blood_group'] ?? 'A+')) ?>">
        <input type="hidden" name="reference_weight_kg" value="<?= e((string) ($patient['reference_weight_kg'] ?? '74.5')) ?>">
        <input type="hidden" name="height_cm" value="<?= e((string) ($patient['height_cm'] ?? '172')) ?>">
        <input type="hidden" name="ssn_masked" value="<?= e((string) ($patient['ssn_masked'] ?? '')) ?>">
        <input type="hidden" name="target_glycemia_fasting_min" value="<?= e((string) ($patient['target_glycemia_fasting_min'] ?? '0.80')) ?>">
        <input type="hidden" name="target_glycemia_fasting_max" value="<?= e((string) ($patient['target_glycemia_fasting_max'] ?? '1.30')) ?>">
        <input type="hidden" name="target_glycemia_postmeal_max" value="<?= e((string) ($patient['target_glycemia_postmeal_max'] ?? '1.80')) ?>">
        <input type="hidden" name="hypo_threshold_g_l" value="<?= e((string) ($patient['hypo_threshold_g_l'] ?? '0.70')) ?>">
        <input type="hidden" name="hyper_threshold_g_l" value="<?= e((string) ($patient['hyper_threshold_g_l'] ?? '2.50')) ?>">
        <input type="hidden" name="medical_history" value="<?= e((string) ($patient['medical_history'] ?? '')) ?>">

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block mt-2">
          Enregistrer la fiche patient
        </button>
      </form>
    </div>
  </div>

  <!-- Feuille modale d'ajout d'un contact -->
  <div id="add-contact-sheet" class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="add-contact-title" aria-hidden="true">
    <div class="pc-sheet__backdrop" data-sheet-close></div>
    <div class="pc-sheet__panel">
      <div class="pc-sheet__handle"></div>
      <div class="pc-sheet__header">
        <h2 id="add-contact-title" class="pc-sheet__title">Ajouter un contact d’urgence</h2>
        <button type="button" class="pc-navbar__btn" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>

      <form method="POST" action="/patient/contacts" class="flex flex-col gap-3">
        <?= csrf_field() ?>
        <div class="pc-field">
          <label class="pc-label" for="c_full_name">Nom complet *</label>
          <input type="text" id="c_full_name" name="full_name" required placeholder="Dr Martin / Infirmière" class="pc-input">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="c_relation">Lien ou fonction *</label>
          <input type="text" id="c_relation" name="relation_role" required placeholder="Ex : Diabétologue / Famille" class="pc-input">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="c_phone">Téléphone *</label>
          <input type="tel" id="c_phone" name="phone" required placeholder="06 00 00 00 00" class="pc-input">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="c_email">E-mail</label>
          <input type="email" id="c_email" name="email" placeholder="contact@primecare.test" class="pc-input">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="c_notes">Consigne / Horaires</label>
          <input type="text" id="c_notes" name="notes" placeholder="Ex : Joignable 24h/24" class="pc-input">
        </div>
        <div class="flex items-center justify-between py-1">
          <span class="text-subhead">Contact d’urgence prioritaire</span>
          <label class="pc-switch">
            <input type="checkbox" name="is_emergency_priority" value="1" checked>
            <span class="pc-switch__track"><span class="pc-switch__thumb"></span></span>
          </label>
        </div>
        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block mt-2">
          Ajouter le contact
        </button>
      </form>
    </div>
  </div>

  <!-- Feuilles modales d'édition / suppression de chaque contact -->
  <?php foreach ($contacts as $contact) : ?>
    <div id="edit-contact-sheet-<?= (int) $contact['id'] ?>" class="pc-sheet" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="pc-sheet__backdrop" data-sheet-close></div>
      <div class="pc-sheet__panel">
        <div class="pc-sheet__handle"></div>
        <div class="pc-sheet__header">
          <h2 class="pc-sheet__title">Modifier <?= e($contact['full_name']) ?></h2>
          <button type="button" class="pc-navbar__btn" data-sheet-close aria-label="Fermer">
            <?= icon('x', '', 20) ?>
          </button>
        </div>

        <form method="POST" action="/patient/contacts/<?= (int) $contact['id'] ?>" class="flex flex-col gap-3">
          <?= csrf_field() ?>
          <div class="pc-field">
            <label class="pc-label">Nom complet *</label>
            <input type="text" name="full_name" required value="<?= e((string) $contact['full_name']) ?>" class="pc-input">
          </div>
          <div class="pc-field">
            <label class="pc-label">Lien ou fonction *</label>
            <input type="text" name="relation_role" required value="<?= e((string) $contact['relation_role']) ?>" class="pc-input">
          </div>
          <div class="pc-field">
            <label class="pc-label">Téléphone *</label>
            <input type="tel" name="phone" required value="<?= e((string) $contact['phone']) ?>" class="pc-input">
          </div>
          <div class="pc-field">
            <label class="pc-label">E-mail</label>
            <input type="email" name="email" value="<?= e((string) ($contact['email'] ?? '')) ?>" class="pc-input">
          </div>
          <div class="pc-field">
            <label class="pc-label">Consigne / Horaires</label>
            <input type="text" name="notes" value="<?= e((string) ($contact['notes'] ?? '')) ?>" class="pc-input">
          </div>
          <input type="hidden" name="sort_order" value="<?= (int) ($contact['sort_order'] ?? 10) ?>">
          <div class="flex items-center justify-between py-1">
            <span class="text-subhead">Contact d’urgence prioritaire</span>
            <label class="pc-switch">
              <input type="checkbox" name="is_emergency_priority" value="1" <?= (int) ($contact['is_emergency_priority'] ?? 0) === 1 ? 'checked' : '' ?>>
              <span class="pc-switch__track"><span class="pc-switch__thumb"></span></span>
            </label>
          </div>
          <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
            Enregistrer
          </button>
        </form>

        <form method="POST" action="/patient/contacts/<?= (int) $contact['id'] ?>/delete" class="mt-2">
          <?= csrf_field() ?>
          <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
            <?= icon('trash-2', '', 16) ?>
            <span>Supprimer ce contact</span>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
