-- ============================================================
-- Workforce Automation Scheduling System — Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS scheduling_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE scheduling_db;

-- -----------------------------------------------------------
-- 1. Shifts Master
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS shifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    shift_code  VARCHAR(10)  NOT NULL UNIQUE,
    start_time  TIME         NOT NULL,
    end_time    TIME         NOT NULL,
    category    ENUM('Morning', 'Swing', 'Night') NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 2. Employees (Staff Master)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    employee_id       VARCHAR(20)  NOT NULL UNIQUE,
    name              VARCHAR(100) NOT NULL,
    email             VARCHAR(100) NOT NULL UNIQUE,
    gender            ENUM('Male', 'Female') NOT NULL,
    team_leader       VARCHAR(100) DEFAULT NULL,
    employment_status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    join_date         DATE DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 3. Forecast Requirements (24h × 7 days)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS forecast_requirements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week     TINYINT NOT NULL COMMENT '0=Monday .. 6=Sunday',
    hour            TINYINT NOT NULL COMMENT '0..23',
    required_count  INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_forecast_day_hour (day_of_week, hour)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 4. Leave Requests
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS leave_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT          NOT NULL,
    request_date  DATE         NOT NULL,
    reason        TEXT         DEFAULT NULL,
    status        ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uq_leave_employee_date (employee_id, request_date)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 5. Team Leader Schedules
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tl_schedules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    team_leader     VARCHAR(100) NOT NULL,
    day_of_week     TINYINT NOT NULL COMMENT '0=Monday .. 6=Sunday',
    shift_code      VARCHAR(10) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_code) REFERENCES shifts(shift_code) ON DELETE CASCADE,
    UNIQUE KEY uq_tl_day (team_leader, day_of_week)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 6. Schedule Headers (each generation run)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedule_headers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    week_start_date DATE NOT NULL COMMENT 'Monday of the target week',
    generated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('Draft', 'Final') NOT NULL DEFAULT 'Draft'
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 7. Schedule Details (employee × day assignments)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedule_details (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    schedule_header_id  INT          NOT NULL,
    employee_id         INT          NOT NULL,
    day_of_week         TINYINT      NOT NULL COMMENT '0=Monday .. 6=Sunday',
    shift_code          VARCHAR(10)  NOT NULL COMMENT 'OFF for rest days',
    work_date           DATE         NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_header_id) REFERENCES schedule_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id)        REFERENCES employees(id)       ON DELETE CASCADE,
    UNIQUE KEY uq_schedule_employee_day (schedule_header_id, employee_id, day_of_week)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 8. Female Restricted Shifts (reference table)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS female_restricted_shifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    shift_code  VARCHAR(10) NOT NULL UNIQUE,
    FOREIGN KEY (shift_code) REFERENCES shifts(shift_code) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 9. BAK — Max Potential Earnings (rate card acuan)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS max_potential_earnings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    role           VARCHAR(100)  NOT NULL,
    category       VARCHAR(100)  DEFAULT NULL,
    billing_type   VARCHAR(20)   DEFAULT NULL,
    price_per_unit DECIMAL(18,2) NOT NULL DEFAULT 0,
    quantity       DECIMAL(18,2) NOT NULL DEFAULT 0,
    subtotal       DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bak_role (role)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 10. BAK — Seller Input (nilai yang diajukan seller)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS seller_input (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    max_potential_id INT NOT NULL,
    role             VARCHAR(100)  NOT NULL,
    max_potential    DECIMAL(18,2) NOT NULL DEFAULT 0,
    quantity         DECIMAL(18,2) DEFAULT NULL,
    price_per_unit   DECIMAL(18,2) DEFAULT NULL,
    seller_input     DECIMAL(18,2) DEFAULT NULL,
    variance         DECIMAL(18,2) DEFAULT NULL,
    variance_pct     DECIMAL(10,2) DEFAULT NULL,
    variance_reason  VARCHAR(50)   DEFAULT NULL,
    notes            VARCHAR(255)  DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (max_potential_id) REFERENCES max_potential_earnings(id) ON DELETE CASCADE,
    UNIQUE KEY uq_seller_max_potential (max_potential_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 11. BAK — Actual Input (verifikasi finance)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS actual_input (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    seller_input_id         INT NOT NULL,
    role                    VARCHAR(100)  NOT NULL,
    max_potential           DECIMAL(18,2) NOT NULL DEFAULT 0,
    seller_input            DECIMAL(18,2) DEFAULT NULL,
    quantity                DECIMAL(18,2) DEFAULT NULL,
    price_per_unit          DECIMAL(18,2) DEFAULT NULL,
    actual_input            DECIMAL(18,2) DEFAULT NULL,
    variance                DECIMAL(18,2) DEFAULT NULL,
    variance_pct            DECIMAL(10,2) DEFAULT NULL,
    variance_reason_finance VARCHAR(50)   DEFAULT NULL,
    supporting_docs         VARCHAR(500)  DEFAULT NULL,
    notes                   VARCHAR(255)  DEFAULT NULL,
    status                  ENUM('DRAFT','SUBMITTED','APPROVED','LOCKED') NOT NULL DEFAULT 'DRAFT',
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_input_id) REFERENCES seller_input(id) ON DELETE CASCADE,
    UNIQUE KEY uq_actual_seller_input (seller_input_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 12. Users — akun untuk halaman login + hak akses menu
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(100) DEFAULT NULL,
    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME     DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Izin menu per user. Administrator tidak perlu baris di sini karena
-- otomatis boleh membuka semua menu. Daftar menu ada di config/MenuCatalog.php.
CREATE TABLE IF NOT EXISTS user_permissions (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL,
    menu_key VARCHAR(40) NOT NULL,
    UNIQUE KEY uq_user_menu (user_id, menu_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 13. Family Financial Report (FFR) — modul keuangan
--     Terpisah total dari tabel Workforce di atas.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ffr_buckets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    name        VARCHAR(50)  NOT NULL,
    type        ENUM('wajib','opsional','simpan') NOT NULL DEFAULT 'wajib',
    description VARCHAR(255) DEFAULT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ffr_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ffr_periods (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    period     CHAR(7) NOT NULL UNIQUE,           -- 'YYYY-MM'
    status     ENUM('open','closed') NOT NULL DEFAULT 'open',
    notes      VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ffr_incomes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    period_id  INT NOT NULL,
    source     VARCHAR(100) NOT NULL,
    amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    owner      ENUM('Ivan','Ricka','Bersama') NOT NULL DEFAULT 'Bersama',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES ffr_periods(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ffr_plans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    period_id  INT NOT NULL,
    bucket_id  INT NOT NULL,
    amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ffr_plan (period_id, bucket_id),
    FOREIGN KEY (period_id) REFERENCES ffr_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (bucket_id) REFERENCES ffr_buckets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ffr_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    period_id     INT NOT NULL,
    bucket_id     INT NOT NULL,
    category_id   INT DEFAULT NULL,
    name          VARCHAR(100) NOT NULL,
    freq_type     ENUM('weekly','biweekly','monthly','once') NOT NULL DEFAULT 'monthly',
    unit_price    DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty           DECIMAL(10,2) NOT NULL DEFAULT 1,
    actual_amount DECIMAL(18,2) DEFAULT NULL,
    is_priority   TINYINT(1) NOT NULL DEFAULT 0,
    notes         VARCHAR(255) DEFAULT NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES ffr_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (bucket_id) REFERENCES ffr_buckets(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES ffr_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Catatan: berkas ini hanya dokumentasi skema. Migrasi yang benar-benar
-- dijalankan aplikasi ada di config/Database.php (auto-migration).
-- Akun awal (ivanrajendriya sebagai Administrator, rickapratiwi sebagai User
-- dengan izin bawaan) diisi otomatis oleh Database::ensureUsersTable().
-- Bucket FFR bawaan (UTAMA, TAMBAHAN, TABUNGAN, DARURAT) diisi otomatis
-- oleh Database::ensureFfrTables() saat tabel ffr_buckets masih kosong.
-- Daftar menu & izin bawaan ada di config/MenuCatalog.php.
