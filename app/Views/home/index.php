<?php

declare(strict_types=1);

$user = is_array($user ?? null) ? $user : (auth() ?? []);
$patient = is_array($patient ?? null) ? $patient : [];
$role = (string) ($user['role'] ?? 'caregiver');
$firstName = (string) ($user['first_name'] ?? 'Équipe');
$lastName = (string) ($user['last_name'] ?? '');

$patientFirst = (string) ($patient['first_name'] ?? 'Henri');
$patientLast = (string) ($patient['last_name'] ?? 'Benali');
$patientName = trim($patientFirst . ' ' . $patientLast);
$patientAge = isset($patient['birth_date']) ? calculate_age((string) $patient['birth_date']) : 84;
$diabetesType = (string) ($patient['diabetes_type'] ?? 'Diabète de type 2');
$targetMin = number_format((float) ($patient['target_glycemia_fasting_min'] ?? 0.80), 2, ',', ' ');
$targetMax = number_format((float) ($patient['target_glycemia_fasting_max'] ?? 1.30), 2, ',', ' ');

$isAdmin = $role === 'admin';
$isCoordinator = $role === 'coordinator';
$isCaregiver = $role === 'caregiver';
?>
<?php View::section('hero_header'); ?>
<!-- 1. En-tête Bleu nuit pleine largeur en haut de l'écran (§4 de l'additif / D2.1) -->
<header class="pc-home-hero" aria-label="En-tête d’accueil Prime Care">
  <div class="pc-home-hero__topbar">
    <div class="pc-home-hero__brand-date">
      <span class="pc-home-hero__monogram" aria-hidden="true">
        <?= brand_svg('icone-transparent', '', 30) ?>
      </span>
      <span class="pc-home-hero__date"><?= e(format_date_long()) ?></span>
    </div>

    <div class="pc-home-hero__actions">
      <a href="/patient" class="pc-home-hero__circle-btn" aria-label="Urgences et protocole patient">
        <?= icon('phone', '', 17) ?>
      </a>
      <a href="/profile" class="pc-home-hero__circle-btn pc-home-hero__circle-btn--profile" aria-label="Mon profil (<?= e($firstName) ?>)">
        <?php if (!empty($user['avatar_path'])): ?>
          <img src="/media/<?= e((string) $user['avatar_path']) ?>" alt="<?= e($firstName) ?>">
        <?php else: ?>
          <span><?= e(initials($firstName, $lastName)) ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <div class="pc-home-hero__greeting-row">
    <h1 class="pc-home-hero__greeting"><?= e(t('home.greeting', ['name' => $firstName])) ?></h1>
    <span class="pc-badge pc-badge--hero"><?= e(t('roles.' . $role)) ?></span>
  </div>

  <a href="/patient" class="pc-home-patient" aria-label="Ouvrir la fiche de <?= e($patientName) ?>">
    <div class="pc-home-patient__avatar">
      <?= e(initials($patientFirst, $patientLast)) ?>
    </div>
    <div class="pc-home-patient__info">
      <div class="pc-home-patient__name"><?= e($patientName) ?> · <?= $patientAge ?> ans</div>
      <div class="pc-home-patient__meta">
        <?= e($diabetesType) ?> · Cible à jeun <?= e($targetMin) ?>–<?= e($targetMax) ?> g/L
      </div>
    </div>
    <span class="pc-home-patient__chevron">
      <?= icon('chevron-right', 20) ?>
    </span>
  </a>
</header>
<?php View::endSection(); ?>

