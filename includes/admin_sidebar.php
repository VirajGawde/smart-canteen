<?php
$activePage = $activePage ?? '';
$adminNav = [
    ['key' => 'dashboard',  'href' => 'dashboard.php',  'icon' => 'home',     'label' => 'Dashboard'],
    ['key' => 'benches',    'href' => 'benches.php',    'icon' => 'grid',     'label' => 'Benches & Seats'],
    ['key' => 'students',   'href' => 'students.php',   'icon' => 'user',     'label' => 'Students & Bans'],
    ['key' => 'complaints', 'href' => 'complaints.php', 'icon' => 'flag',     'label' => 'Complaints'],
    ['key' => 'analytics',  'href' => 'analytics.php',  'icon' => 'chart',    'label' => 'Analytics'],
    ['key' => 'settings',   'href' => 'settings.php',   'icon' => 'settings','label' => 'Settings'],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar admin-sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="<?= BASE_URL ?>/images/nmims-crest.png" alt="" width="32" height="32">
    <span>Admin Panel</span>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu"><?= icon('close') ?></button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($adminNav as $item): ?>
      <a href="<?= $item['href'] ?>" class="sidebar-link <?= $activePage === $item['key'] ? 'active' : '' ?>">
        <?= icon($item['icon']) ?>
        <span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/admin/logout.php" class="sidebar-link sidebar-logout">
      <?= icon('logout') ?>
      <span>Logout</span>
    </a>
  </div>
</aside>
