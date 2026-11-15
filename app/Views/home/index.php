<?php

declare(strict_types=1);

use App\Core\Session;
use App\Core\View;
use App\Services\ScheduleService;

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
$trends7d = is_array($trends7d ?? null) ? $trends7d : [];

$clockState = is_array($clockState ?? null) ? $clockState : [
    'is_clocked_in' => false,
    'last_attendance' => null,
    'next_type' => 'in',
    'next_shift' => null,
    'gdpr_accepted' => true,
    'settings' => ['verification_mode' => 'gps', 'radius_m' => 150, 'require_clocked_in' => false],
];
$nextShift = is_array($nextShift ?? null) ? $nextShift : ($clockState['next_shift'] ?? null);
$openAlertsCount = isset($openAlertsCount) ? (int) $openAlertsCount : 0;
$nowLocalHm = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'H\hi');
$nowLocalInputHm = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'H:i');
$todayYmd = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'Y-m-d');
$promptHandover = Session::getFlash('prompt_handover') === '1';

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
<?php if (!empty($cronStale)): ?>
  <div class="pc-card pc-card--danger flex items-center justify-between gap-2 mb-3" role="alert">
    <div class="flex items-center gap-2">
      <?= icon('alert-triangle', 'text-danger', 20) ?>
      <div>
        <div class="font-bold text-danger">Les alertes automatiques sont à l'arrêt</div>
        <div class="text-caption">La tâche cron ne s’est pas exécutée depuis plus de 15 minutes. Vérifiez la planification serveur.</div>
      </div>
    </div>
    <a href="/settings" class="pc-btn pc-btn--secondary pc-btn--sm">Vérifier</a>
  </div>
<?php endif; ?>

<header class="pc-home-hero" aria-label="En-tête d’accueil Prime Care">
  <div class="pc-home-hero__topbar">
    <div class="pc-home-hero__brand-date">
      <span class="pc-home-hero__monogram" aria-hidden="true">
        <?= brand_svg('icone-transparent', '', 30) ?>
      </span>
      <span class="pc-home-hero__date"><?= e(format_date_long()) ?></span>
    </div>

    <div class="pc-home-hero__actions">
      <a href="/alerts" class="pc-home-hero__circle-btn pc-home-hero__circle-btn--bell">
        <span class="sr-only">Centre d’alertes</span>
        <?= icon('bell', '', 17) ?>
        <?php if ($openAlertsCount > 0): ?>
          <span class="pc-alert-badge" data-alert-badge><?= $openAlertsCount > 99 ? '99+' : $openAlertsCount ?></span>
        <?php endif; ?>
      </a>
      <a href="/patient" class="pc-home-hero__circle-btn" aria-label="Urgences et protocole patient">
        <?= icon('phone', '', 17) ?>
      </a>
      <a href="/profile" class="pc-home-hero__circle-btn pc-home-hero__circle-btn--profile">
        <span class="sr-only">Mon profil (<?= e($firstName) ?>)</span>
        <?php if (!empty($user['avatar_path'])): ?>
          <img src="/media/<?= e((string) $user['avatar_path']) ?>" alt="<?= e($firstName) ?>">
        <?php else: ?>
          <span aria-hidden="true"><?= e(initials($firstName, $lastName)) ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <div class="pc-home-hero__greeting-row">
    <h1 class="pc-home-hero__greeting"><?= e(t('home.greeting', ['name' => $firstName])) ?></h1>
    <span class="pc-badge pc-badge--hero"><?= e(t('roles.' . $role)) ?></span>
  </div>

  <a href="/patient" class="pc-home-patient">
    <div class="pc-home-patient__avatar" aria-hidden="true">
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

<?php if ($promptHandover): ?>
  <!-- Invite immédiate de transmission de fin de poste après pointage de départ (E05) -->
  <section class="pc-handover-banner" id="departure-handover-prompt" aria-label="Transmission de fin de poste">
    <div class="pc-handover-banner__header">
      <div class="flex items-center gap-2">
        <span class="pc-list-icon pc-list-icon--notes">
          <?= icon('notebook-pen', '', 18) ?>
        </span>
        <div>
          <div class="text-subhead font-bold">Fin de poste enregistrée</div>
          <div class="text-caption text-muted">Transmettez les points clés à la collègue qui prend la relève.</div>
        </div>
      </div>
      <span class="pc-badge pc-badge--brand">Relève</span>
    </div>
    <div class="pc-handover-banner__actions mt-2">
      <a href="/journal?handover=1" class="pc-btn pc-btn--primary pc-btn--sm">
        <?= icon('notebook-pen', '', 16) ?>
        <span>Rédiger la transmission de fin de poste</span>
      </a>
    </div>
  </section>
