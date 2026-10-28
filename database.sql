-- ============================================================================
-- DATABASE SCHEMA: APLIKASI WEB SLIP GAJI & PAYROLL PRO (PHP & MySQL)
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `db_slip_gaji` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_slip_gaji`;

-- 1. Tabel Pengaturan Perusahaan & SEO
CREATE TABLE IF NOT EXISTS `company_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `company_name` VARCHAR(150) NOT NULL DEFAULT 'PT Nusantara Digital Solusindo',
    `company_tagline` VARCHAR(255) NOT NULL DEFAULT 'Solusi Manajemen HR & Slip Gaji Digital Terintegrasi',
    `company_npwp` VARCHAR(50) NOT NULL DEFAULT '01.234.567.8-091.000',
    `company_address` TEXT NOT NULL,
    `company_phone` VARCHAR(50) NOT NULL DEFAULT '(021) 5588-9900',
    `company_email` VARCHAR(100) NOT NULL DEFAULT 'hrd@nusantaradigital.co.id',
    `signer_name` VARCHAR(100) NOT NULL DEFAULT 'Dr. Hendra Wijaya, M.M.',
    `signer_title` VARCHAR(100) NOT NULL DEFAULT 'Head of Human Capital & Finance',
    `overtime_rate_per_hour` DECIMAL(15,2) NOT NULL DEFAULT 35000.00,
    `late_penalty_per_day` DECIMAL(15,2) NOT NULL DEFAULT 25000.00,
    `alpha_penalty_per_day` DECIMAL(15,2) NOT NULL DEFAULT 150000.00,
    `seo_meta_title` VARCHAR(255) NOT NULL DEFAULT 'PaySlip Pro - Aplikasi Slip Gaji & Payroll Online Berbasis Web',
    `seo_meta_description` TEXT NOT NULL,
    `seo_meta_keywords` VARCHAR(255) NOT NULL DEFAULT 'aplikasi slip gaji, payroll online, hitung pph 21 ter, slip gaji karyawan pdf, hris indonesia',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Divisi / Departemen
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `manager_name` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Jabatan / Positions
CREATE TABLE IF NOT EXISTS `positions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `level` VARCHAR(50) NOT NULL DEFAULT 'Staff',
    `default_position_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Karyawan & Akun Pengguna
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nik` VARCHAR(30) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'employee',
    `phone` VARCHAR(30) DEFAULT NULL,
    `department_id` INT NOT NULL,
    `position_id` INT NOT NULL,
    `employment_status` VARCHAR(30) NOT NULL DEFAULT 'PKWTT (Tetap)',
    `join_date` DATE NOT NULL,
    `ptkp_status` VARCHAR(15) NOT NULL DEFAULT 'TK/0',
    `npwp` VARCHAR(40) DEFAULT NULL,
    `bank_name` VARCHAR(50) NOT NULL DEFAULT 'BCA',
    `bank_account_number` VARCHAR(50) NOT NULL,
    `bank_account_holder` VARCHAR(150) NOT NULL,
    `basic_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `position_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `daily_meal_allowance` DECIMAL(15,2) NOT NULL DEFAULT 35000.00,
    `daily_transport_allowance` DECIMAL(15,2) NOT NULL DEFAULT 30000.00,
    `bpjs_kesehatan_active` TINYINT(1) NOT NULL DEFAULT 1,
    `bpjs_tk_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`position_id`) REFERENCES `positions`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Absensi & Lembur Bulanan
