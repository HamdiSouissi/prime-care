<?php

declare(strict_types=1);

use App\Core\Auth;

/** @var array<string, mixed>|null $patient */
/** @var array<int, array<string, mixed>> $contacts */
/** @var int $teamCount */
$user = Auth::user() ?? [];
$firstName = (string) ($user['first_name'] ?? 'Fatou');
$role = (string) ($user['role'] ?? 'caregiver');
$isAdmin = $role === 'admin';
$isCaregiverOrCoord = in_array($role, ['caregiver', 'coordinator'], true);

$patientName = ($patient['first_name'] ?? 'Henri') . ' ' . ($patient['last_name'] ?? 'Benali');
$patientAge = isset($patient['birth_date']) ? calculate_age((string) $patient['birth_date']) : 84;
$diabetesType = (string) ($patient['diabetes_type'] ?? 'Diabète de type 2');
?>
<!-- Gradient Hero Header (§3.2, §6.2) -->
<section class="pc-home-hero" aria-label="En-tête d’accueil">
  <div class="flex items-center justify-between">
    <div>
      <p class="pc-home-hero__date"><?= e(french_full_date()) ?></p>
      <h2 class="pc-home-hero__greeting">Bonjour <?= e($firstName) ?></h2>
    </div>
    <span class="pc-badge" style="background: rgba(255,255,255,0.2); color: #FFFFFF;">
      <?= e(t('roles.' . $role)) ?>
    </span>
  </div>

  <!-- Patient Summary Card inside Hero -->
  <a href="/patient" class="pc-home-patient">
    <div class="pc-home-patient__avatar">
      <?= e(initials((string) ($patient['first_name'] ?? 'H'), (string) ($patient['last_name'] ?? 'B'))) ?>
    </div>
    <div class="flex-1" style="min-width: 0;">
      <div class="text-headline" style="color: #FFFFFF;"><?= e($patientName) ?> · <?= $patientAge ?> ans</div>
      <div class="text-footnote" style="color: rgba(255,255,255,0.88);">
        <?= e($diabetesType) ?> · Cible <?= e(number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.8), 2, ',', ' ')) ?>–<?= e(number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.3), 2, ',', ' ')) ?> g/L
      </div>
    </div>
    <span style="color: rgba(255,255,255,0.85);">
      <?= icon('chevron-right', '', 20) ?>
    </span>
  </a>
</section>

<!-- Quick Action Bar (3-click rule: 1 tap from Home) -->
<?php if ($isCaregiverOrCoord) : ?>
  <section class="pc-card flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <span class="pc-list-icon" style="background: var(--c-team); width: 42px; height: 42px; border-radius: 12px;">
        <?= icon('clock', '', 22) ?>
      </span>
      <div>
        <div class="text-headline">Prise de poste à domicile</div>
        <div class="text-footnote text-muted">Pointage QR code + GPS (Phase 3)</div>
      </div>
    </div>
    <a href="/coming-soon/timeclock" class="pc-btn pc-btn--primary pc-btn--sm">
      J’arrive
    </a>
  </section>
<?php elseif ($isAdmin) : ?>
  <section class="pc-card flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <span class="pc-list-icon" style="background: var(--c-team); width: 42px; height: 42px; border-radius: 12px;">
        <?= icon('users', '', 22) ?>
      </span>
      <div>
        <div class="text-headline">Équipe de soins active</div>
        <div class="text-footnote text-muted"><?= $teamCount ?> comptes configurés · Gestion des accès</div>
      </div>
    </div>
    <a href="/users" class="pc-btn pc-btn--tinted pc-btn--sm">
      Gérer l’équipe
    </a>
  </section>
<?php endif; ?>

