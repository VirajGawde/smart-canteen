<?php
$admin = currentAdmin();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<header class="app-header">
  <button class="hamburger" id="hamburgerBtn" aria-label="Open menu"><?= icon('menu') ?></button>
  <h1 class="page-title"><?= e($pageTitle) ?></h1>
  <div class="header-actions">
    <div class="dropdown" id="profileDropdown">
      <button class="profile-btn" id="profileBtn">
        <span class="avatar avatar-admin"><?= e(strtoupper(substr($admin['name'], 0, 1))) ?></span>
        <span class="profile-text">
          <strong><?= e($admin['name']) ?></strong>
          <small>Administrator</small>
        </span>
        <?= icon('chevron-down') ?>
      </button>
      <div class="dropdown-panel dropdown-panel-sm" id="profilePanel">
        <a href="<?= BASE_URL ?>/admin/logout.php" class="danger"><?= icon('logout') ?> Logout</a>
      </div>
    </div>
  </div>
</header>
