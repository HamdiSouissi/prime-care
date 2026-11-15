<?php

declare(strict_types=1);

$isLoggedIn = (bool) ($isLoggedIn ?? false);
?>
<div class="<?= $isLoggedIn ? 'pc-page pc-animate-in' : 'pc-auth-card' ?>">
  <?php if (!$isLoggedIn): ?>
    <div class="mb-3">
      <a href="/login" class="pc-btn pc-btn--ghost pc-btn--sm">&larr; Retour à la connexion</a>
    </div>
  <?php endif; ?>

  <article class="pc-card pc-stack gap-3">
    <div>
      <span class="pc-badge pc-badge--brand mb-1">Informations légales</span>
      <h1 class="text-title2 font-bold">Mentions légales</h1>
      <p class="text-caption text-muted">Conformément à l’article 6 de la loi n° 2004-575 du 21 juin 2004 pour la confiance dans l’économie numérique (LCEN).</p>
    </div>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">1. Éditeur et responsable de l’instance</h2>
      <ul class="text-footnote pc-stack gap-1">
        <li><strong>Conception logicielle :</strong> Prime Vision Solutions (<a href="https://primevisionsolutions.fr" target="_blank" rel="noopener" class="text-brand">primevisionsolutions.fr</a>)</li>
        <li><strong>Responsable de publication / Déployeur :</strong> <code class="font-mono">[À COMPLÉTER — Nom ou Raison sociale]</code></li>
        <li><strong>Forme juridique &amp; capital :</strong> <code class="font-mono">[À COMPLÉTER]</code></li>
        <li><strong>Numéro SIRET / RCS :</strong> <code class="font-mono">[À COMPLÉTER]</code></li>
        <li><strong>Adresse postale :</strong> <code class="font-mono">[À COMPLÉTER]</code></li>
        <li><strong>Contact :</strong> <code class="font-mono">[À COMPLÉTER — Adresse e-mail / Téléphone]</code></li>
      </ul>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">2. Hébergement (Données de santé — HDS)</h2>
      <p class="text-footnote text-muted">
        Toute mise en production traitant des données de santé à caractère personnel doit être hébergée auprès d’un hébergeur certifié <strong>HDS (Hébergeur de Données de Santé)</strong> conformément à l’article L.1111-8 du Code de la santé publique, ou dans un cadre strictement domestique privé.
      </p>
      <ul class="text-footnote pc-stack gap-1">
        <li><strong>Hébergeur :</strong> <code class="font-mono">[À COMPLÉTER — Raison sociale de l’hébergeur certifié HDS]</code></li>
        <li><strong>Certification HDS :</strong> <code class="font-mono">[À COMPLÉTER — Référence du certificat HDS]</code></li>
        <li><strong>Adresse de l’hébergeur :</strong> <code class="font-mono">[À COMPLÉTER]</code></li>
        <li><strong>Téléphone de l’hébergeur :</strong> <code class="font-mono">[À COMPLÉTER]</code></li>
      </ul>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">3. Nature de l’outil</h2>
      <p class="text-footnote">
        <strong>Prime Care</strong> est un carnet de coordination et de suivi de soins à domicile. Il ne constitue pas un dispositif médical de diagnostic ou de prescription automatique et <strong>ne remplace en aucun cas un avis médical</strong>. En cas d’urgence vitale, appelez immédiatement le <strong>15 (SAMU)</strong> ou le <strong>112</strong>.
      </p>
    </section>

    <div class="pt-2 border-t flex gap-3 flex-wrap text-caption">
      <a href="/legal/privacy" class="text-brand font-semibold">Politique de confidentialité</a>
      <a href="/legal/attendance-notice" class="text-brand font-semibold">Notice pointage &amp; géolocalisation</a>
    </div>
  </article>
</div>
