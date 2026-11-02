<?php
/**
 * manifest.php — Web App Manifest, generated so it works wherever the
 * project is hosted (/smart-canteen, site root, etc.). Deliberately does
 * NOT include config.php: that would start a session on every fetch.
 */
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

echo json_encode([
    'id'               => $base . '/',
    'name'             => 'Smart Canteen · MPSTME',
    'short_name'       => 'Smart Canteen',
    'description'      => 'Fair, real-time canteen seat reservations for MPSTME students.',
    'start_url'        => $base . '/index.php?source=pwa',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#F5F8FC',
    'theme_color'      => '#0F2A4A',
    'lang'             => 'en-IN',
    'categories'       => ['education', 'food'],
    'icons' => [
        ['src' => $base . '/images/icons/icon-192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/images/icons/icon-512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/images/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Book a seat',  'url' => $base . '/student/canteen.php'],
        ['name' => 'Scan QR',      'url' => $base . '/student/scan.php'],
        ['name' => 'My bookings',  'url' => $base . '/student/my_bookings.php'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
