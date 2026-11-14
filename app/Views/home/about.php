<?php

declare(strict_types=1);

use App\Core\Config;

$version = (string) Config::get('app.version', '1.0.0-phase1');
?>
<section class="pc-card flex flex-col items-center text-center py-6" style="padding: 32px 20px;">
  <div class="mb-3" style="color: var(--brand-ink);">
    <?= brand_svg('icone-transparent', '', 64) ?>
  </div>

  <div style="color: var(--brand-ink);">
    <?= brand_svg('logo-complet', '', 220) ?>
  </div>

  <p class="text-caption text-muted mt-2" style="letter-spacing: 0.08em;">
    <?= e(t('app.by_publisher')) ?>
  </p>

  <span class="pc-badge pc-badge--brand mt-3">Version <?= e($version) ?></span>

  <p class="text-subhead text-muted mt-4" style="max-width: 340px;">
    Application mobile de coordination et de suivi de soins à domicile 24h/24 pour patient âgé diabétique.
  </p>

  <div class="mt-5 w-full">
    <a href="https://primevisionsolutions.fr" target="_blank" rel="noopener" class="pc-btn pc-btn--tinted pc-btn--block">
      <span>Visiter Prime Vision Solutions</span>
      <?= icon('external-link', '', 18) ?>
    </a>
  </div>
</section>

<div>
  <div class="pc-section-header">Sécurité & Confidentialité</div>
  <div class="pc-list-group">
    <div class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--ok);">
        <?= icon('shield', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Zéro ressource externe (No-CDN)</div>
        <div class="pc-list-subtitle">Polices, icônes et scripts 100 % auto-hébergés pour protéger les données de santé.</div>
      </div>
    </div>

    <div class="pc-list-item">
      <span class="pc-list-icon" style="background: var(--brand);">
        <?= icon('lock', '', 18) ?>
      </span>
      <div class="pc-list-body">
        <div class="pc-list-title">Traçabilité médicale intégrale</div>
        <div class="pc-list-subtitle">Journal d’audit horodaté UTC et conservation légale sans suppression physique.</div>
      </div>
    </div>
  </div>
</div>
