<?php
session_start();
ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}

// 1. Tentukan Path Autoload
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die("Error: Folder 'vendor' tidak ditemukan. Jalankan 'composer install'.");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Siswa');

// 3. Header Tabel (Ubah KELAS ID menjadi NAMA KELAS)
$headers = ['NISN', 'NAMA LENGKAP', 'USERNAME', 'NAMA KELAS', 'SESI', 'AGAMA'];
$sheet->fromArray($headers, NULL, 'A1');

// 4. Ambil Data dengan JOIN ke tabel cbt_classes
// Kita mengambil 'nama_kelas' dari tabel cbt_classes
$sql = "SELECT s.nisn, s.nama_lengkap, s.username, c.nama_kelas, s.sesi, s.agama 
        FROM cbt_students s
        LEFT JOIN cbt_classes c ON s.class_id = c.id 
        ORDER BY c.nama_kelas ASC, s.nama_lengkap ASC";

$query = $pdo->query($sql);
$dataSiswa = $query->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
foreach ($dataSiswa as $row) {
    $rows[] = [
        "'" . $row['nisn'], 
        $row['nama_lengkap'],
        $row['username'],
        $row['nama_kelas'] ?? 'Tanpa Kelas', // Jika class_id tidak ditemukan
        $row['sesi'],
        $row['agama']
    ];
}

// 5. Masukkan data ke Excel
$sheet->fromArray($rows, NULL, 'A2');

// Auto-size kolom
foreach (range('A','F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 6. Proses Download
$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$filename = 'Data_Siswa_Per_Kelas_' . date('Ymd_His') . '.xlsx';

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;