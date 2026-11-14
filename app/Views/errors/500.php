<?php

declare(strict_types=1);
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon pc-empty-state__icon--danger">
    <?= icon('alert-triangle', 32) ?>
  </div>
  <div class="text-caption text-muted mb-2">Erreur 500</div>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Oups, une erreur est survenue') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Le service a rencontré un problème inattendu.') ?></p>
  <?php if (!empty($exception) && $exception instanceof Throwable): ?>
    <div class="pc-card pc-card--subtle w-full text-left mb-4">
      <strong><?= e($exception::class) ?>:</strong> <?= e($exception->getMessage()) ?><br>
      <span class="text-muted"><?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?></span>
    </div>
  <?php endif; ?>
  <a href="/" class="pc-btn pc-btn--primary">Retourner à l’accueil</a>
</div>
