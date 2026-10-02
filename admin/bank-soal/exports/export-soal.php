<?php
session_start();
// Pastikan path config benar sesuai struktur folder baru
require_once dirname(__DIR__, 3) . '/config/database.php';

$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Ambil data Bank Soal
$stmt = $pdo->prepare("SELECT b.*, s.nama_mapel 
                       FROM cbt_bank_soal b 
                       JOIN cbt_subjects s ON b.subject_id = s.id 
                       WHERE b.id = ?");
$stmt->execute([$id_bank]);
$bank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bank) die("Bank soal tidak ditemukan.");

// 2. Ambil semua soal
$stmt_q = $pdo->prepare("SELECT * FROM cbt_questions WHERE bank_soal_id = ?");
$stmt_q->execute([$id_bank]);
$questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua opsi sekaligus (Lebih Cepat!)
$stmt_o = $pdo->prepare("SELECT o.* FROM cbt_question_options o 
                         JOIN cbt_questions q ON o.question_id = q.id 
                         WHERE q.bank_soal_id = ?");
$stmt_o->execute([$id_bank]);
$all_options = $stmt_o->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC); 
// FETCH_GROUP akan mengelompokkan opsi berdasarkan question_id jika query disesuaikan

// Susun data soal & opsi secara efisien
$data_soal = [];
foreach ($questions as $q) {
    $stmt_o_fix = $pdo->prepare("SELECT * FROM cbt_question_options WHERE question_id = ?");
    $stmt_o_fix->execute([$q['id']]);
    $q['options'] = $stmt_o_fix->fetchAll(PDO::FETCH_ASSOC);
    $data_soal[] = $q;
}

// Ekstrak filename gambar yang tertanam dalam HTML (konten TinyMCE / value_target)
function extractImgFilenames($html) {
    if (empty($html)) return [];
    preg_match_all('/assets\/uploads\/soal\/([^\s"\'<>?#]+)/i', $html, $m);
    return array_unique(array_filter($m[1]));
}

$backup_json = json_encode(['bank' => $bank, 'soal' => $data_soal], JSON_PRETTY_PRINT);

// 3. Proses Kompresi ke ZIP
$zip = new ZipArchive();
// Bersihkan nama file dari karakter aneh
$safe_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $bank['nama_bank_soal']);
$filename = "Backup_" . $safe_name . "_" . date('YmdHis') . ".zip";

// Gunakan folder temp sistem agar tidak perlu buat folder manual
$zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Tambahkan data JSON
    $zip->addFromString('data.json', $backup_json);

    // Kumpulkan semua gambar: dari media_files, konten_soal HTML, dan value_target opsi
    $uploadDir = dirname(__DIR__, 3) . "/assets/uploads/soal/";
    $allImages = [];
    foreach ($data_soal as $qs) {
        if (!empty($qs['media_files'])) $allImages[] = $qs['media_files'];
        foreach (extractImgFilenames($qs['konten_soal'] ?? '') as $f) $allImages[] = $f;
        foreach ($qs['options'] as $o) {
            foreach (extractImgFilenames($o['value_target'] ?? '') as $f) $allImages[] = $f;
        }
    }
    foreach (array_unique(array_filter($allImages)) as $fname) {
        $filePath = $uploadDir . $fname;
        if (file_exists($filePath)) {
            $zip->addFile($filePath, 'images/' . $fname);
        }
    }
    $zip->close();

    // ... (setelah proses $zip->close())

    if (file_exists($zipPath)) {
        // 1. Bersihkan semua output buffer yang mungkin ada
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 2. Kirim header download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        
        // 3. Baca file dan kirim ke browser
        readfile($zipPath);
        
        // 4. Hapus file sementara
        unlink($zipPath);
        exit;
    }
    die("Gagal membuat file ZIP.");
}