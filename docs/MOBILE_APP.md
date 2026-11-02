# Smart Canteen — Mobile App (PWA) Guide

The site is now an installable Progressive Web App. On Android Chrome it installs
to the home screen, opens full-screen with its own icon, and behaves like an app.

## What was added
| File | Purpose |
|---|---|
| `manifest.php` | App name, icon, colours, start page, home-screen shortcuts (generated, so it follows wherever you host) |
| `sw.php` | Service worker. Pages and `/api/` calls always go to the network (seat data is live); only static files are cached; offline page if the network drops |
| `offline.html` | Friendly screen shown when the phone has no internet |
| `js/pwa.js` | Registers the service worker; shows an "Install" bar on Android |
| `includes/pwa_head.php` | Manifest + meta tags, included in the `<head>` of all 18 pages |
| `images/icons/` | 192/512 px icons, a maskable icon for Android, Apple touch icon |

## Step 1 — Host it online (required)
A phone cannot reach `localhost`. PWAs and the QR camera both require **HTTPS**.
You need a host with PHP 8 + MySQL + free HTTPS (any shared/cPanel host, a VPS, etc.).

1. Create a MySQL database and user in the host's panel.
2. Import `database/schema.hosting.sql`, then `database/seed.hosting.sql` (or skip seed for real data) via phpMyAdmin. (These are copies of the originals without `CREATE DATABASE`/`USE`, which shared hosts reject.)
3. Upload the `smart-canteen` folder contents to the host.
4. Edit `includes/config.php`:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` -> the host's values
   - `BASE_URL` -> `''` if the app is at the domain root, or `'/smart-canteen'` if in a sub-folder
   - `ini_set('display_errors', '0');`
5. Make sure `uploads/complaints/` is writable.
6. Enable HTTPS (free SSL / Let's Encrypt in the host panel).
7. Optional: add a cron job for `cron_expire.php` every minute (see the file header).

## Step 2 — Install on Android
1. Open the HTTPS site in **Chrome** on the phone.
2. Tap the **Install** bar at the bottom, or Chrome menu (⋮) -> **Install app** / **Add to Home screen**.
3. Open "Smart Canteen" from the home screen.

## Step 3 — Share with students
Send them the site link and the two lines above. No Play Store needed.

## Notes
- Needs internet (live seat data). Offline shows the offline screen.
- To ship a new version of the cached files, change `$ver = 'v1'` in `sw.php`.
- The icon was made from the 173 px crest, so it is slightly soft at large sizes. Supply a 512x512 crest to replace `images/icons/icon-512.png` for a sharper icon.
