<?php

declare(strict_types=1);

/** @var array<string, string> $moduleMeta */
?>
<section class="pc-empty-state">
  <div class="pc-empty-state__icon" style="background: <?= e($moduleMeta['bg'] ?? 'var(--brand-soft)') ?>; color: <?= e($moduleMeta['color'] ?? 'var(--brand)') ?>;">
    <?= icon($moduleMeta['icon'] ?? 'sparkles', '', 30) ?>
  </div>

  <span class="pc-badge pc-badge--brand mb-2"><?= e($moduleMeta['phase'] ?? 'Disponible bientôt') ?></span>
  <h2 class="text-title-2 mb-2"><?= e($moduleMeta['title'] ?? 'Module en préparation') ?></h2>
  <p class="text-subhead text-muted mb-6" style="max-width: 360px;">
    <?= e($moduleMeta['description'] ?? 'Cet écran sera activé dans la prochaine phase du projet.') ?>
  </p>

  <a href="/" class="pc-btn pc-btn--primary pc-btn--block">
    Retourner à l’accueil
  </a>
</section>
