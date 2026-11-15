<?php

declare(strict_types=1);

$alerts = is_array($alerts ?? null) ? $alerts : [];
$statusFilter = (string) ($statusFilter ?? ($filter ?? 'open'));
$severityFilter = (string) ($severityFilter ?? 'all');
$openCount = isset($openCount) ? (int) $openCount : 0;
$canHandle = !empty($canHandle);
?>
<!-- Barre de filtres du Centre d'alertes (§3.8) -->
<section class="pc-card mb-3" aria-label="Filtres du centre d’alertes">
  <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
    <div class="pc-segmented" role="group" aria-label="Statut des alertes">
      <a
        href="/alerts?filter=open&amp;severity=<?= e($severityFilter) ?>"
        class="pc-segmented__btn <?= $statusFilter === 'open' ? 'is-active' : '' ?>"
      >
        <?= icon('bell', '', 15) ?>
        <span>Ouvertes (<?= $openCount ?>)</span>
      </a>
      <a
        href="/alerts?filter=handled&amp;severity=<?= e($severityFilter) ?>"
        class="pc-segmented__btn <?= $statusFilter === 'handled' ? 'is-active' : '' ?>"
      >
        <?= icon('check-circle', '', 15) ?>
        <span>Traitées</span>
      </a>
      <a
        href="/alerts?filter=all&amp;severity=<?= e($severityFilter) ?>"
        class="pc-segmented__btn <?= $statusFilter === 'all' ? 'is-active' : '' ?>"
      >
        <span>Toutes</span>
      </a>
      <a
        href="/alerts?tab=rules"
        class="pc-segmented__btn <?= $statusFilter === 'rules' ? 'is-active' : '' ?>"
      >
        <?= icon('sliders-horizontal', '', 15) ?>
        <span>7 Règles</span>
      </a>
    </div>

    <?php if ($statusFilter !== 'rules'): ?>
    <div class="flex items-center gap-2 flex-wrap">
      <a
        href="/alerts?filter=<?= e($statusFilter) ?>&amp;severity=all"
        class="pc-chip <?= $severityFilter === 'all' ? 'is-active' : '' ?>"
      >Toutes gravités</a>
      <a
        href="/alerts?filter=<?= e($statusFilter) ?>&amp;severity=critical"
        class="pc-chip <?= $severityFilter === 'critical' ? 'is-active' : '' ?>"
      >Critiques</a>
      <a
        href="/alerts?filter=<?= e($statusFilter) ?>&amp;severity=warning"
        class="pc-chip <?= $severityFilter === 'warning' ? 'is-active' : '' ?>"
      >Attention</a>
      <a
        href="/alerts?filter=<?= e($statusFilter) ?>&amp;severity=info"
        class="pc-chip <?= $severityFilter === 'info' ? 'is-active' : '' ?>"
      >Info</a>
    </div>
    <?php endif; ?>
  </div>

  <div class="flex items-center justify-between text-caption text-muted">
    <span>Triées par niveau de gravité puis par date (escalade automatique après 15 min pour les alertes critiques).</span>
    <a href="/profile#notifications-section" class="font-semibold text-brand">Mes préférences &rsaquo;</a>
  </div>
</section>

