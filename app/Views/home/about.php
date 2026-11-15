<?php

declare(strict_types=1);

$version = (string) \App\Core\Config::get('app.version', '1.0.0');
?>
<!-- Carte identité de marque (C6.3 : uniquement le logo complet, sans doublon monogramme) -->
<section class="pc-card flex flex-col items-center text-center">
  <div class="pc-about-logo">
    <?= brand_svg('prime-care-logo-complet.svg') ?>
  </div>
  <p class="pc-about-tagline"><?= e(t('app.tagline')) ?></p>
  <span class="pc-badge pc-badge--brand mb-3">Version <?= e($version) ?></span>
  <p class="text-subhead text-muted">
    Application mobile de coordination et de suivi médical quotidien à domicile, conçue pour accompagner les aides-soignantes et les proches aidants en toute simplicité.
  </p>
</section>

<!-- Éditeur & Conception -->
<section aria-labelledby="about-publisher-heading">
  <div class="pc-section-header" id="about-publisher-heading">Éditeur & Conception</div>
  <div class="pc-list-group">
    <a href="https://primevisionsolutions.fr" target="_blank" rel="noopener" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--brand">
        <?= icon('sparkles', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Prime Vision Solutions</div>
        <div class="pc-list-subtitle">Conception logicielle, design & développement sur mesure</div>
        <div class="text-caption text-brand font-semibold mt-1 flex items-center gap-1">
          <span>primevisionsolutions.fr</span>
          <?= icon('external-link', 13) ?>
        </div>
      </div>
    </a>

    <div class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--ok">
        <?= icon('shield', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Confidentialité & Données de santé</div>
        <div class="pc-list-subtitle">Aucun traceur publicitaire, aucun CDN tiers, chiffrement des mots de passe Argon2id et journal d’audit intégral.</div>
      </div>
    </div>
  </div>
</section>

<!-- Informations légales & RGPD (Phase 4 §5.3) -->
<section aria-labelledby="about-legal-heading" class="mt-3">
  <div class="pc-section-header" id="about-legal-heading">Informations légales &amp; RGPD</div>
  <div class="pc-list-group">
    <a href="/legal/mentions" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--docs"><?= icon('file-text', 18) ?></span>
      <div class="pc-list-body">
        <div class="pc-list-title">Mentions légales</div>
        <div class="pc-list-subtitle">Éditeur, responsable de publication et hébergement HDS</div>
      </div>
      <?= icon('chevron-right', 16) ?>
    </a>

    <a href="/legal/privacy" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--ok"><?= icon('shield-check', 18) ?></span>
      <div class="pc-list-body">
        <div class="pc-list-title">Politique de confidentialité</div>
        <div class="pc-list-subtitle">Finalités, durées de conservation RGPD et exercice des droits</div>
      </div>
      <?= icon('chevron-right', 16) ?>
    </a>

    <a href="/legal/attendance-notice" class="pc-list-item">
      <span class="pc-list-icon pc-list-icon--team"><?= icon('map-pin', 18) ?></span>
      <div class="pc-list-body">
        <div class="pc-list-title">Notice pointage &amp; géolocalisation</div>
        <div class="pc-list-subtitle">Information des intervenants sur la vérification ponctuelle de présence</div>
      </div>
      <?= icon('chevron-right', 16) ?>
    </a>
  </div>
</section>
