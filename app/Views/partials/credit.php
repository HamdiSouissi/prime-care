<?php

declare(strict_types=1);

/** @var bool $onGradient */
$onGradient = $onGradient ?? false;
?>
<div class="pc-credit <?= $onGradient ? 'pc-credit--on-gradient' : '' ?>">
  <a href="https://primevisionsolutions.fr" target="_blank" rel="noopener" class="pc-credit__link">
    <span><?= e(t('app.credit_prefix')) ?> <strong class="pc-credit__publisher"><?= e(t('app.credit_publisher')) ?></strong></span>
    <?= icon('external-link', '', 12) ?>
  </a>
</div>
