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
      <span class="pc-badge pc-badge--ok mb-1">RGPD &amp; Données de santé</span>
      <h1 class="text-title2 font-bold">Politique de confidentialité</h1>
      <p class="text-caption text-muted">Protection des données personnelles conformément au Règlement (UE) 2016/679 (RGPD) et à la loi Informatique et Libertés.</p>
    </div>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">1. Responsable de traitement &amp; DPO</h2>
      <ul class="text-footnote pc-stack gap-1">
        <li><strong>Responsable de traitement :</strong> <code class="font-mono">[À COMPLÉTER — Nom du particulier employeur ou de la structure]</code></li>
        <li><strong>Contact RGPD / Délégué à la protection des données (DPO) :</strong> <code class="font-mono">[À COMPLÉTER — Adresse e-mail / Adresse postale]</code></li>
      </ul>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">2. Finalités et bases légales</h2>
      <ul class="text-footnote pc-stack gap-1">
        <li><strong>Suivi médical quotidien à domicile</strong> (constantes, glycémie, prises de médicaments, repas, transmissions, documents médicaux) : <em>Consentement explicite du patient ou de son représentant légal</em> (Art. 6.1.a et Art. 9.2.a / 9.2.h du RGPD).</li>
        <li><strong>Gestion du planning et pointage des intervenants à domicile</strong> : <em>Exécution du contrat de travail et respect des obligations légales de décompte du temps de travail</em> (Art. 6.1.b et 6.1.c du RGPD).</li>
        <li><strong>Sécurité et traçabilité médico-légale (journal d’audit)</strong> : <em>Intérêt légitime et obligation de sécurité des données de santé</em> (Art. 6.1.f et Art. 32 du RGPD).</li>
      </ul>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">3. Durées de conservation</h2>
      <ul class="text-footnote pc-stack gap-1">
        <li><strong>Données médicales du patient :</strong> conservées pendant toute la durée de la prise en charge (jamais purgées automatiquement sans action explicite de l’administrateur).</li>
        <li><strong>Pointages et relevés d’heures :</strong> 5 ans (prescription légale en matière de salaire, Art. L.3245-1 du Code du travail).</li>
        <li><strong>Journaux techniques et tentatives de connexion :</strong> 12 mois.</li>
        <li><strong>Alertes traitées :</strong> 24 mois.</li>
        <li><strong>Journal d’audit médico-légal :</strong> 6 ans.</li>
      </ul>
      <p class="text-caption text-muted">
        À l’expiration de ces délais, la tâche automatisée procède à une suppression logique puis à une suppression physique définitive après 30 jours.
      </p>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">4. Destinataires &amp; Absence de transfert tiers</h2>
      <p class="text-footnote">
        Les données sont exclusivement accessibles aux membres habilités de l’équipe de soins et de la famille selon leur rôle (Responsable, Coordinateur, Aide-soignante, Lecteur médical). <strong>Aucun traceur publicitaire, aucun service d’analyse tiers et aucun CDN externe</strong> ne sont utilisés.
      </p>
    </section>

    <section class="pc-stack gap-1">
      <h2 class="text-headline">5. Vos droits (Accès, rectification, portabilité, effacement)</h2>
      <p class="text-footnote">
        Chaque utilisateur dispose d’un bouton <strong>« Exporter mes données »</strong> directement dans son profil pour récupérer ses données personnelles au format JSON structuré. L’administrateur dispose également de l’export intégral du dossier patient. Pour toute demande d’exercice de droits ou réclamation auprès de la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener" class="text-brand">www.cnil.fr</a>), contactez : <code class="font-mono">[À COMPLÉTER]</code>.
      </p>
    </section>

    <div class="pt-2 border-t flex gap-3 flex-wrap text-caption">
      <a href="/legal/mentions" class="text-brand font-semibold">Mentions légales</a>
      <a href="/legal/attendance-notice" class="text-brand font-semibold">Notice pointage &amp; géolocalisation</a>
    </div>
  </article>
</div>
