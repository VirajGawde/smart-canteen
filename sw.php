<?php
/**
 * sw.php — service worker, served as JavaScript from the app root so its
 * scope covers the whole project. Generated so paths follow wherever the
 * project is hosted.
 *
 * Strategy (kept deliberately conservative — this app shows LIVE seat data
 * and per-student sessions):
 *   • HTML pages and /api/ calls  -> always network. Never cached, so a
 *     student never sees a stale seat map or someone else's cached page.
 *     If the network is down, show offline.html instead.
 *   • Static files (css, js, images) -> stale-while-revalidate, so the app
 *     shell opens instantly.
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('Service-Worker-Allowed: ' . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/');
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$ver  = 'v1';
?>
const BASE = <?= json_encode($base) ?>;
const CACHE = 'smart-canteen-<?= $ver ?>';
const OFFLINE_URL = BASE + '/offline.html';
const PRECACHE = [
  OFFLINE_URL,
  BASE + '/css/style.css',
  BASE + '/css/app.css',
  BASE + '/images/icons/icon-192.png',
  BASE + '/images/nmims-crest.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET' || url.origin !== self.location.origin) return; // POSTs, CDN libs: browser default
  if (url.pathname.startsWith(BASE + '/api/') || (url.pathname.endsWith('.php') && req.mode !== 'navigate')) return;

  // Page navigations: network only, offline page as fallback
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Static assets: stale-while-revalidate
  if (/\.(css|js|png|jpg|jpeg|svg|webp|woff2?)$/.test(url.pathname)) {
    event.respondWith(
      caches.open(CACHE).then((cache) =>
        cache.match(req).then((cached) => {
          const fresh = fetch(req).then((res) => {
            if (res.ok) cache.put(req, res.clone());
            return res;
          }).catch(() => cached);
          return cached || fresh;
        })
      )
    );
  }
});
