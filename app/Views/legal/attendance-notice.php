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
      <span class="pc-badge pc-badge--info mb-1">Information des salariés &amp; intervenants</span>
      <h1 class="text-title2 font-bold">Notice d’information — Pointage &amp; Géolocalisation</h1>
      <p class="text-caption text-muted">Information individuelle préalable conformément au RGPD et aux recommandations de la CNIL.</p>
    </div>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">1. Principe : vérification ponctuelle uniquement, zéro suivi continu</h2>
      <p class="text-footnote">
        Lorsqu’elle est activée par le responsable dans les réglages, la vérification de présence s’effectue <strong>exclusivement à l’instant précis où l’intervenant appuie sur « Pointer mon arrivée » ou « Pointer mon départ »</strong> (par comparaison ponctuelle de distance avec le domicile ou par scan du QR code affiché au domicile).
      </p>
      <p class="text-footnote font-semibold">
        Prime Care n’effectue aucun suivi GPS en arrière-plan, aucun traçage continu des déplacements et aucune géolocalisation en dehors de l’instant du clic de pointage.
      </p>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">2. Absence de blocage et droit au pointage manuel</h2>
      <p class="text-footnote">
        Si la géolocalisation est refusée sur le téléphone, indisponible ou hors du rayon configuré, <strong>le pointage n’est jamais bloqué</strong> : l’intervenant peut toujours enregistrer son arrivée ou son départ (ou saisir un pointage manuel avec motif). Le pointage est alors soumis à la validation du responsable.
      </p>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">3. Données enregistrées et durée de conservation</h2>
      <ul class="text-footnote pc-stack gap-1">
        <li><strong>Données collectées lors du pointage :</strong> horodatage serveur d’arrivée et de départ, mode de vérification (GPS, QR code ou déclaratif), distance par rapport au domicile à l’instant du pointage et statut de validation.</li>
        <li><strong>Durée de conservation :</strong> 5 ans (durée légale de conservation des pièces justificatives du temps de travail), puis purge automatique.</li>
        <li><strong>Employeur / Responsable de traitement :</strong> <code class="font-mono">[À COMPLÉTER — Nom et coordonnées de l’employeur]</code></li>
      </ul>
    </section>

    <div class="pt-2 border-t flex gap-3 flex-wrap text-caption">
      <a href="/legal/mentions" class="text-brand font-semibold">Mentions légales</a>
      <a href="/legal/privacy" class="text-brand font-semibold">Politique de confidentialité</a>
    </div>
  </article>
</div>
