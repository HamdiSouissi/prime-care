<?php

declare(strict_types=1);
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon pc-empty-state__icon--danger">
    <?= icon('shield-alert', 32) ?>
  </div>
  <div class="text-caption text-muted mb-2">Erreur 403</div>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Accès non autorisé') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Votre profil ne dispose pas des autorisations nécessaires.') ?></p>
  <a href="/" class="pc-btn pc-btn--primary">Retourner à l’accueil</a>
</div>
