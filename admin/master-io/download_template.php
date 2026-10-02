<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (ob_get_length()) ob_end_clean();
    header("Location: " . BASE_URL . "index.php"); exit;
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Ambil data kelas dan sesi dari database
$daftarKelas = $pdo->query("SELECT id, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY nama_kelas ASC")->fetchAll();
$daftarSesi  = $pdo->query("SELECT id, nama_sesi, jam_mulai, jam_selesai FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id ASC")->fetchAll();

$spreadsheet = new Spreadsheet();

// ── Sheet 1: Template Data Siswa ──────────────────────────────────────────────
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Data Siswa');

$header = ['NISN', 'NAMA LENGKAP', 'USERNAME', 'PASSWORD', 'KELAS_ID', 'SESI_ID', 'AGAMA'];
$sheet1->fromArray($header, NULL, 'A1');

$example = ['Ahmad Siswa Contoh', 'ahmad01', 'pass123', '1', '1', 'Islam'];
$sheet1->fromArray($example, NULL, 'B2');
$sheet1->getCell('A2')->setValueExplicit('0012345678', DataType::TYPE_STRING);

// Style header baris 1
$sheet1->getStyle('A1:G1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343A40']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// Format kolom NISN sebagai Teks agar leading zero terjaga
$sheet1->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

// Catatan di baris 4-5
$sheet1->setCellValue('A4', 'CATATAN:');
$sheet1->setCellValue('A5', '* Lihat sheet "Ref Kelas" untuk daftar KELAS_ID yang valid');
$sheet1->setCellValue('A6', '* Lihat sheet "Ref Sesi" untuk daftar SESI_ID yang valid');
$sheet1->setCellValue('A7', '* Kolom NISN sudah diformat sebagai Teks. Ketik NISN langsung tanpa tanda kutip, contoh: 0012345678');
$sheet1->setCellValue('A8', '* Jika PASSWORD dikosongkan, NISN akan digunakan sebagai password');
$sheet1->getStyle('A4')->getFont()->setBold(true);
$sheet1->getStyle('A5:A8')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF856404'));

foreach (range('A', 'G') as $col) {
    $sheet1->getColumnDimension($col)->setAutoSize(true);
}

// ── Sheet 2: Referensi Kelas ──────────────────────────────────────────────────
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Ref Kelas');

$sheet2->setCellValue('A1', 'KELAS_ID');
$sheet2->setCellValue('B1', 'NAMA KELAS');

$sheet2->getStyle('A1:B1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '198754']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$row = 2;
foreach ($daftarKelas as $k) {
    $sheet2->setCellValue('A' . $row, $k['id']);
    $sheet2->setCellValue('B' . $row, $k['nama_kelas']);
    $row++;
}

$sheet2->getColumnDimension('A')->setAutoSize(true);
$sheet2->getColumnDimension('B')->setAutoSize(true);

// ── Sheet 3: Referensi Sesi ───────────────────────────────────────────────────
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Ref Sesi');

$sheet3->setCellValue('A1', 'SESI_ID');
$sheet3->setCellValue('B1', 'NAMA SESI');
$sheet3->setCellValue('C1', 'JAM MULAI');
$sheet3->setCellValue('D1', 'JAM SELESAI');

$sheet3->getStyle('A1:D1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D6EFD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$row = 2;
foreach ($daftarSesi as $s) {
    $sheet3->setCellValue('A' . $row, $s['id']);
    $sheet3->setCellValue('B' . $row, $s['nama_sesi']);
    $sheet3->setCellValue('C' . $row, substr($s['jam_mulai'], 0, 5));
    $sheet3->setCellValue('D' . $row, substr($s['jam_selesai'], 0, 5));
    $row++;
}

foreach (['A', 'B', 'C', 'D'] as $col) {
    $sheet3->getColumnDimension($col)->setAutoSize(true);
}

// Aktifkan sheet pertama saat dibuka
$spreadsheet->setActiveSheetIndex(0);

$writer = new Xlsx($spreadsheet);
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template_Import_Siswa.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: public');
$writer->save('php://output');
exit;
