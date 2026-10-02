<?php
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();

// Sheet 2: Petunjuk Formula
$sheetInfo = $spreadsheet->createSheet();
$sheetInfo->setTitle('Petunjuk Formula');
$sheetInfo->setCellValue('A1', 'PETUNJUK PENULISAN FORMULA MATEMATIKA (LaTeX)');
$sheetInfo->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheetInfo->setCellValue('A2', 'Tulis notasi LaTeX langsung di kolom SOAL atau OPSI. JANGAN gunakan equation editor bawaan Excel/Word.');
$sheetInfo->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('CC0000');

$infoHeaders = [['Jenis', 'Notasi LaTeX', 'Keterangan']];
$infoData = [
    ['Inline (dalam kalimat)', '$x^2 + y^2 = z^2$', 'Diapit tanda dolar tunggal $...$'],
    ['Display (baris tersendiri)', '$$\frac{-b \pm \sqrt{b^2-4ac}}{2a}$$', 'Diapit dua tanda dolar $$...$$'],
    ['Pecahan', '$\frac{1}{2}$', '\frac{pembilang}{penyebut}'],
    ['Akar', '$\sqrt{x}$ atau $\sqrt[3]{x}$', '\sqrt{x} atau \sqrt[n]{x}'],
    ['Pangkat', '$x^{2}$ atau $2^{n+1}$', 'Gunakan ^ untuk pangkat'],
    ['Subskrip', '$x_{1}$ atau $a_{ij}$', 'Gunakan _ untuk subskrip'],
    ['Sigma', '$\sum_{i=1}^{n} x_i$', '\sum_{bawah}^{atas}'],
    ['Integral', '$\int_{a}^{b} f(x)\,dx$', '\int_{bawah}^{atas}'],
    ['Plus minus', '$\pm$', '\pm'],
    ['Ketidaksamaan', '$\leq$ atau $\geq$', '\leq atau \geq'],
];
$r = 4;
foreach (array_merge($infoHeaders, $infoData) as $row) {
    $sheetInfo->fromArray($row, NULL, 'A' . $r);
    if ($r === 4) {
        $sheetInfo->getStyle('A4:C4')->getFont()->setBold(true);
        $sheetInfo->getStyle('A4:C4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4E73DF');
        $sheetInfo->getStyle('A4:C4')->getFont()->getColor()->setRGB('FFFFFF');
    }
    $r++;
}
$sheetInfo->getColumnDimension('A')->setWidth(30);
$sheetInfo->getColumnDimension('B')->setWidth(40);
$sheetInfo->getColumnDimension('C')->setWidth(40);
$sheetInfo->getStyle('A4:C' . ($r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$spreadsheet->setActiveSheetIndex(0);

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Import Soal');

// 1. DEFINISI HEADER (Dipisah berdasarkan fungsi)
// Kolom A-E: Data Soal
// Kolom F-J: Opsi (Untuk PG / PG Kompleks / Benar-Salah)
// Kolom K-P: Pasangan (Khusus Menjodohkan)
// Kolom Q: Jawaban Singkat / Kunci Utama
$headers = [
    'TIPE', 'SOAL (Teks/HTML/Latex)', 'BOBOT', 'KESULITAN', 'ACAK_OPSI', // A-E
    'OPSI_A', 'OPSI_B', 'OPSI_C', 'OPSI_D', 'OPSI_E',               // F-J
    'MATCH_L1', 'MATCH_R1', 'MATCH_L2', 'MATCH_R2', 'MATCH_L3', 'MATCH_R3', // K-P (Menjodohkan)
    'KUNCI_JAWABAN'                                                 // Q
];

// Styling Header
$column = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($column . '1', $h);
    
    $style = $sheet->getStyle($column . '1');
    $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Beri warna berbeda tiap grup kolom agar guru tidak bingung
    if ($column <= 'E') {
        $color = '4E73DF'; // Biru - Utama
    } elseif ($column <= 'J') {
        $color = '1CC88A'; // Hijau - Opsi PG
    } elseif ($column <= 'P') {
        $color = 'F6C23E'; // Kuning - Menjodohkan
    } else {
        $color = 'E74A3B'; // Merah - Kunci
    }
    
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
    $column++;
}

// 2. DATA CONTOH (Mewakili 6 Tipe Soal sesuai DB Anda)
$data = [
    // PG (kunci = label opsi: A/B/C/D/E)
    ['pg', 'Hasil dari $2^3$ adalah...', 1, 'mudah', 1, '4', '6', '8', '10', '12', '', '', '', '', '', '', 'C'],

    // PG Kompleks (kunci = label opsi dipisah koma)
    ['pg_kompleks', 'Bilangan prima antara 1-10 adalah...', 1, 'sedang', 1, '2', '3', '4', '5', '9', '', '', '', '', '', '', 'A,B,D'],

    // Isian Singkat (kunci = teks jawaban)
    ['isian', 'Ibu kota Jawa Timur adalah...', 1, 'mudah', 0, '', '', '', '', '', '', '', '', '', '', '', 'Surabaya'],

    // Benar / Salah (kunci = A jika Benar, B jika Salah)
    ['benar_salah', 'Matahari terbit dari sebelah barat.', 1, 'mudah', 0, '', '', '', '', '', '', '', '', '', '', '', 'B'],

    // Menjodohkan (MATCH_L = Kiri, MATCH_R = Kanan; KUNCI_JAWABAN dikosongkan)
    ['menjodohkan', 'Pasangkan negara dan mata uangnya.', 1, 'sedang', 0, '', '', '', '', '', 'Indonesia', 'Rupiah', 'Jepang', 'Yen', 'USA', 'Dollar', ''],

    // Essay (kunci = tanda - karena dinilai manual)
    ['essay', 'Jelaskan cara kerja fotosintesis!', 1, 'sulit', 0, '', '', '', '', '', '', '', '', '', '', '', '-']
];

$row = 2;
foreach ($data as $d) {
    $sheet->fromArray($d, NULL, 'A' . $row);
    $row++;
}

// 3. VALIDASI DROPDOWN (Opsional tapi sangat membantu guru)
// Tipe Soal
$validationTipe = $sheet->getCell('A2')->getDataValidation();
$validationTipe->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
               ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP)
               ->setAllowBlank(false)
               ->setShowInputMessage(true)
               ->setShowErrorMessage(true)
               ->setShowDropDown(true)
               ->setFormula1('"pg,pg_kompleks,isian,benar_salah,menjodohkan,essay"');

// Kesulitan
$validationDiff = $sheet->getCell('D2')->getDataValidation();
$validationDiff->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
               ->setFormula1('"mudah,sedang,sulit"');

// Copy validasi ke baris bawahnya
for($i=3; $i<=100; $i++){
    $sheet->getCell('A'.$i)->setDataValidation(clone $validationTipe);
    $sheet->getCell('D'.$i)->setDataValidation(clone $validationDiff);
}

// 4. FORMATTING AKHIR
foreach (range('A', 'Q') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Border
$sheet->getStyle('A1:Q100')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// 5. OUTPUT
$filename = "Template_Soal_Lengkap_" . date('Ymd') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;