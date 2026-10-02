<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); exit;
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST['import'])) {
    csrf_verify();
    if (!isset($_FILES['file_excel']['tmp_name']) || empty($_FILES['file_excel']['tmp_name']) || !is_uploaded_file($_FILES['file_excel']['tmp_name'])) {
        header("Location: ../master/guru.php?msg=pilih_file");
        exit;
    }

    $file = $_FILES['file_excel']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file_excel']['name'] ?? '', PATHINFO_EXTENSION));
    
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);
    }

    $allowed_mimes = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream',
        'application/zip',
        'text/plain',
        ''
    ];
    if (!in_array($ext, ['xlsx', 'xls', 'csv']) || ($mime !== '' && !in_array($mime, $allowed_mimes))) {
        header("Location: ../master/guru.php?msg=invalid_format");
        exit;
    }
    
    try {
        $spreadsheet = IOFactory::load($file);
        $data = $spreadsheet->getActiveSheet()->toArray();

        $pdo->beginTransaction();
        
        $count_success = 0;
        $count_skipped = 0;
        $skipped_names = [];

        // Looping mulai baris ke-2
        for ($i = 1; $i < count($data); $i++) {
            $nip      = trim($data[$i][0]);
            $nama     = trim($data[$i][1]);
            $username = trim($data[$i][2]);
            $pass_raw = trim($data[$i][3]);

            if (empty($nama) || empty($username)) continue;

            // --- PROTEKSI DATA GANDA ---
            // Cek apakah username sudah terdaftar
            $check = $pdo->prepare("SELECT COUNT(*) FROM cbt_teachers WHERE username = ?");
            $check->execute([$username]);
            
            if ($check->fetchColumn() > 0) {
                // Jika sudah ada, jangan insert, tapi catat
                $count_skipped++;
                $skipped_names[] = $nama;
                continue; 
            }

            $final_pass = !empty($pass_raw) ? $pass_raw : $nip;
            $password_hashed = password_hash($final_pass, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO cbt_teachers (nip, nama_lengkap, username, password, is_aktif, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$nip, $nama, $username, $password_hashed]);
            $count_success++;
        }

        $pdo->commit();
        log_activity("Import guru: $count_success berhasil, $count_skipped dilewati", null, null, null, 'import');

        // Simpan info skipping ke session agar bisa ditampilkan di halaman guru.php
        if ($count_skipped > 0) {
            $_SESSION['import_info'] = [
                'skipped' => $count_skipped,
                'names' => $skipped_names
            ];
        }

        header("Location: ../master/guru.php?msg=import_done&success=$count_success&skipped=$count_skipped");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../master/guru.php?msg=error&detail=" . urlencode($e->getMessage()));
        exit;
    }
}