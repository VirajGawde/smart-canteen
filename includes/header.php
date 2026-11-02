<?php
/**
 * header.php — top bar for every student page.
 * Requires $pdo and a logged-in student session (enforced by the
 * including page calling requireStudentLogin() first).
 */
$student = currentStudent();
$unreadCount = unreadNotificationCount($pdo, $student['id']);
$recentNotifs = $pdo->prepare('SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 5');
$recentNotifs->execute([$student['id']]);
$recentNotifs = $recentNotifs->fetchAll();
$pageTitle = $pageTitle ?? 'Home';
?>
<header class="app-header">
  <button class="hamburger" id="hamburgerBtn" aria-label="Open menu"><?= icon('menu') ?></button>
  <h1 class="page-title"><?= e($pageTitle) ?></h1>

  <div class="header-actions">
    <div class="dropdown" id="notifDropdown">
      <button class="icon-btn" id="notifBtn" aria-label="Notifications">
        <?= icon('bell') ?>
        <?php if ($unreadCount > 0): ?><span class="badge-dot" id="unreadBadge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
      </button>
      <div class="dropdown-panel" id="notifPanel">
        <div class="dropdown-panel-head">
          <strong>Notifications</strong>
          <span style="display:flex; gap:12px;">
            <?php if ($unreadCount > 0): ?><a href="#" id="markAllReadLink">Mark all read</a><?php endif; ?>
            <a href="notifications.php">View all</a>
          </span>
        </div>
        <?php if (!$recentNotifs): ?>
          <p class="dropdown-empty">You're all caught up.</p>
        <?php else: foreach ($recentNotifs as $n): ?>
          <div class="notif-row <?= $n['is_read'] ? '' : 'unread' ?>">
            <p><?= e($n['message']) ?></p>
            <span><?= timeAgo($n['created_at']) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="dropdown" id="profileDropdown">
      <button class="profile-btn" id="profileBtn">
        <span class="avatar"><?= e(strtoupper(substr($student['name'], 0, 1))) ?></span>
        <span class="profile-text">
          <strong><?= e($student['name']) ?></strong>
          <small class="mono"><?= e($student['sap_id']) ?></small>
        </span>
        <?= icon('chevron-down') ?>
      </button>
      <div class="dropdown-panel dropdown-panel-sm" id="profilePanel">
        <a href="profile.php"><?= icon('user') ?> My Profile</a>
        <a href="help.php"><?= icon('help') ?> Help &amp; Support</a>
        <a href="<?= BASE_URL ?>/logout.php" class="danger"><?= icon('logout') ?> Logout</a>
      </div>
    </div>
  </div>
</header>
