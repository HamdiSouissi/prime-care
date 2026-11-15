<?php

declare(strict_types=1);

use App\Core\View;

$user = is_array($user ?? null) ? $user : (auth() ?? []);
$patient = is_array($patient ?? null) ? $patient : [];
$role = (string) ($userRole ?? ($user['role'] ?? 'caregiver'));
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
$isViewer = $role === 'viewer';

$dayProgram = is_array($dayProgram ?? null) ? $dayProgram : [];
$counts = $dayProgram['counts'] ?? ['total' => 0, 'done' => 0, 'late' => 0, 'due' => 0, 'progress_pct' => 0];
$flatItems = $dayProgram['flat'] ?? [];
$unreadHandover = is_array($unreadHandover ?? null) ? $unreadHandover : null;
$attention = is_array($attention ?? null) ? $attention : ['count' => 0, 'items' => []];
$observance = is_array($observance ?? null) ? $observance : ['days_7' => ['rate' => 100, 'given' => 0, 'total' => 0], 'days_30' => ['rate' => 100, 'given' => 0, 'total' => 0]];
$adminMetrics = is_array($adminMetrics ?? null) ? $adminMetrics : [];

// Build 7-day glycemia ApexCharts config for Admin / Coordinator / Viewer synthesis
$glySeriesData = [];
foreach (($adminMetrics['glycemia_7d']['points'] ?? []) as $pt) {
    $glySeriesData[] = [
        'x' => $pt['x'],
        'y' => $pt['y'],
        'alertLevel' => $pt['alert_level'],
        'statusLabel' => $pt['context'],
    ];
}
$homeApexConfig = [
    'chart' => ['type' => 'area', 'height' => 205],
    'colors' => ['#FF2D55'],
    'series' => [
        [
            'name' => 'Glycémie (g/L)',
            'data' => $glySeriesData,
            'color' => '#FF2D55',
        ],
    ],
    'yaxis' => ['unit' => 'g/L', 'decimalsInFloat' => 2],
    'annotations' => [
        'yaxis' => [
            [
                'y' => 0.80,
                'y2' => 1.80,
                'fillColor' => '#34C759',
                'borderColor' => '#34C759',
                'opacity' => 0.13,
                'label' => ['text' => 'Cible (0,80–1,80 g/L)'],
            ],
            [
                'y' => 0.70,
                'borderColor' => '#FF3B30',
                'strokeDashArray' => '4 4',
                'label' => ['text' => 'Hypo < 0,70'],
            ],
        ],
    ],
];
?>
<?php View::section('hero_header'); ?>
<!-- 1. En-tête Bleu nuit pleine largeur en haut de l'écran (§4 & §8 de l'additif Design B) -->
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
      <?= icon('chevron-right', '', 20) ?>
    </span>
  </a>
</header>
<?php View::endSection(); ?>

<!-- 2. Bandeau de transmission de fin de poste non lue (E13 / §8.2) -->
<?php if ($unreadHandover !== null): ?>
  <?php
  $hSummary = is_string($unreadHandover['handover_summary'] ?? null)
      ? (json_decode((string) $unreadHandover['handover_summary'], true) ?: [])
      : [];
  $hTime = \App\Services\ScheduleService::utcToLocalParis((string) $unreadHandover['recorded_at'], 'd/m à H:i');
  ?>
  <section class="pc-handover-banner" id="unread-handover-banner" aria-label="Transmission de relève non lue">
    <div class="pc-handover-banner__header">
      <div class="flex items-center gap-2">
        <span class="pc-list-icon pc-list-icon--notes">
          <?= icon('notebook-pen', '', 18) ?>
        </span>
        <div>
          <div class="text-subhead font-bold">Transmission de relève · <?= e((string) $unreadHandover['author_first_name']) ?></div>
          <div class="text-caption text-muted">Publiée le <?= e($hTime) ?></div>
        </div>
      </div>
      <span class="pc-badge pc-badge--warn">Non lue</span>
    </div>

    <p class="pc-handover-banner__body"><?= e((string) $unreadHandover['content']) ?></p>

    <?php if (!empty($hSummary['summary_text'])): ?>
      <div class="pc-handover-banner__auto">
        <?= icon('activity', '', 15) ?>
        <span><?= e((string) $hSummary['summary_text']) ?></span>
      </div>
    <?php endif; ?>

    <div class="pc-handover-banner__actions">
      <form method="POST" action="/journal/notes/<?= (int) $unreadHandover['id'] ?>/read">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/">
        <button
          type="submit"
          class="pc-btn pc-btn--primary pc-btn--sm"
          data-mark-read-id="<?= (int) $unreadHandover['id'] ?>"
        >
          <?= icon('check', '', 16) ?>
          <span>Marquer comme lu</span>
        </button>
      </form>
      <a href="/journal" class="pc-btn pc-btn--ghost pc-btn--sm">Ouvrir le journal</a>
    </div>
  </section>
