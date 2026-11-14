<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $description */
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon" style="background: var(--brand-soft); color: var(--brand);">
    <?= icon('search', '', 30) ?>
  </div>
  <span class="pc-badge pc-badge--brand mb-2">Erreur 404</span>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Page introuvable') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'L’écran demandé est introuvable ou a été déplacé.') ?></p>
  <a href="/" class="pc-btn pc-btn--primary pc-btn--block">Retourner à l’accueil</a>
</div>
