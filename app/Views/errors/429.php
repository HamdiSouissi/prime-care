<?php

declare(strict_types=1);
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon pc-empty-state__icon--warn">
    <?= icon('clock', 32) ?>
  </div>
  <div class="text-caption text-muted mb-2">Erreur 429</div>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Trop de tentatives') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Veuillez patienter quelques minutes avant de réessayer.') ?></p>
  <a href="/login" class="pc-btn pc-btn--primary">Retourner à la connexion</a>
</div>
