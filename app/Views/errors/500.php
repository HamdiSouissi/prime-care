<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $description */
/** @var Throwable|null $exception */
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon" style="background: var(--danger-soft); color: var(--danger);">
    <?= icon('alert-triangle', '', 30) ?>
  </div>
  <span class="pc-badge pc-badge--danger mb-2">Erreur 500</span>
  <h1 class="text-title-2 mb-2"><?= e($title ?? 'Oups, une erreur est survenue') ?></h1>
  <p class="text-subhead text-muted mb-6"><?= e($description ?? 'Le service a rencontré un problème inattendu.') ?></p>
  <?php if (isset($exception) && $exception instanceof Throwable) : ?>
    <div class="pc-card w-full text-left mb-4" style="background: var(--surface-2); font-size: 13px; overflow-x: auto;">
      <strong><?= e($exception::class) ?> :</strong> <?= e($exception->getMessage()) ?>
      <div class="text-muted mt-1"><?= e($exception->getFile()) ?>:<?= e((string) $exception->getLine()) ?></div>
    </div>
  <?php endif; ?>
  <a href="/" class="pc-btn pc-btn--primary pc-btn--block">Reprendre à l’accueil</a>
</div>
