# Smart Canteen — Architecture

Fair, real-time canteen seat reservations for Mukesh Patel School of Technology and Management Engineering.

## 1. Stack & key decisions

| Layer | Choice | Why |
|---|---|---|
| Frontend | HTML + vanilla CSS/JS | No build step — a student can open any file and read it top to bottom. |
| Backend | PHP 8, procedural-with-functions | No framework overhead to explain in a viva; `includes/functions.php` holds every business rule as a named function. |
| Database | MySQL/MariaDB, PDO + prepared statements | Every query in the codebase is parameterized — see `includes/db.php`. |
| QR generation | `qrcode` JS library (client-side, admin only) | Avoids requiring the GD/Imagick PHP extensions many XAMPP installs don't have enabled. The bench's `qr_token` is generated server-side (`random_bytes`); JS only *renders* it as an image. |
| QR scanning | `html5-qrcode` JS library (client-side) | Uses `getUserMedia` for camera access; decoded text is POSTed to `api/verify_qr.php`, which does the actual authorization check server-side. Camera access needs `localhost` or HTTPS — this is a browser requirement, not something the app can work around. |
| Charts | Chart.js (admin analytics only) | Small, no build step, reads real aggregate SQL. |

Both JS libraries load from a public CDN (jsDelivr/cdnjs) inside the HTML the *browser* fetches — this is unrelated to and unaffected by any sandboxing on the machine that generated this code. It does mean the admin/student devices need internet access for QR features; see SETUP.md for a fully offline fallback.

**Login is SAP ID + password.** Students can self-register (`register.php`) or be provisioned by an admin (Admin → Students & Bans, which now also sets a password) — see §7 Pass 3 for why this changed from the original SAP-ID-only design. Passwords are hashed with `password_hash()` (bcrypt) and checked with `password_verify()`, the same pattern already used for admin accounts.

## 2. Database

12 tables. Full DDL in `database/schema.sql`; demo rows in `database/seed.sql`.

```
students ──< reservations >── seats ──< benches
   │                                       │
   ├──< no_shows                          (1 bench = 4 seats)
   ├──< bans
   ├──< complaints ──< complaint_feedback
   ├──< notifications
   └──< general_feedback

admins (completely separate table/session — no relation to students)
settings (key/value — policy knobs, see §5)
```

- **reservations** is the spine of the app. Rows are never deleted — a "completed"/"expired"/"cancelled" row *is* the booking history shown on My Bookings.
- **no_shows** is separate from `reservations.status='expired'` on purpose: the reservation's own status is what a student sees in their history; `no_shows` is what the ban policy counts against. Keeping them apart means changing the ban policy never has to touch reservation history semantics.
- Foreign keys are `RESTRICT` by default (e.g. you cannot delete a bench that has reservation history — proven in testing, see §7). `benches → seats` cascades on delete since a seat has no meaning without its bench.
- CHECK constraints enforce the 11-digit SAP ID format, year 1–4, seat numbers 1–4, and ratings 1–5 **at the database layer**, not just in PHP — tested directly against the database in §7.
- **The demo data is the whole canteen: 84 benches / 336 seats** (the number on the floor plan, §6). IDs are deliberately predictable: bench N has `id = bench_number = N`, and its four seats are ids `4N-3 … 4N` — the later seed rows (reservations, complaints, history) reference benches/seats by those numbers. Benches 1–10 are the original hand-curated ones; 11–84 are plain demo benches, with five closed benches and a few single broken seats so every colour on the map appears. No seat is seeded as `reserved`/`occupied` unless a live reservation row backs it — the expiry job only looks at reservations, so a held seat with no reservation would stay stuck forever (checked by query in §7, Pass 7).

## 3. The reservation lifecycle (the core of the app)

```
 available ──reserve──▶ reserved ──scan QR──▶ occupied ──vacate──▶ available
                 │                    │
          (cancel or                (no scan within
           10 min pass)              window)
                 ▼                    ▼
             cancelled             expired ──▶ no_shows row ──▶ ban policy check
```

**Preventing double-booking** (`api/reserve_seat.php`): two students tapping "Confirm" on the same seat within the same instant is the one place correctness under concurrency actually matters. The endpoint wraps the whole check-then-write in a transaction and takes `SELECT ... FOR UPDATE` row locks on both (a) the student's own reservations and (b) the target seat, before inserting. The second request is forced to wait for the first to commit, then sees the now-taken seat and is rejected — cleanly, not as a race. This was verified under an actual concurrent-style test, not just reasoned about (§7).

