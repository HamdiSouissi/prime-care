<?php

declare(strict_types=1);

$user = is_array($user ?? null) ? $user : (auth() ?? []);
$patient = is_array($patient ?? null) ? $patient : [];
$firstName = (string) ($user['first_name'] ?? 'Équipe');
$role = (string) ($user['role'] ?? 'caregiver');
$patientFirst = (string) ($patient['first_name'] ?? 'Henri');
$patientLast = (string) ($patient['last_name'] ?? 'Benali');
$patientName = trim($patientFirst . ' ' . $patientLast);
$patientAge = isset($patient['birth_date']) ? calculate_age((string) $patient['birth_date']) : 84;
?>
<!-- En-tête Bleu nuit avec halos lumineux (Additif B §9.3) -->
<section class="pc-home-hero" aria-label="En-tête d’accueil">
  <div class="flex items-center justify-between">
    <div>
      <div class="pc-home-hero__date"><?= e(french_full_date()) ?></div>
      <div class="pc-home-hero__greeting">Bonjour <?= e($firstName) ?></div>
    </div>
    <span class="pc-badge pc-badge--hero">
      <?= e(t('roles.' . $role)) ?>
    </span>
  </div>

  <a href="/patient" class="pc-home-patient">
    <div class="pc-home-patient__avatar">
      <?= e(initials($patientFirst, $patientLast)) ?>
    </div>
    <div class="flex-1 min-w-0">
      <div class="text-headline text-white"><?= e($patientName) ?> · <?= $patientAge ?> ans</div>
      <div class="pc-home-patient__subtitle">
        <?= e((string) ($patient['diabetes_type'] ?? 'Diabète de type 2')) ?> · Cible à jeun <?= e(number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.80), 2, ',', ' ')) ?>–<?= e(number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.30), 2, ',', ' ')) ?> g/L
      </div>
    </div>
    <span class="text-white">
      <?= icon('chevron-right', 20) ?>
    </span>
  </a>
</section>

<!-- Carte de garde / pointage du jour -->
<?php if (in_array($role, ['caregiver', 'coordinator'], true)): ?>
  <section class="pc-card flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <span class="pc-list-icon pc-list-icon--lg pc-list-icon--team">
        <?= icon('clock', 22) ?>
      </span>
      <div>
        <div class="text-headline">Poste du jour · Domicile</div>
        <div class="text-footnote text-muted">Pointage QR / GPS disponible en Phase 3</div>
      </div>
    </div>
    <a href="/coming-soon/clock" class="pc-btn pc-btn--tinted pc-btn--sm">
      J’arrive
    </a>
  </section>
<?php else: ?>
  <section class="pc-card flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <span class="pc-list-icon pc-list-icon--lg pc-list-icon--team">
        <?= icon('users', 22) ?>
      </span>
      <div>
        <div class="text-headline">Équipe de soins à domicile</div>
        <div class="text-footnote text-muted">5 intervenants configurés · Suivi continu 24h/24</div>
      </div>
    </div>
    <?php if (can('users', 'view')): ?>
      <a href="/users" class="pc-btn pc-btn--tinted pc-btn--sm">Équipe</a>
    <?php endif; ?>
  </section>
<?php endif; ?>

<!-- Cartes de synthèse médicale (Squelettes Phase 2) -->
<section aria-labelledby="home-summary-heading">
  <div class="pc-section-header" id="home-summary-heading">Suivi quotidien (Phase 2 — Bientôt disponible)</div>

  <div class="pc-module-grid pc-module-grid--2col pc-card-stagger">
    <a href="/coming-soon/glycemia" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module pc-health-card__module--vitals">
          <?= icon('droplet', 18) ?>
          <span>Glycémie</span>
        </span>
        <span class="pc-badge pc-badge--brand">Phase 2</span>
      </div>
      <div class="pc-health-card__body">
        <div>
          <div class="pc-skeleton pc-skeleton--lg mb-2"></div>
          <div class="pc-skeleton pc-skeleton--sm"></div>
        </div>
        <div class="pc-skeleton pc-skeleton--pill"></div>
      </div>
    </a>

    <a href="/coming-soon/medication" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module pc-health-card__module--meds">
          <?= icon('pill', 18) ?>
          <span>Prochaine prise</span>
        </span>
        <span class="pc-badge pc-badge--brand">Phase 2</span>
      </div>
      <div class="pc-health-card__body">
        <div>
          <div class="pc-skeleton pc-skeleton--md mb-2"></div>
          <div class="pc-skeleton pc-skeleton--sm"></div>
        </div>
        <span class="pc-btn pc-btn--tinted pc-btn--sm pointer-none">Donné</span>
      </div>
    </a>

    <a href="/coming-soon/meal" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module pc-health-card__module--meals">
          <?= icon('utensils', 18) ?>
          <span>Dernier repas</span>
        </span>
        <span class="pc-badge pc-badge--brand">Phase 2</span>
      </div>
      <div class="pc-health-card__body">
        <div>
          <div class="pc-skeleton pc-skeleton--md mb-2"></div>
          <div class="pc-skeleton pc-skeleton--sm"></div>
        </div>
        <div class="pc-skeleton pc-skeleton--thumb"></div>
      </div>
    </a>

    <a href="/coming-soon/journal" class="pc-card">
      <div class="pc-health-card__head">
        <span class="pc-health-card__module pc-health-card__module--notes">
          <?= icon('notebook-pen', 18) ?>
          <span>Transmissions</span>
        </span>
        <span class="pc-badge pc-badge--brand">Phase 2</span>
      </div>
      <div class="pc-health-card__body">
        <div class="w-full">
          <div class="pc-skeleton pc-skeleton--md mb-2"></div>
          <div class="pc-skeleton pc-skeleton--sm"></div>
        </div>
      </div>
    </a>
  </div>
</section>

<!-- Accès rapides Phase 1 -->
<section aria-labelledby="home-quick-heading">
  <div class="pc-section-header" id="home-quick-heading">Dossier patient & Urgences</div>
  <div class="pc-list-group">
    <a href="/patient" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('heart-pulse', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Fiche d’urgence & Protocole d’hypoglycémie</div>
        <div class="pc-list-subtitle">Contacts SAMU 15, médecin traitant, consignes permanentes</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
    </a>

    <?php if (!empty($patient['attending_physician_phone'])): ?>
      <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $patient['attending_physician_phone']) ?? '') ?>" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--ok">
          <?= icon('phone', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Appeler <?= e((string) ($patient['attending_physician_name'] ?? 'le médecin traitant')) ?></div>
          <div class="pc-list-subtitle"><?= e((string) $patient['attending_physician_phone']) ?></div>
        </div>
        <span class="pc-badge pc-badge--ok">Appel direct</span>
      </a>
    <?php endif; ?>
  </div>
</section>
