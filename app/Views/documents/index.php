<?php

declare(strict_types=1);

$documents = is_array($documents ?? null) ? $documents : [];
$categories = is_array($categories ?? null) ? $categories : [];
$counts = is_array($counts ?? null) ? $counts : ['all' => count($documents)];
$selectedCategory = (string) ($selectedCategory ?? 'all');
$searchQuery = (string) ($searchQuery ?? '');
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$canManage = !empty($canManage);
?>
<!-- En-tête Coffre-fort de documents médicaux (E22) -->
<section class="pc-card pc-hero-card mb-3" aria-labelledby="docs-hero-title">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
      <div class="text-caption uppercase tracking-wide opacity-80">Dossier médical · M. Henri Benali</div>
      <h2 class="text-title-2 mt-1" id="docs-hero-title">Documents médicaux &amp; Ordonnances</h2>
      <p class="text-caption opacity-85 mt-1">
        Ordonnances reliées aux prescriptions, comptes rendus hospitaliers, analyses biologiques (HbA1c), courriers et imagerie.
      </p>
    </div>
    <?php if ($canManage): ?>
      <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-sheet-open="document-upload-sheet">
        <?= icon('upload', '', 16) ?>
        <span>Déposer un document</span>
      </button>
    <?php endif; ?>
  </div>
</section>

<!-- Recherche et filtre par catégorie -->
<section class="pc-card mb-3" aria-label="Recherche et filtres de documents">
  <form method="GET" action="/documents" class="pc-stack gap-3">
    <?php if ($selectedCategory !== 'all'): ?>
      <input type="hidden" name="category" value="<?= e($selectedCategory) ?>">
    <?php endif; ?>
    <div class="pc-field">
      <label class="pc-label" for="doc-search-input">Rechercher dans le titre ou la description</label>
      <div class="flex items-center gap-2">
        <input
          type="search"
          id="doc-search-input"
          name="q"
          value="<?= e($searchQuery) ?>"
          class="pc-input flex-1"
          placeholder="Ex. : Ordonnance, HbA1c, CHU Henri-Mondor, Lantus..."
        >
        <button type="submit" class="pc-btn pc-btn--tinted pc-btn--sm">
          <?= icon('search', '', 16) ?>
          <span>Filtrer</span>
        </button>
        <?php if ($searchQuery !== '' || $selectedCategory !== 'all'): ?>
          <a href="/documents" class="pc-btn pc-btn--ghost pc-btn--sm">Réinitialiser</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Pastilles de catégories -->
  <nav class="pc-chips-scroll mt-3" aria-label="Catégories de documents">
    <a
      href="/documents<?= $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : '' ?>"
      class="pc-chip <?= $selectedCategory === 'all' ? 'is-active' : '' ?>"
    >
      <span>Toutes (<?= (int) ($counts['all'] ?? 0) ?>)</span>
    </a>
    <?php foreach ($categories as $catKey => $catMeta): ?>
      <?php
      $url = '/documents?category=' . urlencode((string) $catKey) . ($searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '');
      $cnt = (int) ($counts[$catKey] ?? 0);
      ?>
      <a
        href="<?= e($url) ?>"
        class="pc-chip <?= $selectedCategory === $catKey ? 'is-active' : '' ?>"
      >
        <?= icon((string) $catMeta['icon'], '', 14) ?>
        <span><?= e((string) $catMeta['label']) ?> (<?= $cnt ?>)</span>
      </a>
    <?php endforeach; ?>
  </nav>
</section>

