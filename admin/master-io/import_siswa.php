<?php
// File: C:\xampp\htdocs\cbt_native\admin\master-io\import_siswa.php
session_start();
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}

if (isset($_POST['import'])) {
    csrf_verify();
    if (!isset($_FILES['file_excel']['tmp_name']) || empty($_FILES['file_excel']['tmp_name']) || !is_uploaded_file($_FILES['file_excel']['tmp_name'])) {
        header("Location: ../master/siswa.php?msg=pilih_file");
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
        header("Location: ../master/siswa.php?msg=invalid_format");
        exit;
    }
    
    try {
        $spreadsheet = IOFactory::load($file);

        // Baca sheet "Data Siswa" by name; fallback ke sheet pertama jika tidak ada
        $sheet = $spreadsheet->getSheetByName('Data Siswa') ?? $spreadsheet->getSheet(0);
        $data  = $sheet->toArray();

        $pdo->beginTransaction();
        
        $count_success = 0;
        $count_skipped = 0;

        // Loop mulai baris ke-2 (Index 1)
        for ($i = 1; $i < count($data); $i++) {
            $nisn     = ltrim(trim((string)($data[$i][0] ?? '')), "'"); // Kolom A
            $nama     = trim((string)($data[$i][1] ?? '')); // Kolom B
            $username = trim((string)($data[$i][2] ?? '')); // Kolom C
            $password = trim((string)($data[$i][3] ?? '')); // Kolom D
            $id_kelas = trim((string)($data[$i][4] ?? '')); // Kolom E
            $id_sesi  = trim((string)($data[$i][5] ?? '')); // Kolom F
            $agama    = trim((string)($data[$i][6] ?? '')); // Kolom G

            // Skip baris kosong, header duplikat, atau baris catatan
            if (empty($nisn) || empty($nama)) continue;
            if (!is_numeric($nisn) && strtoupper($nisn) === 'NISN') continue; // skip header
            if (!is_numeric($id_kelas) || !is_numeric($id_sesi)) continue;    // skip baris catatan
            // echo "Processing: NISN=$nisn, Nama=$nama, Username=$username, KelasID=$id_kelas, Sesi=$id_sesi, Agama=$agama <br>";
        // }
            // --- PROTEKSI NISN GANDA ---
            $check = $pdo->prepare("SELECT COUNT(*) FROM cbt_students WHERE nisn = ? OR username = ?");
            $check->execute([$nisn, $username]);
            
            if ($check->fetchColumn() > 0) {
                $count_skipped++;
                continue; 
            }

            // Hash password (jika kosong gunakan NISN)
            $final_pass = !empty($password) ? $password : $nisn;
            $pass_hashed = password_hash($final_pass, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO cbt_students
                (nisn, nama_lengkap, username, password, kartu, class_id, sesi, agama)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([$nisn, $nama, $username, $pass_hashed, $final_pass, $id_kelas, $id_sesi, $agama]);
            $count_success++;
        }

        $pdo->commit();
        log_activity("Import siswa: $count_success berhasil, $count_skipped dilewati", null, null, null, 'import');
        header("Location: ../master/siswa.php?msg=import_done&success=$count_success&skipped=$count_skipped");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../master/siswa.php?msg=error&detail=" . urlencode($e->getMessage()));
        exit;
    }
}