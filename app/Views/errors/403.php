<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $description */
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon" style="background: var(--danger-soft); color: var(--danger);">
    <?= icon('shield-alert', '', 30) ?>
  </div>
  <span class="pc-badge pc-badge--danger mb-2">Erreur 403</span>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Accès non autorisé') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Votre profil ne dispose pas des autorisations nécessaires.') ?></p>
  <a href="/" class="pc-btn pc-btn--primary pc-btn--block">Retourner à l’accueil</a>
</div>
