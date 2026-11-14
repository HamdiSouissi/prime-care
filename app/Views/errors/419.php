<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $description */
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon" style="background: var(--warn-soft); color: var(--warn-ink);">
    <?= icon('refresh-cw', '', 30) ?>
  </div>
  <span class="pc-badge pc-badge--warn mb-2">Erreur 419</span>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Session de sécurité expirée') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Le formulaire a expiré par mesure de sécurité. Veuillez actualiser l’écran.') ?></p>
  <a href="/" class="pc-btn pc-btn--primary pc-btn--block">Actualiser et reprendre</a>
</div>
