<?php

declare(strict_types=1);
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon pc-empty-state__icon--warn">
    <?= icon('refresh-cw', 32) ?>
  </div>
  <div class="text-caption text-muted mb-2">Erreur 419</div>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Session de sécurité expirée') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Veuillez actualiser la page et réessayer.') ?></p>
  <a href="/login" class="pc-btn pc-btn--primary">Actualiser et reprendre</a>
</div>