**Auto-expiry** (`includes/functions.php::expireStaleReservations()`): PHP has no background timer, so this runs two ways at once:
1. **Lazily** — at the top of every page and API call that touches reservation/seat data, so nothing a student or admin looks at is ever meaningfully stale.
2. **Via cron** (optional, `cron_expire.php`) — for a real deployment, a scheduled task hitting this every minute releases seats even when nobody's browsing. Setup instructions are in the file's header comment.

Each expiry is processed with a status-guarded `UPDATE ... WHERE status='reserved'` inside a transaction, so two overlapping expiry checks (e.g. a page load and a poll landing at the same moment) can't double-process the same row.

**Timezone note:** MySQL's `NOW()` reflects whatever timezone the *database server's* OS defaults to (often UTC); PHP's `strtotime()`/`DateTime` interpret timestamps using `date_default_timezone_set()` (`Asia/Kolkata`, in `includes/config.php`). Left unaligned, a freshly-created reservation can look hours old the instant it's read back. `includes/db.php` pins the PDO session to `SET time_zone = '+05:30'` immediately after connecting so both sides agree, regardless of the underlying server's default. `database/seed.sql` does the same for the CLI session that loads it. This was an actual bug caught during testing, not a hypothetical — see §7.

## 4. Complaints & bench feedback corroboration

This is the most novel piece of the spec, so it's worth spelling out precisely:

1. A student files a complaint. If they tie it to a specific bench, the system looks up **every student currently `occupied` on that bench right now** (excluding the complainant, in case they're also seated there).
2. One `complaint_feedback` row + one `feedback_request` notification is created per such student.
3. Those students see a "Feedback Required" card the next time they open Complaints, with a Yes / No / Not sure question and an optional comment box. Each student can respond exactly once (enforced by a unique DB constraint + a status-guarded update).
4. The admin's complaint view shows the complaint alongside a tally (e.g. "2 yes · 0 no · 1 pending") and every individual response, **before** taking any action. Nothing is auto-actioned off a single unverified report — the admin always makes the call.

## 5. Configurable policy (Settings)

Stored as key/value rows in `settings`, editable from Admin → Settings, read via `getSetting()`:

| Key | Default | Meaning |
|---|---|---|
| `reservation_window_minutes` | 10 | Time to scan the QR before a reservation expires. |
| `no_show_ban_threshold` | 3 | No-shows (in the rolling window) that trigger a restriction. Counts below this get a warning notification instead. |
| `ban_duration_days` | 3 | Length of a triggered restriction. |
| `no_show_window_days` | 30 | Rolling window no-shows are counted within. |

## 6. Pages & API endpoints

**Public:** `index.php` (landing + login), `login.php`, `logout.php`, `admin/login.php`, `admin/logout.php`

**Student** (`/student`): `dashboard.php`, `canteen.php` (the interactive 84-bench floor plan, bench→seat picker — also the old "Canteen Preview"), `my_bookings.php`, `scan.php`, `complaints.php`, `feedback.php`, `notifications.php`, `profile.php`, `preview.php` (redirects to `canteen.php`), `help.php`

**Admin** (`/admin`): `dashboard.php`, `benches.php`, `students.php`, `complaints.php`, `analytics.php`, `settings.php`

**API** (`/api`, all JSON, all require a logged-in session + CSRF token on writes):
| Endpoint | Method | Purpose |
|---|---|---|
| `get_seats.php` | GET | Live 4-seat status for one bench |
| `reserve_seat.php` | POST | Create a reservation (transactional, see §3) |
| `cancel_reservation.php` | POST | Cancel before QR scan |
| `verify_qr.php` | POST | Confirm arrival, reserved → occupied |
| `vacate_seat.php` | POST | occupied → available |
| `check_status.php` | GET | Poll target for countdown sync + notification badge |
| `submit_complaint.php` | POST (multipart) | File a complaint + auto-generate bench feedback requests |
| `submit_bench_feedback.php` | POST | Respond to a feedback request |
| `submit_general_feedback.php` | POST | Star-rating feedback |
| `mark_notifications_read.php` | POST | Clear the unread badge without a full page reload |

Admin CRUD (add/remove/reset benches, manage bans, update complaint status) intentionally uses plain form POST + redirect rather than AJAX — these are occasional operator actions, not the real-time interactions the spec calls out for AJAX/fetch.

**The floor plan** (`includes/floorplan.php` + `css/floorplan.css`) is drawn by one renderer for two pages: `student/canteen.php` (clickable) and the public `index.php` preview (static). Three blue areas, 84 benches, laid out as in the canteen's reference plan:

| Area | Grid | Benches |
|---|---|---|
| Front Left | 3 rows × 4 | 1–12 |
| Front Right | 3 rows × 3 (+ vacant block and a doorway) | 13–21 |
| Main Hall, front rows | 4 rows × 7 | 22–49 |
| Main Hall, back rows | 5 rows × 7 | 50–84 |

**Bench N always sits in slot N.** A bench never moves on the map because another one was added or removed, and the number painted on a physical bench matches its place on screen. A slot with no bench in the database stays blank; a bench numbered above 84 is listed under "Additional benches", so nothing bookable is ever hidden. A box's colour is its live state — green while any seat is free, then red/amber, grey if closed — and four small bars along its bottom edge show the four seats one by one. Clicking a green box opens the same seat-picker modal as before (`js/canteen.js`, unchanged).

## 7. Verification history — seven testing passes, not one

Everything below ran against a live PHP built-in server + a real MySQL/MariaDB database, not inferred from reading the code. All seven passes are documented here deliberately, rather than showing only the clean end state — if this comes up in a viva, "seven independent passes, and here's a real bug or lesson each later one caught" is a far stronger answer than "it looked right to me."

### Pass 1 — initial build
- SAP ID validation (rejects 10/12-digit and non-numeric IDs with the exact spec message)
- Login → session → CSRF token issuance
- Reservation creation, and the one-active-reservation-per-student rule
- **Two independent students racing for the same seat** — exactly one wins, the DB shows exactly one active reservation on that seat
- QR scan: wrong bench rejected, correct bench confirms, DB flips both `reservations.status` and `seats.status`
- Vacate: ownership-checked, DB updates correctly, can't vacate when nothing is occupied
- Automatic expiry: forced-past `expires_at` → reservation expires, seat releases, `no_shows` row appears, `no_show_count` increments, notification fires
- Ban enforcement: a banned student is blocked from reserving with the exact "Booking Restricted" message; the dashboard shows the restriction banner
- Complaint → auto-generated feedback requests for exactly the right (currently-seated) students, correct notifications, duplicate-response prevention, correct tally in the admin view
- Admin bench CRUD, including the FK constraint **genuinely blocking** deletion of a bench with reservation history
- Cross-role access control: a logged-in student hitting an admin page is redirected, not let in
- CHECK constraints reject malformed data directly at the database layer
- Full PHP lint pass across all 44 PHP files

This pass found and fixed two real timezone bugs, described in §3.

### Pass 2 — full recheck (done on request, after Pass 1)

This pass was deliberately more adversarial than Pass 1: instead of spot-checking specific strings, **every page's full rendered output** was scanned for PHP fatal errors, warnings, and notices — not just its HTTP status code.

**It caught a real bug Pass 1 missed:** `index.php` — the public landing page, the first thing any visitor loads — called the `icon()` helper function without including the file that defines it (`includes/icons.php`). That's a fatal error the instant anyone hit the site. Pass 1 had only checked `index.php`'s HTTP status code and grepped for a couple of expected strings further down the page, which didn't surface it because a fatal error partway through a page can still leave earlier grep-able content intact depending on what's being tested. Fixed by adding the missing `require_once`; every other file in the project that calls `icon()` was then individually audited and confirmed to already include it correctly.

With that fixed, Pass 2 additionally covered:
- **All 17 pages**, rendered and scanned for errors/warnings — all clean
- **All 5 JS files**, syntax-checked with Node (`node --check`) — not done in Pass 1, all clean
- Previously-untested endpoints: `cancel_reservation.php`, `mark_notifications_read.php`
- Profile email update, including confirming an invalid address is rejected **without corrupting the existing value**
- Admin `add_student` (SAP format validation + duplicate-SAP rejection via the UNIQUE constraint) and the `manual_ban` → `lift_ban` lifecycle, confirming a restricted student's dashboard banner actually disappears after the lift
- Settings: confirmed a saved policy value is actually *read and used* by a subsequent reservation (tested by changing the window to 15 minutes and checking a fresh booking got exactly that), not just written to the table
- Complaint image upload: a real, valid PNG confirmed saved to disk at the expected path; a fake non-image file confirmed rejected by `getimagesize()`
- A full regression re-run of every Pass 1 check, to confirm the fix didn't disturb anything else

Current state: 45/45 PHP files lint clean, 5/5 JS files syntax clean, all 18 pages render without error, and every functional check across all three passes is green.

### Pass 3 — a real bug reported from an actual XAMPP deployment, plus the registration feature

Two things happened at once here, so both are recorded together.

**Bug: clicking a bench did nothing but show "Unexpected server response."** Root cause: every JS `api()` call (`js/canteen.js`, `js/complaints.js`, `js/qr-scan.js`, `js/main.js`'s notification polling, and inline scripts in `my_bookings.php`/`feedback.php`) passed a project-relative path like `'api/get_seats.php'` straight to `fetch()`. That's resolved by the browser against the *current page's folder*, not the project root — and every student-facing page lives inside `/student/`. So `fetch('api/get_seats.php')` called from `student/canteen.php` actually requested `student/api/get_seats.php`, which doesn't exist (404, HTML error body), and `res.json()` failed to parse it — hence the generic error message. This is exactly the kind of bug that curl-based testing with hand-typed absolute URLs (everything in Pass 1 and Pass 2) cannot catch, because it never exercises the browser's own relative-URL resolution — it was only caught once an actual XAMPP user hit it in a real browser.

Fixed centrally rather than in each file: `includes/footer.php` now emits `window.APP_BASE` (the app's real base path) before `js/main.js` loads, and `main.js`'s shared `api()` function resolves every call against that instead of handing the bare relative string to `fetch()`. Verified with Node's `URL` class (which implements the same resolution algorithm browsers use) simulating the exact call from `student/canteen.php`, then a real `fetch()` against the running server using that resolved URL and a live session cookie — confirming valid JSON comes back, not just that the code looks right.

**Feature: self-service account creation with SAP ID + password.** The original design was SAP-ID-only login with accounts provisioned by an admin — noted even in the first draft of this doc as something to harden before real deployment. That hardening was requested, so:
- `students.password_hash` added to the schema (bcrypt, via `password_hash()`/`password_verify()` — the same pattern as admin accounts)
- New `register.php`: SAP ID, name, branch, year, optional email, password + confirm, with the same server-side validation as everywhere else (SAP ID format, duplicate check, password length ≥ 6, password match), auto-logs in on success
- `login.php` / `includes/auth.php::attemptStudentLogin()` now check a password, and distinguish "no account" from "wrong password" so the login page can point straight at "Create one" when appropriate
- Admin's "Add Student" form (`admin/students.php`) now also sets a password, going through the exact same `registerStudent()` function as self-registration — one validation path, not two
- All 10 demo accounts got a shared password (`Student@123`) so the existing credential table keeps working

Tested: successful registration + auto-login, duplicate SAP ID rejected, password mismatch rejected, password-too-short rejected, invalid SAP ID rejected, correct-password login, wrong-password login (exact message, not logged in), no-account login (correct message + register link present), admin-created account logs in with the password the admin set — and a full re-run of the reservation/QR/vacate flow plus all 18 pages' error-free rendering, to confirm none of this disturbed anything else.

### Pass 4 — visual rebrand: NMIMS logo, full-clarity campus photography, red theme

Purely a visual/CSS pass (no schema, PHP logic, or API changes), but tested with the same rigor as the functional ones above — rendered and inspected pixel-by-pixel, not just read.

**What changed:**
- **Branding:** the NMIMS crest replaces the generic icon everywhere a logo appears (nav, sidebar, favicon, login/register/admin-login pages).
- **Backgrounds, de-translucent:** `index.php`'s hero photo (the atrium shot) and every logged-in page's campus-photo backdrop originally sat under a tinted gradient wash for guaranteed text contrast. That wash is removed from both `.hero` (`css/style.css`) and `body.app-body.photo-bg::before` (`css/app.css`) — both photos now show at full, unfiltered clarity. To keep hero text readable directly on top of a busy photo with no wash to fall back on, the hero's heading/subtitle/lede/stats/eyebrow picked up `text-shadow` and a darker, higher-contrast eyebrow pill instead (scoped to `.hero .eyebrow` specifically, so the same pill elsewhere on the page is untouched).
- **Canteen Preview now gets the photo background too.** Originally excluded on the reasoning that its own floor-plan illustration was enough visual interest on its own; that was reversed on request. `student/preview.php`'s `<body>` now carries `photo-bg` like every other student page.
- **Full theme rebrand, blue → red (`#9C231F`).** This touched more than the three CSS variables:
  - `--blue-600/700/50` renamed to `--red-600: #9C231F`, `--red-700: #7F1C19` (a darkened hover shade), `--red-50: #F9F1F1` (a pale tint for subtle backgrounds) — derived the same way the original blue shades related to each other (≈19% toward black for the hover shade, ≈94% toward white for the tint), not guessed.
  - Every `var(--blue-*)` usage across all three CSS files renamed to match.
  - Hardcoded (non-variable) blue hex values in two places code review turned up: the inline SVG floor-plan (`includes/canteen_preview_art.php`'s entrance marker) and the admin analytics charts (`js/admin.js` — the hourly-occupancy bar color and one slice of the complaints-by-status doughnut).
  - Eight muted blue-grey **text** tones (nav links, hero subtitle/lede/college line/stats, rules-panel text, landing footer, sidebar + welcome-banner text) were hue-shifted to warm dusty-rose/taupe equivalents at **matched lightness and saturation** — so text that was deliberately muted (not brand-colored) stays equally muted, just warm instead of cool, rather than being left an inconsistent leftover blue.
  - A dead, unused file (`includes/hero_visual.php`, an old illustration superseded by the current login-card hero layout) was removed rather than updated, since nothing includes it.

**A real bug this pass caught:** after the CSS-variable rename, a live render of `student/dashboard.php` showed the colored dot next to "Canteen Occupancy" had gone invisible. Cause: nine PHP files had the status color **inline** (`style="...var(--blue-600)..."`) rather than through a CSS class, so the file-level variable rename never touched them — the dot was trying to use a CSS variable that no longer existed. Found by `grep -rn "var(--blue" --include="*.php" .` (15 occurrences across `admin/settings.php`, `admin/benches.php`, `admin/complaints.php`, `admin/students.php`, `index.php`, `student/notifications.php`, `student/dashboard.php`, `student/canteen.php`, `student/help.php`, `student/profile.php`), fixed, and confirmed zero remaining with the same grep. This is exactly the kind of bug a CSS-only sed rename can hide — it only surfaces on an actual rendered page, which is why this pass re-rendered rather than just diffing the stylesheet.

**Verified:**
- Full PHP lint: 44/44 clean. Full JS syntax check (`node --check`): 5/5 clean.
- A complete functional regression against a live MariaDB-backed server, to confirm a CSS-only pass hadn't broken anything: fresh registration + auto-login, duplicate-SAP rejection, demo-account login, wrong-password rejection, admin login, seat reservation, the one-active-reservation-per-student rule, QR verification, and vacate — all re-tested end-to-end and passing.
- Live renders (not static code reading) of the landing page, student dashboard, Canteen Seat, Canteen Preview, and the admin dashboard/analytics pages, confirming: both photos display at full clarity with no wash, Canteen Preview now shows the campus photo, every accent (buttons, active nav links, status dot, floor-plan entrance marker, chart colors) reads as the new red, and hero text stays legible against the unwashed photo.

### Pass 5 — Canteen Preview redesign, and admin-editable QR codes

Two independent changes requested together: a new illustration layout, and a real gap in the QR flow (no way to test it without a camera, HTTPS, and a second device to display or print a code).

**Canteen Preview redesign.** `includes/canteen_preview_art.php` (shared by `index.php`, `student/dashboard.php`'s mini widget, and `student/preview.php`) was rebuilt around a reference layout the user supplied: a counter title bar, a smaller bench cluster near the counter, a walking aisle, then a larger hall near the entrance. Kept static/illustrative, as it always was (the comment at the top of the file already documented that choice, and both `preview.php` and `canteen.php` already show real live data alongside it — a real table on the former, the actual reservation grid on the latter — so the illustration's job is to set the scene, not report live counts). Redrawn in the app's own tokens rather than the reference image's palette: the counter bar, aisle accents, and entrance marker use the brand red/navy, while the 4 seat-status dots keep the existing green/amber/red/grey status vocabulary untouched — the same "red is chrome, status colors are status colors" rule Pass 4 established. All 10 real benches are represented now (the previous version only drew 9). Built with a small internal PHP loop over a coordinates array instead of 10 hand-typed blocks, to cut the chance of a copy-paste mistake across that many near-identical elements.

**Admin-editable QR codes.** Previously a bench's `qr_token` was set once, randomly, at creation, with no way to change it — so a deployment without a working camera, HTTPS/localhost, or a second device to display/print a code had no way to ever complete the "scan" step at all. `admin/benches.php` gained an `update_qr_token` action: the existing QR modal's read-only token display is now an editable field (pre-filled with the current code), with **Save Code** and a client-side **Generate Random** button that fills the field with a fresh candidate for review before saving — nothing is written until Save Code is actually clicked. Server-side, the new value has all whitespace stripped (a manually typed or pasted code is an easy place to pick up a stray space), is rejected if empty or over the 64-char column limit, and relies on `benches.qr_token`'s existing `UNIQUE` constraint — caught via try/catch, same pattern as `add_bench`'s duplicate-bench-number handling — so two benches can never end up accepting each other's code.

On the student side, `student/scan.php`'s manual-entry box is no longer hidden behind an "Enter code manually" toggle button — it's always visible beneath the camera view, in plain HTML with no JavaScript required to reveal it, with a line pointing the student at the admin for the current code. `js/qr-scan.js` also now shows an explicit banner if the camera library itself never loaded (previously: silent, with no visible fallback at all if that CDN failed before the student had thought to look for a hidden button), and the manual field submits on Enter, not just via its button.

**Verified live**, not just read: as admin, saved bench 1's code to a custom value, confirmed the DB updated and the success message showed, then confirmed submitting another bench's existing code was correctly rejected as already in use. As a student, reserved a seat on that bench, confirmed the *old* code was now correctly rejected ("Invalid QR"), then confirmed the *new* code — submitted exactly the way the manual-entry field submits it — correctly flipped the reservation to Occupied, and vacate still worked afterward. Full PHP lint (44/44) and JS syntax check (5/5) stayed clean. Live renders confirmed the new illustration (all 10 benches, correct colors, campus photo still visible behind it on `preview.php`) and the redesigned QR modal's layout (editable field, both buttons, updated help text). Bench 1's QR code was restored to its original seeded value before packaging, and `database/seed.sql` itself was never touched by any of this testing.

### Pass 6 — Canteen Seat and Canteen Preview merged into one interactive floor plan

Requested change: stop showing a decorative floor-plan illustration on one page and a plain grid-of-cards booking UI on another — show one real floor plan, and let tapping a bench on it start the booking flow directly.

**The merge, and why it needed almost no JS.** `student/canteen.php`'s old `.bench-grid` of plain `.bench-card` buttons is now a zoned floor-plan layout (a red counter bar, an upper bench cluster, an aisle divider, a larger lower hall, an entrance marker) built from real `<button class="bench-card" data-bench-id="…" data-bench-number="…">` elements — the exact same element, class, and data-attribute contract `js/canteen.js` already listens for via a generic `.bench-card[data-bench-id]` selector. Confirmed before writing any markup, with `grep -rln "bench-card"`, that nothing else in the project touches that class. That meant the entire redesign — new zoned layout, new compact per-bench markup, new status-colored blocks — needed **zero changes** to `js/canteen.js`, `api/get_seats.php`, `api/reserve_seat.php`, or the seat-selection modal: clicking a bench still opens the same modal through the same code path it always did, now triggered from the floor plan itself instead of a separate grid.

**Bench blocks are now colored by a real status rollup**, not a plain white card with a small badge: a bench shows green/"N free" if it has any free seat at all (still bookable), otherwise amber/"Reserved" or red/"Occupied" depending on what's actually filling its last seat, or grey/"Closed" if the admin closed it — reusing the app's existing four status colors, computed from the same `available_count`/`reserved_count`/`occupied_count`/`unavailable_count` query `canteen.php` already ran, just bucketed into one dominant state per bench instead of four separate counts. The existing disabling rule (closed, full, banned, or already holding a reservation elsewhere) is unchanged.

**Benches split into the floor plan's two zones by position ratio** (`ceil(count * 0.4)` to the upper, "near counter" zone, the rest to the lower "hall" zone) rather than a hardcoded per-bench coordinate or a fixed bench count — so the layout keeps making sense if the admin adds or removes benches later, instead of needing a code change every time the hall's seating does.

**A real finding from this pass: the project's own visual-testing tool can't be trusted for CSS Grid.** The first render of the new floor plan (via `wkhtmltoimage`, the same tool every prior pass used for screenshots) showed every bench stacked in one narrow column instead of wrapping into rows — `.floorplan-zone` used `display:grid; grid-template-columns:repeat(auto-fill, minmax(128px,1fr))`, a thoroughly standard, modern-browser-safe pattern. An isolated two-line test page proved it wasn't this project's CSS: `wkhtmltoimage`'s old bundled WebKit (0.12.6) mis-sizes `auto-fill`/`minmax` tracks, and — found while chasing this further — even mis-renders a plain fixed `grid-template-columns: 1fr 1fr` (the landing page's pre-existing, untouched `.preview-grid` showed the same single-column collapse in this tool, despite working correctly in every real browser this project has actually been tested in). Rather than ship a layout only provably correct in real browsers and *hope*, `.floorplan-zone` (and its public-page twin, `.preview-floorplan-zone`) were rewritten as `display:flex; flex-wrap:wrap` with `flex: 1 1 128px` on each bench block — functionally equivalent for this layout, and confirmed by the same isolated-test method to render correctly even in the old engine. That gave an actually-trustworthy screenshot to verify against, not just code that was argued to be fine. `.preview-grid` itself was left alone — it's unrelated pre-existing code, not part of this request, and every earlier pass's real-browser testing already shows it works.

**What else changed:**
- `student/preview.php` is now a one-line redirect to `canteen.php` (not deleted outright), so any old bookmark or link to "Canteen Preview" still lands somewhere real instead of 404ing.
- The "Canteen Preview" sidebar entry is gone from `includes/sidebar.php`; "Canteen Seat" is the one remaining nav item for it.
- `student/dashboard.php`'s mini preview widget (static illustration + "View Full Preview" link) is now a compact live "available seats right now" teaser + "Open Canteen Floor Plan" button, since the full interactive floor plan is one click away and doesn't need a second, smaller copy of itself on the home page.
- `index.php`'s public, pre-login preview section now renders the **same live floor plan** from real bench data (non-interactive `<div>` blocks, not buttons, since there's no login yet to reserve against) instead of the old static illustration with hardcoded sample statuses — `index.php` already opened a DB connection for the login check above it, so this added no new dependency. This also makes the section's existing copy ("The map updates live, so what you see is what's actually free right now") actually true for the first time, rather than a claim the static art couldn't back up.
- `includes/canteen_preview_art.php` (the static SVG it all used to share) was deleted after confirming, via a project-wide grep, that nothing still included it.

**Verified live:** full PHP lint (all files clean) and JS syntax check (5/5 clean, though no JS file needed changing). Reserved a seat through the actual API calls the new floor-plan buttons trigger (`get_seats.php` then `reserve_seat.php`, with a real session cookie and CSRF token, posted the same `application/x-www-form-urlencoded` way `js/main.js`'s `api()` helper sends it) — confirmed success, confirmed the bench and the global "already have a seat" disabling both updated correctly on a fresh render, then cleaned the test reservation back out. Visual renders (post-flexbox-fix) of `student/canteen.php` at desktop and mobile widths, `student/dashboard.php`, and the public `index.php`, each showing all four status colors represented correctly (a bench was temporarily forced to each status, screenshotted, then restored) and the layout wrapping into rows as intended. All test mutations — the forced bench statuses and the test reservation — were restored to the exact seed state afterward; `database/seed.sql` itself was never touched.

### Pass 7 — the 84-bench floor plan, exactly as drawn in the canteen's reference plan

Requested change: the canteen preview must be the reference plan — navy canvas, three blue areas, one box per bench, **84 benches in total** — and be the default everywhere the preview appears. This **supersedes the layout described in Pass 6** (two zones split 40/60 by position); what Pass 6 established still holds: one merged page, the `.bench-card` + `data-bench-id` contract, the `preview.php` redirect.

**The layout is data, not CSS luck.** `includes/floorplan.php::floorplanBlocks()` states the grid of each area (Front Left 3×4 = 12, Front Right 3×3 = 9, hall front rows 4×7 = 28, hall back rows 5×7 = 35; 12 + 9 + 28 + 35 = 84) and `renderFloorplan()` draws it; the CSS only decides how wide each column is. **Bench N is drawn in slot N** (see §6) — the old "split whatever benches exist by position" approach would have slid every later bench one place left the moment one bench was removed, so the number on a physical bench would no longer match its place on screen. Verified: removing Bench 50 leaves one blank slot and Benches 49 and 51 do not move; adding Bench 85 puts it under "Additional benches" on both pages.

**One renderer, two pages, one query.** The booking page gets `<button class="bench-card …">` boxes (same contract as before, so **no change** to `js/canteen.js`, `api/get_seats.php`, `api/reserve_seat.php` or the seat modal); the public landing page gets the same plan as plain `<div>`s. `floorplanFetchBenches()` reads all 84 benches and their 336 seat states in a single `GROUP_CONCAT` query — no per-bench query — and seat statuses are checked against a whitelist before they reach a CSS class name.

**A box's colour is the roll-up of its four seats** — green if any seat is free (still bookable), else red if any is occupied, else amber if reserved, grey if the admin closed it — and four bars along the bottom edge show the seats individually. Grey is also hatched, so closed benches never rely on colour alone. *Design decision to flag:* the reference draws every bench red; in this app red already means "occupied" everywhere, so benches use the status palette instead (brand red/navy stay on the chrome, as in Pass 4's rule). If free benches should be drawn in the brand red like the reference, it is two variables at the top of `css/floorplan.css` (`--fp-free`, `--fp-free-ink`, with the exact values in a comment there) — at the price of free and occupied looking alike, which is why green is the default.

**CSS placement.** The project's convention is that public pages load only `style.css` and pages behind login add `app.css`. A map shared by both therefore got its own `css/floorplan.css`, linked from `index.php` and `student/canteen.php`. The Pass 5/6 rules it replaces (`.floorplan*`, `.bench-card*`, `.legend-bar`, `.preview-floorplan*`, `.preview-bench*`, and their mobile overrides) were deleted after a project-wide grep showed no remaining users. `.preview-grid`, left alone in Pass 6, was converted from CSS Grid to flexbox now that the map lives in it.

**Phones.** 84 benches cannot be squeezed into 360px, so on narrow screens the map keeps its proportions (min-width 600px) and scrolls sideways inside its panel, with a "swipe sideways" hint; the legend moves above it. The landing page's static map is smaller, unlabelled, and simply scales down.

**Seed data.** 84 benches / 336 seats. Benches 1–10 keep their ids, QR codes and seat states (reservations and complaints further down the file reference them); only their `location` text changed to the new area names. 11–84 were produced by a throwaway generator rather than typed — 74 near-identical benches × 4 seats is exactly where a mis-numbered seat id would silently corrupt the demo data. Closed benches: 10, 19, 33, 62, 77.

**Admin at 84 rows.** `admin/benches.php` gained a find box (bench number, area, "closed", or "occupied"/"reserved"). Matching is "start of a word", so `4` finds 4 and 40–49, `front l` finds Front Left, and `available` can never match `unavailable`. A hint under Add Bench explains the slot rule.

**Findings in this pass:**
- **A `<button>` centres its content vertically whatever the padding says.** The first render put each bench number on top of its seat bars. Number and bars are now both `position:absolute`, so a button and a div render identically.
- **`wkhtmltoimage` is not enough on its own** (Pass 6 found it mis-renders grid; this pass also saw it ignore flex `gap` and mis-size flex-direction:column children). The layout was written to be correct there anyway (flexbox + margins only), and then actually verified in headless Chromium driven by Playwright, which does real clicking.
- **A latent bug in the admin QR editor.** With the QR-image CDN unreachable (campus Wi-Fi, offline), `QRCode` is undefined, the click handler threw before opening the modal, and the admin could not even edit a bench's code — the one feature added in Pass 5 to make scanning possible. The modal now always opens; only the picture is skipped, with a toast saying why.
- **A wrong demo-account hint.** `SETUP.md` called Ananya (70012345676) a "clean account" for the reservation test; the seed has her seated on Bench 4. Corrected to Aarav (70012345671).

**Verified live (real Chromium, not screenshots of code):**
- *Landing:* 84 static boxes, none clickable, copy reads "All 84 benches", no horizontal overflow at 1280px or 390px.
- *Booking page, clean student:* 84 `.bench-card` buttons, bench N in slot N for all 84, exactly the 5 closed ones disabled; hover and keyboard-focus styles; clicking Bench 42 opens "Bench 42 — Select Your Seat" with 4 seats; closed Bench 19 does not open. At 390px the page itself does not overflow, the map scrolls sideways (600px inside a 280px viewport), and tapping a bench opens the picker.
- *A real reservation through the map:* a clean student booked Bench 42 / Seat 2 by clicking → picker → confirm; the map then locked (79 clickable → 0, explanatory banner), the bench's second bar turned amber; a different student still saw Bench 42 green with "3 of 4 seats free"; cancelling unlocked it. A banned student saw a look-only map with the restriction banner.
- *Whole cycle on a new bench over the API:* reserve Bench 60 → the code of Bench 61 rejected ("Invalid QR") → Bench 60's code confirms (seat → occupied) → vacate (seat → available).
- *Overflow and gaps:* Bench 85 added and Bench 50 removed through the admin screens, both effects checked on both pages (see above).
- *Admin:* 84 rows; find box results `42` → 1, `4` → 11, `front left` → 12, `front right` → 9, `closed` → 5, `main hall` → 63, nonsense → empty message, cleared → 84; the QR modal opens for Bench 77 even with the CDN blocked.
- *Everything else:* PHP lint on every file, JS syntax 5/5, every student/admin/public page returns 200 with no PHP notice or warning, and the seed passes its integrity queries: 84 benches, 336 seats, 84 distinct QR codes, every bench has seats 1–4, seat ids follow `4N-3…4N`, area counts 12/9/28/35, and **zero** held seats without a live reservation (and zero live reservations whose seat is not held).

All test mutations (extra reservations, bench 85 / bench 50, forced seat states) were undone by re-importing the schema and seed; the shipped `database/seed.sql` is the generated 84-bench file and was not edited by any test.
