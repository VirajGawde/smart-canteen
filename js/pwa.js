/**
 * pwa.js — registers the service worker and offers an "Install app"
 * button on Android Chrome. Loaded on every page via includes/pwa_head.php.
 */
(function () {
  const BASE = window.PWA_BASE || '';

  // 1. Service worker (needs HTTPS, or localhost for testing)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(BASE + '/sw.php', { scope: BASE + '/' }).catch(() => {});
    });
  }

  // 2. Install button
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  if (standalone) return;

  let deferred = null;
  let dismissed = false;
  try { dismissed = localStorage.getItem('pwaInstallDismissed') === '1'; } catch (e) {}

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferred = e;
    if (!dismissed) showBar();
  });

  window.addEventListener('appinstalled', () => { removeBar(); deferred = null; });

  function removeBar() { const b = document.getElementById('pwaInstallBar'); if (b) b.remove(); }

  function showBar() {
    if (document.getElementById('pwaInstallBar')) return;
    const bar = document.createElement('div');
    bar.id = 'pwaInstallBar';
    bar.setAttribute('role', 'dialog');
    bar.setAttribute('aria-label', 'Install Smart Canteen');
    bar.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:9999;display:flex;align-items:center;gap:12px;' +
      'background:#0F2A4A;color:#fff;padding:12px 14px;border-radius:16px;box-shadow:0 12px 32px rgba(15,42,74,.35);' +
      'font:500 14px/1.3 Inter,"Segoe UI",sans-serif;max-width:480px;margin:0 auto';
    bar.innerHTML =
      '<img src="' + BASE + '/images/icons/icon-192.png" alt="" width="40" height="40" style="border-radius:10px">' +
      '<span style="flex:1">Install Smart Canteen for quick access</span>' +
      '<button id="pwaInstallBtn" style="background:#fff;color:#0F2A4A;border:0;border-radius:999px;padding:8px 16px;font-weight:600;cursor:pointer">Install</button>' +
      '<button id="pwaCloseBtn" aria-label="Dismiss" style="background:transparent;color:#fff;border:0;font-size:20px;line-height:1;cursor:pointer">×</button>';
    document.body.appendChild(bar);

    document.getElementById('pwaInstallBtn').addEventListener('click', async () => {
      if (!deferred) return;
      deferred.prompt();
      await deferred.userChoice;
      deferred = null;
      removeBar();
    });
    document.getElementById('pwaCloseBtn').addEventListener('click', () => {
      try { localStorage.setItem('pwaInstallDismissed', '1'); } catch (e) {}
      removeBar();
    });
  }
})();