<!-- 2. Carte Poste du jour / Pointage (Aide-soignante & Référente) -->
<?php if ($isCaregiver || $isCoordinator): ?>
  <section class="pc-card" aria-labelledby="shift-heading">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <span class="pc-list-icon pc-list-icon--lg pc-list-icon--team">
          <?= icon('clock', 22) ?>
        </span>
        <div class="min-w-0">
          <h2 class="text-headline" id="shift-heading">Poste du jour · Domicile</h2>
          <p class="text-footnote text-muted">Pointage QR / GPS disponible en Phase 3</p>
        </div>
      </div>
      <a href="/coming-soon/timeclock" class="pc-btn pc-btn--tinted pc-btn--sm">
        <?= e(t('home.shift_arrive')) ?>
      </a>
    </div>
  </section>
<?php endif; ?>

<!-- 3. Aperçu des modules Phase 2 (Squelettes Shimmer honnêtes §6.8) -->
<section aria-labelledby="phase2-preview-heading">
  <div class="pc-section-header" id="phase2-preview-heading">Suivi quotidien (Phase 2 — bientôt disponible)</div>
  <div class="pc-module-grid pc-module-grid--2col">
    <!-- Carte Glycémie -->
    <a href="/coming-soon/glycemia" class="pc-card flex flex-col justify-between gap-3">
      <div class="flex items-center justify-between">
        <div class="pc-health-card__header text-vitals">
          <?= icon('droplet', 18) ?>
          <span><?= e(t('home.glycemia_card')) ?></span>
        </div>
        <span class="pc-badge pc-badge--brand">Phase 2</span>
      </div>
      <div>
        <div class="pc-skeleton pc-skeleton--lg mb-2"></div>
        <div class="pc-skeleton pc-skeleton--sm"></div>
      </div>
      <span class="pc-skeleton pc-skeleton--pill"></span>
    </a>

    <!-- Carte Prochaine prise -->
    <a href="/coming-soon/medication" class="pc-card flex flex-col justify-between gap-3">
      <div class="flex items-center justify-between">
        <div class="pc-health-card__header text-meds">
          <?= icon('pill', 18) ?>
          <span><?= e(t('home.next_med')) ?></span>
        </div>
        <span class="pc-badge pc-badge--brand">Phase 2</span>
      </div>
      <div>
        <div class="pc-skeleton pc-skeleton--md mb-2"></div>
        <div class="pc-skeleton pc-skeleton--sm"></div>
      </div>
      <span class="pc-btn pc-btn--tinted pc-btn--sm self-start">
        <?= e(t('home.med_given')) ?>
      </span>
    </a>
  </div>
</section>

<!-- 4. Accès rapides Phase 1 (100% fonctionnels en <= 2 taps) -->
<section aria-labelledby="quick-access-heading">
  <div class="pc-section-header" id="quick-access-heading"><?= e(t('home.quick_access')) ?></div>
  <div class="pc-list-group">
    <a href="/patient" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--danger">
        <?= icon('alert-triangle', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title"><?= e(t('home.emergency_card')) ?></div>
        <div class="pc-list-subtitle">Protocole &lt; 0,70 g/L, SAMU (15) &amp; contacts directs</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
    </a>

    <a href="/patient" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('heart-pulse', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title"><?= e(t('home.patient_card')) ?></div>
        <div class="pc-list-subtitle">Cibles glycémiques, allergies, antécédents &amp; digicode</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
    </a>

    <?php if ($isAdmin): ?>
      <a href="/users" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--team">
          <?= icon('users', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title"><?= e(t('home.team_card')) ?></div>
          <div class="pc-list-subtitle">Comptes, rôles, contrats, codes PIN &amp; accès</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
      </a>

      <a href="/design" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--notes">
          <?= icon('palette', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Design System (« Proposition B — Bleu nuit »)</div>
          <div class="pc-list-subtitle">Tokens CSS, composants, états &amp; loader animé</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
      </a>
    <?php endif; ?>

    <a href="/profile" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--brand">
        <?= icon('user', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Mon profil &amp; Appareil de confiance PIN</div>
        <div class="pc-list-subtitle">Photo, code PIN sur cet appareil, thème Clair / Sombre</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', 18) ?></span>
    </a>
  </div>
</section>
