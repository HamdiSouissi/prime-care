<?php

declare(strict_types=1);

use App\Core\Session;

/**
 * Animated Prime Care Launch Loader (§6.8 + Additif B §9.5 — Proposition B Bleu nuit)
 * Fond Bleu nuit #0A1633 avec halos, monogramme PC animé, logotype PRIME CARE,
 * barre de progression en dégradé #5E5CE6 → #BF5AF2 et signature « by Prime Vision Solutions ».
 */
$forceShow = Session::getFlash('show_launch_loader', false) ? '1' : '0';
?>
<div
  id="pc-launch-loader"
  class="pc-loader"
  data-force-loader="<?= e($forceShow) ?>"
  role="status"
  aria-live="polite"
  aria-label="Chargement de Prime Care"
>
  <svg
    class="pc-loader__monogram"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 280 280"
    aria-hidden="true"
  >
    <defs>
      <linearGradient id="pcLoaderGradient" x1="0" y1="1" x2="1" y2="0">
        <stop offset="0%" stop-color="#5E5CE6"/>
        <stop offset="100%" stop-color="#BF5AF2"/>
      </linearGradient>
    </defs>
    <g transform="translate(12 7) scale(1.02)" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <path
        class="pc-loader__stroke-p"
        d="M58 218 V76 C58 53 75 36 98 36 H159 C196 36 217 57 217 87 C217 117 196 137 165 137 H133"
        stroke="#FFFFFF"
        stroke-width="24"
      />
      <path
        class="pc-loader__stroke-c"
        d="M177 105 C163 97 148 93 132 93 C91 93 62 123 62 163 C62 202 91 231 130 231 C154 231 174 221 188 204"
        stroke="url(#pcLoaderGradient)"
        stroke-width="24"
      />
    </g>
  </svg>

  <div class="pc-loader__wordmark" aria-hidden="true">PRIME CARE</div>

  <p class="pc-loader__slogan"><?= e(t('app.tagline')) ?></p>

  <div class="pc-loader__progress" aria-hidden="true">
    <div class="pc-loader__progress-bar"></div>
  </div>

  <p class="pc-loader__byline">by Prime Vision Solutions</p>
</div>
