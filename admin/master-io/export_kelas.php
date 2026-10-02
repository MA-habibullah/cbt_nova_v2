<?php
// Pastikan tidak ada spasi atau karakter sebelum tag PHP
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    ob_end_clean();
    header("Location: " . BASE_URL . "index.php"); exit;
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Settings;

// --- 1. OPTIMASI MEMORI (Opsional, butuh composer require symfony/cache) ---
// Jika tidak ada library cache, PhpSpreadsheet akan tetap berjalan tapi lebih berat.
// ---

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Referensi ID Kelas');

// --- 2. PERSIAPAN DATA (ARRAY METHOD) ---
// Mengambil data sekaligus jauh lebih cepat daripada query di dalam loop
$query = $pdo->query("SELECT id, jenjang, nama_kelas FROM cbt_classes ORDER BY jenjang ASC, nama_kelas ASC");
$dataKelas = $query->fetchAll(PDO::FETCH_ASSOC);

// Header
$rows = [
    ['ID (PENTING)', 'JENJANG', 'NAMA KELAS']
];

// Masukkan data database ke array utama
foreach ($dataKelas as $row) {
    $rows[] = [
        $row['id'],
        $row['jenjang'],
        $row['nama_kelas']
    ];
}

// --- 3. EKSEKUSI PENULISAN (FAST LOAD) ---
// Menggunakan fromArray memproses ribuan data dalam hitungan milidetik
$sheet->fromArray($rows, NULL, 'A1');

// Styling Ringan
$sheet->getStyle('A1:C1')->getFont()->setBold(true);
foreach (range('A','C') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Tambahkan catatan
$sheet->setCellValue('E2', 'CATATAN:');
$sheet->setCellValue('E3', 'Gunakan angka di kolom ID untuk mengisi file');
$sheet->setCellValue('E4', 'Import Siswa pada bagian KELAS_ID.');

// --- 4. OUTPUT HANDLER ---
$writer = new Xlsx($spreadsheet);
// Matikan pre-calculation untuk mempercepat download
$writer->setPreCalculateFormulas(false);

$filename = 'Referensi_ID_Kelas_' . date('Ymd_His') . '.xlsx';

// Bersihkan buffer untuk mencegah file corrupt
if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;