<!-- Liste des documents par date -->
<section aria-labelledby="docs-list-heading">
  <div class="flex items-center justify-between mb-2">
    <h2 class="pc-section-header mb-0" id="docs-list-heading">
      <?= count($documents) ?> document<?= count($documents) > 1 ? 's' : '' ?> classé<?= count($documents) > 1 ? 's' : '' ?> par date
    </h2>
  </div>

  <?php if ($documents === []): ?>
    <div class="pc-card pc-empty-state">
      <div class="pc-empty-state__icon">
        <?= icon('file-text', '', 28) ?>
      </div>
      <div class="pc-empty-state__title">Aucun document trouvé</div>
      <p class="pc-empty-state__text">
        Aucun document médical ne correspond aux critères sélectionnés.
      </p>
      <?php if ($canManage): ?>
        <button type="button" class="pc-btn pc-btn--primary pc-btn--sm mt-2" data-sheet-open="document-upload-sheet">
          <?= icon('upload', '', 16) ?>
          <span>Déposer un premier document</span>
        </button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="pc-stack gap-2">
      <?php foreach ($documents as $doc): ?>
        <?php
        $docId = (int) $doc['id'];
        $docDateFr = format_date_fr((string) $doc['doc_date']);
        $isPdf = !empty($doc['is_pdf']);
        ?>
        <article class="pc-card pc-doc-card">
          <div class="flex items-start justify-between gap-3">
            <a href="/documents/<?= $docId ?>" class="flex items-start gap-3 flex-1 min-w-0">
              <span class="pc-list-icon <?= $isPdf ? 'pc-list-icon--vitals' : 'pc-list-icon--docs' ?>">
                <?= icon((string) $doc['category_icon'], '', 20) ?>
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <span class="pc-badge <?= e((string) $doc['category_badge']) ?>">
                    <?= e((string) $doc['category_label']) ?>
                  </span>
                  <span class="pc-badge pc-badge--neutral font-mono">
                    <?= $isPdf ? 'PDF' : 'IMAGE' ?> · <?= e((string) $doc['size_formatted']) ?>
                  </span>
                  <span class="text-caption text-muted font-mono"><?= e($docDateFr) ?></span>
                </div>

                <h3 class="text-subhead font-bold"><?= e((string) $doc['title']) ?></h3>

                <?php if (!empty($doc['description'])): ?>
                  <p class="text-caption text-muted mt-1"><?= e((string) $doc['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($doc['medication_name'])): ?>
                  <div class="text-caption font-semibold text-brand mt-1 flex items-center gap-1">
                    <?= icon('pill', '', 13) ?>
                    <span>Relié à la prescription : <?= e((string) $doc['medication_name']) ?></span>
                  </div>
                <?php endif; ?>

                <div class="text-caption text-muted mt-1">
                  Déposé par <?= e(trim($doc['uploader_first_name'] . ' ' . $doc['uploader_last_name'])) ?>
                </div>
              </div>
            </a>

            <div class="flex items-center gap-1 shrink-0">
              <a
                href="/documents/<?= $docId ?>"
                class="pc-btn pc-btn--tinted pc-btn--sm"
                aria-label="Voir — <?= e((string) $doc['title']) ?>"
              >
                <?= icon('eye', '', 15) ?>
                <span>Voir</span>
              </a>
              <a
                href="/documents/<?= $docId ?>/download"
                class="pc-btn pc-btn--ghost pc-btn--sm"
                aria-label="Télécharger <?= e((string) $doc['title']) ?>"
              >
                <?= icon('download', '', 15) ?>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
  <!-- Bottom-sheet : Déposer un document médical (Admin uniquement) -->
  <div class="pc-sheet-backdrop" id="document-upload-sheet" hidden aria-hidden="true">
    <div class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="document-upload-title">
      <div class="pc-sheet__handle" aria-hidden="true"></div>
      <div class="pc-sheet__header">
        <h2 class="pc-sheet__title" id="document-upload-title">Déposer un document médical</h2>
        <button type="button" class="pc-sheet__close" data-sheet-close aria-label="Fermer">
          <?= icon('x', '', 20) ?>
        </button>
      </div>

      <form method="POST" action="/documents" enctype="multipart/form-data" class="pc-sheet__body pc-stack gap-3">
        <?= csrf_field() ?>

        <div class="pc-field">
          <label class="pc-label" for="doc-title-input">Titre du document</label>
          <input
            type="text"
            id="doc-title-input"
            name="title"
            class="pc-input"
            placeholder="Ex. : Bilan biologique HbA1c — Laboratoire BioCréteil"
            required
          >
        </div>

        <div class="pc-grid-2">
          <div class="pc-field">
            <label class="pc-label" for="doc-category-select">Catégorie</label>
            <select id="doc-category-select" name="category" class="pc-input" required>
              <?php foreach ($categories as $catKey => $catMeta): ?>
                <option value="<?= e((string) $catKey) ?>"><?= e((string) $catMeta['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="pc-field">
            <label class="pc-label" for="doc-date-input">Date du document</label>
            <input
              type="date"
              id="doc-date-input"
              name="doc_date"
              value="<?= e(date('Y-m-d')) ?>"
              class="pc-input"
              required
            >
          </div>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="doc-rx-select">Rattacher à une prescription en cours (optionnel)</label>
          <select id="doc-rx-select" name="prescription_id" class="pc-input">
            <option value="">— Aucun rattachement spécifique —</option>
            <?php foreach ($prescriptions as $rx): ?>
              <option value="<?= (int) $rx['id'] ?>">
                <?= e((string) $rx['medication_name']) ?> (<?= e((string) $rx['dose']) ?> <?= e((string) $rx['unit']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="doc-desc-input">Notes ou résumé clinique (optionnel)</label>
          <textarea
            id="doc-desc-input"
            name="description"
            class="pc-input"
            rows="2"
            placeholder="Ex. : HbA1c à 7,1 %, fonction rénale stable (DFG 68 mL/min)."
          ></textarea>
        </div>

        <div class="pc-field">
          <label class="pc-label" for="doc-file-input">Fichier (PDF, JPG ou PNG · max. 10 Mo)</label>
          <input
            type="file"
            id="doc-file-input"
            name="document_file"
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            class="pc-input"
            required
          >
          <span class="text-caption text-muted">
            Vérification MIME réelle (finfo), suppression automatique des métadonnées EXIF sur les photos et détection de doublon par empreinte SHA-256.
          </span>
        </div>

        <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
          <?= icon('upload', '', 18) ?>
          <span>Enregistrer le document</span>
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>