<!-- Health & Care Module Skeletons ("Bientôt disponible" — Phase 2/3) -->
<div>
  <div class="pc-section-header">Suivi quotidien · Modules médicaux</div>
  <div class="pc-module-grid pc-card-stagger">
    <!-- Glycémie & Constantes -->
    <a href="/coming-soon/glycemia" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module" style="color: var(--c-vitals);">
          <?= icon('droplet', '', 18) ?>
          <span>Glycémie & Constantes</span>
        </span>
        <span class="pc-badge pc-badge--brand">Bientôt disponible</span>
      </div>
      <div class="pc-health-card__body">
        <div class="flex-1">
          <div class="pc-skeleton mb-2" style="width: 110px; height: 28px;"></div>
          <div class="pc-skeleton" style="width: 170px; height: 14px;"></div>
        </div>
        <div class="pc-skeleton" style="width: 84px; height: 36px; border-radius: 10px;"></div>
      </div>
      <p class="text-footnote text-muted mt-3">
        Courbes glycémiques, tension artérielle, SpO2 et seuils d’alerte (Phase 2).
      </p>
    </a>

    <!-- Prochaine prise de médicament -->
    <a href="/coming-soon/medication" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module" style="color: var(--c-meds);">
          <?= icon('pill', '', 18) ?>
          <span>Plan de traitement & Insuline</span>
        </span>
        <span class="pc-badge pc-badge--brand">Bientôt disponible</span>
      </div>
      <div class="pc-health-card__body">
        <div class="flex-1">
          <div class="pc-skeleton mb-2" style="width: 140px; height: 24px;"></div>
          <div class="pc-skeleton" style="width: 190px; height: 14px;"></div>
        </div>
        <span class="pc-btn pc-btn--tinted pc-btn--sm" style="pointer-events: none;">Donné</span>
      </div>
      <p class="text-footnote text-muted mt-3">
        Validation en 1 tap des prises de médicaments et doses d’insuline (Phase 2).
      </p>
    </a>

    <!-- Repas & Hydratation -->
    <a href="/coming-soon/meal" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module" style="color: var(--c-meals);">
          <?= icon('utensils', '', 18) ?>
          <span>Repas & Hydratation</span>
        </span>
        <span class="pc-badge pc-badge--brand">Bientôt disponible</span>
      </div>
      <div class="pc-health-card__body">
        <div class="flex-1">
          <div class="pc-skeleton mb-2" style="width: 125px; height: 22px;"></div>
          <div class="pc-skeleton" style="width: 165px; height: 14px;"></div>
        </div>
        <div class="pc-skeleton" style="width: 52px; height: 52px; border-radius: 12px;"></div>
      </div>
      <p class="text-footnote text-muted mt-3">
        Photos avant/après repas, quantité consommée et apports hydriques (Phase 2).
      </p>
    </a>

    <!-- Journal & Transmissions -->
    <a href="/coming-soon/journal" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module" style="color: var(--c-notes);">
          <?= icon('notebook-pen', '', 18) ?>
          <span>Journal & Transmissions</span>
        </span>
        <span class="pc-badge pc-badge--brand">Bientôt disponible</span>
      </div>
      <div class="pc-health-card__body">
        <div class="flex-1">
          <div class="pc-skeleton mb-2" style="width: 180px; height: 18px;"></div>
          <div class="pc-skeleton" style="width: 140px; height: 14px;"></div>
        </div>
      </div>
      <p class="text-footnote text-muted mt-3">
        Soins rapides (toilette, change) et transmissions de fin de poste (Phase 2).
      </p>
    </a>
  </div>
</div>

<!-- Accès direct Fiche Patient & Urgences -->
<div>
  <div class="pc-section-header">Accès rapide · Patient & Urgences</div>
  <div class="pc-list-group">
    <a href="/patient" class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--c-vitals);">
        <?= icon('alert-triangle', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Protocole d’hypoglycémie & Consignes</div>
        <div class="pc-list-subtitle">Conduite à tenir immédiate si glycémie &lt; 0,70 g/L</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <?php foreach (array_slice($contacts, 0, 2) as $contact) : ?>
      <div class="pc-list-item">
        <span class="pc-list-icon" style="background: var(--ok);">
          <?= icon('phone', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title"><?= e($contact['full_name']) ?></div>
          <div class="pc-list-subtitle"><?= e($contact['relation_role']) ?> · <?= e($contact['phone']) ?></div>
        </div>
        <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $contact['phone']) ?? '') ?>" class="pc-contact-btn pc-contact-btn--call" aria-label="Appeler <?= e($contact['full_name']) ?>">
          <?= icon('phone', '', 18) ?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
