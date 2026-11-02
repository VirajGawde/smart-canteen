<?php
/**
 * pwa_head.php — everything the browser needs to treat the site as an
 * installable app. Included inside <head> on every page, right after the
 * favicon link. Requires BASE_URL (from config.php).
 */
?>
<link rel="manifest" href="<?= BASE_URL ?>/manifest.php">
<meta name="theme-color" content="#0F2A4A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Smart Canteen">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/images/icons/apple-touch-icon.png">
<script>window.PWA_BASE = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/js/pwa.js" defer></script>
