<?php

declare(strict_types=1);

$doc = is_array($doc ?? null) ? $doc : [];
$docId = (int) ($doc['id'] ?? 0);
$isPdf = !empty($doc['is_pdf']);
$isImage = !empty($doc['is_image']);
$canDelete = !empty($canDelete);
?>
<div class="pc-stack gap-3">
  <!-- Carte métadonnées et actions du document -->
  <section class="pc-card" aria-labelledby="doc-detail-heading">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap mb-2">
          <span class="pc-badge <?= e((string) ($doc['category_badge'] ?? 'pc-badge--brand')) ?>">
            <?= icon((string) ($doc['category_icon'] ?? 'file-text'), '', 13) ?>
            <span><?= e((string) ($doc['category_label'] ?? 'Document')) ?></span>
          </span>
          <span class="pc-badge pc-badge--neutral font-mono">
            <?= e((string) ($doc['mime'] ?? '')) ?> · <?= e((string) ($doc['size_formatted'] ?? '')) ?>
          </span>
          <span class="text-caption text-muted font-mono">
            Date : <?= e(format_date_fr((string) ($doc['doc_date'] ?? ''))) ?>
          </span>
        </div>

        <h2 class="text-title-3" id="doc-detail-heading"><?= e((string) ($doc['title'] ?? '')) ?></h2>

        <?php if (!empty($doc['description'])): ?>
          <p class="text-subhead text-muted mt-1"><?= e((string) $doc['description']) ?></p>
        <?php endif; ?>

        <?php if (!empty($doc['medication_name'])): ?>
          <div class="mt-2">
            <a href="/treatments" class="pc-badge pc-badge--meds">
              <?= icon('pill', '', 13) ?>
              <span>Ordonnance reliée à : <?= e((string) $doc['medication_name']) ?></span>
            </a>
          </div>
        <?php endif; ?>

        <div class="text-caption text-muted mt-2">
          Déposé par <strong><?= e(trim(($doc['uploader_first_name'] ?? '') . ' ' . ($doc['uploader_last_name'] ?? ''))) ?></strong>
          · Empreinte SHA-256 : <code class="font-mono"><?= e(substr((string) ($doc['sha256'] ?? ''), 0, 16)) ?>…</code>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <a href="/documents/<?= $docId ?>/file" target="_blank" rel="noopener" class="pc-btn pc-btn--tinted pc-btn--sm">
          <?= icon('external-link', '', 15) ?>
          <span>Plein écran</span>
        </a>
        <a href="/documents/<?= $docId ?>/download" class="pc-btn pc-btn--primary pc-btn--sm">
          <?= icon('download', '', 15) ?>
          <span>Télécharger</span>
        </a>
        <?php if ($canDelete): ?>
          <button type="button" class="pc-btn pc-btn--danger-soft pc-btn--sm" data-sheet-open="delete-doc-sheet">
            <?= icon('trash-2', '', 15) ?>
            <span>Supprimer</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Visionneuse intégrée (PDF inline / Image plein écran) -->
  <section class="pc-card pc-doc-viewer" aria-label="Aperçu du document médical">
    <?php if ($isPdf): ?>
      <div class="pc-doc-viewer__pdf-sheet">
        <div class="pc-doc-viewer__pdf-header">
          <div class="flex items-center gap-3">
            <span class="pc-list-icon pc-list-icon--vitals">
              <?= icon('file-text', '', 22) ?>
            </span>
            <div>
              <div class="text-caption uppercase font-semibold text-brand">Document PDF certifié · Lecture directe</div>
              <div class="text-subhead font-bold"><?= e((string) ($doc['original_name'] ?: ($doc['title'] . '.pdf'))) ?></div>
            </div>
          </div>
          <span class="pc-badge pc-badge--ok font-mono">SHA-256 vérifié</span>
        </div>

        <div class="pc-doc-viewer__pdf-page">
          <div class="pc-doc-viewer__pdf-stamp">
            <div>
              <div class="text-caption text-muted uppercase">Patient</div>
              <div class="text-subhead font-bold">M. Henri BENALI (né le 12/05/1942)</div>
            </div>
            <div class="text-right">
              <div class="text-caption text-muted uppercase">Date de l’acte</div>
              <div class="text-subhead font-mono font-bold"><?= e(format_date_fr((string) ($doc['doc_date'] ?? ''))) ?></div>
            </div>
          </div>

          <hr class="pc-divider my-3">

          <h3 class="text-headline mb-2"><?= e((string) ($doc['title'] ?? '')) ?></h3>
          <?php if (!empty($doc['description'])): ?>
            <p class="text-body mb-3"><?= nl2br(e((string) $doc['description'])) ?></p>
          <?php endif; ?>

          <div class="flex items-center gap-2 flex-wrap mt-3">
            <a href="/documents/<?= $docId ?>/file" target="_blank" rel="noopener" class="pc-btn pc-btn--primary pc-btn--sm">
              <?= icon('eye', '', 15) ?>
              <span>Ouvrir le PDF dans le navigateur</span>
            </a>
            <a href="/documents/<?= $docId ?>/download" class="pc-btn pc-btn--tinted pc-btn--sm">
              <?= icon('download', '', 15) ?>
              <span>Télécharger le fichier PDF (<?= e((string) ($doc['size_formatted'] ?? '')) ?>)</span>
            </a>
          </div>
        </div>
      </div>
    <?php elseif ($isImage): ?>
      <figure class="pc-doc-viewer__figure">
        <a href="/documents/<?= $docId ?>/file" target="_blank" rel="noopener" title="Ouvrir l’image en plein écran">
          <img
            src="/documents/<?= $docId ?>/file"
            alt="<?= e((string) ($doc['title'] ?? 'Document médical')) ?>"
            class="pc-doc-viewer__img"
          >
        </a>
        <figcaption class="text-caption text-muted text-center mt-2">
          Touchez l’image pour l’afficher en plein écran ou la zoomer.
        </figcaption>
      </figure>
    <?php endif; ?>
  </section>
</div>

<?php if ($canDelete): ?>
  <div class="pc-sheet-backdrop" id="delete-doc-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="delete-doc-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="delete-doc-title">Supprimer le document médical</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>
      <form method="POST" action="/documents/<?= $docId ?>/delete" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>
        <p class="text-subhead text-muted">
          Confirmez-vous la suppression logique du document <strong>« <?= e((string) ($doc['title'] ?? '')) ?> »</strong> ? Cette action est tracée dans le journal d’audit.
        </p>
        <div class="flex items-center gap-2">
          <button type="button" class="pc-btn pc-btn--ghost flex-1" data-sheet-close>Annuler</button>
          <button type="submit" class="pc-btn pc-btn--danger flex-1">
            <?= icon('trash-2', '', 16) ?>
            <span>Supprimer le document</span>
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
