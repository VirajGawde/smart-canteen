-- ============================================================
-- SMART CANTEEN — Demo / Seed Data
-- Run this AFTER schema.sql. Safe to re-run: it deletes existing
-- rows and resets auto-increment counters before reinserting, so
-- re-importing this file puts everything back to the demo state
-- below.
--
-- Uses DELETE + explicit AUTO_INCREMENT reset rather than TRUNCATE:
-- TRUNCATE's interaction with FOREIGN_KEY_CHECKS=0 is inconsistent
-- across MySQL/MariaDB versions (some builds still reject truncating
-- a table another table has a live FK constraint against, even with
-- checks disabled — error 1701). DELETE reliably respects
-- FOREIGN_KEY_CHECKS in every version, so this works everywhere.
--
-- ID map (because later inserts reference these by number):
--   students 1..10  -> Aarav, Priya, Rohan, Sneha, Kabir,
--                      Ananya, Vivaan, Ishita, Aditya, Meera
--   benches  1..84  -> bench_number 1..84 (same value, on purpose)
--   seats    1..336 -> bench 1 = seats 1-4, bench 2 = seats 5-8, ...
--                      bench N = seats (4N-3)..4N
-- ============================================================

USE smart_canteen;

-- Match the app's session timezone (see includes/db.php) so that the
-- NOW()-based timestamps below — e.g. "Priya's reservation, still
-- counting down" — line up with what the live app considers "now",
-- regardless of what timezone the underlying MySQL server defaults to.
SET time_zone = '+05:30';

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM notifications;
DELETE FROM general_feedback;
DELETE FROM complaint_feedback;
DELETE FROM complaints;
DELETE FROM bans;
DELETE FROM no_shows;
DELETE FROM reservations;
DELETE FROM seats;
DELETE FROM benches;
DELETE FROM students;
DELETE FROM admins;
DELETE FROM settings;

-- Reset auto-increment so IDs start from 1 again on every reseed —
-- this file's later INSERTs rely on predictable IDs (see the ID map
-- above). `settings` has no AUTO_INCREMENT column (setting_key is
-- the primary key), so it's skipped.
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE general_feedback AUTO_INCREMENT = 1;
ALTER TABLE complaint_feedback AUTO_INCREMENT = 1;
ALTER TABLE complaints AUTO_INCREMENT = 1;
ALTER TABLE bans AUTO_INCREMENT = 1;
ALTER TABLE no_shows AUTO_INCREMENT = 1;
ALTER TABLE reservations AUTO_INCREMENT = 1;
ALTER TABLE seats AUTO_INCREMENT = 1;
ALTER TABLE benches AUTO_INCREMENT = 1;
ALTER TABLE students AUTO_INCREMENT = 1;
ALTER TABLE admins AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- SETTINGS (admin-configurable policy)
-- ------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
  ('reservation_window_minutes', '10'),
  ('no_show_ban_threshold', '3'),
  ('ban_duration_days', '3'),
  ('no_show_window_days', '30');

-- ------------------------------------------------------------
-- ADMIN  (username: admin / password: Admin@123)
-- ------------------------------------------------------------
INSERT INTO admins (username, password_hash, name) VALUES
  ('admin', '$2y$10$813ZSJ2VpOuXlqRJ8yNMtu66aYcOpZ/G1v1Yvu4.BIOacaID2rZNm', 'Canteen Administrator');