CREATE TABLE IF NOT EXISTS `attendances` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `employee_id` INT NOT NULL,
    `period_month` INT NOT NULL,
    `period_year` INT NOT NULL,
    `working_days` INT NOT NULL DEFAULT 22,
    `present_days` INT NOT NULL DEFAULT 22,
    `late_days` INT NOT NULL DEFAULT 0,
    `leave_days` INT NOT NULL DEFAULT 0,
    `alpha_days` INT NOT NULL DEFAULT 0,
    `overtime_hours` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `bonus_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `thr_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_emp_period` (`employee_id`, `period_month`, `period_year`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Pinjaman / Kasbon Karyawan
CREATE TABLE IF NOT EXISTS `loans` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `employee_id` INT NOT NULL,
    `loan_code` VARCHAR(30) NOT NULL UNIQUE,
    `loan_date` DATE NOT NULL,
    `principal_amount` DECIMAL(15,2) NOT NULL,
    `tenor_months` INT NOT NULL DEFAULT 1,
    `monthly_installment` DECIMAL(15,2) NOT NULL,
    `remaining_amount` DECIMAL(15,2) NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel Slip Gaji (Payslips)
CREATE TABLE IF NOT EXISTS `payslips` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `slip_code` VARCHAR(40) NOT NULL UNIQUE,
    `verification_hash` VARCHAR(64) NOT NULL UNIQUE,
    `employee_id` INT NOT NULL,
    `period_month` INT NOT NULL,
    `period_year` INT NOT NULL,
    `working_days` INT NOT NULL DEFAULT 22,
    `present_days` INT NOT NULL DEFAULT 22,
    `late_days` INT NOT NULL DEFAULT 0,
    `alpha_days` INT NOT NULL DEFAULT 0,
    `overtime_hours` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `basic_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `position_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `meal_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `transport_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `overtime_pay` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bonus_pay` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `thr_pay` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `gross_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bpjs_kes_emp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bpjs_tk_jht_emp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bpjs_tk_jp_emp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bpjs_kes_comp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bpjs_tk_comp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_pph21` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_ter_category` VARCHAR(10) NOT NULL DEFAULT 'TER A',
    `tax_ter_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `late_deduction` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `alpha_deduction` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `loan_deduction` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_deductions` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `net_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Published',
    `payment_date` DATE DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_slip_emp_period` (`employee_id`, `period_month`, `period_year`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabel Audit Log Aktivitas
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_name` VARCHAR(100) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT '127.0.0.1',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- MODUL AKUNTANSI & LAPORAN KEUANGAN BUMDes (PP 11/2021 & Kepmendesa)
-- ============================================================================

-- 9. Tabel Unit Usaha BUMDes
CREATE TABLE IF NOT EXISTS `bumdes_units` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `unit_code` VARCHAR(20) NOT NULL UNIQUE,
    `unit_name` VARCHAR(120) NOT NULL,
    `manager_name` VARCHAR(100) NOT NULL,
    `capital_allocated` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `assets_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `short_term_debt` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `target_revenue` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Tabel Bagan Akun Standar / Chart of Accounts (COA) BUMDes
CREATE TABLE IF NOT EXISTS `bumdes_accounts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `account_code` VARCHAR(20) NOT NULL UNIQUE,
    `account_name` VARCHAR(150) NOT NULL,
    `account_type` ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense') NOT NULL,
    `sub_category` VARCHAR(60) NOT NULL,
    `normal_balance` ENUM('Debit', 'Kredit') NOT NULL,
    `cash_flow_type` ENUM('Operating', 'Investing', 'Financing', 'Cash', 'None') NOT NULL DEFAULT 'None',
    `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Tabel Jurnal Umum & Buku Bantu BUMDes
CREATE TABLE IF NOT EXISTS `bumdes_journals` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `journal_no` VARCHAR(30) NOT NULL UNIQUE,
    `trans_date` DATE NOT NULL,
    `unit_id` INT DEFAULT NULL,
    `partner_name` VARCHAR(120) DEFAULT NULL,
    `sub_ledger_type` VARCHAR(40) DEFAULT 'Kas & Bank',
    `description` VARCHAR(255) NOT NULL,
    `debit_account_id` INT NOT NULL,
    `credit_account_id` INT NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_by` VARCHAR(100) DEFAULT 'Admin BUMDes',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`unit_id`) REFERENCES `bumdes_units`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`debit_account_id`) REFERENCES `bumdes_accounts`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`credit_account_id`) REFERENCES `bumdes_accounts`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Tabel Konfigurasi Pembagian Hasil Usaha (SHU) Tutup Buku BUMDes
CREATE TABLE IF NOT EXISTS `bumdes_shu_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `pades_percent` DECIMAL(5,2) NOT NULL DEFAULT 35.00,
    `capital_reserve_percent` DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    `management_incentive_percent` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `advisor_supervisor_percent` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    `social_education_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `general_reserve_percent` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    `fiscal_year` INT NOT NULL DEFAULT 2026,
    `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
    `closed_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Tabel Penyimpanan Dokumen & E-Arsip Digital BUMDes
CREATE TABLE IF NOT EXISTS `bumdes_documents` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `doc_code` VARCHAR(40) NOT NULL UNIQUE,
    `doc_number` VARCHAR(100) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `category` VARCHAR(80) NOT NULL,
    `unit_id` INT DEFAULT NULL,
    `doc_date` DATE NOT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `access_level` VARCHAR(30) NOT NULL DEFAULT 'Internal BUMDes',
    `file_name` VARCHAR(255) NOT NULL,
    `file_ext` VARCHAR(20) NOT NULL DEFAULT 'PDF',
    `file_size_kb` INT NOT NULL DEFAULT 240,
    `description` TEXT DEFAULT NULL,
    `uploaded_by` VARCHAR(100) NOT NULL DEFAULT 'Sekretaris BUMDes',
    `download_count` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`unit_id`) REFERENCES `bumdes_units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


