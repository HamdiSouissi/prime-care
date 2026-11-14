<?php

declare(strict_types=1);
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon">
    <?= icon('search', 32) ?>
  </div>
  <div class="text-caption text-muted mb-2">Erreur 404</div>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Page introuvable') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'L’écran demandé est introuvable.') ?></p>
  <a href="/" class="pc-btn pc-btn--primary">Retourner à l’accueil</a>
</div>
