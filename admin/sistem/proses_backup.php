<?php
ob_start();
require_once '../../config/database.php';
if (ob_get_length()) ob_clean();
csrf_verify();

set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$filename  = "backup_" . DB_NAME . "_" . date("Ymd_His") . ".sql";
$backupDir = __DIR__ . '/../../backups/';
$path      = $backupDir . $filename;

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

try {
    $handle = fopen($path, 'w');
    if (!$handle) {
        throw new RuntimeException("Tidak bisa membuat file backup di: $path");
    }

    fwrite($handle, "-- AXON CBT Database Backup\n");
    fwrite($handle, "-- Generated: " . date("Y-m-d H:i:s") . "\n");
    fwrite($handle, "-- Database: " . DB_NAME . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
    fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($handle, "SET NAMES utf8mb4;\n\n");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Struktur tabel
        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Table: $table\n");
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($handle, $createRow[1] . ";\n\n");

        // Nama kolom
        $cols    = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $colList = implode("`, `", $cols);

        // Fetch baris satu per satu — tidak load semua ke RAM
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $stmt->setFetchMode(PDO::FETCH_NUM);

        $chunk = [];
        while ($row = $stmt->fetch()) {
            $values = array_map(function ($val) use ($pdo) {
                return $val === null ? 'NULL' : $pdo->quote($val);
            }, $row);
            $chunk[] = "(" . implode(", ", $values) . ")";

            if (count($chunk) >= 100) {
                fwrite($handle, "INSERT INTO `$table` (`$colList`) VALUES\n" . implode(",\n", $chunk) . ";\n");
                $chunk = [];
            }
        }
        if (!empty($chunk)) {
            fwrite($handle, "INSERT INTO `$table` (`$colList`) VALUES\n" . implode(",\n", $chunk) . ";\n");
        }

        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);

    log_activity("Backup database berhasil: $filename", null, null, null, 'sistem');

    echo json_encode([
        'status'  => 'success',
        'message' => "Backup berhasil dibuat: $filename"
    ]);

} catch (Throwable $e) {
    if (!empty($handle) && is_resource($handle)) {
        fclose($handle);
        @unlink($path);
    }
    echo json_encode([
        'status'  => 'error',
        'message' => "Gagal membuat backup: " . $e->getMessage()
    ]);
}

ob_end_flush();