<?php endif; ?>

<!-- 3. Carte d'installation PWA sur mobile (§4 bis.2 — masquée en mode standalone ou fermée 7j) -->
<section class="pc-install-banner is-hidden" id="pc-home-install-banner" aria-label="Installer Prime Care sur l’écran d’accueil">
  <div class="pc-install-banner__icon">
    <?= icon('smartphone', '', 22) ?>
  </div>
  <div class="pc-install-banner__content">
    <div class="text-subhead font-semibold">Installer Prime Care sur cet appareil</div>
    <div class="text-caption text-muted">Accès plein écran en 1 tap depuis l’écran d’accueil (sans barre navigateur).</div>
    <div class="flex items-center gap-2 mt-2">
      <a href="/install" class="pc-btn pc-btn--tinted pc-btn--sm" id="pc-home-install-cta">Installer</a>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" data-dismiss-install-banner>Plus tard</button>
    </div>
  </div>
</section>

<?php if ($isAdmin || $isCoordinator || $isViewer): ?>
  <!-- ======================================================================
       VUE SYNTHÈSE RESPONSABLE / RÉFÉRENTE / LECTURE SEULE (§3 Écran 2)
       ====================================================================== -->

  <!-- Points d'attention en temps réel -->
  <?php if (($attention['count'] ?? 0) > 0): ?>
    <section aria-labelledby="home-attention-heading">
      <div class="pc-section-header" id="home-attention-heading">
        Points d’attention (<?= (int) $attention['count'] ?>)
      </div>
      <div class="pc-stack gap-2">
        <?php foreach (array_slice($attention['items'], 0, 4) as $att): ?>
          <a href="<?= e((string) $att['link']) ?>" class="pc-attention-card pc-attention-card--<?= e((string) $att['severity']) ?>">
            <span class="pc-attention-card__dot"></span>
            <div class="flex-1 min-w-0">
              <div class="text-subhead font-semibold"><?= e((string) $att['title']) ?></div>
              <div class="text-caption text-muted truncate"><?= e((string) $att['subtitle']) ?></div>
            </div>
            <span class="text-caption font-mono text-muted"><?= e((string) $att['time']) ?></span>
            <?= icon('chevron-right', '', 16) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Grille synthèse 2 colonnes : Dernières constantes & Observance -->
  <section aria-labelledby="home-kpi-heading">
    <div class="pc-section-header" id="home-kpi-heading">Synthèse médicale temps réel</div>
    <div class="pc-module-grid pc-module-grid--2col">
      <!-- Dernière glycémie -->
      <?php $lastGly = $adminMetrics['latest']['glycemia'] ?? null; ?>
      <a href="/health/glycemia" class="pc-card flex flex-col justify-between gap-2">
        <div class="flex items-center justify-between">
          <span class="pc-health-card__header text-vitals">
            <?= icon('droplet', '', 17) ?>
            <span>Glycémie</span>
          </span>
          <?php if ($lastGly): ?>
            <span class="pc-badge <?= e((string) $lastGly['eval']['badge_class']) ?>">
              <?= e((string) $lastGly['eval']['label']) ?>
            </span>
          <?php endif; ?>
        </div>
        <?php if ($lastGly): ?>
          <div>
            <div class="pc-metric-lg">
              <?= e((string) $lastGly['formatted']) ?>
              <span class="pc-metric-unit"><?= e((string) $lastGly['unit']) ?></span>
            </div>
            <div class="text-caption text-muted">
              <?= e((string) $lastGly['context_label']) ?> · <?= e((string) $lastGly['time_local']) ?>
            </div>
          </div>
        <?php else: ?>
          <div class="text-subhead text-muted">Aucun relevé</div>
        <?php endif; ?>
      </a>

      <!-- Observance des traitements -->
      <a href="/treatments/history" class="pc-card flex flex-col justify-between gap-2">
        <div class="flex items-center justify-between">
          <span class="pc-health-card__header text-meds">
            <?= icon('pill', '', 17) ?>
            <span>Observance 7j</span>
          </span>
          <span class="pc-badge <?= ($observance['days_7']['rate'] ?? 100) >= 90 ? 'pc-badge--ok' : 'pc-badge--warn' ?>">
            <?= number_format((float) ($observance['days_7']['rate'] ?? 100), 1, ',', '') ?> %
          </span>
        </div>
        <div>
          <div class="pc-metric-lg">
            <?= (int) ($observance['days_7']['given'] ?? 0) ?>/<?= (int) ($observance['days_7']['total'] ?? 0) ?>
            <span class="pc-metric-unit">prises</span>
          </div>
          <div class="text-caption text-muted">
            Sur 30 j : <?= number_format((float) ($observance['days_30']['rate'] ?? 100), 1, ',', '') ?> %
            · Aujourd’hui : <?= (int) $counts['done'] ?>/<?= (int) $counts['total'] ?>
          </div>
        </div>
      </a>

      <!-- Dernière tension artérielle -->
      <?php $lastBp = $adminMetrics['latest']['blood_pressure'] ?? null; ?>
      <a href="/health/blood_pressure" class="pc-card flex flex-col justify-between gap-2">
        <div class="flex items-center justify-between">
          <span class="pc-health-card__header text-vitals">
            <?= icon('heart-pulse', '', 17) ?>
            <span>Tension</span>
          </span>
          <?php if ($lastBp): ?>
            <span class="pc-badge <?= e((string) $lastBp['eval']['badge_class']) ?>">
              <?= e((string) $lastBp['eval']['label']) ?>
            </span>
          <?php endif; ?>
        </div>
        <?php if ($lastBp): ?>
          <div>
            <div class="pc-metric-lg">
              <?= e((string) $lastBp['formatted']) ?>
              <span class="pc-metric-unit">mmHg</span>
            </div>
            <div class="text-caption text-muted">Relevé le <?= e((string) $lastBp['time_local']) ?></div>
          </div>
        <?php else: ?>
          <div class="text-subhead text-muted">Aucun relevé</div>
        <?php endif; ?>
      </a>

      <!-- SpO2 & Poids -->
      <?php
      $lastSpo2 = $adminMetrics['latest']['spo2'] ?? null;
      $lastWeight = $adminMetrics['latest']['weight'] ?? null;
      ?>
      <a href="/health" class="pc-card flex flex-col justify-between gap-2">
        <div class="flex items-center justify-between">
          <span class="pc-health-card__header text-vitals">
            <?= icon('activity', '', 17) ?>
            <span>SpO2 &amp; Poids</span>
          </span>
          <span class="pc-badge pc-badge--ok">Suivi</span>
        </div>
        <div>
          <div class="pc-metric-lg">
            <?= $lastSpo2 ? e((string) $lastSpo2['formatted']) . ' %' : '—' ?>
            <span class="pc-metric-unit">· <?= $lastWeight ? e((string) $lastWeight['formatted']) . ' kg' : '—' ?></span>
          </div>
          <div class="text-caption text-muted">Toutes les constantes &rarr;</div>
        </div>
      </a>
    </div>
  </section>

  <!-- Courbe de glycémie sur 7 jours (ApexCharts self-hosted + résumé textuel accessible) -->
  <section class="pc-card" aria-labelledby="home-chart-heading">
    <div class="flex items-center justify-between mb-2">
      <div>
        <h2 class="text-headline" id="home-chart-heading">Courbe glycémique · 7 derniers jours</h2>
        <p class="text-caption text-muted"><?= e((string) ($adminMetrics['glycemia_7d']['summary_text'] ?? '')) ?></p>
      </div>
      <a href="/health/glycemia" class="pc-btn pc-btn--tinted pc-btn--sm">Détail</a>
    </div>

    <div
      class="pc-chart-box"
      data-pc-chart
      data-chart-config="<?= e((string) json_encode($homeApexConfig, JSON_UNESCAPED_UNICODE)) ?>"
      role="img"
      aria-label="<?= e((string) ($adminMetrics['glycemia_7d']['summary_text'] ?? 'Courbe glycémique 7 jours')) ?>"
    ></div>
  </section>