<?php endif; ?>

<?php if ($isCaregiver || $isCoordinator): ?>
  <!-- ======================================================================
       BLOC POINTAGE (E05) & MON PROCHAIN CRÉNEAU (E16)
       ====================================================================== -->
  <?php
  $isClockedIn = !empty($clockState['is_clocked_in']);
  $lastAtt = $clockState['last_attendance'] ?? null;
  $lastAttLocalHm = $lastAtt
      ? ScheduleService::utcToLocalParis((string) ($lastAtt['declared_at'] ?? $lastAtt['recorded_at']), 'H\hi')
      : null;
  $vMode = (string) ($clockState['settings']['verification_mode'] ?? 'gps');
  $gdprAccepted = !empty($clockState['gdpr_accepted']);
  ?>
  <section class="pc-card pc-punch-card" aria-labelledby="home-punch-heading" data-punch-widget data-verification-mode="<?= e($vMode) ?>" data-next-type="<?= $isClockedIn ? 'out' : 'in' ?>" data-gdpr-accepted="<?= $gdprAccepted ? '1' : '0' ?>">
    <div class="flex items-center justify-between gap-2 mb-3">
      <div class="flex items-center gap-2">
        <span class="pc-list-icon <?= $isClockedIn ? 'pc-list-icon--ok' : 'pc-list-icon--team' ?>">
          <?= icon('clock', '', 18) ?>
        </span>
        <div>
          <h2 class="text-subhead font-bold" id="home-punch-heading">
            <?= $isClockedIn ? 'En poste depuis ' . e((string) $lastAttLocalHm) : 'Pointage de prise de poste' ?>
          </h2>
          <div class="text-caption text-muted">
            <?php if ($nextShift): ?>
              <?= !empty($nextShift['is_active_now']) ? 'Créneau en cours' : 'Mon prochain créneau' ?> :
              <strong><?= e((string) $nextShift['local_start_time']) ?> – <?= e((string) $nextShift['local_end_time']) ?></strong>
              <?php if (!empty($nextShift['replaced_first_name'])): ?>
                <span>(remplace <?= e((string) $nextShift['replaced_first_name']) ?>)</span>
              <?php endif; ?>
            <?php else: ?>
              Heure enregistrée côté serveur · Vérification <?= e(strtoupper($vMode)) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <span class="pc-badge <?= $isClockedIn ? 'pc-badge--ok' : 'pc-badge--neutral' ?>" id="pc-punch-status-badge">
        <?= icon($isClockedIn ? 'check-circle' : 'clock', '', 13) ?>
        <span><?= $isClockedIn ? 'En poste' : 'Hors poste' ?></span>
      </span>
    </div>

    <?php if (!$gdprAccepted): ?>
      <div class="pc-gdpr-notice mb-3" id="pc-gdpr-notice">
        <div class="text-caption mb-2">
          <strong>Information RGPD (Pointage) :</strong> Lors du pointage uniquement, votre position ponctuelle ou le scan du QR code du domicile est vérifié afin d’attester la présence auprès de M. Henri Benali (aucun suivi en continu). Conservation limitée à la durée légale de gestion des heures.
        </div>
        <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-accept-gdpr-btn>
          <?= icon('check', '', 14) ?>
          <span>J’ai compris</span>
        </button>
      </div>
    <?php endif; ?>

    <!-- Rail glissant tactile ("Glisser pour arriver" / "Glisser pour partir") — Addendum B §8.2 (G5) -->
    <div
      class="pc-slide-punch pc-slider-punch <?= $isClockedIn ? 'pc-slide-punch--out' : 'pc-slide-punch--in' ?>"
      data-slide-punch
      data-punch-type="<?= $isClockedIn ? 'out' : 'in' ?>"
    >
      <div class="pc-slide-punch__track" aria-hidden="true">
        <span class="pc-slide-punch__label" data-slide-punch-label>
          <?= $isClockedIn ? 'Glisser pour partir' : 'Glisser pour arriver' ?>
        </span>
      </div>
      <button
        type="button"
        class="pc-slide-punch__thumb"
        data-slide-punch-thumb
        aria-label="<?= $isClockedIn ? 'Pointer mon départ (clavier ou glisser)' : 'Pointer mon arrivée (clavier ou glisser)' ?>"
      >
        <span class="pc-slide-punch__icon-arrow"><?= icon('chevrons-right', '', 22) ?></span>
        <span class="pc-slide-punch__icon-check"><?= icon('check', '', 22) ?></span>
      </button>
    </div>

    <!-- Alternative accessible clavier / lecteur d'écran + Scan QR code + Oubli de pointage -->
    <div class="pc-punch-card__links mt-3">
      <button
        type="button"
        class="pc-btn pc-btn--tinted pc-btn--sm"
        data-sheet-open="punch-confirm-sheet"
      >
        <?= icon('check-circle', '', 15) ?>
        <span><?= $isClockedIn ? 'Confirmer mon départ' : 'Confirmer mon arrivée' ?></span>
      </button>

      <button
        type="button"
        class="pc-btn pc-btn--ghost pc-btn--sm"
        data-open-qr-scanner
      >
        <?= icon('qr-code', '', 15) ?>
        <span>Scanner QR domicile</span>
      </button>

      <button
        type="button"
        class="pc-btn pc-btn--ghost pc-btn--sm"
        data-sheet-open="forgot-punch-sheet"
      >
        <span>J’ai oublié de pointer</span>
      </button>
    </div>

    <?php if ($nextShift): ?>
      <div class="pc-punch-card__shift-footer mt-3">
        <div class="text-caption text-muted">
          <?= icon('calendar-days', '', 14) ?>
          <span>Mon prochain créneau : <?= e(format_date_fr((string) $nextShift['local_date'])) ?> · <?= e((string) $nextShift['local_start_time']) ?>–<?= e((string) $nextShift['local_end_time']) ?></span>
        </div>
        <a href="/planning" class="text-caption font-semibold text-brand">Voir mon planning &rsaquo;</a>
      </div>
    <?php endif; ?>
  </section>

  <!-- Feuille de confirmation accessible (clavier / lecteur d'écran : "Confirmer mon arrivée à HHhMM") -->
  <div class="pc-sheet-backdrop" id="punch-confirm-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="punch-confirm-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="punch-confirm-title">
          <?= $isClockedIn ? "Confirmer mon départ à {$nowLocalHm}" : "Confirmer mon arrivée à {$nowLocalHm}" ?>
        </h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/attendance/punch" class="pc-sheet__body pc-stack gap-3" data-accessible-punch-form>
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= $isClockedIn ? 'out' : 'in' ?>">
        <input type="hidden" name="method" value="slide">
        <input type="hidden" name="latitude" value="" data-punch-lat>
        <input type="hidden" name="longitude" value="" data-punch-lon>
        <input type="hidden" name="accuracy_m" value="" data-punch-acc>
        <input type="hidden" name="redirect_to" value="/">

        <p class="text-subhead text-muted">
          L’heure officielle enregistrée sera l’heure exacte du serveur au moment de la validation (<?= e($nowLocalHm) ?>).
        </p>

        <div class="flex items-center gap-2">
          <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
            <?= icon('check', '', 18) ?>
            <span><?= $isClockedIn ? "Confirmer mon départ à {$nowLocalHm}" : "Confirmer mon arrivée à {$nowLocalHm}" ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Feuille de mention d'information RGPD pour le premier pointage (G2) -->
  <div class="pc-sheet-backdrop" id="gdpr-notice-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="gdpr-notice-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="gdpr-notice-title">Information sur le pointage (RGPD)</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <div class="pc-sheet__body pc-stack gap-3">
        <div class="pc-gdpr-notice">
          <p class="text-subhead font-semibold mb-1">Traitement ponctuel des données de présence</p>
          <p class="text-caption text-muted">
            Conformément au RGPD, l’application Prime Care vérifie votre présence au domicile de M. Henri Benali <strong>uniquement à l’instant précis où vous pointez</strong> votre arrivée ou votre départ (par position GPS ponctuelle ou scan du QR code de l’entrée).
          </p>
          <ul class="text-caption text-muted mt-2 pc-stack gap-1">
            <li>• Aucun suivi géolocalisé en continu n’est effectué pendant ou en dehors de vos gardes.</li>
            <li>• Finalité : suivi des heures travaillées, gardes de nuit, dimanches et jours fériés.</li>
            <li>• Destinataires : le responsable du maintien à domicile et la coordinatrice.</li>
          </ul>
        </div>
        <button type="button" class="pc-btn pc-btn--primary pc-btn--block" data-accept-gdpr-btn>
          <?= icon('check', '', 18) ?>
          <span>J’ai compris</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Feuille de scan du QR code du domicile (Caméra BarcodeDetector + jsQR fallback) -->
  <div class="pc-sheet-backdrop" id="qr-punch-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="qr-punch-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="qr-punch-title">Scanner le QR code du domicile</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close data-stop-qr-scanner aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <div class="pc-sheet__body pc-stack gap-3">
        <div class="pc-qr-scanner-box">
          <video id="pc-qr-video" class="pc-qr-video" playsinline muted></video>
          <canvas id="pc-qr-canvas" class="is-hidden"></canvas>
          <div class="pc-qr-scanner-status" id="pc-qr-status">Pointez la caméra vers l’affiche QR code de l’entrée.</div>
        </div>
        <form method="POST" action="/attendance/punch" id="pc-qr-manual-form" class="pc-stack gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="type" value="<?= $isClockedIn ? 'out' : 'in' ?>">
          <input type="hidden" name="method" value="qr">
          <label class="pc-label" for="qr-payload-input">Ou saisir le code de secours figurant sous le QR :</label>
          <input type="text" id="qr-payload-input" name="qr_payload" class="pc-input font-mono" placeholder="PRIMECARE-HOME:..." required>
          <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
            <?= icon('qr-code', '', 18) ?>
            <span>Valider le pointage par QR code</span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Feuille "J'ai oublié de pointer" (heure réelle déclarée + motif obligatoire -> pending) -->
  <div class="pc-sheet-backdrop" id="forgot-punch-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="forgot-punch-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="forgot-punch-title">Déclarer un oubli de pointage</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/attendance/declare" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/">

        <div>
          <label class="pc-label" for="declared-type">Type de pointage oublié</label>
          <select id="declared-type" name="type" class="pc-input">
            <option value="in">Arrivée (prise de poste)</option>
            <option value="out">Départ (fin de poste)</option>
          </select>
        </div>

        <div class="pc-module-grid pc-module-grid--2col">
          <div>
            <label class="pc-label" for="declared-date">Date réelle</label>
            <input type="date" id="declared-date" name="declared_date" class="pc-input" value="<?= e($todayYmd) ?>" required>
          </div>
          <div>
            <label class="pc-label" for="declared-time">Heure réelle</label>
            <input type="time" id="declared-time" name="declared_time" class="pc-input" value="<?= e($nowLocalInputHm) ?>" required>
          </div>
        </div>

        <div>
          <label class="pc-label" for="declared-reason">Motif de l’oubli (obligatoire)</label>
          <textarea id="declared-reason" name="reason" class="pc-input" rows="2" placeholder="Ex. : Soin urgent dès mon arrivée auprès de M. Benali..." required></textarea>
        </div>

        <p class="text-caption text-muted">
          Ce pointage sera enregistré avec le statut « À valider » et notifié au responsable.
        </p>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('check', '', 18) ?>
          <span>Envoyer pour validation</span>
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($isAdmin || $isCoordinator || $isViewer): ?>
  <?php
  $attLevel = (string) ($attention['status_level'] ?? (($attention['count'] ?? 0) > 0 ? 'warn' : 'ok'));
  $attIcon = (string) ($attention['status_icon'] ?? ($attLevel === 'ok' ? 'check-circle' : ($attLevel === 'critical' ? 'shield-alert' : 'alert-triangle')));
  $attHeadline = (string) ($attention['headline'] ?? 'Tout va bien aujourd’hui');
  ?>
  <!-- Phrase de synthèse en tête de l'accueil admin (Additif Design B §8 / E3) -->
  <section class="pc-admin-headline pc-admin-headline--<?= e($attLevel) ?>" aria-label="État général du jour">
    <span class="pc-admin-headline__icon">
      <?= icon($attIcon, '', 20) ?>
    </span>
    <div class="pc-admin-headline__body">
      <div class="pc-admin-headline__title"><?= e($attHeadline) ?></div>
      <div class="pc-admin-headline__sub">
        <?= ($attention['count'] ?? 0) === 0
            ? 'Constantes et prises conformes au programme du jour'
            : 'Vérifiez les éléments signalés ci-dessous' ?>
      </div>
    </div>
    <a href="/alerts" class="pc-btn pc-btn--tinted pc-btn--sm">Alertes</a>
  </section>
<?php endif; ?>

<!-- 2. Bandeau de transmission de fin de poste non lue (E13 / §8.2 — Aide-soignante) -->
<?php if ($isCaregiver && $unreadHandover !== null): ?>
  <?php
  $hSummary = is_string($unreadHandover['handover_summary'] ?? null)
      ? (json_decode((string) $unreadHandover['handover_summary'], true) ?: [])
      : [];
  $hTime = ScheduleService::utcToLocalParis((string) $unreadHandover['recorded_at'], 'd/m à H:i');
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

  <!-- Points d'attention en temps réel (unifiés sur la table `alerts`) -->
  <?php if (($attention['count'] ?? 0) > 0): ?>
    <section aria-labelledby="home-attention-heading">
      <div class="flex items-center justify-between mb-2">
        <div class="pc-section-header mb-0" id="home-attention-heading">
          Points d’attention &amp; Alertes ouvertes (<?= (int) $attention['count'] ?>)
        </div>
        <a href="/alerts" class="text-footnote font-semibold text-brand">Centre d’alertes &rsaquo;</a>
      </div>
      <div class="pc-stack gap-2">
        <?php foreach (array_slice($attention['items'], 0, 2) as $att): ?>
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

  <!-- Section « Tendances 7 jours » sous les points d'attention (Phase 4 §2.3) -->
  <?php if ($trends7d !== []): ?>
    <?php
    $tGly = $trends7d['avg_glycemia'] ?? [];
    $tTir = $trends7d['time_in_target'] ?? [];
    $tObs = $trends7d['observance'] ?? [];
    $tHypo = $trends7d['hypo_count'] ?? [];
    $arrowChar = static fn(string $dir): string => '';
    ?>
    <section class="pc-trends-7d" aria-labelledby="home-trends-heading">
      <div class="flex items-center justify-between mb-2">
        <h2 class="pc-section-header mb-0" id="home-trends-heading">Tendances 7 jours</h2>
        <a href="/reports?period=30d" class="text-footnote font-semibold text-brand">Rapport complet &rsaquo;</a>
      </div>

      <div class="pc-module-grid pc-module-grid--2col">
        <!-- 1. Glycémie moyenne -->
        <div class="pc-card pc-trend-card">
          <div class="flex items-center justify-between">
            <span class="text-caption font-semibold text-vitals">Glycémie moyenne</span>
            <span class="pc-badge pc-badge--<?= e((string) ($tGly['trend_tone'] ?? 'ok')) ?> font-mono">
              <?= e($arrowChar((string) ($tGly['direction'] ?? 'flat'))) ?>
              <?= e((string) ($tGly['diff_formatted'] ?? '0,00')) ?>
            </span>
          </div>
          <div class="pc-metric-lg mt-1">
            <span
              data-count-up="<?= e((string) ($tGly['value'] ?? 0)) ?>"
              data-count-decimals="2"
            ><?= e((string) ($tGly['formatted'] ?? '0,00')) ?></span>
            <span class="pc-metric-unit">g/L</span>
          </div>
          <div class="text-caption text-muted">vs 7 jours précédents</div>
        </div>

        <!-- 2. Temps dans la cible -->
        <div class="pc-card pc-trend-card">
          <div class="flex items-center justify-between">
            <span class="text-caption font-semibold text-ok">Temps dans la cible</span>
            <span class="pc-badge pc-badge--<?= e((string) ($tTir['trend_tone'] ?? 'ok')) ?> font-mono">
              <?= e($arrowChar((string) ($tTir['direction'] ?? 'flat'))) ?>
              <?= e((string) ($tTir['diff_formatted'] ?? '0,0 pts')) ?>
            </span>
          </div>
          <div class="pc-metric-lg mt-1">
            <span
              data-count-up="<?= e((string) ($tTir['value'] ?? 0)) ?>"
              data-count-decimals="1"
            ><?= e((string) ($tTir['formatted'] ?? '0,0')) ?></span>
            <span class="pc-metric-unit">%</span>
          </div>
          <div class="text-caption text-muted">mesures dans la cible</div>
        </div>

        <!-- 3. Observance -->
        <div class="pc-card pc-trend-card">
          <div class="flex items-center justify-between">
            <span class="text-caption font-semibold text-meds">Observance</span>
            <span class="pc-badge pc-badge--<?= e((string) ($tObs['trend_tone'] ?? 'ok')) ?> font-mono">
              <?= e($arrowChar((string) ($tObs['direction'] ?? 'flat'))) ?>
              <?= e((string) ($tObs['diff_formatted'] ?? '0,0 pts')) ?>
            </span>
          </div>
          <div class="pc-metric-lg mt-1">
            <span
              data-count-up="<?= e((string) ($tObs['value'] ?? 100)) ?>"
              data-count-decimals="1"
            ><?= e((string) ($tObs['formatted'] ?? '100,0')) ?></span>
            <span class="pc-metric-unit">%</span>
          </div>
          <div class="text-caption text-muted">prises administrées</div>
        </div>

        <!-- 4. Hypoglycémies -->
        <div class="pc-card pc-trend-card">
          <div class="flex items-center justify-between">
            <span class="text-caption font-semibold text-danger">Hypoglycémies</span>
            <span class="pc-badge pc-badge--<?= e((string) ($tHypo['trend_tone'] ?? 'ok')) ?> font-mono">
              <?= e($arrowChar((string) ($tHypo['direction'] ?? 'flat'))) ?>
              <?= e((string) ($tHypo['diff_formatted'] ?? '0 vs J-7')) ?>
            </span>
          </div>
          <div class="pc-metric-lg mt-1">
            <span
              data-count-up="<?= (int) ($tHypo['value'] ?? 0) ?>"
              data-count-decimals="0"
            ><?= e((string) ($tHypo['formatted'] ?? '0')) ?></span>
            <span class="pc-metric-unit">&lt; 0,70 g/L</span>
          </div>
          <div class="text-caption text-muted">sur les 7 derniers jours</div>
        </div>
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

      <!-- Planning & Pointages (Accès rapide Responsable) -->
      <a href="/planning" class="pc-card flex flex-col justify-between gap-2">
        <div class="flex items-center justify-between">
          <span class="pc-health-card__header text-brand">
            <?= icon('calendar-check', '', 17) ?>
            <span>Planning &amp; Heures</span>
          </span>
          <span class="pc-badge pc-badge--brand">Équipe</span>
        </div>
        <div>
          <div class="pc-metric-lg">
            24h/24
            <span class="pc-metric-unit">garde continue</span>
          </div>
          <div class="text-caption text-muted">Planning, pointages &amp; heures &rsaquo;</div>
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
    <a href="/today" class="text-footnote font-semibold text-brand">Tout voir &rsaquo;</a>
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
    <a href="/planning" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--team">
        <?= icon('calendar-check', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title"><?= $isCaregiver ? 'Mon planning de gardes' : 'Planning d’équipe &amp; Gardes' ?></div>
        <div class="pc-list-subtitle">Créneaux de jour et de nuit, remplacements et absences</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <?php if (!$isViewer): ?>
      <a href="/attendance" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--ok">
          <?= icon('clock', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title"><?= ($isAdmin || $isCoordinator) ? 'Contrôle des pointages &amp; Heures' : 'Mon pointage &amp; Mes heures' ?></div>
          <div class="pc-list-subtitle">Suivi hebdomadaire, récapitulatif mensuel et export CSV paie</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    <?php endif; ?>

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
  </div>
</section>
