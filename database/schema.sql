-- ============================================================
-- SMART CANTEEN — Database Schema
-- Mukesh Patel School of Technology and Management Engineering
-- ============================================================
-- Engine: InnoDB everywhere (required for foreign keys AND for
-- row-level locking, which is what stops two students from
-- grabbing the same seat at the same time — see api/reserve_seat.php)
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_canteen
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE smart_canteen;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. STUDENTS — identity is the 11-digit SAP ID, not a password.
--    (See docs/ARCHITECTURE.md for why, and how to harden this
--    for a real deployment.)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS students;
CREATE TABLE students (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  sap_id         VARCHAR(11) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  name           VARCHAR(100) NOT NULL,
  branch         VARCHAR(100) NOT NULL,
  year           TINYINT NOT NULL,
  email          VARCHAR(150) DEFAULT NULL,
  no_show_count  INT NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_sap_id_format CHECK (sap_id REGEXP '^[0-9]{11}$'),
  CONSTRAINT chk_year_range CHECK (year BETWEEN 1 AND 4)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. ADMINS — completely separate login/table from students
-- ------------------------------------------------------------
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(50) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  name           VARCHAR(100) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. BENCHES — the unit shown on the main canteen map.
--    qr_token is the secret string embedded in that bench's QR
--    code; it is NOT the bench_number, so a QR can't be forged
--    by guessing "Bench 3".
-- ------------------------------------------------------------
DROP TABLE IF EXISTS benches;
CREATE TABLE benches (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  bench_number   INT NOT NULL UNIQUE,
  location       VARCHAR(100) DEFAULT NULL,
  qr_token       VARCHAR(64) NOT NULL UNIQUE,
  status         ENUM('active','unavailable') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. SEATS — exactly 4 per bench, enforced at the application
--    layer when a bench is created (see admin/benches.php).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS seats;
CREATE TABLE seats (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  bench_id       INT NOT NULL,
  seat_number    TINYINT NOT NULL,
  status         ENUM('available','reserved','occupied','unavailable') NOT NULL DEFAULT 'available',
  UNIQUE KEY uniq_bench_seat (bench_id, seat_number),
  CONSTRAINT fk_seats_bench FOREIGN KEY (bench_id) REFERENCES benches(id) ON DELETE CASCADE,
  CONSTRAINT chk_seat_number CHECK (seat_number BETWEEN 1 AND 4)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. RESERVATIONS — the core table. One row per booking attempt;
--    rows are never deleted, so this table doubles as booking
--    history. "Only one active reservation per student" and
--    "no double-booking a seat" are enforced in PHP inside a
--    transaction with SELECT ... FOR UPDATE (MySQL has no clean
--    partial-unique-index for "unique while status IN (...)").
-- ------------------------------------------------------------
DROP TABLE IF EXISTS reservations;
CREATE TABLE reservations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  student_id     INT NOT NULL,
  seat_id        INT NOT NULL,
  bench_id       INT NOT NULL,
  status         ENUM('reserved','occupied','completed','expired','cancelled') NOT NULL DEFAULT 'reserved',
  reserved_at    DATETIME NOT NULL,
  expires_at     DATETIME NOT NULL,
  confirmed_at   DATETIME DEFAULT NULL,
  vacated_at     DATETIME DEFAULT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_res_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_seat    FOREIGN KEY (seat_id) REFERENCES seats(id),
  CONSTRAINT fk_res_bench   FOREIGN KEY (bench_id) REFERENCES benches(id),
  INDEX idx_student_status (student_id, status),
  INDEX idx_status_expiry (status, expires_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. NO_SHOWS — one row per expired reservation; this is what
--    the ban policy counts against, separate from the
--    reservation's own "expired" status label.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS no_shows;
CREATE TABLE no_shows (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  student_id     INT NOT NULL,
  reservation_id INT NOT NULL,
  occurred_at    DATETIME NOT NULL,
  CONSTRAINT fk_ns_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_ns_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. BANS — a student can be manually lifted early by an admin
--    (lifted_at), otherwise expires naturally at end_date.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS bans;
CREATE TABLE bans (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  student_id     INT NOT NULL,
  reason         VARCHAR(255) NOT NULL,
  start_date     DATETIME NOT NULL,
  end_date       DATETIME NOT NULL,
  lifted_at      DATETIME DEFAULT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ban_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_student_active (student_id, lifted_at, end_date)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. COMPLAINTS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS complaints;
CREATE TABLE complaints (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  student_id       INT NOT NULL,
  complaint_type   ENUM('seat_misuse','reserved_seat_occupied','broken_furniture','cleanliness','other') NOT NULL,
  bench_id         INT DEFAULT NULL,
  seat_id          INT DEFAULT NULL,
  description      TEXT NOT NULL,
  image_path       VARCHAR(255) DEFAULT NULL,
  status           ENUM('submitted','under_review','verified','rejected','resolved') NOT NULL DEFAULT 'submitted',
  admin_notes      TEXT DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_complaint_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaint_bench FOREIGN KEY (bench_id) REFERENCES benches(id) ON DELETE SET NULL,
  CONSTRAINT fk_complaint_seat FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. COMPLAINT_FEEDBACK — auto-generated one row per student who
--    was OCCUPYING the reported bench at the moment the complaint
--    was filed. This is what lets admins corroborate a complaint
--    instead of acting on a single unverified report.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS complaint_feedback;
CREATE TABLE complaint_feedback (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id   INT NOT NULL,
  student_id     INT NOT NULL,
  response       ENUM('yes','no','not_sure') DEFAULT NULL,
  comments       TEXT DEFAULT NULL,
  status         ENUM('pending','responded') NOT NULL DEFAULT 'pending',
  requested_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at   DATETIME DEFAULT NULL,
  CONSTRAINT fk_cf_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_cf_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_complaint_student (complaint_id, student_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. NOTIFICATIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  student_id     INT NOT NULL,
  type           VARCHAR(50) NOT NULL,
  message        VARCHAR(255) NOT NULL,
  related_id     INT DEFAULT NULL,
  is_read        TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_student_read (student_id, is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. GENERAL_FEEDBACK — star ratings, 1-5
-- ------------------------------------------------------------
DROP TABLE IF EXISTS general_feedback;
CREATE TABLE general_feedback (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  student_id            INT NOT NULL,
  cleanliness_rating    TINYINT NOT NULL,
  availability_rating   TINYINT NOT NULL,
  overall_rating        TINYINT NOT NULL,
  app_rating            TINYINT NOT NULL,
  comments              TEXT DEFAULT NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gf_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT chk_ratings CHECK (
    cleanliness_rating BETWEEN 1 AND 5 AND
    availability_rating BETWEEN 1 AND 5 AND
    overall_rating BETWEEN 1 AND 5 AND
    app_rating BETWEEN 1 AND 5
  )
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. SETTINGS — admin-configurable policy knobs (key/value)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  setting_key    VARCHAR(50) PRIMARY KEY,
  setting_value  VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
