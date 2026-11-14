<?php

declare(strict_types=1);

/** @var string $id */
$id = $id ?? 'pc-loader-mini';
/** @var bool $hidden */
$hidden = $hidden ?? false;
?>
<span id="<?= e($id) ?>" class="pc-loader-mini <?= $hidden ? 'hidden' : '' ?>" role="status" aria-label="Chargement en cours">
  <?= brand_svg('icone-transparent', '', 32) ?>
</span>