-- ------------------------------------------------------------
-- STUDENTS (id 1..10, in this exact order)
-- ------------------------------------------------------------
-- All 10 demo accounts share one password for simplicity: Student@123
INSERT INTO students (sap_id, password_hash, name, branch, year, email) VALUES
  ('70012345671', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Aarav Shah',      'Computer Engineering',        2, 'aarav.shah@example.edu'),
  ('70012345672', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Priya Mehta',     'Information Technology',      3, 'priya.mehta@example.edu'),
  ('70012345673', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Rohan Gupta',     'Electronics & Telecom',       1, 'rohan.gupta@example.edu'),
  ('70012345674', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Sneha Iyer',      'Mechanical Engineering',      4, 'sneha.iyer@example.edu'),
  ('70012345675', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Kabir Nair',      'AI & Data Science',           2, 'kabir.nair@example.edu'),
  ('70012345676', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Ananya Rao',      'Computer Engineering',        3, 'ananya.rao@example.edu'),
  ('70012345677', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Vivaan Joshi',    'Civil Engineering',           1, 'vivaan.joshi@example.edu'),
  ('70012345678', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Ishita Desai',    'Information Technology',      2, 'ishita.desai@example.edu'),
  ('70012345679', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Aditya Kulkarni', 'Electronics & Telecom',       3, 'aditya.kulkarni@example.edu'),
  ('70012345680', '$2y$10$xD76n5hFtfjuHH5P673PFeoS/ovpQvVteY6t/2R6mL5qMLQtzdWf2', 'Meera Pillai',    'Mechanical Engineering',      4, 'meera.pillai@example.edu');

-- ------------------------------------------------------------
-- BENCHES + SEATS (id 1..84, bench_number = id)
--
-- 84 benches in four areas, matching the canteen floor plan
-- (includes/floorplan.php draws bench N in slot N):
--   1-12   Front Left               (4 x 3 block)
--   13-21  Front Right              (3 x 3 block)
--   22-49  Main Hall - Front Rows   (7 columns x 4 rows)
--   50-84  Main Hall - Back Rows    (7 columns x 5 rows)
-- Benches 1-10 are the original hand-curated demo benches (their ids, QR
-- codes and seat states are referenced by the reservations/complaints
-- further down). 11-84 are plain demo benches: all seats available, except
-- a few closed benches and a few single broken seats. No seat is ever seeded
-- as reserved/occupied unless a reservation row below actually holds it, so
-- nothing can get "stuck" — the map fills up as students really book.
-- ------------------------------------------------------------
INSERT INTO benches (bench_number, location, qr_token, status) VALUES
  (1,  'Front Left', 'SCB-3f8a91d2', 'active'),
  (2,  'Front Left', 'SCB-7c2e04b6', 'active'),
  (3,  'Front Left', 'SCB-a19d5e3f', 'active'),
  (4,  'Front Left', 'SCB-118cba4d', 'active'),
  (5,  'Front Left', 'SCB-e04f7729', 'active'),
  (6,  'Front Left', 'SCB-9b3a6601', 'active'),
  (7,  'Front Left', 'SCB-52d8f4ee', 'active'),
  (8,  'Front Left', 'SCB-cd671a3b', 'active'),
  (9,  'Front Left', 'SCB-0f4482c7', 'active'),
  (10, 'Front Left', 'SCB-6a2e9dd4', 'unavailable'),
  (11, 'Front Left', 'SCB-bb459fdf', 'active'),
  (12, 'Front Left', 'SCB-48993a14', 'active'),
  (13, 'Front Right', 'SCB-e9fff41d', 'active'),
  (14, 'Front Right', 'SCB-c6601970', 'active'),
  (15, 'Front Right', 'SCB-098e7486', 'active'),
  (16, 'Front Right', 'SCB-7d37e306', 'active'),
  (17, 'Front Right', 'SCB-011fcd94', 'active'),
  (18, 'Front Right', 'SCB-858e74df', 'active'),
  (19, 'Front Right', 'SCB-fe52bf9f', 'unavailable'),
  (20, 'Front Right', 'SCB-a9513dba', 'active'),
  (21, 'Front Right', 'SCB-518a6b5c', 'active'),
  (22, 'Main Hall - Front Rows', 'SCB-d546c5d9', 'active'),
  (23, 'Main Hall - Front Rows', 'SCB-3256822a', 'active'),
  (24, 'Main Hall - Front Rows', 'SCB-75d70c1a', 'active'),
  (25, 'Main Hall - Front Rows', 'SCB-7fac6686', 'active'),
  (26, 'Main Hall - Front Rows', 'SCB-daa9826c', 'active'),
  (27, 'Main Hall - Front Rows', 'SCB-8d2b6954', 'active'),
  (28, 'Main Hall - Front Rows', 'SCB-c11910f6', 'active'),
  (29, 'Main Hall - Front Rows', 'SCB-bd412cd6', 'active'),
  (30, 'Main Hall - Front Rows', 'SCB-5de42b93', 'active'),
  (31, 'Main Hall - Front Rows', 'SCB-9bcd5e01', 'active'),
  (32, 'Main Hall - Front Rows', 'SCB-2678ab38', 'active'),
  (33, 'Main Hall - Front Rows', 'SCB-8b7eb6c7', 'unavailable'),
  (34, 'Main Hall - Front Rows', 'SCB-f26e553e', 'active'),
  (35, 'Main Hall - Front Rows', 'SCB-c18fc53e', 'active'),
  (36, 'Main Hall - Front Rows', 'SCB-b79fe01d', 'active'),
  (37, 'Main Hall - Front Rows', 'SCB-42e630a2', 'active'),
  (38, 'Main Hall - Front Rows', 'SCB-868f59e0', 'active'),
  (39, 'Main Hall - Front Rows', 'SCB-77a62af1', 'active'),
  (40, 'Main Hall - Front Rows', 'SCB-660ac3b0', 'active'),
  (41, 'Main Hall - Front Rows', 'SCB-e418ab4b', 'active'),
  (42, 'Main Hall - Front Rows', 'SCB-e5266648', 'active'),
  (43, 'Main Hall - Front Rows', 'SCB-b9c270b5', 'active'),
  (44, 'Main Hall - Front Rows', 'SCB-5487fe3e', 'active'),
  (45, 'Main Hall - Front Rows', 'SCB-9a60ee5a', 'active'),
  (46, 'Main Hall - Front Rows', 'SCB-382f23f9', 'active'),
  (47, 'Main Hall - Front Rows', 'SCB-2032180d', 'active'),
  (48, 'Main Hall - Front Rows', 'SCB-a9006e77', 'active'),
  (49, 'Main Hall - Front Rows', 'SCB-f04777c6', 'active'),
  (50, 'Main Hall - Back Rows', 'SCB-7930be73', 'active'),
  (51, 'Main Hall - Back Rows', 'SCB-0b372b79', 'active'),
  (52, 'Main Hall - Back Rows', 'SCB-834274d1', 'active'),
  (53, 'Main Hall - Back Rows', 'SCB-cf917d54', 'active'),
  (54, 'Main Hall - Back Rows', 'SCB-0b486adc', 'active'),
  (55, 'Main Hall - Back Rows', 'SCB-263ea464', 'active'),
  (56, 'Main Hall - Back Rows', 'SCB-0beefce1', 'active'),
  (57, 'Main Hall - Back Rows', 'SCB-7febcc59', 'active'),
  (58, 'Main Hall - Back Rows', 'SCB-874c5b3c', 'active'),
  (59, 'Main Hall - Back Rows', 'SCB-afe5bdcb', 'active'),
  (60, 'Main Hall - Back Rows', 'SCB-4aabb2b2', 'active'),
  (61, 'Main Hall - Back Rows', 'SCB-892684cd', 'active'),
  (62, 'Main Hall - Back Rows', 'SCB-1b90768e', 'unavailable'),
  (63, 'Main Hall - Back Rows', 'SCB-03043114', 'active'),
  (64, 'Main Hall - Back Rows', 'SCB-3dd205cd', 'active'),
  (65, 'Main Hall - Back Rows', 'SCB-33d1d271', 'active'),
  (66, 'Main Hall - Back Rows', 'SCB-e764f47d', 'active'),
  (67, 'Main Hall - Back Rows', 'SCB-6c27d031', 'active'),
  (68, 'Main Hall - Back Rows', 'SCB-22bd536c', 'active'),
  (69, 'Main Hall - Back Rows', 'SCB-4ae506c1', 'active'),
  (70, 'Main Hall - Back Rows', 'SCB-b421b846', 'active'),
  (71, 'Main Hall - Back Rows', 'SCB-1cd176f0', 'active'),
  (72, 'Main Hall - Back Rows', 'SCB-458d0b6b', 'active'),
  (73, 'Main Hall - Back Rows', 'SCB-1efb4e36', 'active'),
  (74, 'Main Hall - Back Rows', 'SCB-e813aa73', 'active'),
  (75, 'Main Hall - Back Rows', 'SCB-bb2f5756', 'active'),
  (76, 'Main Hall - Back Rows', 'SCB-1a2dfb22', 'active'),
  (77, 'Main Hall - Back Rows', 'SCB-8b4dd165', 'unavailable'),
  (78, 'Main Hall - Back Rows', 'SCB-12c19e87', 'active'),
  (79, 'Main Hall - Back Rows', 'SCB-771ce774', 'active'),
  (80, 'Main Hall - Back Rows', 'SCB-8d41f8be', 'active'),
  (81, 'Main Hall - Back Rows', 'SCB-07875930', 'active'),
  (82, 'Main Hall - Back Rows', 'SCB-25787eb8', 'active'),
  (83, 'Main Hall - Back Rows', 'SCB-c31d818b', 'active'),
  (84, 'Main Hall - Back Rows', 'SCB-c661847c', 'active');

-- 4 seats per bench, generated in order so IDs are predictable (see ID map above)
INSERT INTO seats (bench_id, seat_number, status) VALUES
  (1,1,'occupied'), (1,2,'available'), (1,3,'occupied'), (1,4,'available'),   -- seats 1-4
  (2,1,'reserved'), (2,2,'available'), (2,3,'available'), (2,4,'available'),  -- seats 5-8
  (3,1,'available'),(3,2,'available'),(3,3,'available'), (3,4,'available'),  -- seats 9-12
  (4,1,'available'),(4,2,'occupied'), (4,3,'available'), (4,4,'available'),  -- seats 13-16
  (5,1,'available'),(5,2,'available'),(5,3,'available'), (5,4,'available'),  -- seats 17-20
  (6,1,'available'),(6,2,'available'),(6,3,'available'), (6,4,'available'),  -- seats 21-24
  (7,1,'available'),(7,2,'available'),(7,3,'available'), (7,4,'available'),  -- seats 25-28
  (8,1,'available'),(8,2,'available'),(8,3,'available'), (8,4,'available'),  -- seats 29-32
  (9,1,'unavailable'),(9,2,'available'),(9,3,'available'),(9,4,'available'), -- seats 33-36
  (10,1,'unavailable'),(10,2,'unavailable'),(10,3,'unavailable'),(10,4,'unavailable'),  -- seats 37-40
  (11,1,'available'),(11,2,'available'),(11,3,'available'),(11,4,'available'),  -- seats 41-44
  (12,1,'available'),(12,2,'available'),(12,3,'available'),(12,4,'available'),  -- seats 45-48
  (13,1,'available'),(13,2,'available'),(13,3,'available'),(13,4,'available'),  -- seats 49-52
  (14,1,'available'),(14,2,'unavailable'),(14,3,'available'),(14,4,'available'),  -- seats 53-56
  (15,1,'available'),(15,2,'available'),(15,3,'available'),(15,4,'available'),  -- seats 57-60
  (16,1,'available'),(16,2,'available'),(16,3,'available'),(16,4,'available'),  -- seats 61-64
  (17,1,'available'),(17,2,'available'),(17,3,'available'),(17,4,'available'),  -- seats 65-68
  (18,1,'available'),(18,2,'available'),(18,3,'available'),(18,4,'available'),  -- seats 69-72
  (19,1,'unavailable'),(19,2,'unavailable'),(19,3,'unavailable'),(19,4,'unavailable'),  -- seats 73-76
  (20,1,'available'),(20,2,'available'),(20,3,'available'),(20,4,'available'),  -- seats 77-80
  (21,1,'available'),(21,2,'available'),(21,3,'available'),(21,4,'available'),  -- seats 81-84
  (22,1,'available'),(22,2,'available'),(22,3,'available'),(22,4,'available'),  -- seats 85-88
  (23,1,'available'),(23,2,'available'),(23,3,'available'),(23,4,'available'),  -- seats 89-92
  (24,1,'available'),(24,2,'available'),(24,3,'available'),(24,4,'available'),  -- seats 93-96
  (25,1,'available'),(25,2,'available'),(25,3,'available'),(25,4,'available'),  -- seats 97-100
  (26,1,'available'),(26,2,'available'),(26,3,'available'),(26,4,'available'),  -- seats 101-104
  (27,1,'available'),(27,2,'available'),(27,3,'available'),(27,4,'available'),  -- seats 105-108
  (28,1,'available'),(28,2,'available'),(28,3,'available'),(28,4,'available'),  -- seats 109-112
  (29,1,'available'),(29,2,'available'),(29,3,'available'),(29,4,'unavailable'),  -- seats 113-116
  (30,1,'available'),(30,2,'available'),(30,3,'available'),(30,4,'available'),  -- seats 117-120
  (31,1,'available'),(31,2,'available'),(31,3,'available'),(31,4,'available'),  -- seats 121-124
  (32,1,'available'),(32,2,'unavailable'),(32,3,'available'),(32,4,'available'),  -- seats 125-128
  (33,1,'unavailable'),(33,2,'unavailable'),(33,3,'unavailable'),(33,4,'unavailable'),  -- seats 129-132
  (34,1,'available'),(34,2,'available'),(34,3,'available'),(34,4,'available'),  -- seats 133-136
  (35,1,'available'),(35,2,'available'),(35,3,'available'),(35,4,'available'),  -- seats 137-140
  (36,1,'unavailable'),(36,2,'available'),(36,3,'available'),(36,4,'available'),  -- seats 141-144
  (37,1,'available'),(37,2,'available'),(37,3,'available'),(37,4,'unavailable'),  -- seats 145-148
  (38,1,'available'),(38,2,'available'),(38,3,'available'),(38,4,'available'),  -- seats 149-152
  (39,1,'available'),(39,2,'available'),(39,3,'available'),(39,4,'available'),  -- seats 153-156
  (40,1,'available'),(40,2,'available'),(40,3,'available'),(40,4,'available'),  -- seats 157-160
  (41,1,'available'),(41,2,'available'),(41,3,'available'),(41,4,'available'),  -- seats 161-164
  (42,1,'available'),(42,2,'available'),(42,3,'available'),(42,4,'available'),  -- seats 165-168
  (43,1,'available'),(43,2,'available'),(43,3,'available'),(43,4,'available'),  -- seats 169-172
  (44,1,'available'),(44,2,'available'),(44,3,'available'),(44,4,'available'),  -- seats 173-176
  (45,1,'available'),(45,2,'available'),(45,3,'available'),(45,4,'available'),  -- seats 177-180
  (46,1,'unavailable'),(46,2,'available'),(46,3,'available'),(46,4,'available'),  -- seats 181-184
  (47,1,'available'),(47,2,'available'),(47,3,'available'),(47,4,'available'),  -- seats 185-188
  (48,1,'available'),(48,2,'available'),(48,3,'available'),(48,4,'available'),  -- seats 189-192
  (49,1,'available'),(49,2,'available'),(49,3,'available'),(49,4,'available'),  -- seats 193-196
  (50,1,'available'),(50,2,'available'),(50,3,'available'),(50,4,'available'),  -- seats 197-200
  (51,1,'available'),(51,2,'available'),(51,3,'available'),(51,4,'available'),  -- seats 201-204
  (52,1,'available'),(52,2,'available'),(52,3,'available'),(52,4,'available'),  -- seats 205-208
  (53,1,'available'),(53,2,'available'),(53,3,'available'),(53,4,'available'),  -- seats 209-212
  (54,1,'available'),(54,2,'available'),(54,3,'available'),(54,4,'available'),  -- seats 213-216
  (55,1,'available'),(55,2,'available'),(55,3,'available'),(55,4,'available'),  -- seats 217-220
  (56,1,'available'),(56,2,'available'),(56,3,'available'),(56,4,'available'),  -- seats 221-224
  (57,1,'available'),(57,2,'available'),(57,3,'available'),(57,4,'available'),  -- seats 225-228
  (58,1,'available'),(58,2,'available'),(58,3,'available'),(58,4,'available'),  -- seats 229-232
  (59,1,'available'),(59,2,'available'),(59,3,'available'),(59,4,'available'),  -- seats 233-236
  (60,1,'available'),(60,2,'available'),(60,3,'available'),(60,4,'available'),  -- seats 237-240
  (61,1,'available'),(61,2,'available'),(61,3,'available'),(61,4,'available'),  -- seats 241-244
  (62,1,'unavailable'),(62,2,'unavailable'),(62,3,'unavailable'),(62,4,'unavailable'),  -- seats 245-248
  (63,1,'available'),(63,2,'available'),(63,3,'available'),(63,4,'available'),  -- seats 249-252
  (64,1,'available'),(64,2,'available'),(64,3,'available'),(64,4,'available'),  -- seats 253-256
  (65,1,'available'),(65,2,'available'),(65,3,'available'),(65,4,'available'),  -- seats 257-260
  (66,1,'available'),(66,2,'available'),(66,3,'available'),(66,4,'available'),  -- seats 261-264
  (67,1,'available'),(67,2,'available'),(67,3,'available'),(67,4,'available'),  -- seats 265-268
  (68,1,'available'),(68,2,'available'),(68,3,'available'),(68,4,'available'),  -- seats 269-272
  (69,1,'available'),(69,2,'available'),(69,3,'available'),(69,4,'available'),  -- seats 273-276
  (70,1,'available'),(70,2,'available'),(70,3,'available'),(70,4,'unavailable'),  -- seats 277-280
  (71,1,'available'),(71,2,'available'),(71,3,'available'),(71,4,'available'),  -- seats 281-284
  (72,1,'available'),(72,2,'available'),(72,3,'available'),(72,4,'available'),  -- seats 285-288
  (73,1,'available'),(73,2,'available'),(73,3,'available'),(73,4,'available'),  -- seats 289-292
  (74,1,'available'),(74,2,'available'),(74,3,'available'),(74,4,'available'),  -- seats 293-296
  (75,1,'available'),(75,2,'available'),(75,3,'unavailable'),(75,4,'available'),  -- seats 297-300
  (76,1,'available'),(76,2,'available'),(76,3,'available'),(76,4,'available'),  -- seats 301-304
  (77,1,'unavailable'),(77,2,'unavailable'),(77,3,'unavailable'),(77,4,'unavailable'),  -- seats 305-308
  (78,1,'available'),(78,2,'unavailable'),(78,3,'available'),(78,4,'available'),  -- seats 309-312
  (79,1,'available'),(79,2,'available'),(79,3,'available'),(79,4,'available'),  -- seats 313-316
  (80,1,'unavailable'),(80,2,'available'),(80,3,'available'),(80,4,'available'),  -- seats 317-320
  (81,1,'available'),(81,2,'available'),(81,3,'available'),(81,4,'available'),  -- seats 321-324
  (82,1,'available'),(82,2,'available'),(82,3,'available'),(82,4,'available'),  -- seats 325-328
  (83,1,'available'),(83,2,'available'),(83,3,'available'),(83,4,'available'),  -- seats 329-332
  (84,1,'available'),(84,2,'available'),(84,3,'available'),(84,4,'available');  -- seats 333-336

-- ------------------------------------------------------------
-- RESERVATIONS — live/current state
-- ------------------------------------------------------------

-- Priya (2): fresh RESERVED seat, timer still running -> log in as her to test the QR scan flow
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
VALUES (2, 5, 2, 'reserved', NOW(), NOW() + INTERVAL 10 MINUTE);

-- Rohan (3): OCCUPIED seat -> log in as him to test "Vacate Seat"
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at, confirmed_at)
VALUES (3, 3, 1, 'occupied', NOW() - INTERVAL 15 MINUTE, NOW() - INTERVAL 5 MINUTE, NOW() - INTERVAL 13 MINUTE);

-- Ishita (8): OCCUPIED seat, longer ago
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at, confirmed_at)
VALUES (8, 1, 1, 'occupied', NOW() - INTERVAL 25 MINUTE, NOW() - INTERVAL 15 MINUTE, NOW() - INTERVAL 23 MINUTE);

-- Ananya (6): OCCUPIED seat
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at, confirmed_at)
VALUES (6, 14, 4, 'occupied', NOW() - INTERVAL 40 MINUTE, NOW() - INTERVAL 30 MINUTE, NOW() - INTERVAL 38 MINUTE);

-- ------------------------------------------------------------
-- HISTORY: one no-show (Kabir, id 5) -> triggers a WARNING, not a ban
-- ------------------------------------------------------------
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
VALUES (5, 9, 3, 'expired', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 10 MINUTE);
INSERT INTO no_shows (student_id, reservation_id, occurred_at)
VALUES (5, LAST_INSERT_ID(), NOW() - INTERVAL 1 DAY + INTERVAL 10 MINUTE);
UPDATE students SET no_show_count = 1 WHERE id = 5;

-- ------------------------------------------------------------
-- HISTORY: three no-shows (Sneha, id 4) -> triggers an ACTIVE BAN
-- ------------------------------------------------------------
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
VALUES (4, 17, 5, 'expired', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY + INTERVAL 10 MINUTE);
INSERT INTO no_shows (student_id, reservation_id, occurred_at) VALUES (4, LAST_INSERT_ID(), NOW() - INTERVAL 20 DAY + INTERVAL 10 MINUTE);

INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
VALUES (4, 18, 5, 'expired', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY + INTERVAL 10 MINUTE);
INSERT INTO no_shows (student_id, reservation_id, occurred_at) VALUES (4, LAST_INSERT_ID(), NOW() - INTERVAL 10 DAY + INTERVAL 10 MINUTE);

INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
VALUES (4, 19, 5, 'expired', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY + INTERVAL 10 MINUTE);
INSERT INTO no_shows (student_id, reservation_id, occurred_at) VALUES (4, LAST_INSERT_ID(), NOW() - INTERVAL 2 DAY + INTERVAL 10 MINUTE);

UPDATE students SET no_show_count = 3 WHERE id = 4;

INSERT INTO bans (student_id, reason, start_date, end_date)
VALUES (4, 'Repeated no-shows (3 within 30 days)', NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 1 DAY);

-- ------------------------------------------------------------
-- HISTORY: normal completed / cancelled bookings (for "My Booking" history + analytics)
-- ------------------------------------------------------------
INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at, confirmed_at, vacated_at)
VALUES (1, 21, 6, 'completed', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY + INTERVAL 10 MINUTE, NOW() - INTERVAL 3 DAY + INTERVAL 2 MINUTE, NOW() - INTERVAL 3 DAY + INTERVAL 40 MINUTE);

INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at, confirmed_at, vacated_at)
VALUES (7, 22, 6, 'completed', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY + INTERVAL 10 MINUTE, NOW() - INTERVAL 2 DAY + INTERVAL 3 MINUTE, NOW() - INTERVAL 2 DAY + INTERVAL 35 MINUTE);

INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at, confirmed_at, vacated_at)
VALUES (9, 23, 6, 'completed', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 10 MINUTE, NOW() - INTERVAL 1 DAY + INTERVAL 1 MINUTE, NOW() - INTERVAL 1 DAY + INTERVAL 30 MINUTE);

INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
VALUES (10, 25, 7, 'cancelled', NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 5 HOUR + INTERVAL 10 MINUTE);

-- ------------------------------------------------------------
-- COMPLAINTS + auto-generated bench feedback requests
-- ------------------------------------------------------------

-- Complaint 1: Vivaan (7) reports cleanliness at Bench 1, where Ishita(8) and Rohan(3) currently sit
INSERT INTO complaints (student_id, complaint_type, bench_id, seat_id, description, status, created_at)
VALUES (7, 'cleanliness', 1, NULL, 'The table at Bench 1 was sticky and had food waste on it when I walked past around lunch.', 'under_review', NOW() - INTERVAL 3 HOUR);

SET @complaint1_id = LAST_INSERT_ID();

INSERT INTO complaint_feedback (complaint_id, student_id, response, comments, status, requested_at, responded_at)
VALUES (@complaint1_id, 8, 'yes', 'Yes, the table was a bit sticky when I sat down too.', 'responded', NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 2 HOUR);

INSERT INTO complaint_feedback (complaint_id, student_id, response, comments, status, requested_at, responded_at)
VALUES (@complaint1_id, 3, NULL, NULL, 'pending', NOW() - INTERVAL 3 HOUR, NULL);

-- Complaint 2: Aditya (9) reports broken furniture at Bench 9 -> already resolved (matches seat 33 being 'unavailable')
INSERT INTO complaints (student_id, complaint_type, bench_id, seat_id, description, status, admin_notes, created_at, updated_at)
VALUES (9, 'broken_furniture', 9, 33, 'Seat 1 at Bench 9 wobbles badly, one leg looks loose. Feels unsafe to sit on.', 'resolved',
        'Verified in person and reported to facilities. Seat marked unavailable until repaired.', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY);

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------
INSERT INTO notifications (student_id, type, message, is_read, created_at) VALUES
  (2, 'confirmed',         'Your seat has been reserved. Reach the canteen and scan the QR code within 10 minutes to confirm your seat.', 0, NOW()),
  (3, 'occupied',          'Seat confirmed successfully!', 1, NOW() - INTERVAL 13 MINUTE),
  (8, 'occupied',          'Seat confirmed successfully!', 1, NOW() - INTERVAL 23 MINUTE),
  (5, 'expired',           'Your reservation expired because you did not reach the canteen within 10 minutes.', 1, NOW() - INTERVAL 1 DAY),
  (5, 'warning',           'Warning: you have 1 no-show. Reaching 3 no-shows within 30 days will result in a temporary booking restriction.', 0, NOW() - INTERVAL 1 DAY),
  (4, 'ban',               'Your account is temporarily restricted from making seat reservations due to repeated no-shows.', 0, NOW() - INTERVAL 2 DAY),
  (7, 'complaint_update',  'Your complaint status has been updated to Under Review.', 0, NOW() - INTERVAL 2 HOUR),
  (9, 'complaint_update',  'Your complaint status has been updated to Resolved.', 1, NOW() - INTERVAL 1 DAY),
  (8, 'feedback_request',  'You have received a complaint feedback request for Bench 1.', 1, NOW() - INTERVAL 3 HOUR),
  (3, 'feedback_request',  'You have received a complaint feedback request for Bench 1.', 0, NOW() - INTERVAL 3 HOUR);

-- ------------------------------------------------------------
-- GENERAL FEEDBACK (ratings, for admin analytics)
-- ------------------------------------------------------------
INSERT INTO general_feedback (student_id, cleanliness_rating, availability_rating, overall_rating, app_rating, comments) VALUES
  (9,  5, 4, 5, 5, 'Great system, saved me so much time finding a seat!'),
  (10, 3, 3, 4, 4, 'Good idea but wish there were more benches near the counter.'),
  (1,  4, 5, 4, 4, 'Works well, easy to use.'),
  (6,  4, 4, 4, 5, NULL);
