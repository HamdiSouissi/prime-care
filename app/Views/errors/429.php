<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $description */
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon" style="background: var(--warn-soft); color: var(--warn-ink);">
    <?= icon('clock', '', 30) ?>
  </div>
  <span class="pc-badge pc-badge--warn mb-2">Erreur 429</span>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Trop de tentatives') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Veuillez patienter quelques minutes avant de réessayer.') ?></p>
  <a href="/login" class="pc-btn pc-btn--primary pc-btn--block">Retourner à la connexion</a>
</div>