<?php endif; ?>

<!-- ======================================================================
     CHRONOLOGIE DE POSTE (Aide-soignante, Référente & Suivi du jour) (§8.1)
     ====================================================================== -->
<section aria-labelledby="shift-timeline-heading">
  <div class="flex items-center justify-between mb-2">
    <div class="pc-section-header mb-0" id="shift-timeline-heading">
      Chronologie du jour · <?= (int) $counts['done'] ?>/<?= (int) $counts['total'] ?> réalisés
    </div>
    <a href="/today" class="text-footnote font-semibold text-brand">Tout voir &rarr;</a>
  </div>

  <!-- Barre de progression du poste -->
  <div class="pc-card mb-3">
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2">
        <?= icon('calendar-check', 'text-brand', 18) ?>
        <span class="text-subhead font-semibold">Avancement du programme</span>
      </div>
      <span class="pc-badge <?= $counts['late'] > 0 ? 'pc-badge--danger' : 'pc-badge--brand' ?>">
        <?= $counts['late'] > 0 ? $counts['late'] . ' en retard' : $counts['progress_pct'] . ' %' ?>
      </span>
    </div>
    <progress class="pc-progress" value="<?= (int) $counts['progress_pct'] ?>" max="100">
      <?= (int) $counts['progress_pct'] ?> %
    </progress>
  </div>

  <!-- Fil chronologique vertical à 3 états (Fait / En cours / À venir) -->
  <div class="pc-shift-timeline">
    <?php foreach (array_slice($flatItems, 0, 10) as $item): ?>
      <?php
      $state = (string) ($item['timeline_state'] ?? 'upcoming');
      $kind = (string) ($item['kind'] ?? 'intake');
      ?>
      <article class="pc-shift-step pc-shift-step--<?= e($state) ?>">
        <div class="pc-shift-step__rail" aria-hidden="true">
          <span class="pc-shift-step__dot">
            <?php if ($state === 'done'): ?>
              <?= icon('check', '', 13) ?>
            <?php elseif ($state === 'current'): ?>
              <?= icon('clock', '', 13) ?>
            <?php endif; ?>
          </span>
        </div>

        <div class="pc-shift-step__card">
          <div class="pc-shift-step__top">
            <span class="pc-shift-step__time"><?= e((string) $item['time']) ?></span>
            <span class="pc-badge <?= e((string) $item['badge_class']) ?>"><?= e((string) $item['status_label']) ?></span>
          </div>

          <div class="pc-shift-step__title"><?= e((string) $item['title']) ?></div>
          <div class="pc-shift-step__subtitle"><?= e((string) $item['subtitle']) ?></div>

          <?php if (!$isViewer && $state !== 'done'): ?>
            <div class="pc-shift-step__actions">
              <?php if ($kind === 'intake'): ?>
                <?php if (!empty($item['is_variable_insulin'])): ?>
                  <button
                    type="button"
                    class="pc-btn pc-btn--primary pc-btn--sm"
                    data-open-insulin-give="<?= (int) $item['id'] ?>"
                    data-insulin-title="<?= e((string) $item['title']) ?>"
                  >
                    <?= icon('syringe', '', 15) ?>
                    <span>Grille &amp; Injecter</span>
                  </button>
                <?php else: ?>
                  <button
                    type="button"
                    class="pc-btn pc-btn--primary pc-btn--sm"
                    data-quick-give-id="<?= (int) $item['id'] ?>"
                  >
                    <?= icon('check', '', 15) ?>
                    <span>Donné</span>
                  </button>
                <?php endif; ?>
                <button
                  type="button"
                  class="pc-btn pc-btn--ghost pc-btn--sm"
                  data-open-skip-id="<?= (int) $item['id'] ?>"
                  data-skip-title="<?= e((string) $item['title']) ?>"
                >
                  <span>Non pris</span>
                </button>
              <?php elseif ($kind === 'measure_expectation'): ?>
                <button
                  type="button"
                  class="pc-btn pc-btn--tinted pc-btn--sm"
                  data-open-measure-sheet="<?= e((string) $item['type_code']) ?>"
                  data-expectation-id="<?= (int) $item['id'] ?>"
                  data-measure-context="<?= e((string) $item['context']) ?>"
                >
                  <?= icon('droplet', '', 15) ?>
                  <span>Mesurer</span>
                </button>
              <?php elseif ($kind === 'meal_slot'): ?>
                <button
                  type="button"
                  class="pc-btn pc-btn--tinted pc-btn--sm"
                  data-open-meal-sheet="<?= e((string) $item['meal_type']) ?>"
                >
                  <?= icon('utensils', '', 15) ?>
                  <span>Saisir le repas</span>
                </button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- Accès rapides -->
