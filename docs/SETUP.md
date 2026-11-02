# Smart Canteen — Setup & Testing Guide

> **Verification status:** this build has been through seven independent testing passes against a live PHP + MySQL stack (not just read-through) — including several real bugs found and fixed along the way, one of them reported from an actual XAMPP deployment. Full details, including what broke and why, are in `ARCHITECTURE.md` §7. Nothing below assumes an untested build.

## 1. Requirements

- XAMPP (PHP 8.1+ and MySQL/MariaDB) — [apachefriends.org](https://www.apachefriends.org/)
- A browser with camera access for QR scanning (works on `localhost` without HTTPS)
- Internet access on the device running the browser, for two small CDN-hosted JS libraries (QR generation/scanning, charts). No internet is needed for anything else — see the offline note at the bottom.

## 2. Install

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy the `smart-canteen` folder into your XAMPP `htdocs` directory, so you have:
   ```
   C:\xampp\htdocs\smart-canteen\index.php      (Windows)
   /Applications/XAMPP/htdocs/smart-canteen/index.php   (Mac)
   /opt/lampp/htdocs/smart-canteen/index.php     (Linux)
   ```

## 3. Create the database

> **Already set this up before, from an earlier version of this project?** The `students` table now includes a password column, so you need to re-import `schema.sql` (not just `seed.sql`) — it drops and recreates every table. Any data you'd created before this will be wiped in the process.

**Option A — phpMyAdmin (easiest):**
1. Open `http://localhost/phpmyadmin`
2. Click **Import** → choose `database/schema.sql` → Go
3. Click **Import** again → choose `database/seed.sql` → Go

**Option B — command line:**
```bash
mysql -u root < database/schema.sql
mysql -u root smart_canteen < database/seed.sql
```

This creates the `smart_canteen` database, all 12 tables, and demo data: 10 students, the **whole canteen — 84 benches / 336 seats** (mostly free, with a few closed benches and a few held seats so every colour shows on the map), plus sample reservations, complaints, bans, and feedback.

## 4. Configure the connection

Open `includes/config.php`. The defaults match a stock XAMPP install (user `root`, no password):
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_canteen');
define('DB_USER', 'root');
define('DB_PASS', '');
```
Only change these if your MySQL uses a different user/password/port.

If you placed the project somewhere other than `/smart-canteen` under `htdocs`, update `BASE_URL` in the same file to match.

## 5. Run it

Visit **`http://localhost/smart-canteen/`**.

## 6. Demo credentials

All student demo accounts share one password: **`Student@123`**

| Role | Login | Notes |
|---|---|---|
| Admin | username `admin`, password `Admin@123` | at `/smart-canteen/admin/login.php` |
| Student — clean | SAP ID `70012345671` (Aarav Shah) | no active booking |
| Student — mid-reservation | SAP ID `70012345672` (Priya Mehta) | has a live "reserved" seat with the countdown running — log in as her to test QR scanning immediately |
| Student — occupied | SAP ID `70012345673` (Rohan Gupta) | has an occupied seat — log in as him to test "Vacate Seat" immediately |
| Student — restricted | SAP ID `70012345674` (Sneha Iyer) | has an active booking ban — log in as her to see the restriction banner |
| Student — 1 warning | SAP ID `70012345675` (Kabir Nair) | 1 recorded no-show, not yet banned |
| Students 70012345676–70012345680 | (Ananya, Vivaan, Ishita, Aditya, Meera) | mixed history — see `database/seed.sql` for exact state of each |

Need a fresh account instead? Click **New here? Create an account** on the login page — SAP ID, name, branch, year, optional email, and your own password (min. 6 characters). It logs you in immediately after.

## 7. Testing each flow

### Creating an account
1. From the login page, click **New here? Create an account**.
2. Fill in an unused 11-digit SAP ID, your details, and a password (typed twice).
3. Submit — you're taken straight to the dashboard, already logged in.
4. Try registering the same SAP ID again — you'll get "An account already exists for this SAP ID."
5. Try logging out and back in with the SAP ID + password you just set, to confirm it was saved correctly.

### Reading the canteen map
**Canteen Seat** is the floor plan of the whole canteen, drawn from live data: navy canvas, three areas (Front Left, Front Right, and the Main Hall), **one box per bench, 84 in all**. The number on a box is the bench's number, and bench N always sits in the same spot (spot N, counted left to right and top to bottom through Front Left → Front Right → Main Hall front rows → back rows), so the map matches the numbers on the real benches.

- **Box colour** = the bench right now: green = at least one seat is free, amber = all taken but reserved, red = occupied, grey with stripes = closed. Every box also has **4 small bars along its bottom edge — one per seat** — so you can see which seats are free before you click.
- **Click a green box** to open its seat picker. Grey and red boxes can't be opened.
- On a phone the map keeps its shape and scrolls sideways ("swipe sideways to see the whole hall").
- A bench numbered above 84 (an admin added one) appears under **Additional benches**, below the plan. If a bench is deleted, its spot stays blank — the others never move.
- The same map, without the numbers and unclickable, is the **Canteen Preview** on the public landing page.

### Reservation flow
1. Log in as **70012345671 (Aarav)** or any other clean account (not Ananya — the demo data has her already seated).
2. Go to **Canteen Seat** and click any green bench on the map — for example **Bench 42**.
3. Pick a green seat → **Confirm Reservation**.
4. You're taken to **My Bookings**, where a live countdown is running.
5. Try reserving again from another tab (same login) — you'll be blocked, since only one active reservation is allowed at a time.

### QR verification
No camera, no printed QR codes yet, or testing solo? You don't need either — every bench's code can be set to anything you like from the admin panel, and the **Scan QR** page always has a plain text field that accepts it directly (it isn't hidden behind a "no camera" toggle; it's just there).

1. As **admin** → **Benches & Seats**, type the bench's number in the **find box** above the list (e.g. `42`) and click **QR** next to it. The modal shows the current code in an editable field — change it to something short and memorable (e.g. `TEST1`), or click **Generate Random**, then **Save Code**. (Two benches can never share a code — the form rejects a duplicate.)
2. As a student with an active "reserved" booking, go to **Scan QR**. Camera access needs `localhost` or HTTPS anyway, so on a typical local setup just skip straight to the **Bench QR code** box under "or enter the code yourself" and type the code from step 1 → **Submit Code**.
3. You should see "Seat confirmed successfully!" and the status flips to Occupied.
4. Try entering a *different* bench's code first — you should see "Invalid QR. Please scan the QR code of your reserved bench."
5. If you do have a camera and HTTPS (or `localhost`), **Download PNG** from the same modal and print it, or point the camera at the on-screen canvas from a second device — both decode to the same code checked in step 3.

### Automatic expiry
Waiting 10 real minutes works, but for a quick demo:
1. Log in as **admin** → **Settings** → set **Reservation Window** to `1` minute → Save.
2. Log in as a student, reserve a seat, and simply wait ~70 seconds without scanning.
3. Refresh **My Bookings** (or wait for the 20-second auto-poll) — the booking will show as **Expired** and the seat becomes available again.
4. Check **Notifications** for "Your reservation expired because you did not reach the canteen within 10 minutes." (the message always references the *configured* window at the time of booking).
5. Remember to set the window back to `10` afterward.

*(Alternative, no settings change needed: after reserving, open phpMyAdmin and run `UPDATE reservations SET expires_at = expires_at - INTERVAL 15 MINUTE WHERE status='reserved';`, then refresh any page.)*

### No-show → booking restriction
1. Repeat the expiry test above **3 times** with the same student (with the threshold at its default of 3).
2. After the 3rd no-show, that student will see the **Booking Restricted** banner and be unable to reserve until the restriction (default 3 days) lifts.
3. As admin, go to **Students & Bans** to see the restriction and optionally **Lift Ban** early.

### Complaint + bench feedback
1. Log in as **70012345680 (Meera)**.
2. Go to **Complaints** → submit a complaint, setting **Bench** to Bench 1 (which has seeded students currently occupying it).
3. Log in as **70012345678 (Ishita)** or **70012345673 (Rohan)** (both currently occupy Bench 1 in the seed data) — you'll see a **Feedback Required** card asking whether you experienced the reported issue.
4. Submit a response, then log back in as **admin** → **Complaints** to see the complaint with the tallied responses, and update its status (Under Review → Verified/Rejected → Resolved).

### Admin functionality
- **Dashboard** — live counts (students, reservations, occupancy, no-shows, bans, complaints).
- **Benches & Seats** — find a bench by number, area, `closed`, or `occupied` (84 rows is a long list), add a bench (auto-creates its 4 seats; numbers 1–84 fill that spot on the floor plan), toggle a bench/seat's availability, view, edit, or regenerate a bench's QR code (and download it as a PNG), reset a bench (frees all 4 seats and cancels any live reservation on it), or remove a bench (only allowed if it has no booking history — try deleting Bench 1 to see this protection in action).
- **Students & Bans** — search, add an account, manually restrict/lift a restriction.
- **Complaints** — filter by status, review bench feedback, update outcomes.
- **Analytics** — occupancy by hour, most-used benches, 7-day no-show trend, complaints by status, and key averages.
- **Settings** — adjust the reservation window and no-show/ban policy.

## 8. Offline note

Two features load a small JS library from a public CDN in the *browser*, not the server:
- QR generation/scanning (`qrcode`, `html5-qrcode`)
- Admin analytics charts (`Chart.js`)

Everything else — login, reservations, expiry, complaints, notifications, and the whole canteen floor plan — works with zero internet access, since it's plain PHP/MySQL/CSS. (If the QR library is blocked, the admin QR window still opens so you can view, edit and save a bench's code; only the QR *picture* and its download are unavailable until the library loads.) If your deployment environment has no internet access at all, download those three files once and change the `<script src="https://...">` tags in `admin/benches.php`, `student/scan.php`, and `admin/analytics.php` to point at local copies instead.
