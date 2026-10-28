<?php
/**
 * Main Router & Controller — PaySlip Pro (Aplikasi Web Slip Gaji & Payroll PHP + MySQL)
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDB();
$settings = getCompanySettings($pdo);
$action = $_GET['action'] ?? '';

// ============================================================================
// ACTION HANDLERS (POST / GET ACTIONS)
// ============================================================================

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrNik = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM employees WHERE (email = ? OR nik = ?) AND is_active = 1 LIMIT 1");
    $stmt->execute([$emailOrNik, $emailOrNik]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'nik'       => $user['nik'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role']
        ];
        logActivity($pdo, $user['full_name'], 'LOGIN_SUCCESS', 'Berhasil masuk ke portal aplikasi.');
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selamat datang kembali, ' . $user['full_name'] . '!'];
        $target = ($user['role'] === 'employee') ? 'my_slips' : 'dashboard';
        header("Location: index.php?page=$target");
        exit;
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Email/NIK atau Kata Sandi tidak sesuai. Silakan gunakan tombol Demo Login di sebelah kiri.'];
        header("Location: index.php?page=login");
        exit;
    }
}

if ($action === 'logout') {
    unset($_SESSION['user']);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Anda telah berhasil keluar dari sesi aplikasi.'];
    header("Location: index.php?page=login");
    exit;
}

if ($action === 'generate_payroll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)($_POST['month'] ?? 10);
    $year  = (int)($_POST['year'] ?? 2026);

    $emps = $pdo->query("SELECT id FROM employees WHERE is_active = 1")->fetchAll();
    $count = 0;
    foreach ($emps as $e) {
        generateSinglePayslip($pdo, (int)$e['id'], $month, $year, $settings, false);
        $count++;
    }
    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'GENERATE_PAYROLL', "Generate ulang $count slip gaji periode " . getMonthName($month) . " $year.");
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Berhasil menghitung & menerbitkan $count Slip Gaji untuk periode " . getMonthName($month) . " $year!"];
    header("Location: index.php?page=payroll&month=$month&year=$year");
    exit;
}

if ($action === 'save_employee' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $nik         = trim($_POST['nik'] ?? '');
    $fullName    = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $deptId      = (int)($_POST['department_id'] ?? 1);
    $posId       = (int)($_POST['position_id'] ?? 1);
    $empStatus   = trim($_POST['employment_status'] ?? 'PKWTT (Tetap)');
    $ptkp        = trim($_POST['ptkp_status'] ?? 'TK/0');
    $basicSalary = (float)($_POST['basic_salary'] ?? 0);
    $posAllow    = (float)($_POST['position_allowance'] ?? 0);
    $mealAllow   = (float)($_POST['daily_meal_allowance'] ?? 35000);
    $transAllow  = (float)($_POST['daily_transport_allowance'] ?? 30000);
    $bankName    = trim($_POST['bank_name'] ?? 'BCA');
    $bankAcc     = trim($_POST['bank_account_number'] ?? '');
    $npwp        = trim($_POST['npwp'] ?? '');
    $role        = trim($_POST['role'] ?? 'employee');

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE employees SET
            nik = ?, full_name = ?, email = ?, phone = ?, department_id = ?, position_id = ?,
            employment_status = ?, ptkp_status = ?, basic_salary = ?, position_allowance = ?,
            daily_meal_allowance = ?, daily_transport_allowance = ?, bank_name = ?, bank_account_number = ?,
            bank_account_holder = ?, npwp = ?, role = ?
            WHERE id = ?");
        $stmt->execute([
            $nik, $fullName, $email, $phone, $deptId, $posId,
            $empStatus, $ptkp, $basicSalary, $posAllow,
            $mealAllow, $transAllow, $bankName, $bankAcc,
            $fullName, $npwp, $role, $id
        ]);
        generateSinglePayslip($pdo, $id, 10, 2026, $settings, false);
        logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'UPDATE_EMPLOYEE', "Memperbarui data & gaji karyawan: $fullName ($nik).");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Data karyawan $fullName berhasil diperbarui & slip gaji dihitung ulang!"];
    } else {
        $passHash = password_hash('karyawan123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO employees (
            nik, full_name, email, password_hash, role, phone, department_id, position_id,
            employment_status, join_date, ptkp_status, npwp, bank_name, bank_account_number,
            bank_account_holder, basic_salary, position_allowance, daily_meal_allowance, daily_transport_allowance
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nik, $fullName, $email, $passHash, $role, $phone, $deptId, $posId,
            $empStatus, date('Y-m-d'), $ptkp, $npwp, $bankName, $bankAcc,
            $fullName, $basicSalary, $posAllow, $mealAllow, $transAllow
        ]);
        $newEmpId = (int)$pdo->lastInsertId();
        generateSinglePayslip($pdo, $newEmpId, 10, 2026, $settings, false);
        logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'CREATE_EMPLOYEE', "Menambahkan karyawan baru: $fullName ($nik).");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Karyawan baru $fullName berhasil ditambahkan (Password default: karyawan123)!"];
    }
    header("Location: index.php?page=employees");
    exit;
}

if ($action === 'save_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)($_POST['month'] ?? 10);
    $year  = (int)($_POST['year'] ?? 2026);
    $attIds = $_POST['att_id'] ?? [];
    $empIds = $_POST['emp_id'] ?? [];
    $workDays = $_POST['working_days'] ?? [];
    $presDays = $_POST['present_days'] ?? [];
    $lateDays = $_POST['late_days'] ?? [];
    $alphaDays = $_POST['alpha_days'] ?? [];
    $ovtHours = $_POST['overtime_hours'] ?? [];
    $bonuses  = $_POST['bonus_amount'] ?? [];
    $thrs     = $_POST['thr_amount'] ?? [];

    $stmtUpd = $pdo->prepare("UPDATE attendances SET working_days = ?, present_days = ?, late_days = ?, alpha_days = ?, overtime_hours = ?, bonus_amount = ?, thr_amount = ? WHERE id = ?");

    foreach ($attIds as $i => $attId) {
        $stmtUpd->execute([
            (int)($workDays[$i] ?? 22),
            (int)($presDays[$i] ?? 22),
            (int)($lateDays[$i] ?? 0),
            (int)($alphaDays[$i] ?? 0),
            (float)($ovtHours[$i] ?? 0),
            (float)($bonuses[$i] ?? 0),
            (float)($thrs[$i] ?? 0),
            (int)$attId
        ]);
        if (!empty($empIds[$i])) {
            generateSinglePayslip($pdo, (int)$empIds[$i], $month, $year, $settings, false);
        }
    }

    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'UPDATE_ATTENDANCE', "Memperbarui rekap absensi, lembur & bonus periode " . getMonthName($month) . " $year.");
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Data absensi, jam lembur, dan bonus berhasil disimpan! Seluruh Slip Gaji periode ini telah diperbarui otomatis.'];
    header("Location: index.php?page=attendance&month=$month&year=$year");
    exit;
}

if ($action === 'save_loan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId     = (int)($_POST['employee_id'] ?? 1);
    $principal = (float)($_POST['principal_amount'] ?? 0);
    $tenor     = max(1, (int)($_POST['tenor_months'] ?? 1));
    $installment = round($principal / $tenor);
    $reason    = trim($_POST['reason'] ?? 'Pinjaman Karyawan');
    $loanCode  = 'KSB-' . date('Y') . '-' . rand(100, 999);

    $stmt = $pdo->prepare("INSERT INTO loans (employee_id, loan_code, loan_date, principal_amount, tenor_months, monthly_installment, remaining_amount, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$empId, $loanCode, date('Y-m-d'), $principal, $tenor, $installment, $principal, $reason]);

    generateSinglePayslip($pdo, $empId, 10, 2026, $settings, false);
    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'CREATE_LOAN', "Mencatat pinjaman baru $loanCode sebesar " . formatRupiah($principal));
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Pinjaman $loanCode berhasil dicatat dan otomatis terintegrasi ke potongan slip gaji!"];
    header("Location: index.php?page=loans");
    exit;
}

if ($action === 'mark_loan_paid') {
    $loanId = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE loans SET status = 'paid', remaining_amount = 0 WHERE id = ?");
    $stmt->execute([$loanId]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status pinjaman berhasil ditandai LUNAS.'];
    header("Location: index.php?page=loans");
    exit;
}

if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE company_settings SET
        company_name = ?, company_npwp = ?, company_email = ?, company_phone = ?, company_address = ?,
        signer_name = ?, signer_title = ?, overtime_rate_per_hour = ?, late_penalty_per_day = ?, alpha_penalty_per_day = ?,
        seo_meta_title = ?, seo_meta_description = ?, seo_meta_keywords = ?
        WHERE id = ?");
    $stmt->execute([
        trim($_POST['company_name'] ?? ''),
        trim($_POST['company_npwp'] ?? ''),
        trim($_POST['company_email'] ?? ''),
        trim($_POST['company_phone'] ?? ''),
        trim($_POST['company_address'] ?? ''),
        trim($_POST['signer_name'] ?? ''),
        trim($_POST['signer_title'] ?? ''),
        (float)($_POST['overtime_rate_per_hour'] ?? 40000),
        (float)($_POST['late_penalty_per_day'] ?? 25000),
        (float)($_POST['alpha_penalty_per_day'] ?? 150000),
        trim($_POST['seo_meta_title'] ?? ''),
        trim($_POST['seo_meta_description'] ?? ''),
        trim($_POST['seo_meta_keywords'] ?? ''),
        $settings['id']
    ]);
    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'UPDATE_SETTINGS', 'Memperbarui pengaturan perusahaan & meta SEO.');
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pengaturan perusahaan, tarif payroll, dan Meta SEO berhasil disimpan!'];
    header("Location: index.php?page=settings");
    exit;
}

if ($action === 'save_mysql_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newCfg = [
        'driver'   => 'mysql',
        'host'     => trim($_POST['host'] ?? '127.0.0.1'),
        'port'     => trim($_POST['port'] ?? '3306'),
        'dbname'   => trim($_POST['dbname'] ?? 'db_slip_gaji'),
        'username' => trim($_POST['username'] ?? 'root'),
        'password' => $_POST['password'] ?? ''
    ];
    file_put_contents(CONFIG_FILE, json_encode($newCfg, JSON_PRETTY_PRINT));

    try {
        $dsn = "mysql:host={$newCfg['host']};port={$newCfg['port']};charset=utf8mb4";
        $testPdo = new PDO($dsn, $newCfg['username'], $newCfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        $testPdo->exec("CREATE DATABASE IF NOT EXISTS `{$newCfg['dbname']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $testPdo->exec("USE `{$newCfg['dbname']}`");
        initializeSchemaAndSeed($testPdo, 'mysql');
        ensureBumdesSchema($testPdo, 'mysql');
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Berhasil terhubung ke MySQL ({$newCfg['host']}:{$newCfg['port']}/{$newCfg['dbname']}) dan seluruh tabel otomatis dibuat!"];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Koneksi MySQL gagal: ' . $e->getMessage() . ' — Aplikasi tetap berjalan aman menggunakan engine lokal.'];
    }
    header("Location: index.php?page=settings");
    exit;
}

if ($action === 'add_bumdes_journal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $transDate = $_POST['trans_date'] ?? date('Y-m-d');
    $unitId    = !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
    $subType   = trim($_POST['sub_ledger_type'] ?? 'Kas & Bank');
    $partner   = trim($_POST['partner_name'] ?? 'Mitra BUMDes');
    $debitId   = (int)($_POST['debit_account_id'] ?? 1);
    $creditId  = (int)($_POST['credit_account_id'] ?? 1);
    $amount    = (float)($_POST['amount'] ?? 0);
    $desc      = trim($_POST['description'] ?? 'Transaksi Operasional BUMDes');
    $returnTab = $_POST['return_tab'] ?? 'subledger';

    if ($debitId === $creditId) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Akun Debit dan Akun Kredit tidak boleh sama!'];
        header("Location: index.php?page=bumdes_finance&tab=$returnTab");
        exit;
    }

    $jNo = 'JRN-' . date('Y') . '-' . rand(200, 999);
    $stmt = $pdo->prepare("INSERT INTO bumdes_journals (journal_no, trans_date, unit_id, partner_name, sub_ledger_type, description, debit_account_id, credit_account_id, amount, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $jNo, $transDate, $unitId, $partner, $subType, $desc,
        $debitId, $creditId, $amount, $_SESSION['user']['full_name'] ?? 'Bendahara BUMDes'
    ]);

    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'POST_JOURNAL', "Posting jurnal $jNo ($desc) senilai " . formatRupiah($amount));
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Jurnal $jNo berhasil diposting! Buku Besar, Neraca Saldo, Laba Rugi, Arus Kas & Neraca telah diperbarui otomatis."];
    header("Location: index.php?page=bumdes_finance&tab=$returnTab");
    exit;
}

if ($action === 'sync_payroll_journal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $totalOctPayroll = (float)$pdo->query("SELECT COALESCE(SUM(net_salary), 0) FROM payslips WHERE period_month = 10 AND period_year = 2026")->fetchColumn();
    if ($totalOctPayroll <= 0) {
        $totalOctPayroll = 84500000;
    }

    $accPayrollId = (int)$pdo->query("SELECT id FROM bumdes_accounts WHERE account_code = '5-201'")->fetchColumn();
    $accBankId    = (int)$pdo->query("SELECT id FROM bumdes_accounts WHERE account_code = '1-102'")->fetchColumn();

    if ($accPayrollId && $accBankId) {
        $stmtUpd = $pdo->prepare("UPDATE bumdes_journals SET amount = ?, description = ?, trans_date = ? WHERE journal_no = 'JRN-2026-108'");
        $stmtUpd->execute([
            $totalOctPayroll,
            'Sinkronisasi Otomatis Pembayaran Slip Gaji Pegawai BUMDes (Periode Oktober 2026)',
            date('Y-m-d')
        ]);
    }

    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'SYNC_PAYROLL_JOURNAL', 'Sinkronisasi realisasi Slip Gaji ke Jurnal Akuntansi BUMDes sebesar ' . formatRupiah($totalOctPayroll));
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Total pengeluaran Slip Gaji (' . formatRupiah($totalOctPayroll) . ') berhasil disinkronkan ke Jurnal Akuntansi & Laporan Laba Rugi BUMDes!'];
    header("Location: index.php?page=bumdes_finance&tab=income");
    exit;
}

if ($action === 'save_shu_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pades = (float)($_POST['pades_percent'] ?? 35);
    $cap   = (float)($_POST['capital_reserve_percent'] ?? 30);
    $mgt   = (float)($_POST['management_incentive_percent'] ?? 15);
    $adv   = (float)($_POST['advisor_supervisor_percent'] ?? 5);
    $soc   = (float)($_POST['social_education_percent'] ?? 10);
    $gen   = (float)($_POST['general_reserve_percent'] ?? 5);
    $mode  = $_POST['mode'] ?? 'update';

    $totalPct = round($pades + $cap + $mgt + $adv + $soc + $gen, 2);
    if (abs($totalPct - 100.0) > 0.01) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Total persentase pembagian SHU harus berjumlah 100% (Saat ini: {$totalPct}%)."];
        header("Location: index.php?page=bumdes_finance&tab=shu");
        exit;
    }

    $isClosed = ($mode === 'close_book') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE bumdes_shu_settings SET pades_percent = ?, capital_reserve_percent = ?, management_incentive_percent = ?, advisor_supervisor_percent = ?, social_education_percent = ?, general_reserve_percent = ?, is_closed = ?, closed_at = CURRENT_TIMESTAMP");
    $stmt->execute([$pades, $cap, $mgt, $adv, $soc, $gen, $isClosed]);

    $msg = ($isClosed === 1)
        ? 'Proses Tutup Buku Akhir Tahun BUMDes selesai! Alokasi Bagi Hasil PADes dan SHU telah disahkan.'
        : 'Konfigurasi persentase Pembagian Hasil Usaha (SHU) berhasil diperbarui!';
    logActivity($pdo, $_SESSION['user']['full_name'] ?? 'Admin', 'SHU_TUTUP_BUKU', $msg);
    $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
    header("Location: index.php?page=bumdes_finance&tab=shu");
    exit;
}

if ($action === 'save_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $docNumber   = trim($_POST['doc_number'] ?? '');
    $category    = trim($_POST['category'] ?? 'Dokumen Unit Usaha');
    $unitId      = !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
    $docDate     = $_POST['doc_date'] ?? date('Y-m-d');
    $expiryDate  = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $accessLevel = trim($_POST['access_level'] ?? 'Internal BUMDes');
    $description = trim($_POST['description'] ?? '');
    $uploadedBy  = $_SESSION['user']['full_name'] ?? 'Sekretaris BUMDes';

    $docCode   = 'DOC-BUMDES-' . rand(100, 999);
    $fileName  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title) . '.pdf';
    $fileExt   = 'PDF';
    $fileSizeKb = rand(320, 1850);

    if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $origName = basename($_FILES['doc_file']['name']);
        $ext = strtoupper(pathinfo($origName, PATHINFO_EXTENSION) ?: 'PDF');
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
        $destPath = BASE_PATH . '/storage/documents/' . $safeName;
        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $destPath)) {
            $fileName = $safeName;
            $fileExt  = $ext;
            $fileSizeKb = max(1, (int)round($_FILES['doc_file']['size'] / 1024));
        }
    }

    $stmt = $pdo->prepare("INSERT INTO bumdes_documents (doc_code, doc_number, title, category, unit_id, doc_date, expiry_date, access_level, file_name, file_ext, file_size_kb, description, uploaded_by, download_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([
        $docCode, $docNumber, $title, $category, $unitId, $docDate, $expiryDate,
        $accessLevel, $fileName, $fileExt, $fileSizeKb, $description, $uploadedBy
    ]);

    logActivity($pdo, $uploadedBy, 'UPLOAD_DOCUMENT', "Menyimpan dokumen arsip BUMDes: $title ($docNumber)");
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Dokumen '$title' ($docCode) berhasil disimpan ke dalam E-Arsip Digital BUMDes!"];
    header("Location: index.php?page=documents");
    exit;
}

if ($action === 'delete_document') {
    $docId = (int)($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM bumdes_documents WHERE id = ?")->execute([$docId]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Dokumen berhasil dihapus dari repositori E-Arsip BUMDes.'];
    header("Location: index.php?page=documents");
    exit;
}

if ($action === 'export_bank_csv') {
    $month = (int)($_GET['month'] ?? 10);
    $year  = (int)($_GET['year'] ?? 2026);

    $stmt = $pdo->prepare("
        SELECT p.slip_code, e.nik, e.full_name, e.bank_name, e.bank_account_number, e.bank_account_holder,
               p.gross_salary, p.total_deductions, p.net_salary, p.payment_date
        FROM payslips p
        JOIN employees e ON p.employee_id = e.id
        WHERE p.period_month = ? AND p.period_year = ?
        ORDER BY e.id ASC
    ");
    $stmt->execute([$month, $year]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Payroll_Bank_AutoCredit_' . $year . '_' . sprintf('%02d', $month) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No_Slip', 'NIK', 'Nama_Karyawan', 'Nama_Bank', 'Nomor_Rekening', 'Atas_Nama_Rekening', 'Gaji_Bruto_IDR', 'Total_Potongan_IDR', 'Take_Home_Pay_Transfer_IDR', 'Tanggal_Transfer']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['slip_code'],
            $r['nik'],
            $r['full_name'],
            $r['bank_name'],
            $r['bank_account_number'],
            $r['bank_account_holder'],
            $r['gross_salary'],
            $r['total_deductions'],
            $r['net_salary'],
            $r['payment_date']
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================================
// PAGE ROUTING
// ============================================================================
$allowedPages = ['landing', 'login', 'verify', 'dashboard', 'payroll', 'print_slip', 'employees', 'attendance', 'loans', 'bumdes_finance', 'documents', 'view_document', 'print_report', 'my_slips', 'settings'];
$page = $_GET['page'] ?? (isset($_SESSION['user']) ? ($_SESSION['user']['role'] === 'employee' ? 'my_slips' : 'dashboard') : 'landing');

if (!in_array($page, $allowedPages, true)) {
    $page = 'landing';
}

// Proteksi Halaman Internal
$publicPages = ['landing', 'login', 'verify', 'print_slip', 'view_document', 'print_report'];
if (!in_array($page, $publicPages, true) && empty($_SESSION['user'])) {
    header("Location: index.php?page=login");
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/pages/' . $page . '.php';
require_once __DIR__ . '/includes/footer.php';
