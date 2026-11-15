<?php

declare(strict_types=1);

use App\Core\Session;

$toastSuccess = Session::flash('toast_success') ?? Session::flash('success');
$toastError = Session::flash('toast_error') ?? Session::flash('error');
$toastInfo = Session::flash('toast_info') ?? Session::flash('info');
?>
<div id="pc-toast-container" class="pc-toast-container" aria-live="polite" aria-atomic="true">
  <?php if (is_string($toastSuccess) && $toastSuccess !== '') : ?>
    <div class="pc-toast pc-toast--success">
      <svg class="pc-toast__check text-ok" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M20 6 9 17l-5-5"/>
      </svg>
      <span class="flex-1"><?= e($toastSuccess) ?></span>
    </div>
  <?php endif; ?>

  <?php if (is_string($toastError) && $toastError !== '') : ?>
    <div class="pc-toast pc-toast--error">
      <?= icon('circle-x', 'text-danger', 22) ?>
      <span class="flex-1"><?= e($toastError) ?></span>
    </div>
  <?php endif; ?>

  <?php if (is_string($toastInfo) && $toastInfo !== '') : ?>
    <div class="pc-toast pc-toast--info">
      <?= icon('info', 'text-brand', 22) ?>
      <span class="pc-1"><?= e($toastInfo) ?></span>
    </div>
  <?php endif; ?>
</div>
