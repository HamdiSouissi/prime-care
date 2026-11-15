<?php

declare(strict_types=1);

/** @var string $appUrl */
$appUrl = (string) ($appUrl ?? 'http://localhost:8080/?source=pwa');
/** @var string $qrSvg */
$qrSvg = (string) ($qrSvg ?? '');
?>
<section class="pc-card pc-card--hero-navy">
  <div class="flex items-center gap-3 mb-2">
    <span class="pc-home-hero__monogram">
      <?= brand_svg('icone-transparent', '', 34) ?>
    </span>
    <div>
      <h2 class="text-title-2 text-white">Prime Care en plein écran</h2>
      <p class="text-footnote text-white-70">Application Web Progressive (PWA) autonome · iOS &amp; Android</p>
    </div>
  </div>
  <p class="text-subhead text-white-80">
    Une fois installée sur l’écran d’accueil de votre téléphone, Prime Care s’ouvre comme une application native avec son splash screen Bleu nuit, sans barre d’adresse du navigateur.
  </p>
  <div class="mt-3 flex items-center gap-2">
    <span class="pc-badge pc-badge--hero" id="pwa-standalone-status">Mode : Navigateur web</span>
    <button type="button" class="pc-btn pc-btn--primary pc-btn--sm is-hidden" id="pwa-native-install-btn">
      <?= icon('download', '', 16) ?>
      <span>Installer maintenant</span>
    </button>
  </div>
</section>

<!-- QR Code généré côté serveur (zéro service tiers externe) -->
<section class="pc-card text-center" aria-labelledby="install-qr-heading">
  <h2 class="text-headline mb-1" id="install-qr-heading">Scanner depuis le téléphone de l’aide-soignante</h2>
  <p class="text-footnote text-muted mb-3">
    Scannez ce QR code généré localement pour ouvrir directement <code><?= e($appUrl) ?></code> sur mobile :
  </p>
  <div class="pc-qr-frame">
    <?= $qrSvg ?>
  </div>
  <p class="text-caption text-muted mt-2">
    Astuce : en réseau local ou via tunnel HTTPS (Cloudflare Tunnel / Tailscale / mkcert), l’appareil photo ouvre directement l’application.
  </p>
</section>

<!-- Guide illustré iPhone / iPad (Safari iOS) -->
<section class="pc-card" aria-labelledby="install-ios-heading">
  <div class="flex items-center gap-2 mb-3">
    <span class="pc-list-icon pc-list-icon--brand">
      <?= icon('smartphone', '', 18) ?>
    </span>
    <div>
      <h2 class="text-headline" id="install-ios-heading">Sur iPhone / iPad (Safari)</h2>
      <p class="text-caption text-muted">iOS 16.4+ · Plein écran avec splash screen Bleu nuit</p>
    </div>
  </div>

  <ol class="pc-install-steps">
    <li>
      <span class="pc-install-steps__num">1</span>
      <div>
        <strong>Ouvrir dans Safari</strong>
        <p class="text-footnote text-muted">Rendez-vous sur l’adresse de Prime Care depuis Safari sur votre iPhone.</p>
      </div>
    </li>
    <li>
      <span class="pc-install-steps__num">2</span>
      <div>
        <strong>Appuyer sur le bouton « Partager »</strong>
        <p class="text-footnote text-muted">Icône carrée avec une flèche vers le haut en bas de l’écran Safari.</p>
      </div>
    </li>
    <li>
      <span class="pc-install-steps__num">3</span>
      <div>
        <strong>Choisir « Sur l’écran d’accueil » puis « Ajouter »</strong>
        <p class="text-footnote text-muted">L’icône Prime Care apparaît sur votre écran d’accueil et lance l’application en mode autonome.</p>
      </div>
    </li>
  </ol>
</section>

<!-- Guide illustré Android (Chrome / Edge / Samsung Internet) -->
<section class="pc-card" aria-labelledby="install-android-heading">
  <div class="flex items-center gap-2 mb-3">
    <span class="pc-list-icon pc-list-icon--ok">
      <?= icon('check-circle', '', 18) ?>
    </span>
    <div>
      <h2 class="text-headline" id="install-android-heading">Sur Android (Chrome)</h2>
      <p class="text-caption text-muted">Installation WebAPK native avec raccourcis rapides</p>
    </div>
  </div>

  <ol class="pc-install-steps">
    <li>
      <span class="pc-install-steps__num">1</span>
      <div>
        <strong>Appuyer sur « Installer » dans la bannière</strong>
        <p class="text-footnote text-muted">Si votre navigateur affiche le bouton « Installer maintenant » en haut de cette page, appuyez dessus directement.</p>
      </div>
    </li>
    <li>
      <span class="pc-install-steps__num">2</span>
      <div>
        <strong>Ou ouvrir le menu ⋮ de Chrome (en haut à droite)</strong>
        <p class="text-footnote text-muted">Sélectionnez <strong>« Installer l’application »</strong> ou <strong>« Ajouter à l’écran d’accueil »</strong>.</p>
      </div>
    </li>
    <li>
      <span class="pc-install-steps__num">3</span>
      <div>
        <strong>Astuce raccourcis (appui long sur l’icône)</strong>
        <p class="text-footnote text-muted">Un appui long sur l’icône Prime Care propose les raccourcis directs : <em>Aujourd’hui</em>, <em>Saisir glycémie</em> et <em>Journal</em>.</p>
      </div>
    </li>
  </ol>
</section>
