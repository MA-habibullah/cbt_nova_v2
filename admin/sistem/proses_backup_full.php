<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
csrf_verify();

// Setting agar server tidak timeout dan sanggup menangani file banyak
set_time_limit(0); 
ini_set('memory_limit', '512M');

$timestamp = date("Ymd_His");
$zipFilename = "FULL_BACKUP_" . $timestamp . ".zip";
$sqlFilename = "db_temp_" . $timestamp . ".sql";

$backupDir = __DIR__ . '/../../backups/';
$zipPath = $backupDir . $zipFilename;
$sqlPath = $backupDir . $sqlFilename;

if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

// --- 1. BACKUP DATABASE ---
$mysqldumpPath = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "C:\\xampp\\mysql\\bin\\mysqldump.exe" : "mysqldump";
$cmd = sprintf('%s --user=%s --password=%s --host=%s %s > %s 2>&1', 
        escapeshellarg($mysqldumpPath), 
        escapeshellarg($db_user), 
        escapeshellarg($db_pass), 
        escapeshellarg($db_host), 
        escapeshellarg($db_name), 
        escapeshellarg($sqlPath));
exec($cmd);

// --- 2. PROSES ZIP (STRUKTUR FOLDER UTUH) ---
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $rootPath = realpath(__DIR__ . '/../../');
    
    // Gunakan SELF_FIRST agar folder diproses sebelum filenya
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST 
    );

    foreach ($files as $name => $file) {
        $filePath = $file->getRealPath();
        // Buat path relatif untuk di dalam ZIP
        $relativePath = substr($filePath, strlen($rootPath) + 1);

        // Abaikan file ZIP yang sedang dibuat agar tidak terjadi loop
        if ($relativePath === "backups/" . $zipFilename) continue;

        if ($file->isDir()) {
            // LOGIKA KHUSUS FOLDER BACKUPS:
            // Tetap buat foldernya saja di ZIP, tapi jangan ambil isinya
            if (strpos($relativePath, 'backups') === 0) {
                if ($relativePath === 'backups') {
                    $zip->addEmptyDir($relativePath);
                }
                continue; 
            }

            // Tambahkan folder (baik kosong maupun berisi) ke dalam ZIP
            $zip->addEmptyDir($relativePath);
        } else {
            // Jika ini file (bukan folder) dan bukan di dalam folder backups, masukkan ke ZIP
            if (strpos($relativePath, 'backups') !== 0) {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    // Masukkan database ke dalam ZIP di lokasi utama atau di folder backups (pilih satu)
    if (file_exists($sqlPath)) {
        $zip->addFile($sqlPath, "database_backup.sql");
    }
    
    $zip->close();

    // Hapus file SQL sementara setelah berhasil masuk ZIP
    if (file_exists($sqlPath)) unlink($sqlPath);

    log_activity("Backup full berhasil: $zipFilename", null, null, null, 'sistem');
    echo json_encode([
        'status' => 'success',
        'message' => "Backup Berhasil! Struktur folder (termasuk folder kosong) telah diamankan."
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => "Gagal membuat file ZIP."]);
}