-- ============================================================
-- FeedPulse AI — Full Database Schema
-- MySQL 8.0+  |  utf8mb4_unicode_ci
-- Run: mysql -u root -p < feedpulse.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS feedpulse_ai
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE feedpulse_ai;

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. departments ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS departments (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  code       VARCHAR(20)  NOT NULL,
  head_name  VARCHAR(120)          DEFAULT NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dept_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. users ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(180) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','staff','admin','super_admin') NOT NULL DEFAULT 'student',
  department_id INT UNSIGNED          DEFAULT NULL,
  roll_number   VARCHAR(50)           DEFAULT NULL,
  phone         VARCHAR(20)           DEFAULT NULL,
  avatar        VARCHAR(255)          DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_role (role),
  KEY idx_dept (department_id),
  CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. feedback_categories ──────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  description TEXT                  DEFAULT NULL,
  icon        VARCHAR(60)           DEFAULT 'circle',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. sla_targets ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sla_targets (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id   INT UNSIGNED NOT NULL,
  target_hours  INT UNSIGNED NOT NULL DEFAULT 72,
  warning_hours INT UNSIGNED NOT NULL DEFAULT 48,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sla_cat (category_id),
  CONSTRAINT fk_sla_cat FOREIGN KEY (category_id) REFERENCES feedback_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. feedback ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id            INT UNSIGNED          DEFAULT NULL,
  department_id         INT UNSIGNED NOT NULL,
  category_id           INT UNSIGNED NOT NULL,
  rating                TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
  title                 VARCHAR(200)          DEFAULT NULL,
  comment               TEXT         NOT NULL,
  is_anonymous          TINYINT(1)   NOT NULL DEFAULT 0,
  moderation_status     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  moderation_note       TEXT                  DEFAULT NULL,
  moderated_by          INT UNSIGNED          DEFAULT NULL,
  moderated_at          DATETIME              DEFAULT NULL,
  ai_sentiment          ENUM('positive','neutral','negative') DEFAULT NULL,
  ai_confidence         DECIMAL(5,2)          DEFAULT NULL,
  ai_category_suggestion VARCHAR(100)         DEFAULT NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fb_student  (student_id),
  KEY idx_fb_dept     (department_id),
  KEY idx_fb_cat      (category_id),
  KEY idx_fb_modstatus(moderation_status),
  KEY idx_fb_created  (created_at),
  CONSTRAINT fk_fb_student FOREIGN KEY (student_id)    REFERENCES users(id)               ON DELETE SET NULL,
  CONSTRAINT fk_fb_dept    FOREIGN KEY (department_id) REFERENCES departments(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_fb_cat     FOREIGN KEY (category_id)   REFERENCES feedback_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fb_modby   FOREIGN KEY (moderated_by)  REFERENCES users(id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. complaints ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS complaints (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_number VARCHAR(20)  NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  department_id    INT UNSIGNED NOT NULL,
  category_id      INT UNSIGNED NOT NULL,
  assigned_to      INT UNSIGNED          DEFAULT NULL,
  title            VARCHAR(200) NOT NULL,
  description      TEXT         NOT NULL,
  priority         ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status           ENUM('open','in_progress','escalated','resolved','closed','reopened') NOT NULL DEFAULT 'open',
  sla_deadline     DATETIME              DEFAULT NULL,
  sla_breached     TINYINT(1)   NOT NULL DEFAULT 0,
  escalated_at     DATETIME              DEFAULT NULL,
  escalation_count TINYINT      NOT NULL DEFAULT 0,
  resolution_note  TEXT                  DEFAULT NULL,
  resolved_at      DATETIME              DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ref (reference_number),
  KEY idx_cmp_student (student_id),
  KEY idx_cmp_dept    (department_id),
  KEY idx_cmp_status  (status),
  KEY idx_cmp_assign  (assigned_to),
  KEY idx_cmp_sla     (sla_deadline),
  KEY idx_cmp_created (created_at),
  CONSTRAINT fk_cmp_student FOREIGN KEY (student_id)    REFERENCES users(id)               ON DELETE RESTRICT,
  CONSTRAINT fk_cmp_dept    FOREIGN KEY (department_id) REFERENCES departments(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_cmp_cat     FOREIGN KEY (category_id)   REFERENCES feedback_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cmp_assign  FOREIGN KEY (assigned_to)   REFERENCES users(id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. feedback_attachments ─────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback_attachments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  feedback_id   INT UNSIGNED          DEFAULT NULL,
  complaint_id  INT UNSIGNED          DEFAULT NULL,
  uploaded_by   INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(100) NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_fb  (feedback_id),
  KEY idx_att_cmp (complaint_id),
  CONSTRAINT fk_att_fb  FOREIGN KEY (feedback_id)  REFERENCES feedback(id)   ON DELETE CASCADE,
  CONSTRAINT fk_att_cmp FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. complaint_updates ────────────────────────────────────
CREATE TABLE IF NOT EXISTS complaint_updates (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  complaint_id INT UNSIGNED NOT NULL,
  updated_by   INT UNSIGNED NOT NULL,
  old_status   VARCHAR(50)           DEFAULT NULL,
  new_status   VARCHAR(50)  NOT NULL,
  note         TEXT                  DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cu_cmp (complaint_id),
  CONSTRAINT fk_cu_cmp FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_user FOREIGN KEY (updated_by)  REFERENCES users(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. complaint_escalations ────────────────────────────────
CREATE TABLE IF NOT EXISTS complaint_escalations (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  complaint_id   INT UNSIGNED NOT NULL,
  escalated_from ENUM('low','medium','high','critical') NOT NULL,
  escalated_to   ENUM('low','medium','high','critical') NOT NULL,
  reason         VARCHAR(255) NOT NULL DEFAULT 'SLA breach',
  notified_user  INT UNSIGNED          DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ce_cmp (complaint_id),
  CONSTRAINT fk_ce_cmp  FOREIGN KEY (complaint_id)  REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_ce_user FOREIGN KEY (notified_user) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. feedback_responses ──────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback_responses (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  feedback_id   INT UNSIGNED NOT NULL,
  responded_by  INT UNSIGNED NOT NULL,
  response      TEXT         NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fr_fb (feedback_id),
  CONSTRAINT fk_fr_fb   FOREIGN KEY (feedback_id)  REFERENCES feedback(id) ON DELETE CASCADE,
  CONSTRAINT fk_fr_user FOREIGN KEY (responded_by) REFERENCES users(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. post_resolution_surveys ─────────────────────────────
CREATE TABLE IF NOT EXISTS post_resolution_surveys (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  complaint_id INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  rating       TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment      TEXT                  DEFAULT NULL,
  submitted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_survey_cmp (complaint_id),
  CONSTRAINT fk_survey_cmp  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_survey_user FOREIGN KEY (student_id)   REFERENCES users(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. notifications ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    TEXT         NOT NULL,
  type       ENUM('complaint','feedback','sla','survey','system') NOT NULL DEFAULT 'system',
  related_id INT UNSIGNED          DEFAULT NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user   (user_id),
  KEY idx_notif_unread (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 13. otp_tokens ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_tokens (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token      VARCHAR(10)  NOT NULL,
  expires_at DATETIME     NOT NULL,
  used       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_otp_user (user_id),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 14. rate_limit_log ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limit_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45)  NOT NULL,
  action     VARCHAR(50)  NOT NULL DEFAULT 'login',
  attempts   SMALLINT     NOT NULL DEFAULT 1,
  blocked_until DATETIME           DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rl_ip_action (ip_address, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 15. engagement_scores ───────────────────────────────────
CREATE TABLE IF NOT EXISTS engagement_scores (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  score      INT          NOT NULL DEFAULT 0,
  tier       ENUM('bronze','silver','gold','platinum') NOT NULL DEFAULT 'bronze',
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eng_student (student_id),
  CONSTRAINT fk_eng_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 16. ai_analyses ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_analyses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  feedback_id  INT UNSIGNED NOT NULL,
  raw_response TEXT                  DEFAULT NULL,
  processed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_fb (feedback_id),
  CONSTRAINT fk_ai_fb FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 17. app_logs ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS app_logs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  level      ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
  category   VARCHAR(50)  NOT NULL DEFAULT 'app',
  message    TEXT         NOT NULL,
  context    JSON                  DEFAULT NULL,
  user_id    INT UNSIGNED          DEFAULT NULL,
  ip_address VARCHAR(45)           DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_level   (level),
  KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (name, code, head_name) VALUES
  ('Computer Science',           'CS',   'Dr. Ahmed Khan'),
  ('Electrical Engineering',     'EE',   'Dr. Sara Malik'),
  ('Civil Engineering',          'CE',   'Dr. Umar Farooq'),
  ('Business Administration',    'BBA',  'Dr. Nadia Hussain'),
  ('Campus Facilities',          'FAC',  'Mr. Tariq Butt'),
  ('Student Affairs',            'SA',   'Ms. Sana Iqbal'),
  ('Library Services',           'LIB',  'Mr. Imran Shah'),
  ('IT Services',                'ITS',  'Mr. Faisal Raza'),
  ('Cafeteria & Food Services',  'CAF',  'Mr. Bilal Chaudhry'),
  ('Health Center',              'HC',   'Dr. Ayesha Siddiqui');

-- Feedback Categories
INSERT INTO feedback_categories (name, description, icon) VALUES
  ('Academic Quality',    'Lectures, curriculum, exams, grading',            'book'),
  ('Facilities',          'Classrooms, labs, washrooms, furniture',          'building'),
  ('Food Services',       'Cafeteria quality, hygiene, pricing',             'coffee'),
  ('IT & Technology',     'Wi-Fi, computers, software, portals',             'cpu'),
  ('Staff & Admin',       'Administration, support staff behavior',          'people'),
  ('Health & Safety',     'Medical center, emergency, campus safety',        'heart-pulse'),
  ('Library',             'Books, resources, study environment',             'journal-bookmark'),
  ('Extracurricular',     'Clubs, events, sports, societies',                'trophy'),
  ('Transportation',      'Shuttle, parking, campus connectivity',           'bus-front'),
  ('General',             'Anything not in above categories',                'chat-square-text');

-- SLA Targets (per category)
INSERT INTO sla_targets (category_id, target_hours, warning_hours) VALUES
  (1, 48, 36), (2, 24, 16), (3, 12, 8),  (4, 24, 16),
  (5, 72, 48), (6, 12, 8),  (7, 48, 36), (8, 96, 72),
  (9, 48, 36), (10,72, 48);

-- Default Users (all passwords hash to "password")
INSERT INTO users (name, email, password_hash, role, department_id) VALUES
  ('Super Admin',     'superadmin@feedpulse.edu', '$2y$10$fKUbYb7mnZUUdQek1aBbtOUo6bUsdOsi0GnG1.Dr1ey58SwIc39CS', 'super_admin', NULL),
  ('Admin User',      'admin@feedpulse.edu',       '$2y$10$fKUbYb7mnZUUdQek1aBbtOUo6bUsdOsi0GnG1.Dr1ey58SwIc39CS', 'admin',       NULL),
  ('CS Staff Member', 'staff.cs@feedpulse.edu',    '$2y$10$fKUbYb7mnZUUdQek1aBbtOUo6bUsdOsi0GnG1.Dr1ey58SwIc39CS', 'staff',       1),
  ('Demo Student',    'student@feedpulse.edu',     '$2y$10$fKUbYb7mnZUUdQek1aBbtOUo6bUsdOsi0GnG1.Dr1ey58SwIc39CS', 'student',     1);

-- NOTE: All seed passwords hash to "password" via password_hash('password', PASSWORD_BCRYPT)
-- Change all passwords immediately after first login!
