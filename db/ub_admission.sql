-- ============================================================
-- University of Botswana Online Admission System
-- Database Setup Script for phpMyAdmin (XAMPP / localhost)
--
-- HOW TO USE:
--   1. Start MySQL in XAMPP Control Panel
--   2. Open phpMyAdmin → http://localhost/phpmyadmin
--   3. Click the "SQL" tab at the top
--   4. Paste this entire file and click "Go"
--   (The database will be created automatically)
-- ============================================================

CREATE DATABASE IF NOT EXISTS ub_admission
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ub_admission;

-- ============================================================
-- TABLE: users (prospective students)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(100)    NOT NULL,
    last_name       VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password        VARCHAR(255)    NOT NULL,
    phone           VARCHAR(20),
    dob             DATE,
    gender          ENUM('Male','Female','Other'),
    nationality     VARCHAR(100)    DEFAULT 'Motswana',
    omang_number    VARCHAR(20),
    address         TEXT,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: admins
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    full_name   VARCHAR(200),
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: applications
--   qual_type        : IGCSE | BGCSE | IB | Matric | Other
--   qual_type_other  : filled when qual_type = 'Other'
--   admin_notes      : optional message from admissions to student
-- ============================================================
CREATE TABLE IF NOT EXISTS applications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT             NOT NULL,
    program             VARCHAR(200)    NOT NULL DEFAULT 'BSc General',
    qual_type           ENUM('IGCSE','BGCSE','IB','Matric','Other') DEFAULT 'BGCSE',
    qual_type_other     VARCHAR(150)    DEFAULT NULL,
    status              ENUM('Pending','Under Review','Accepted','Rejected') DEFAULT 'Pending',
    submitted           TINYINT(1)      DEFAULT 0,
    submitted_at        TIMESTAMP       NULL,
    admin_notes         TEXT            DEFAULT NULL,
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: academic_qualifications
--   Points scale (applies to all qual types):
--     A* = 8 | A = 7 | B = 6 | C = 5 | D = 4 | E = 3 | U = 0
--   Admission score = SUM of the 6 highest subject points (max 48)
-- ============================================================
CREATE TABLE IF NOT EXISTS academic_qualifications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    application_id      INT         NOT NULL,
    subject             VARCHAR(150) NOT NULL,
    grade               ENUM('A*','A','B','C','D','E','U') NOT NULL,
    points              INT         NOT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: documents (uploaded files)
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    application_id      INT         NOT NULL,
    doc_type            ENUM('Omang/ID','BGCSE Certificate','IGCSE Certificate','IB Certificate','Matric Certificate','Transcript','Birth Certificate','Other') NOT NULL,
    original_name       VARCHAR(255) NOT NULL,
    file_path           VARCHAR(500) NOT NULL,
    uploaded_at         TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: password_resets (for forgot-password flow)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT         NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    expires_at  DATETIME    NOT NULL,
    used        TINYINT(1)  DEFAULT 0,
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
--   Username : admin
--   Password : Admin@UB2024!
--   !! Change this password immediately after first login !!
-- ============================================================
INSERT IGNORE INTO admins (username, password, full_name)
VALUES (
    'admin',
    '$2y$12$xM.rJm/VSqEJE6G.06f.Lu3jJgnccPkHDZN1BdstY0TbcUG5Q6tEW',
    'System Administrator'
);
-- Hash above is bcrypt of 'Admin@UB2024!' (cost=12)
-- To regenerate: php -r "echo password_hash('Admin@UB2024!', PASSWORD_BCRYPT, ['cost'=>12]);"

-- ============================================================
-- MIGRATION: add admin_notes to existing installations
-- (safe to run even if column already exists — will silently fail)
-- ============================================================
ALTER TABLE applications ADD COLUMN IF NOT EXISTS admin_notes TEXT DEFAULT NULL;
