<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireStudentLogin();
$pdo = getDB();

$faqs = [
    'How do I reserve a seat?' => 'Go to "Canteen Seat", tap an available bench, choose one of its 4 seats, and tap "Confirm Reservation". You can only hold one active reservation at a time.',
    'What happens after I reserve?' => 'A 10-minute countdown starts immediately. You need to reach the canteen and scan the QR code printed on your bench before it runs out.',
    'What if I don\'t reach in time?' => 'Your reservation expires automatically, the seat becomes available for someone else, and it\'s logged as a no-show. Repeated no-shows can lead to a temporary booking restriction — see "My Bookings" for your history.',
    'How does QR scanning work?' => 'Open "Scan QR" from the sidebar (or from your active booking) and point your camera at the QR code on your reserved bench. If the camera isn\'t available, you can type the code shown under the QR printout instead.',
    'How do I free up my seat?' => 'Once you\'re done eating, go to "My Bookings" and tap "Vacate Seat". This immediately frees the seat for another student — please don\'t skip this step.',
    'Can I reserve for a friend?' => 'No — reservations are tied to your own SAP ID and only you can scan the QR to confirm them. This is by design, to keep seating fair for everyone.',
    'How do complaints work?' => 'Submit a complaint from the "Complaints" page. If it concerns a specific bench, students currently seated there may be asked a quick yes/no question to help the admin verify what happened before any action is taken.',
    'I think there\'s a mistake with my booking restriction.' => 'Booking restrictions are reviewed by the canteen admin team. Visit the admin desk near the canteen counter, or raise it as a complaint with type "Other" describing the issue.',
];

$activePage = 'help';
$pageTitle = 'Help & Support';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & Support · Smart Canteen</title>
<link rel="icon" href="<?= BASE_URL ?>/images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/../includes/pwa_head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<style>
  details { border-bottom: 1px solid var(--slate-100); padding: 16px 4px; }
  summary { cursor: pointer; font-weight: 600; font-size: 14.5px; color: var(--navy-900); list-style: none; display:flex; justify-content:space-between; align-items:center; }
  summary::-webkit-details-marker { display: none; }
  summary::after { content: '+'; font-size: 20px; color: var(--red-600); }
  details[open] summary::after { content: '−'; }
  details p { margin: 12px 0 0; color: var(--ink-600); font-size: 13.5px; line-height: 1.6; }
</style>
</head>
<body class="app-body photo-bg">
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="app-main">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="app-content">
      <div class="card">
        <div class="card-title">Frequently Asked Questions</div>
        <div class="card-sub">Everything about reservations, QR scanning, and no-shows.</div>
        <div>
          <?php foreach ($faqs as $q => $a): ?>
            <details>
              <summary><?= e($q) ?></summary>
              <p><?= e($a) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card section-gap" style="margin-top:22px;">
        <div class="card-title">Still need help?</div>
        <p style="color:var(--ink-600); font-size:14px;">Visit the canteen admin desk in person, or submit a complaint with type "Other" describing your issue — the admin team reviews these regularly.</p>
        <a href="complaints.php" class="btn btn-outline">Go to Complaints</a>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
