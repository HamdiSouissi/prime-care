<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;

/** @var string $activeTab */
$activeTab = $activeTab ?? 'home';
$isAdmin = Auth::hasRole('admin');
?>
<aside class="pc-sidebar" aria-label="Menu latéral">
  <div class="pc-sidebar__brand">
    <a href="/" aria-label="Prime Care — Accueil">
      <?= brand_svg('logo-complet', '', 190) ?>
    </a>
  </div>

  <nav class="pc-sidebar__nav">
    <a href="/" class="pc-sidebar__link <?= $activeTab === 'home' ? 'is-active' : '' ?>">
      <?= icon('home', '', 20) ?>
      <span>Accueil</span>
    </a>
    <a href="/today" class="pc-sidebar__link <?= $activeTab === 'today' ? 'is-active' : '' ?>">
      <?= icon('calendar-days', '', 20) ?>
      <span>Aujourd’hui</span>
    </a>
    <a href="/health" class="pc-sidebar__link <?= $activeTab === 'health' ? 'is-active' : '' ?>">
      <?= icon('heart-pulse', '', 20) ?>
      <span>Santé &amp; Constantes</span>
    </a>
    <a href="/treatments" class="pc-sidebar__link">
      <?= icon('pill', '', 20) ?>
      <span>Traitements &amp; Insuline</span>
    </a>
    <a href="/meals" class="pc-sidebar__link">
      <?= icon('utensils', '', 20) ?>
      <span>Repas &amp; Photos</span>
    </a>
    <a href="/journal" class="pc-sidebar__link <?= $activeTab === 'journal' ? 'is-active' : '' ?>">
      <?= icon('notebook-pen', '', 20) ?>
      <span>Journal de bord</span>
    </a>
    <a href="/patient" class="pc-sidebar__link <?= $activeTab === 'patient' ? 'is-active' : '' ?>">
      <?= icon('user', '', 20) ?>
      <span>Fiche patient</span>
    </a>
    <?php if ($isAdmin) : ?>
      <a href="/admin/treatments" class="pc-sidebar__link">
        <?= icon('clipboard-list', '', 20) ?>
        <span>Plan de traitement</span>
      </a>
      <a href="/admin/measures" class="pc-sidebar__link">
        <?= icon('sliders', '', 20) ?>
        <span>Mesures &amp; Seuils</span>
      </a>
      <a href="/settings" class="pc-sidebar__link">
        <?= icon('settings', '', 20) ?>
        <span>Réglages médicaux</span>
      </a>
      <a href="/users" class="pc-sidebar__link <?= $activeTab === 'team' ? 'is-active' : '' ?>">
        <?= icon('users', '', 20) ?>
        <span>Équipe</span>
      </a>
    <?php endif; ?>
    <a href="/install" class="pc-sidebar__link">
      <?= icon('smartphone', '', 20) ?>
      <span>Installer l’app</span>
    </a>
    <a href="/more" class="pc-sidebar__link <?= $activeTab === 'more' ? 'is-active' : '' ?>">
      <?= icon('more-horizontal', '', 20) ?>
      <span>Plus &amp; À propos</span>
    </a>
  </nav>

  <div class="pc-sidebar__footer">
    <?= View::partial('credit') ?>
  </div>
</aside>