<?php if ($statusFilter === 'rules'): ?>
  <?php
  $rulesCatalog = [
      ['code' => 'measure_critical', 'label' => 'Constante critique (glycémie < 0,70 ou > 2,50 g/L, SpO₂ < 90 %, TA critique)', 'level' => 'critical', 'delay' => 'Immédiat + escalade 15 min', 'closure' => 'Nouvelle mesure normale enregistrée'],
      ['code' => 'measure_warn', 'label' => 'Constante hors cible (vigilance orange)', 'level' => 'warning', 'delay' => 'Immédiat', 'closure' => 'Mesure suivante dans la cible'],
      ['code' => 'intake_late', 'label' => 'Prise de médicament ou insuline en retard', 'level' => 'critical', 'delay' => '+30 min (Warning) / +60 min (Critique)', 'closure' => 'Prise validée (« Donné » ou « Non donné »)'],
      ['code' => 'measure_missing', 'label' => 'Mesure attendue non saisie', 'level' => 'warning', 'delay' => '+60 min après l’horaire prévu', 'closure' => 'Mesure du type attendu enregistrée'],
      ['code' => 'meal_missing', 'label' => 'Repas non enregistré sur la plage horaire', 'level' => 'warning', 'delay' => 'Plage dépassée (09h30, 14h00, 20h30)', 'closure' => 'Repas du moment enregistré'],
      ['code' => 'shift_unclocked', 'label' => 'Pointage d’arrivée absent sur une garde planifiée', 'level' => 'critical', 'delay' => '+15 min après le début de garde', 'closure' => 'Pointage d’arrivée enregistré'],
      ['code' => 'handover_unread', 'label' => 'Transmission de fin de poste non lue par la relève', 'level' => 'info', 'delay' => '+30 min après la prise de poste', 'closure' => 'Transmission marquée comme lue'],
  ];
  ?>
  <section class="pc-card mb-3" aria-labelledby="rules-heading">
    <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
      <div>
        <h2 class="text-headline" id="rules-heading">7 règles d’alerte &amp; fermeture automatique</h2>
        <p class="text-caption text-muted">Moteur de règles actif avec dédoublonnage strict par <code>dedupe_key</code> et escalade à 15 min.</p>
      </div>
      <span class="pc-badge pc-badge--ok">
        <?= icon('check-circle', '', 13) ?>
        <span>Moteur actif</span>
      </span>
    </div>

    <div class="pc-stack gap-2">
      <?php foreach ($rulesCatalog as $r): ?>
        <?php
        $badgeMod = $r['level'] === 'critical' ? 'pc-badge--danger' : ($r['level'] === 'warning' ? 'pc-badge--warn' : 'pc-badge--brand');
        $badgeLbl = $r['level'] === 'critical' ? 'Critique' : ($r['level'] === 'warning' ? 'Attention' : 'Info');
        ?>
        <article class="pc-notif-pref-row">
          <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <span class="pc-badge <?= e($badgeMod) ?>"><?= e($badgeLbl) ?></span>
              <strong class="text-subhead"><?= e($r['label']) ?></strong>
            </div>
            <div class="text-caption text-muted">
              Déclenchement : <strong><?= e($r['delay']) ?></strong> · Clôture auto : <?= e($r['closure']) ?>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <span class="pc-badge pc-badge--brand">Web Push</span>
            <span class="pc-badge pc-badge--neutral">E-mail</span>
            <span class="pc-badge pc-badge--neutral" title="Interface SmsChannel prête">SMS (bientôt)</span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php elseif (empty($alerts)): ?>
  <section class="pc-card text-center py-6">
    <div class="pc-empty-icon mx-auto mb-2">
      <?= icon('check-circle', 'text-ok', 32) ?>
    </div>
    <h2 class="text-headline">Aucune alerte dans cette vue</h2>
    <p class="text-caption text-muted mt-1">Toutes les constantes, prises et gardes sont suivies normalement.</p>
  </section>
