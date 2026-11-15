<?php

declare(strict_types=1);

$user = is_array($user ?? null) ? $user : (auth() ?? []);
$theme = (string) ($user['theme'] ?? 'auto');
$role = (string) ($user['role'] ?? 'caregiver');
$avatarClass = avatar_color_class((int) ($user['id'] ?? 1));
?>
<!-- En-tête utilisateur -->
<a href="/profile" class="pc-card flex items-center gap-4">
  <div class="pc-avatar pc-avatar--lg <?= e($avatarClass) ?>">
    <?php if (!empty($user['avatar_path'])): ?>
      <img src="/media/<?= e((string) $user['avatar_path']) ?>" alt="<?= e((string) $user['first_name']) ?>">
    <?php else: ?>
      <?= e(initials((string) ($user['first_name'] ?? 'P'), (string) ($user['last_name'] ?? 'C'))) ?>
    <?php endif; ?>
  </div>
  <div class="flex-1 min-w-0">
    <div class="text-title-2"><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></div>
    <div class="text-subhead text-muted truncate"><?= e((string) ($user['email'] ?? '')) ?></div>
    <div class="mt-1">
      <span class="pc-badge pc-badge--brand"><?= e(t('roles.' . $role)) ?></span>
    </div>
  </div>
  <span class="pc-list-chevron"><?= icon('chevron-right', '', 20) ?></span>
</a>

<!-- Modules médicaux Phase 2 -->
<section aria-labelledby="more-medical-heading">
  <div class="pc-section-header" id="more-medical-heading">Suivi médical quotidien</div>
  <div class="pc-list-group">
    <a href="/today" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--brand">
        <?= icon('calendar-days', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Programme d’aujourd’hui</div>
        <div class="pc-list-subtitle">Prises, mesures attendues et repas par moment</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/health" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('heart-pulse', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Santé &amp; Constantes vitales</div>
        <div class="pc-list-subtitle">Glycémie, tension, SpO2, poids, température &amp; courbes</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/treatments" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meds">
        <?= icon('pill', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Traitements &amp; Grille d’insuline</div>
        <div class="pc-list-subtitle">Prises du jour, rotation des sites et « Si besoin »</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/treatments/history" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meds">
        <?= icon('activity', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Historique des prises &amp; Observance</div>
        <div class="pc-list-subtitle">Taux d’observance 7j / 30j et traçabilité complète</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/meals" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--meals">
        <?= icon('utensils', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Repas &amp; Hydratation</div>
        <div class="pc-list-subtitle">Photos Avant/Après, portions et glycémies liées</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/journal" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--notes">
        <?= icon('notebook-pen', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Journal de bord &amp; Transmissions</div>
        <div class="pc-list-subtitle">Fil chronologique unifié, soins rapides et relève</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>
  </div>
</section>

<!-- Dossier & Compte -->
<section aria-labelledby="more-patient-heading">
  <div class="pc-section-header" id="more-patient-heading">Patient &amp; Compte</div>
  <div class="pc-list-group">
    <a href="/patient" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('user-check', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Fiche patient &amp; Contacts d’urgence</div>
        <div class="pc-list-subtitle">M. Henri Benali · Protocole d’hypoglycémie</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/profile" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--brand">
        <?= icon('user', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Mon profil &amp; Sécurité</div>
        <div class="pc-list-subtitle">Photo, code PIN sur cet appareil, mot de passe, thème</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/coming-soon/documents" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--docs">
        <?= icon('file-text', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Coffre-fort de documents médicaux</div>
        <div class="pc-list-subtitle">Disponible en Phase 4</div>
      </div>
      <span class="pc-badge pc-badge--brand">Phase 4</span>
    </a>
  </div>
</section>

<!-- Administration (Admin uniquement) -->
<?php if (can('users', 'view')): ?>
  <section aria-labelledby="more-admin-heading">
    <div class="pc-section-header" id="more-admin-heading">Administration</div>
    <div class="pc-list-group">
      <a href="/admin/treatments" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--meds">
          <?= icon('clipboard-list', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Plan de traitement &amp; Ordonnances</div>
          <div class="pc-list-subtitle">Médicaments, posologies, grille d’insuline du responsable</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/admin/measures" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--vitals">
          <?= icon('sliders', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Types de mesures &amp; Seuils d’alerte</div>
          <div class="pc-list-subtitle">Seuils critiques, mesures personnalisées et horaires attendus</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/settings" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--neutral">
          <?= icon('settings', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Réglages médicaux</div>
          <div class="pc-list-subtitle">Fenêtre de prise, unité de glycémie, soins rapides, favoris</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/users" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--team">
          <?= icon('users', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Gestion de l’équipe</div>
          <div class="pc-list-subtitle">Comptes, rôles, contrats, activation</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/design" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--notes">
          <?= icon('palette', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Design System Prime Care</div>
          <div class="pc-list-subtitle">Proposition B — Bleu nuit, composants &amp; loader</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    </div>
  </section>
<?php endif; ?>

<!-- Apparence & Application -->
<section aria-labelledby="more-app-heading">
  <div class="pc-section-header" id="more-app-heading">Apparence &amp; Application</div>
  <div class="pc-card mb-3">
    <div class="flex items-center justify-between mb-2">
      <span class="text-subhead font-semibold">Thème d’affichage</span>
      <span class="text-caption text-muted">Instantané</span>
    </div>
    <div class="pc-segmented" data-theme-segmented role="radiogroup" aria-label="Thème d’affichage">
      <button type="button" class="pc-segmented__btn <?= $theme === 'auto' ? 'is-active' : '' ?>" data-theme-value="auto">
        <?= icon('monitor', '', 16) ?>
        <span>Auto</span>
      </button>
      <button type="button" class="pc-segmented__btn <?= $theme === 'light' ? 'is-active' : '' ?>" data-theme-value="light">
        <?= icon('sun', '', 16) ?>
        <span>Clair</span>
      </button>
      <button type="button" class="pc-segmented__btn <?= $theme === 'dark' ? 'is-active' : '' ?>" data-theme-value="dark">
        <?= icon('moon', '', 16) ?>
        <span>Sombre</span>
      </button>
    </div>
  </div>

  <div class="pc-list-group">
    <a href="/install" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--ok">
        <?= icon('smartphone', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Installer l’application sur son téléphone</div>
        <div class="pc-list-subtitle">QR code local, étapes iOS / Android &amp; mode plein écran</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/about" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--info">
        <?= icon('info', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">À propos de Prime Care</div>
        <div class="pc-list-subtitle">Version <?= e((string) \App\Core\Config::get('app.version', '1.0.0')) ?> · Prime Vision Solutions</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>
  </div>
</section>

<!-- Déconnexion -->
<form method="POST" action="/logout" class="mt-2">
  <?= csrf_field() ?>
  <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
    <?= icon('log-out', '', 18) ?>
    <span>Se déconnecter</span>
  </button>
</form>
