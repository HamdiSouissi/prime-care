<?php

declare(strict_types=1);

$moduleMeta = is_array($moduleMeta ?? null) ? $moduleMeta : [];
$iconClass = (string) ($moduleMeta['icon_class'] ?? 'pc-empty-state__icon--vitals');
?>
<div class="pc-empty-state">
  <div class="pc-empty-state__icon <?= e($iconClass) ?>">
    <?= icon((string) ($moduleMeta['icon'] ?? 'sparkles'), 32) ?>
  </div>
  <span class="pc-badge pc-badge--brand mb-2"><?= e((string) ($moduleMeta['phase'] ?? 'Phase 2')) ?></span>
  <h2 class="text-title-2 mb-2"><?= e((string) ($moduleMeta['title'] ?? 'Bientôt disponible')) ?></h2>
  <p class="text-subhead text-muted mb-6 max-w-prose">
    <?= e((string) ($moduleMeta['description'] ?? 'Ce module sera activé lors de la prochaine phase du projet Prime Care.')) ?>
  </p>
  <a href="/" class="pc-btn pc-btn--primary">
    <span>Retour à l’accueil</span>
  </a>
</div>
