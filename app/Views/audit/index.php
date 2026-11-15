<?php

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $logs */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var array<string, string> $filters */
/** @var array<int, array<string, mixed>> $users */
/** @var array<string, string> $entityLabels */
/** @var array<string, string> $actionCategories */

$logs = is_array($logs ?? null) ? $logs : [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$filters = is_array($filters ?? null) ? $filters : [];
$users = is_array($users ?? null) ? $users : [];
$entityLabels = is_array($entityLabels ?? null) ? $entityLabels : [];
$actionCategories = is_array($actionCategories ?? null) ? $actionCategories : [];

$toneForCategory = static function (string $cat): string {
    return match ($cat) {
        'delete', 'login_failed', 'revoke_device' => 'danger',
        'update' => 'warn',
        'login', 'validate', 'create' => 'ok',
        default => 'info',
    };
};

$exportQuery = http_build_query([
    'from' => ($filters['from'] ?? '') !== '' ? $filters['from'] : date('Y-m-d', strtotime('-30 days')),
    'to' => ($filters['to'] ?? '') !== '' ? $filters['to'] : date('Y-m-d'),
]);
?>

<div class="pc-page pc-animate-in">

  <div class="flex items-center justify-between gap-2 mb-3">
    <div>
      <div class="text-caption text-muted">Traçabilité médico-légale immuable · Lecture seule</div>
      <div class="font-semibold"><?= $total ?> entrée<?= $total > 1 ? 's' : '' ?> enregistrée<?= $total > 1 ? 's' : '' ?></div>
    </div>
    <a href="<?= e(route('reports.export_csv', ['type' => 'audit']) . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="pc-btn pc-btn--secondary pc-btn--sm">
      <?= icon('download', 16) ?>
      <span>Exporter CSV</span>
    </a>
  </div>

  <!-- Filtres -->
  <form method="GET" action="/audit" class="pc-card mb-3 p-3">
    <div class="pc-module-grid pc-module-grid--2col gap-2">
      <div class="pc-field">
        <label class="pc-field-label" for="audit-user">Utilisateur</label>
        <select id="audit-user" name="user_id" class="pc-input">
          <option value="">Tous les utilisateurs</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= (string) ($filters['user_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
              <?= e(trim((string) ($u['first_name'] ?? '') . ' ' . (string) ($u['last_name'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pc-field">
        <label class="pc-field-label" for="audit-entity">Module / Table</label>
        <select id="audit-entity" name="entity_type" class="pc-input">
          <option value="">Tous les modules</option>
          <?php foreach ($entityLabels as $entKey => $entLabel): ?>
            <option value="<?= e($entKey) ?>" <?= ($filters['entity_type'] ?? '') === $entKey ? 'selected' : '' ?>>
              <?= e($entLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pc-field">
        <label class="pc-field-label" for="audit-action">Action</label>
        <select id="audit-action" name="action" class="pc-input">
          <option value="">Toutes les actions</option>
          <?php foreach ($actionCategories as $actKey => $actLabel): ?>
            <option value="<?= e($actKey) ?>" <?= ($filters['action'] ?? '') === $actKey ? 'selected' : '' ?>>
              <?= e($actLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="flex gap-2">
        <div class="flex-1 pc-field">
          <label class="pc-field-label" for="audit-from">Du</label>
          <input id="audit-from" type="date" name="from" class="pc-input" value="<?= e((string) ($filters['from'] ?? '')) ?>">
        </div>
        <div class="flex-1 pc-field">
          <label class="pc-field-label" for="audit-to">Au</label>
          <input id="audit-to" type="date" name="to" class="pc-input" value="<?= e((string) ($filters['to'] ?? '')) ?>">
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-2 mt-2">
      <a href="/audit" class="pc-btn pc-btn--ghost pc-btn--sm">Réinitialiser</a>
      <button type="submit" class="pc-btn pc-btn--primary pc-btn--sm">Filtrer</button>
    </div>
  </form>

  <!-- Liste paginée avec diff champ par champ -->
  <?php if ($logs === []): ?>
    <div class="pc-card pc-empty-state">
      <div class="pc-empty-state__icon"><?= icon('shield-check', 28) ?></div>
      <h2 class="pc-empty-state__title">Aucune entrée correspondante</h2>
      <p class="pc-empty-state__text">Modifiez vos critères de recherche pour consulter l’historique d’audit.</p>
    </div>
  <?php else: ?>
    <div class="pc-group" role="list" aria-label="Entrées du journal d’audit">
      <?php foreach ($logs as $log): ?>
        <?php
        $actor = trim((string) ($log['first_name'] ?? '') . ' ' . (string) ($log['last_name'] ?? ''));
        if ($actor === '') {
            $actor = 'Système / Non authentifié';
        }
        $cat = (string) ($log['action_category'] ?? 'create');
        $tone = $toneForCategory($cat);
        $diffs = is_array($log['diffs'] ?? null) ? $log['diffs'] : [];
        ?>
        <details class="pc-audit-row" role="listitem">
          <summary class="pc-group__row pc-audit-summary">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="pc-badge pc-badge--<?= e($tone) ?>"><?= e((string) ($log['action_label'] ?? $log['action'])) ?></span>
                <span class="text-caption font-semibold text-muted"><?= e((string) ($log['entity_label'] ?? $log['entity_type'])) ?></span>
                <?php if (!empty($log['entity_id'])): ?>
                  <span class="text-caption font-mono text-muted">#<?= (int) $log['entity_id'] ?></span>
                <?php endif; ?>
              </div>
              <div class="text-footnote mt-1">
                <strong><?= e($actor) ?></strong>
                <span class="text-muted"> · <?= e(format_datetime((string) ($log['created_at'] ?? ''))) ?></span>
                <?php if (!empty($log['ip_address'])): ?>
                  <span class="text-caption font-mono text-muted"> · IP <?= e((string) $log['ip_address']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex items-center gap-1 text-muted">
              <?php if ($diffs !== []): ?>
                <span class="pc-badge pc-badge--neutral"><?= count($diffs) ?> champ<?= count($diffs) > 1 ? 's' : '' ?></span>
              <?php endif; ?>
              <?= icon('chevron-right', 16) ?>
            </div>
          </summary>

          <div class="pc-audit-detail p-3">
            <div class="text-caption text-muted mb-2">
              Action technique : <code class="font-mono"><?= e((string) ($log['action'] ?? '')) ?></code>
              <?php if (!empty($log['user_agent'])): ?>
                · Agent : <?= e(mb_substr((string) $log['user_agent'], 0, 80)) ?>
              <?php endif; ?>
            </div>

            <?php if ($diffs === []): ?>
              <div class="text-footnote text-muted">Aucune différence de champ enregistrée pour cet événement.</div>
            <?php else: ?>
              <div class="pc-audit-diff-list">
                <?php foreach ($diffs as $diff): ?>
                  <div class="pc-audit-diff-item">
                    <div class="pc-audit-diff-label"><?= e((string) $diff['label']) ?></div>
                    <div class="pc-audit-diff-values font-mono">
                      <?php if ($diff['old'] !== null && $diff['old'] !== ''): ?>
                        <del class="pc-diff-old"><?= e((string) $diff['old']) ?></del>
                      <?php endif; ?>
                      <?php if ($diff['old'] !== null && $diff['new'] !== null): ?>
                        <span class="text-muted mx-1" aria-hidden="true">&rsaquo;</span>
                      <?php endif; ?>
                      <?php if ($diff['new'] !== null && $diff['new'] !== ''): ?>
                        <ins class="pc-diff-new"><?= e((string) $diff['new']) ?></ins>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
      <nav class="flex items-center justify-between mt-3" aria-label="Pagination du journal d’audit">
        <?php
        $prevQuery = http_build_query(array_merge($filters, ['page' => max(1, $page - 1)]));
        $nextQuery = http_build_query(array_merge($filters, ['page' => min($pages, $page + 1)]));
        ?>
        <?php if ($page > 1): ?>
          <a href="/audit?<?= e($prevQuery) ?>" class="pc-btn pc-btn--secondary pc-btn--sm">&larr; Précédent</a>
        <?php else: ?>
          <span class="pc-btn pc-btn--ghost pc-btn--sm" aria-disabled="true">&larr; Précédent</span>
        <?php endif; ?>

        <span class="text-footnote text-muted">Page <?= $page ?> sur <?= $pages ?></span>

        <?php if ($page < $pages): ?>
          <a href="/audit?<?= e($nextQuery) ?>" class="pc-btn pc-btn--secondary pc-btn--sm">Suivant &rsaquo;</a>
        <?php else: ?>
          <span class="pc-btn pc-btn--ghost pc-btn--sm" aria-disabled="true">Suivant &rsaquo;</span>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

</div>
