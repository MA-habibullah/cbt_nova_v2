<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    ob_end_clean();
    header("Location: " . BASE_URL . "index.php"); exit;
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray(['NIP', 'NAMA LENGKAP', 'USERNAME', 'PASSWORD'], NULL, 'A1');

$query = $pdo->query("SELECT nip, nama_lengkap, username FROM cbt_teachers");
$rowNum = 2;
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $sheet->setCellValue('A' . $rowNum, "'".$row['nip']);
    $sheet->setCellValue('B' . $rowNum, $row['nama_lengkap']);
    $sheet->setCellValue('C' . $rowNum, $row['username']);
    $sheet->setCellValue('D' . $rowNum, ''); // Password kosong saat export
    $rowNum++;
}

$writer = new Xlsx($spreadsheet);
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Data_Guru.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: public');
$writer->save('php://output');
exit;