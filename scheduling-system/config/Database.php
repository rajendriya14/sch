<?php

/**
 * Database connection with automatic schema migration.
 *
 * On first connection, checks if required tables exist.
 * If not, creates all tables and seeds default data automatically.
 */
namespace Config;

use PDO;
use PDOException;

/**
 * Database — PDO Singleton + Auto-Migration
 * Workforce Automation Scheduling System
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Kredensial diambil dari `config/database.credentials.php` (tidak masuk
     * Git) atau dari environment variable. Lihat `database.credentials.example.php`.
     */
    private static string $host     = 'localhost';
    private static string $dbName   = '';
    private static string $username = '';
    private static string $password = '';
    private static string $charset  = 'utf8mb4';

    private static bool $credentialsLoaded = false;

    /** Muat kredensial sekali dari file lokal / environment variable. */
    private static function loadCredentials(): void
    {
        if (self::$credentialsLoaded) {
            return;
        }
        self::$credentialsLoaded = true;

        $file = __DIR__ . '/database.credentials.php';
        if (is_file($file)) {
            $config = require $file;
            if (is_array($config)) {
                self::$host     = (string) ($config['host'] ?? self::$host);
                self::$dbName   = (string) ($config['dbname'] ?? self::$dbName);
                self::$username = (string) ($config['username'] ?? self::$username);
                self::$password = (string) ($config['password'] ?? self::$password);
                self::$charset  = (string) ($config['charset'] ?? self::$charset);
            }
            return;
        }

        self::$host     = (string) (getenv('DB_HOST') ?: self::$host);
        self::$dbName   = (string) (getenv('DB_NAME') ?: self::$dbName);
        self::$username = (string) (getenv('DB_USER') ?: self::$username);
        self::$password = (string) (getenv('DB_PASS') ?: self::$password);
    }

    private static array $requiredTables = [
        'shifts', 'employees', 'forecast_requirements',
        'leave_requests', 'tl_schedules',
        'schedule_headers', 'schedule_details',
        'female_restricted_shifts', 'shift_inactive', 'backdate_data',
        'max_potential_earnings', 'seller_input', 'actual_input',
    ];

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::loadCredentials();
            if (self::$dbName === '') {
                die('Konfigurasi database belum ada: buat scheduling-system/config/database.credentials.php '
                    . '(contoh: database.credentials.example.php).');
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', self::$host, self::$dbName, self::$charset);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, self::$username, self::$password, $options);
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
            self::migrate();
        }
        return self::$instance;
    }

    private static function migrate(): void
    {        // Add is_active column to shifts if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE shifts ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Add description column to shifts if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE shifts ADD COLUMN description VARCHAR(255) DEFAULT NULL AFTER category');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Add billing_type column to max_potential_earnings if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE max_potential_earnings ADD COLUMN billing_type VARCHAR(20) DEFAULT NULL AFTER category');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Max Potential manual (dibandingkan dengan hasil hitung WFM) + penanda
        // role/harga yang diisi manual supaya sinkron WFM tidak menimpanya.
        foreach ([
            'manual_amount' => 'DECIMAL(18,2) DEFAULT NULL AFTER quantity',
            'manual_rate'   => 'DECIMAL(18,2) DEFAULT NULL AFTER quantity',
            'price_manual'  => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER price_per_unit',
            'is_custom'     => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
        ] as $column => $definition) {
            try {
                self::$instance->exec(
                    'ALTER TABLE max_potential_earnings ADD COLUMN ' . $column . ' ' . $definition
                );
            } catch (\PDOException $e) {
                // Column already exists — ignore
            }
        }
        // Add quantity column to seller_input if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE seller_input ADD COLUMN quantity DECIMAL(18,2) DEFAULT NULL AFTER max_potential');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Add price_per_unit column to seller_input if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE seller_input ADD COLUMN price_per_unit DECIMAL(18,2) DEFAULT NULL AFTER quantity');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Snapshot nilai otomatis dari Max Potential, dipakai untuk membedakan
        // nilai hasil sinkron vs nilai yang diubah manual.
        foreach (['auto_quantity' => 'quantity', 'auto_price_per_unit' => 'price_per_unit'] as $column => $after) {
            try {
                self::$instance->exec(
                    'ALTER TABLE seller_input ADD COLUMN ' . $column
                    . ' DECIMAL(18,2) DEFAULT NULL AFTER ' . $after
                );
            } catch (\PDOException $e) {
                // Column already exists — ignore
            }
        }
        // Add quantity column to actual_input if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE actual_input ADD COLUMN quantity DECIMAL(18,2) DEFAULT NULL AFTER seller_input');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Add notes column to actual_input if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE actual_input ADD COLUMN notes VARCHAR(255) DEFAULT NULL AFTER supporting_docs');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        // Add price_per_unit column to actual_input if it doesn't exist
        try {
            self::$instance->exec('ALTER TABLE actual_input ADD COLUMN price_per_unit DECIMAL(18,2) DEFAULT NULL AFTER quantity');
        } catch (\PDOException $e) {
            // Column already exists — ignore
        }
        $existing = self::$instance->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $missing  = array_diff(self::$requiredTables, $existing);
        if (!empty($missing)) {
            self::createTables();
        }

        // Data contoh BAK butuh jalur sendiri: seedData() berhenti lebih awal
        // begitu tabel shifts sudah terisi (dan di server sudah terisi).
        self::seedBakData();

        // Database yang dibuat lewat import schema.sql tidak punya data shift
        // bawaan (schema.sql hanya CREATE TABLE), jadi pastikan sekali saja.
        self::seedDefaultShifts();

        // Tabel user untuk halaman login (dibuat + diisi akun awal bila kosong).
        self::ensureUsersTable();

        // Tabel Family Financial Report (modul keuangan — terpisah dari tabel Workforce).
        self::ensureFfrTables();

        // Tabel absensi BAK (upload rekap attendance per bulan + harga per posisi).
        self::ensureBakAttendanceTables();
    }

    /**
     * Tabel absensi BAK.
     *
     * Dua tabel:
     *   bak_attendance_uploads : satu baris per upload per periode (YYYY-MM)
     *   bak_attendance_days    : agregat hari per posisi & kode absensi
     *   bak_attendance_people  : hari terjadwal per orang (untuk prorata)
     *   bak_position_prices    : harga per posisi per periode
     */
    public static function ensureBakAttendanceTables(): void
    {
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS bak_attendance_uploads (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    period        VARCHAR(7)   NOT NULL,
                    file_name     VARCHAR(255) DEFAULT NULL,
                    total_rows    INT NOT NULL DEFAULT 0,
                    total_people  INT NOT NULL DEFAULT 0,
                    standard_days INT NOT NULL DEFAULT 0,
                    uploaded_by   VARCHAR(100) DEFAULT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_bak_att_period (period)
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS bak_attendance_days (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    upload_id  INT NOT NULL,
                    position   VARCHAR(100) NOT NULL,
                    person_name VARCHAR(150) DEFAULT NULL,
                    code       VARCHAR(20)  NOT NULL DEFAULT "",
                    day_rows   INT NOT NULL DEFAULT 0,
                    people     INT NOT NULL DEFAULT 0,
                    KEY idx_bak_att_days_upload (upload_id),
                    FOREIGN KEY (upload_id) REFERENCES bak_attendance_uploads(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS bak_attendance_people (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    upload_id      INT NOT NULL,
                    position       VARCHAR(100) NOT NULL,
                    employee_id    VARCHAR(64) DEFAULT NULL,
                    remarks        VARCHAR(150) DEFAULT NULL,
                    person_name    VARCHAR(150) NOT NULL,
                    scheduled_days INT NOT NULL DEFAULT 0,
                    days_override   DECIMAL(6,2) DEFAULT NULL,
                    active_override DECIMAL(6,2) DEFAULT NULL,
                    KEY idx_bak_att_people_upload (upload_id),
                    FOREIGN KEY (upload_id) REFERENCES bak_attendance_uploads(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ');

            // NIK / Employee ID per orang (opsional; ada di kolom A berkas WFM).
            try {
                self::$instance->exec(
                    'ALTER TABLE bak_attendance_people ADD COLUMN employee_id VARCHAR(64) DEFAULT NULL AFTER position'
                );
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan
            }

            // Koreksi manual hari masuk per orang (default tetap dari Excel).
            try {
                self::$instance->exec(
                    'ALTER TABLE bak_attendance_people ADD COLUMN days_override DECIMAL(6,2) DEFAULT NULL AFTER scheduled_days'
                );
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan
            }

            // Remarks per orang (kolom T pada berkas WFM).
            try {
                self::$instance->exec(
                    'ALTER TABLE bak_attendance_people ADD COLUMN remarks VARCHAR(150) DEFAULT NULL AFTER employee_id'
                );
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan
            }

            // Hari aktif kalender (koreksi manual; default dari tanggal resign).
            try {
                self::$instance->exec(
                    'ALTER TABLE bak_attendance_people ADD COLUMN active_override DECIMAL(6,2) DEFAULT NULL AFTER days_override'
                );
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan
            }

            // Nama orang pada agregat hari, supaya statistik H/CT/Off bisa
            // dibatasi ke orang yang Billable saja.
            try {
                self::$instance->exec(
                    'ALTER TABLE bak_attendance_days ADD COLUMN person_name VARCHAR(150) DEFAULT NULL AFTER position'
                );
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan
            }

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS bak_position_prices (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    period     VARCHAR(7)   NOT NULL,
                    position   VARCHAR(100) NOT NULL,
                    category   VARCHAR(100) DEFAULT NULL,
                    price      DECIMAL(18,2) NOT NULL DEFAULT 0,
                    is_custom  TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_bak_price (period, position)
                ) ENGINE=InnoDB
            ');
        } catch (\PDOException $e) {
            error_log('ensureBakAttendanceTables: ' . $e->getMessage());
        }
    }

    private static function createTables(): void
    {
        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS shifts (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                shift_code  VARCHAR(10)  NOT NULL UNIQUE,
                start_time  TIME         NOT NULL DEFAULT "00:00:00",
                end_time    TIME         NOT NULL DEFAULT "00:00:00",
                category    ENUM("Morning","Swing","Night","Leave") NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                is_active   TINYINT(1) NOT NULL DEFAULT 1,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS employees (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                employee_id       VARCHAR(20)  NOT NULL UNIQUE,
                name              VARCHAR(100) NOT NULL,
                email             VARCHAR(100) NOT NULL UNIQUE,
                gender            ENUM("Male", "Female") NOT NULL,
                position          VARCHAR(100) DEFAULT NULL,
                lob               VARCHAR(100) DEFAULT NULL,
                team_leader       VARCHAR(100) DEFAULT NULL,
                team_leader_email VARCHAR(100) DEFAULT NULL,
                operational_mgr   VARCHAR(100) DEFAULT NULL,
                domain            VARCHAR(100) DEFAULT NULL,
                compo_month       VARCHAR(20)  DEFAULT NULL,
                compo_year        VARCHAR(10)  DEFAULT NULL,
                file_name         VARCHAR(255) DEFAULT NULL,
                employment_status ENUM("Active", "Inactive") NOT NULL DEFAULT "Active",
                join_date         DATE DEFAULT NULL,
                created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS forecast_requirements (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                day_of_week     TINYINT NOT NULL COMMENT "0=Monday .. 6=Sunday",
                hour            TINYINT NOT NULL COMMENT "0..23",
                required_count  INT NOT NULL DEFAULT 0,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_forecast_day_hour (day_of_week, hour)
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS leave_requests (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                employee_id   INT          NOT NULL,
                request_date  DATE         NOT NULL,
                reason        TEXT         DEFAULT NULL,
                status        ENUM("Pending", "Approved", "Rejected") NOT NULL DEFAULT "Pending",
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                UNIQUE KEY uq_leave_employee_date (employee_id, request_date)
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS tl_schedules (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                team_leader     VARCHAR(100) NOT NULL,
                day_of_week     TINYINT NOT NULL COMMENT "0=Monday .. 6=Sunday",
                shift_code      VARCHAR(10) NOT NULL,
                week_start_date DATE DEFAULT NULL COMMENT "Monday of the target week",
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (shift_code) REFERENCES shifts(shift_code) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS schedule_headers (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                week_start_date DATE NOT NULL COMMENT "Monday of the target week",
                generated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status          ENUM("Draft", "Final") NOT NULL DEFAULT "Draft"
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS schedule_details (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                schedule_header_id  INT          NOT NULL,
                employee_id         INT          NOT NULL,
                day_of_week         TINYINT      NOT NULL COMMENT "0=Monday .. 6=Sunday",
                shift_code          VARCHAR(10)  NOT NULL COMMENT "OFF for rest days",
                work_date           DATE         NOT NULL,
                created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (schedule_header_id) REFERENCES schedule_headers(id) ON DELETE CASCADE,
                FOREIGN KEY (employee_id)        REFERENCES employees(id)       ON DELETE CASCADE,
                UNIQUE KEY uq_schedule_employee_day (schedule_header_id, employee_id, day_of_week)
            ) ENGINE=InnoDB
        ');

        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS female_restricted_shifts (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                shift_code  VARCHAR(10) NOT NULL UNIQUE,
                FOREIGN KEY (shift_code) REFERENCES shifts(shift_code) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ');

        // Create shift_inactive table
        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS shift_inactive (
                shift_code  VARCHAR(10) NOT NULL UNIQUE,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (shift_code) REFERENCES shifts(shift_code) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ');

        // Create backdate_data table
        self::$instance->exec('
            CREATE TABLE IF NOT EXISTS backdate_data (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                week_start_date DATE NOT NULL,
                group_name      VARCHAR(50) NOT NULL DEFAULT "",
                file_name       VARCHAR(255) DEFAULT NULL,
                import_data     LONGTEXT DEFAULT NULL,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_backdate_week_group (week_start_date, group_name)
            ) ENGINE=InnoDB
        ');

        // --- BAK (Berita Acara Kerja) ---

        // Rate card acuan (read-only dari UI).
        self::$instance->exec('
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
            ) ENGINE=InnoDB
        ');

        // Nilai yang diajukan seller per baris rate card.
        self::$instance->exec('
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
            ) ENGINE=InnoDB
        ');

        // Hasil verifikasi finance per baris seller input.
        self::$instance->exec('
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
                status                  ENUM("DRAFT","SUBMITTED","APPROVED","LOCKED") NOT NULL DEFAULT "DRAFT",
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (seller_input_id) REFERENCES seller_input(id) ON DELETE CASCADE,
                UNIQUE KEY uq_actual_seller_input (seller_input_id)
            ) ENGINE=InnoDB
        ');
    }

    /**
     * Seed rate card BAK (contoh).
     *
     * Dibuat terpisah dari seedData() karena seedData() berhenti lebih awal saat
     * tabel shifts sudah terisi, dan berjalan hanya bila tabelnya masih kosong.
     */
    private static function seedBakData(): void
    {
        try {
            $count = (int) self::$instance->query('SELECT COUNT(*) FROM max_potential_earnings')->fetchColumn();
            if ($count > 0) {
                return;
            }

            // [role, kategori, jenis billing, harga per unit, quantity]
            $roles = [
                ['Content Moderator',        'Operations', 'Interaction', 15000000.00, 12],
                ['Senior Content Moderator', 'Operations', 'Interaction', 20000000.00, 4],
                ['Team Leader',              'Operations', 'Headcount',   25000000.00, 3],
                ['QA Analyst',               'Quality',    'Hours',       18000000.00, 5],
                ['QA Lead',                  'Quality',    'Headcount',   28000000.00, 1],
                ['Trainer',                  'Enablement', 'Hours',       22000000.00, 2],
            ];

            $stmt = self::$instance->prepare(
                'INSERT INTO max_potential_earnings (role, category, billing_type, price_per_unit, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($roles as $role) {
                $stmt->execute([
                    $role[0],
                    $role[1],
                    $role[2],
                    $role[3],
                    $role[4],
                    round($role[3] * $role[4], 2),
                ]);
            }
        } catch (\PDOException $e) {
            // Tabel belum terbentuk atau data sudah ada — biarkan request berjalan.
        }
    }

    /**
     * Isi daftar shift bawaan: 16 shift regular + 10 tipe leave.
     *
     * Dipanggil otomatis sekali per database dari migrate(), karena import
     * schema.sql hanya membuat tabel tanpa INSERT. Baris yang sudah ada
     * (termasuk shift buatan user seperti M4) tidak diubah — INSERT IGNORE.
     *
     * @param bool $force true (tombol "Restore Default Shifts") mengabaikan
     *                    penanda sehingga shift bawaan yang terhapus kembali.
     * @return int jumlah shift yang benar-benar ditambahkan
     */
    public static function seedDefaultShifts(bool $force = false): int
    {
        try {
            // Bisa dipanggil sebelum koneksi dibuka (mis. dari tombol Restore Default).
            self::getInstance();

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS system_flags (
                    flag       VARCHAR(64) PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');

            $flag = 'default_shifts_seeded';
            if (!$force) {
                $seeded = (int) self::$instance->query(
                    "SELECT COUNT(*) FROM system_flags WHERE flag = '" . $flag . "'"
                )->fetchColumn();
                if ($seeded > 0) {
                    return 0;
                }
            }

            // [shift_code, start_time, end_time, category, description]
            $shifts = [
                ['P1',  '06:00', '15:00', 'Morning', null],
                ['P2',  '07:00', '16:00', 'Morning', null],
                ['P3',  '08:00', '17:00', 'Morning', null],
                ['P4',  '09:00', '18:00', 'Morning', null],
                ['P10', '10:00', '19:00', 'Morning', null],
                ['S1',  '11:00', '20:00', 'Swing',   null],
                ['S2',  '12:00', '21:00', 'Swing',   null],
                ['S4',  '13:00', '22:00', 'Swing',   null],
                ['S6',  '14:00', '23:00', 'Swing',   null],
                ['S7',  '15:00', '00:00', 'Swing',   null],
                ['S5',  '16:00', '01:00', 'Swing',   null],
                ['M6',  '18:00', '03:00', 'Night',   null],
                ['M5',  '20:00', '05:00', 'Night',   null],
                ['M3',  '21:00', '06:00', 'Night',   null],
                ['M1',  '22:00', '07:00', 'Night',   null],
                ['M4',  '23:00', '08:00', 'Night',   null],
                ['AL',  '00:00', '00:00', 'Leave',   'Annual Leave'],
                ['ML',  '00:00', '00:00', 'Leave',   'Maternity Leave'],
                ['PL',  '00:00', '00:00', 'Leave',   'Paternity Leave'],
                ['MAL', '00:00', '00:00', 'Leave',   'Marriage Leave'],
                ['SL',  '00:00', '00:00', 'Leave',   'Sick Leave'],
                ['HL',  '00:00', '00:00', 'Leave',   'Hospitalization Leave'],
                ['COL', '00:00', '00:00', 'Leave',   'Compassionate Leave'],
                ['UPL', '00:00', '00:00', 'Leave',   'Unpaid Leave'],
                ['EL',  '00:00', '00:00', 'Leave',   'Emergency Leave'],
                ['OFF', '00:00', '00:00', 'Leave',   'Day Off'],
            ];

            $inserted = 0;
            $insertShift = self::$instance->prepare(
                'INSERT IGNORE INTO shifts (shift_code, start_time, end_time, category, description)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($shifts as $shift) {
                $insertShift->execute($shift);
                $inserted += $insertShift->rowCount();
            }

            // Shift malam tetap ditandai terlarang untuk wanita (sama seperti seed awal)
            $insertRestricted = self::$instance->prepare(
                'INSERT IGNORE INTO female_restricted_shifts (shift_code) VALUES (?)'
            );
            foreach (['S5', 'S7', 'M1', 'M3', 'M4', 'M5', 'M6'] as $code) {
                $insertRestricted->execute([$code]);
            }

            self::$instance->exec("INSERT IGNORE INTO system_flags (flag) VALUES ('" . $flag . "')");

            return $inserted;
        } catch (\PDOException $e) {
            // Tabel belum siap atau hak akses terbatas — halaman tetap jalan.
            return 0;
        }
    }

    /**
     * Akun awal halaman login — hanya dipakai sekali saat tabel users pertama
     * kali dibuat. Tambah/ubah user berikutnya langsung lewat tabel `users`
     * atau halaman Ganti Password.
     */
    private const DEFAULT_USERS = [
        ['ivanrajendriya', '123', 'Ivan Rajendriya'],
        ['rickapratiwi',   '123', 'Ricka Pratiwi'],
    ];

    /**
     * Izin bawaan untuk user baru / user lama yang belum punya izin.
     * Manajemen User tidak termasuk — fitur itu khusus administrator.
     */
    private const DEFAULT_USER_PERMISSIONS = [
        'dashboard', 'bak', 'billing',
        'schedule', 'tl_schedule', 'schedule_overview',
        'shifts', 'staff', 'backdate', 'forecast', 'leave',
        'ffr',
    ];

    /**
     * Buat tabel `users` bila belum ada, lalu isi akun awal sekali saja.
     */
    public static function ensureUsersTable(): void
    {
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS users (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    username      VARCHAR(50)  NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    display_name  VARCHAR(100) DEFAULT NULL,
                    role          ENUM("admin","user") NOT NULL DEFAULT "user",
                    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
                    last_login_at DATETIME     DEFAULT NULL,
                    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');

            // Migrasi untuk database yang tabelnya sudah ada tanpa kolom role.
            try {
                self::$instance->exec('ALTER TABLE users ADD COLUMN role ENUM("admin","user") NOT NULL DEFAULT "user" AFTER display_name');
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan.
            }

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS user_permissions (
                    id       INT AUTO_INCREMENT PRIMARY KEY,
                    user_id  INT NOT NULL,
                    menu_key VARCHAR(40) NOT NULL,
                    UNIQUE KEY uq_user_menu (user_id, menu_key),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS system_flags (
                    flag       VARCHAR(64) PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');

            // Penanda supaya akun bawaan tidak muncul lagi setelah dihapus manual.
            $flag = 'default_users_seeded';
            $seeded = (int) self::$instance->query(
                "SELECT COUNT(*) FROM system_flags WHERE flag = '" . $flag . "'"
            )->fetchColumn();
            if ($seeded === 0) {
                $exists = self::$instance->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                $insert = self::$instance->prepare(
                    'INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)'
                );
                foreach (self::DEFAULT_USERS as [$username, $password, $displayName]) {
                    $exists->execute([$username]);
                    if ((int) $exists->fetchColumn() > 0) {
                        continue;
                    }
                    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName]);
                }

                self::$instance->exec("INSERT IGNORE INTO system_flags (flag) VALUES ('" . $flag . "')");
            }

            // Sekali saja: jadikan ivanrajendriya administrator agar ada yang
            // bisa mengelola user. Setelah itu role diatur dari Manajemen User.
            $adminFlag = 'first_admin_assigned';
            $done = (int) self::$instance->query(
                "SELECT COUNT(*) FROM system_flags WHERE flag = '" . $adminFlag . "'"
            )->fetchColumn();
            if ($done === 0) {
                self::$instance->exec("UPDATE users SET role = 'admin' WHERE username = 'ivanrajendriya'");
                self::$instance->exec("INSERT IGNORE INTO system_flags (flag) VALUES ('" . $adminFlag . "')");
            }

            // User lama (dibuat sebelum fitur izin ada) diberi izin bawaan supaya
            // tidak mendadak kehilangan akses.
            //
            // PENTING: dijalankan SEKALI saja. Kalau diulang tiap request, user
            // yang sengaja dikosongkan izinnya oleh admin akan otomatis dapat
            // semua menu lagi.
            $backfillFlag = 'user_permissions_backfilled';
            $backfilled = (int) self::$instance->query(
                "SELECT COUNT(*) FROM system_flags WHERE flag = '" . $backfillFlag . "'"
            )->fetchColumn();

            if ($backfilled === 0) {
                $rows = self::$instance->query(
                    'SELECT u.id FROM users u
                     WHERE u.role = "user"
                       AND NOT EXISTS (SELECT 1 FROM user_permissions p WHERE p.user_id = u.id)'
                )->fetchAll(PDO::FETCH_COLUMN);

                if ($rows) {
                    $grant = self::$instance->prepare('INSERT IGNORE INTO user_permissions (user_id, menu_key) VALUES (?, ?)');
                    foreach ($rows as $userId) {
                        foreach (self::DEFAULT_USER_PERMISSIONS as $menuKey) {
                            $grant->execute([(int) $userId, $menuKey]);
                        }
                    }
                }

                self::$instance->exec("INSERT IGNORE INTO system_flags (flag) VALUES ('" . $backfillFlag . "')");
            }
        } catch (\PDOException $e) {
            // Tabel tidak bisa dibuat / hak akses terbatas — biarkan request berjalan.
        }
    }

    /**
     * Bucket alokasi bawaan — kerangka konsep FFR: gaji dibelah ke kelompok
     * dana, lalu tiap kelompok diisi daftar item belanja.
     */
    private const DEFAULT_BUCKETS = [
        ['UTAMA',    'Utama',    'wajib',    'Kebutuhan wajib: makan, transport, tagihan, cicilan', 1],
        ['TAMBAHAN', 'Tambahan', 'opsional', 'Keinginan / opsional — yang pertama ditunda bila dana kurang', 2],
        ['TABUNGAN', 'Tabungan', 'simpan',   'Target tabungan bulanan', 3],
        ['DARURAT',  'Dana Darurat', 'simpan', 'Sisihan dana darurat', 4],
    ];

    /**
     * Tabel Family Financial Report. Terpisah total dari tabel Workforce
     * supaya modul keuangan tidak saling mengganggu dengan penjadwalan.
     */
    public static function ensureFfrTables(): void
    {
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_buckets (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    code        VARCHAR(20)  NOT NULL UNIQUE,
                    name        VARCHAR(50)  NOT NULL,
                    type        ENUM("wajib","opsional","simpan") NOT NULL DEFAULT "wajib",
                    description VARCHAR(255) DEFAULT NULL,
                    sort_order  INT NOT NULL DEFAULT 0,
                    is_active   TINYINT(1) NOT NULL DEFAULT 1,
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_categories (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(50) NOT NULL UNIQUE,
                    is_active  TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_periods (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    period     CHAR(7) NOT NULL UNIQUE,
                    status     ENUM("open","closed") NOT NULL DEFAULT "open",
                    notes      VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_incomes (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    period_id  INT NOT NULL,
                    source     VARCHAR(100) NOT NULL,
                    amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
                    owner      ENUM("Ivan","Ricka","Bersama") NOT NULL DEFAULT "Bersama",
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (period_id) REFERENCES ffr_periods(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ');

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_plans (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    period_id  INT NOT NULL,
                    bucket_id  INT NOT NULL,
                    amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
                    percent    DECIMAL(6,2) DEFAULT NULL,
                    source     VARCHAR(8) NOT NULL DEFAULT "rp",
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_ffr_plan (period_id, bucket_id),
                    FOREIGN KEY (period_id) REFERENCES ffr_periods(id) ON DELETE CASCADE,
                    FOREIGN KEY (bucket_id) REFERENCES ffr_buckets(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ');

            // Migrasi database lama: alokasi dana bisa diisi sebagai persen.
            foreach ([
                'percent' => 'DECIMAL(6,2) DEFAULT NULL AFTER amount',
                'source'  => 'VARCHAR(8) NOT NULL DEFAULT "rp" AFTER percent',
            ] as $column => $definition) {
                try {
                    self::$instance->exec('ALTER TABLE ffr_plans ADD COLUMN ' . $column . ' ' . $definition);
                } catch (\PDOException $e) {
                    // Kolom sudah ada — abaikan.
                }
            }

            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_items (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    period_id     INT NOT NULL,
                    bucket_id     INT NOT NULL,
                    category_id   INT DEFAULT NULL,
                    name          VARCHAR(100) NOT NULL,
                    freq_type     ENUM("weekly","biweekly","monthly","once") NOT NULL DEFAULT "monthly",
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
                ) ENGINE=InnoDB
            ');

            // Bucket bawaan hanya diisi saat tabelnya masih kosong.
            $count = (int) self::$instance->query('SELECT COUNT(*) FROM ffr_buckets')->fetchColumn();
            if ($count === 0) {
                $stmt = self::$instance->prepare(
                    'INSERT IGNORE INTO ffr_buckets (code, name, type, description, sort_order) VALUES (?,?,?,?,?)'
                );
                foreach (self::DEFAULT_BUCKETS as $bucket) {
                    $stmt->execute($bucket);
                }
            }
        } catch (\PDOException $e) {
            // Tabel belum siap / hak akses terbatas — biarkan request berjalan.
        }

        self::ensureFfrSavingsTables();
    }

    /**
     * Tabel "Tabunganku" — daftar aset/instrumen investasi keluarga.
     *
     * Satu baris = satu kepemilikan (mis. "Emas Antam 5 gram", "BBCA 10 lot",
     * "USD tunai"). Nilai kini dihitung dari jumlah × harga sekarang, jadi
     * tidak perlu menyimpan angka turunan.
     */
    public static function ensureFfrSavingsTables(): void
    {
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_savings_assets (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    type_key      VARCHAR(20)  NOT NULL DEFAULT "tabungan",
                    name          VARCHAR(100) NOT NULL,
                    ticker        VARCHAR(20)  DEFAULT NULL,
                    institution   VARCHAR(100) DEFAULT NULL,
                    account_no    VARCHAR(50)  DEFAULT NULL,
                    gold_brand    VARCHAR(30)  DEFAULT NULL,
                    owner         ENUM("Ivan","Ricka","Bersama") NOT NULL DEFAULT "Bersama",
                    unit_label    VARCHAR(20)  NOT NULL DEFAULT "Rp",
                    quantity      DECIMAL(18,4) NOT NULL DEFAULT 0,
                    gold_weight_gram DECIMAL(10,3) DEFAULT NULL,
                    avg_price     DECIMAL(18,2) NOT NULL DEFAULT 0,
                    current_price DECIMAL(18,2) NOT NULL DEFAULT 0,
                    sell_price    DECIMAL(18,2) NOT NULL DEFAULT 0,
                    fee_buy       DECIMAL(8,4) NOT NULL DEFAULT 0,
                    fee_sell      DECIMAL(8,4) NOT NULL DEFAULT 0,
                    tax_sell      DECIMAL(8,4) NOT NULL DEFAULT 0,
                    coupon_rate   DECIMAL(8,4) NOT NULL DEFAULT 0,
                    maturity_date DATE DEFAULT NULL,
                    currency      VARCHAR(5)   NOT NULL DEFAULT "IDR",
                    exchange_rate DECIMAL(18,4) NOT NULL DEFAULT 1,
                    buy_date      DATE DEFAULT NULL,
                    price_date    DATE DEFAULT NULL,
                    price_source  VARCHAR(60) DEFAULT NULL,
                    price_fetched_at DATETIME DEFAULT NULL,
                    notes         VARCHAR(255) DEFAULT NULL,
                    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
                    sort_order    INT          NOT NULL DEFAULT 0,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');
        } catch (\PDOException $e) {
            // Biarkan request berjalan bila tabel belum bisa dibuat.
        }

        // Kolom tambahan untuk form per jenis aset (dana cash, emas, valas,
        // saham, obligasi, ...). Ditambahkan satu per satu supaya database
        // lama tetap ikut termigrasi tanpa perlu reset.
        $columns = [
            'account_no'    => 'VARCHAR(50) DEFAULT NULL AFTER institution',
            'ticker'        => 'VARCHAR(20) DEFAULT NULL AFTER name',
            'buy_date'      => 'DATE DEFAULT NULL AFTER exchange_rate',
            'price_date'    => 'DATE DEFAULT NULL AFTER buy_date',
            'sell_price'    => 'DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER current_price',
            'fee_buy'       => 'DECIMAL(8,4) NOT NULL DEFAULT 0 AFTER sell_price',
            'fee_sell'      => 'DECIMAL(8,4) NOT NULL DEFAULT 0 AFTER fee_buy',
            'tax_sell'      => 'DECIMAL(8,4) NOT NULL DEFAULT 0 AFTER fee_sell',
            'coupon_rate'   => 'DECIMAL(8,4) NOT NULL DEFAULT 0 AFTER tax_sell',
            'maturity_date' => 'DATE DEFAULT NULL AFTER coupon_rate',
            'gold_brand'    => 'VARCHAR(30) DEFAULT NULL AFTER account_no',
            'gold_weight_gram' => 'DECIMAL(10,3) DEFAULT NULL AFTER quantity',
            'price_source'  => 'VARCHAR(60) DEFAULT NULL AFTER price_date',
            'price_fetched_at' => 'DATETIME DEFAULT NULL AFTER price_source',
            'archived_at'   => 'DATETIME DEFAULT NULL AFTER is_active',
        ];

        foreach ($columns as $column => $definition) {
            try {
                self::$instance->exec(
                    'ALTER TABLE ffr_savings_assets ADD COLUMN ' . $column . ' ' . $definition
                );
            } catch (\PDOException $e) {
                // Kolom sudah ada — abaikan.
            }
        }

        // Master kategori Tabunganku: kategori mana yang bisa dipilih saat
        // input dan mana yang ikut ditampilkan di portofolio.
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_savings_types (
                    type_key     VARCHAR(20) NOT NULL PRIMARY KEY,
                    show_in_form TINYINT(1)  NOT NULL DEFAULT 1,
                    show_in_list TINYINT(1)  NOT NULL DEFAULT 1,
                    sort_order   INT         NOT NULL DEFAULT 0,
                    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');
        } catch (\PDOException $e) {
            // Biarkan request berjalan bila tabel belum bisa dibuat.
        }

        // Riwayat harian nilai portofolio untuk grafik tren dashboard.
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_savings_snapshots (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    snapshot_date DATE NOT NULL UNIQUE,
                    total_value   DECIMAL(18,2) NOT NULL DEFAULT 0,
                    total_cost    DECIMAL(18,2) NOT NULL DEFAULT 0,
                    total_gross   DECIMAL(18,2) NOT NULL DEFAULT 0,
                    asset_count   INT NOT NULL DEFAULT 0,
                    detail        TEXT DEFAULT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ');
        } catch (\PDOException $e) {
            // Biarkan request berjalan bila tabel belum bisa dibuat.
        }

        // Harga emas harian per brand & pecahan (sumber otomatis atau manual).
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_gold_prices (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    price_date     DATE NOT NULL,
                    brand_key      VARCHAR(30) NOT NULL,
                    weight_gram    DECIMAL(10,3) NOT NULL DEFAULT 1.000,
                    buy_price      DECIMAL(18,2) NOT NULL DEFAULT 0,
                    buyback_price  DECIMAL(18,2) DEFAULT NULL,
                    spot_idr_gram  DECIMAL(18,2) DEFAULT NULL,
                    spot_usd_oz    DECIMAL(18,4) DEFAULT NULL,
                    usd_idr        DECIMAL(18,2) DEFAULT NULL,
                    source         VARCHAR(60) NOT NULL DEFAULT "manual",
                    source_url     VARCHAR(255) DEFAULT NULL,
                    is_manual      TINYINT(1) NOT NULL DEFAULT 0,
                    fetched_at     DATETIME DEFAULT NULL,
                    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_gold_price (price_date, brand_key, weight_gram)
                ) ENGINE=InnoDB
            ');
        } catch (\PDOException $e) {
            // Biarkan request berjalan bila tabel belum bisa dibuat.
        }

        // Buku transaksi Tabunganku: setor, tarik/jual, transfer, koreksi,
        // dan saldo awal. Saldo aset = saldo awal + seluruh transaksi.
        try {
            self::$instance->exec('
                CREATE TABLE IF NOT EXISTS ffr_savings_transactions (
                    id                   INT AUTO_INCREMENT PRIMARY KEY,
                    asset_id             INT NOT NULL,
                    tx_date              DATE NOT NULL,
                    tx_type              VARCHAR(20) NOT NULL DEFAULT "out",
                    quantity             DECIMAL(18,4) NOT NULL DEFAULT 0,
                    price                DECIMAL(18,2) NOT NULL DEFAULT 0,
                    gross_amount         DECIMAL(18,2) NOT NULL DEFAULT 0,
                    fee                  DECIMAL(18,2) NOT NULL DEFAULT 0,
                    tax                  DECIMAL(18,2) NOT NULL DEFAULT 0,
                    amount               DECIMAL(18,2) NOT NULL DEFAULT 0,
                    cost_basis           DECIMAL(18,2) DEFAULT NULL,
                    realized_gain        DECIMAL(18,2) DEFAULT NULL,
                    balance_after        DECIMAL(18,4) NOT NULL DEFAULT 0,
                    counterpart_asset_id INT DEFAULT NULL,
                    transfer_group       VARCHAR(40) DEFAULT NULL,
                    category             VARCHAR(40) DEFAULT NULL,
                    note                 VARCHAR(255) DEFAULT NULL,
                    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_asset_date (asset_id, tx_date),
                    KEY idx_date (tx_date),
                    KEY idx_transfer (transfer_group)
                ) ENGINE=InnoDB
            ');
        } catch (\PDOException $e) {
            // Biarkan request berjalan bila tabel belum bisa dibuat.
        }
    }

    private function __clone() {}
}
