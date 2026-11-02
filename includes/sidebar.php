<?php
/**
 * sidebar.php — desktop: fixed left column. Mobile: off-canvas drawer
 * toggled by the hamburger button in header.php (see js/main.js).
 * Expects $activePage to be set by the including page (e.g. 'dashboard').
 */
$activePage = $activePage ?? '';
$navItems = [
    ['key' => 'dashboard',     'href' => 'dashboard.php',     'icon' => 'home',    'label' => 'Home'],
    ['key' => 'canteen',       'href' => 'canteen.php',       'icon' => 'grid',    'label' => 'Canteen Seat'],
    ['key' => 'my_bookings',   'href' => 'my_bookings.php',   'icon' => 'seat',    'label' => 'My Bookings'],
    ['key' => 'complaints',    'href' => 'complaints.php',    'icon' => 'flag',    'label' => 'Complaints'],
    ['key' => 'feedback',      'href' => 'feedback.php',      'icon' => 'star',    'label' => 'Feedback'],
    ['key' => 'notifications', 'href' => 'notifications.php', 'icon' => 'bell',    'label' => 'Notifications'],
    ['key' => 'profile',       'href' => 'profile.php',       'icon' => 'user',    'label' => 'Profile'],
    ['key' => 'help',          'href' => 'help.php',          'icon' => 'help',    'label' => 'Help & Support'],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="<?= BASE_URL ?>/images/nmims-crest.png" alt="" width="32" height="32">
    <span>Smart Canteen</span>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu"><?= icon('close') ?></button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($navItems as $item): ?>
      <a href="<?= $item['href'] ?>" class="sidebar-link <?= $activePage === $item['key'] ? 'active' : '' ?>">
        <?= icon($item['icon']) ?>
        <span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link sidebar-logout">
      <?= icon('logout') ?>
      <span>Logout</span>
    </a>
  </div>
</aside>

<nav class="mobile-bottom-nav">
  <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>"><?= icon('home') ?><span>Home</span></a>
  <a href="canteen.php" class="<?= $activePage === 'canteen' ? 'active' : '' ?>"><?= icon('grid') ?><span>Canteen</span></a>
  <a href="my_bookings.php" class="<?= $activePage === 'my_bookings' ? 'active' : '' ?>"><?= icon('seat') ?><span>Booking</span></a>
  <a href="notifications.php" class="<?= $activePage === 'notifications' ? 'active' : '' ?>"><?= icon('bell') ?><span>Alerts</span></a>
  <a href="profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>"><?= icon('user') ?><span>Profile</span></a>
</nav>
