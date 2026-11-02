/**
 * main.js — shared across every student page.
 * Everything else (canteen.js, qr-scan.js, complaints.js) assumes
 * `api()` and `toast()` from this file are already loaded.
 */

/** POSTs/GETs a JSON api/*.php endpoint and always resolves to {ok, ...}. Never throws.
 *  `url` is always a path relative to the PROJECT ROOT (e.g. 'api/get_seats.php'),
 *  never relative to the current page's folder — a page under /student/ or
 *  /admin/ would otherwise have fetch() silently resolve it one level too
 *  deep (e.g. /student/api/... instead of /api/...), which fails with a
 *  404 that shows up here as "Unexpected server response." That's why
 *  every call is resolved against window.APP_BASE (set in footer.php)
 *  instead of being passed straight to fetch(). */
async function api(url, { method = 'GET', body = null, isForm = false } = {}) {
  try {
    const resolvedUrl = /^https?:\/\//.test(url) ? url : `${window.APP_BASE}/${url}`;
    const opts = { method, headers: {} };
    if (body) {
      if (isForm) {
        opts.body = body; // FormData sets its own multipart headers
      } else {
        opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        opts.body = new URLSearchParams(body).toString();
      }
    }
    const res = await fetch(resolvedUrl, opts);
    const data = await res.json().catch(() => ({ ok: false, message: 'Unexpected server response.' }));
    if (!res.ok && data.ok === undefined) data.ok = false;
    return data;
  } catch (err) {
    return { ok: false, message: 'Network error. Please check your connection and try again.' };
  }
}

function toast(message, type = 'info') {
  const stack = document.getElementById('toast-stack');
  if (!stack) return;
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => el.remove(), 4200);
}

/** Reads the CSRF token that each page embeds as <meta name="csrf-token">. */
function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

// ---------------- Sidebar (mobile drawer) ----------------
(function initSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const openBtn = document.getElementById('hamburgerBtn');
  const closeBtn = document.getElementById('sidebarClose');
  if (!sidebar) return;

  const open = () => { sidebar.classList.add('open'); overlay?.classList.add('open'); };
  const close = () => { sidebar.classList.remove('open'); overlay?.classList.remove('open'); };

  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  overlay?.addEventListener('click', close);
})();

// ---------------- Dropdowns (notifications, profile menu) ----------------
document.querySelectorAll('.dropdown').forEach((dropdown) => {
  const btn = dropdown.querySelector('button');
  btn?.addEventListener('click', (e) => {
    e.stopPropagation();
    const willOpen = !dropdown.classList.contains('open');
    document.querySelectorAll('.dropdown.open').forEach((d) => d.classList.remove('open'));
    if (willOpen) dropdown.classList.add('open');
  });
});
document.addEventListener('click', () => {
  document.querySelectorAll('.dropdown.open').forEach((d) => d.classList.remove('open'));
});

// ---------------- Mark all read (header dropdown) ----------------
document.getElementById('markAllReadLink')?.addEventListener('click', async (e) => {
  e.preventDefault();
  const data = await api('api/mark_notifications_read.php', { method: 'POST', body: { csrf: csrfToken() } });
  if (data.ok) {
    document.getElementById('unreadBadge')?.remove();
    document.querySelectorAll('.notif-row.unread').forEach((row) => row.classList.remove('unread'));
    e.target.remove();
  }
});

// ---------------- Live notification badge polling ----------------
// Keeps the bell badge fresh even if a notification arrives (e.g. a
// reservation auto-expiring) while the student is sitting on another
// page. Every 20s is plenty for a canteen app and keeps load light.
(function pollNotifications() {
  const badgeHolder = document.getElementById('notifBtn');
  if (!badgeHolder) return;

  async function refresh() {
    if (document.hidden) return;
    const data = await api('api/check_status.php');
    if (!data.ok) return;
    let badge = document.getElementById('unreadBadge');
    if (data.unread_count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.id = 'unreadBadge';
        badge.className = 'badge-dot';
        badgeHolder.appendChild(badge);
      }
      badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
    } else if (badge) {
      badge.remove();
    }
  }
  setInterval(refresh, 45000);
})();

// ---------------- Generic modal helpers (used by canteen.js / complaints.js) ----------------
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach((overlay) => {
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('open'); });
});
document.querySelectorAll('[data-close-modal]').forEach((btn) => {
  btn.addEventListener('click', () => btn.closest('.modal-overlay')?.classList.remove('open'));
});