<?php else: ?>
  <div class="pc-stack gap-3">
    <?php foreach ($alerts as $alert): ?>
      <?php
      $sev = (string) ($alert['level'] ?? ($alert['severity'] ?? 'warning'));
      $status = (string) ($alert['status'] ?? 'new');
      $isHandled = $status === 'handled';
      $sevBadgeClass = $sev === 'critical' ? 'pc-badge--danger' : ($sev === 'warning' ? 'pc-badge--warn' : 'pc-badge--brand');
      $sevLabel = $sev === 'critical' ? 'Critique' : ($sev === 'warning' ? 'Attention' : 'Information');
      $sevIcon = $sev === 'critical' ? 'shield-alert' : ($sev === 'warning' ? 'alert-triangle' : 'info');
      $cardMod = $isHandled ? 'pc-alert-card--handled' : 'pc-alert-card--' . $sev;
      ?>
      <article class="pc-card pc-alert-card <?= e($cardMod) ?>" id="alert-<?= (int) $alert['id'] ?>">
        <div class="flex items-start justify-between gap-2 mb-2">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="pc-badge <?= e($sevBadgeClass) ?>">
              <?= icon($sevIcon, '', 13) ?>
              <span><?= e($sevLabel) ?></span>
            </span>

            <?php if (!empty($alert['escalated_at'])): ?>
              <span class="pc-badge pc-badge--danger">
                <?= icon('bell', '', 12) ?>
                <span>Escaladée (15 min)</span>
              </span>
            <?php endif; ?>

            <?php if ($isHandled): ?>
              <span class="pc-badge pc-badge--ok">
                <?= icon('check', '', 12) ?>
                <span>Traitée</span>
              </span>
            <?php else: ?>
              <span class="pc-badge pc-badge--neutral">
                <span><?= $status === 'new' ? 'Nouvelle' : 'Vue' ?></span>
              </span>
            <?php endif; ?>
          </div>

          <span class="text-caption font-mono text-muted"><?= e((string) ($alert['local_created_at'] ?? '')) ?></span>
        </div>

        <h2 class="text-headline mb-1"><?= e((string) $alert['title']) ?></h2>
        <p class="text-subhead text-muted mb-3"><?= e((string) $alert['message']) ?></p>

        <?php if ($isHandled): ?>
          <div class="pc-alert-card__resolution">
            <?= icon('check-circle', 'text-ok', 15) ?>
            <div class="text-caption">
              <?php if (!empty($alert['handled_first_name'])): ?>
                <strong>Traitée par <?= e(trim($alert['handled_first_name'] . ' ' . ($alert['handled_last_name'] ?? ''))) ?></strong>
                <?php if (!empty($alert['local_handled_at'])): ?>
                  le <?= e((string) $alert['local_handled_at']) ?>
                <?php endif; ?>
                —
              <?php endif; ?>
              <span><?= e((string) ($alert['resolution'] ?? 'Traitée')) ?></span>
            </div>
          </div>
        <?php endif; ?>

        <div class="flex items-center justify-between gap-2 flex-wrap mt-3">
          <a href="<?= e((string) ($alert['target_url'] ?? '/')) ?>" class="pc-btn pc-btn--ghost pc-btn--sm">
            <?= icon('chevron-right', '', 14) ?>
            <span>Voir l’élément source</span>
          </a>

          <?php if (!$isHandled && $canHandle): ?>
            <button
              type="button"
              class="pc-btn pc-btn--primary pc-btn--sm"
              data-open-handle-alert="<?= (int) $alert['id'] ?>"
              data-alert-title="<?= e((string) $alert['title']) ?>"
            >
              <?= icon('check', '', 15) ?>
              <span>Marquer comme traitée</span>
            </button>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($canHandle): ?>
  <!-- Bottom-sheet "Marquer comme traitée" avec choix rapides + texte libre -->
  <div class="pc-sheet-backdrop" id="alert-handle-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="alert-handle-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="alert-handle-title">Traiter l’alerte</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/alerts/0/handle" id="alert-handle-form" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/alerts?filter=<?= e($statusFilter) ?>&amp;severity=<?= e($severityFilter) ?>">

        <p class="text-subhead font-semibold" id="alert-handle-subtitle"></p>

        <div>
          <span class="pc-label">Choix rapide d’action réalisée</span>
          <div class="flex items-center gap-2 flex-wrap mt-1">
            <button type="button" class="pc-chip" data-quick-alert-choice="Vérifié">Vérifié</button>
            <button type="button" class="pc-chip" data-quick-alert-choice="Appelé l’aide-soignante">Appelé l’aide-soignante</button>
            <button type="button" class="pc-chip" data-quick-alert-choice="Médecin contacté">Médecin contacté</button>
            <button type="button" class="pc-chip" data-quick-alert-choice="Resucrage fait">Resucrage fait</button>
          </div>
        </div>

        <input type="hidden" name="quick_resolution" id="alert-quick-choice" value="">

        <div>
          <label class="pc-label" for="alert-handle-note">Précision / Observation de prise en charge</label>
          <textarea
            id="alert-handle-note"
            name="resolution"
            class="pc-input"
            rows="3"
            placeholder="Ex. : 15 g de sucre donnés, contrôle capillaire prévu dans 15 min..."
            required
          ></textarea>
        </div>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('check', '', 18) ?>
          <span>Valider le traitement de l’alerte</span>
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>