<section aria-labelledby="quick-access-heading">
  <div class="pc-section-header" id="quick-access-heading"><?= e(t('home.quick_access')) ?></div>
  <div class="pc-list-group">
    <a href="/health" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('heart-pulse', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Santé &amp; Courbes de constantes</div>
        <div class="pc-list-subtitle">Glycémie, tension, SpO2, poids, température &amp; historique</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/treatments" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meds">
        <?= icon('pill', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Traitements, Insuline &amp; Observance</div>
        <div class="pc-list-subtitle">Validation des prises, grille d’insuline, historique 7/30j</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/meals" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meals">
        <?= icon('utensils', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Repas &amp; Photos Avant/Après</div>
        <div class="pc-list-subtitle">Galerie des repas, glucides et glycémies associées</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/journal" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--notes">
        <?= icon('notebook-pen', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Journal unifié &amp; Transmissions</div>
        <div class="pc-list-subtitle">Fil complet horodaté, soins rapides et relève d’équipe</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <?php if ($isAdmin): ?>
      <a href="/admin/treatments" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--meds">
          <?= icon('clipboard-list', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Administration du plan de traitement</div>
          <div class="pc-list-subtitle">Médicaments, prescriptions, grille d’insuline &amp; ordonnances</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    <?php endif; ?>
  </div>
</section>
