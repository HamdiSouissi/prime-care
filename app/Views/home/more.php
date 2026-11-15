<?php

declare(strict_types=1);

use App\Services\AlertService;

$user = is_array($user ?? null) ? $user : (auth() ?? []);
$theme = (string) ($user['theme'] ?? 'auto');
$role = (string) ($user['role'] ?? 'caregiver');
$avatarClass = avatar_color_class((int) ($user['id'] ?? 1));
$openAlertsCount = AlertService::countOpenAlerts(1);
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

<!-- Équipe, Pointage & Alertes (Phase 3) -->
<section aria-labelledby="more-team-heading">
  <div class="pc-section-header" id="more-team-heading">Équipe, pointage &amp; alertes</div>
  <div class="pc-list-group">
    <a href="/planning" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--team">
        <?= icon('calendar-check', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title"><?= $role === 'caregiver' ? 'Mon planning de gardes' : 'Planning d’équipe &amp; Couverture' ?></div>
        <div class="pc-list-subtitle">
          <?= $role === 'caregiver'
              ? 'Consultation de vos prochains créneaux de jour et de nuit'
              : 'Vue semaine/mois, duplication, remplacements et absences' ?>
        </div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <?php if ($role !== 'viewer'): ?>
      <a href="/attendance" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--ok">
          <?= icon('clock', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title"><?= in_array($role, ['admin', 'coordinator'], true) ? 'Contrôle des pointages (E17)' : 'Mon pointage &amp; Déclarations' ?></div>
          <div class="pc-list-subtitle">
            <?= in_array($role, ['admin', 'coordinator'], true)
                ? 'Grille hebdomadaire, retards et pointages en attente de validation'
                : 'Arrivée/départ, QR code du domicile et déclaration d’oubli' ?>
          </div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <a href="/attendance/hours" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--brand">
          <?= icon('file-spreadsheet', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Récapitulatif mensuel des heures (E18)</div>
          <div class="pc-list-subtitle">Heures normales, nuit (21h–6h), dimanche, jours fériés &amp; export CSV</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    <?php endif; ?>

    <a href="/alerts" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--vitals">
        <?= icon('bell', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Centre d’alertes &amp; Notifications</div>
        <div class="pc-list-subtitle">Alertes critiques, retards, escalades 15 min et historique</div>
      </div>
      <?php if ($openAlertsCount > 0): ?>
        <span class="pc-badge pc-badge--danger"><?= $openAlertsCount ?> ouverte<?= $openAlertsCount > 1 ? 's' : '' ?></span>
      <?php endif; ?>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>
  </div>
</section>

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
        <div class="pc-list-title">Mon profil, PIN &amp; Préférences d’alertes</div>
        <div class="pc-list-subtitle">Photo, code PIN, Web Push VAPID, heures calmes, thème</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <a href="/documents" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--docs">
        <?= icon('file-text', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Documents médicaux (E22)</div>
        <div class="pc-list-subtitle">Ordonnances, comptes rendus, analyses biologiques &amp; imagerie</div>
      </div>
      <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
    </a>

    <?php if (in_array($role, ['admin', 'coordinator', 'viewer'], true)): ?>
      <a href="/reports" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--vitals">
          <?= icon('printer', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Rapports &amp; Synthèse médicale (E23)</div>
          <div class="pc-list-subtitle">Rapport imprimable A4 pour consultation médicale (7j à 90j)</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    <?php endif; ?>

    <?php if (in_array($role, ['admin', 'viewer'], true)): ?>
      <a href="/reports/exports" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--ok">
          <?= icon('download', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Exports CSV &amp; Données</div>
          <div class="pc-list-subtitle">Téléchargement au format tableur (UTF-8 BOM, séparateur ;)</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>
    <?php endif; ?>
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
          <div class="pc-list-title">Réglages médicaux &amp; Pointage</div>
          <div class="pc-list-subtitle">Vérification GPS / QR code du domicile, soins rapides, favoris</div>
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

      <a href="/audit" class="pc-list-item">
        <span class="pc-list-icon pc-list-icon--info">
          <?= icon('shield-check', '', 18) ?>
        </span>
        <div class="pc-list-body">
          <div class="pc-list-title">Journal d’audit (E26)</div>
          <div class="pc-list-subtitle">Traçabilité médico-légale et différences avant / après</div>
        </div>
        <span class="pc-list-chevron"><?= icon('chevron-right', '', 18) ?></span>
      </a>

      <?php if ((string) \App\Core\Config::get('app.env', 'development') !== 'production'): ?>
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
      <?php endif; ?>
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
<form method="POST" action="/logout" class="mt-2" data-logout-form>
  <?= csrf_field() ?>
  <button type="submit" class="pc-btn pc-btn--danger-soft pc-btn--block">
    <?= icon('log-out', '', 18) ?>
    <span>Se déconnecter</span>
  </button>
</form>
