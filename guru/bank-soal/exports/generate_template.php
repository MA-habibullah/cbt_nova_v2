<?php
ob_start();
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    ob_end_clean();
    header("Location: " . BASE_URL . "index.php"); exit;
}

ob_end_clean();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();

$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(11);

$section = $phpWord->addSection();

$section->addText("TEMPLATE IMPORT SOAL CBT v1.0", ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
$section->addText("Petunjuk: Jangan mengubah kolom FIELD. Isi konten pada kolom ISI.", ['italic' => true], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$section->addText("PETUNJUK PENULISAN FORMULA MATEMATIKA (LaTeX)", ['bold' => true, 'size' => 12, 'color' => '1155CC']);
$section->addText("Untuk menyisipkan formula/rumus matematika, tulis notasi LaTeX langsung pada kolom ISI:");
$section->addTextBreak(0);

$tableFormula = $section->addTable(['borderSize' => 6, 'borderColor' => 'aaaaaa', 'cellMargin' => 60, 'width' => 100 * 50, 'unit' => 'pct']);
$tableFormula->addRow();
$tableFormula->addCell(2500, ['bgColor' => 'dddddd'])->addText("Jenis Formula", ['bold' => true]);
$tableFormula->addCell(3500, ['bgColor' => 'dddddd'])->addText("Cara Penulisan di Word", ['bold' => true]);
$tableFormula->addCell(4000, ['bgColor' => 'dddddd'])->addText("Keterangan", ['bold' => true]);

$formulaRows = [
    ['Inline (dalam kalimat)', '$x^2 + y^2 = z^2$', 'Diapit satu tanda dolar $...$ '],
    ['Display (baris tersendiri)', '$$\\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}$$', 'Diapit dua tanda dolar $$...$$'],
    ['Pecahan', '$\\frac{1}{2}$', '\\frac{pembilang}{penyebut}'],
    ['Akar', '$\\sqrt{x}$ atau $\\sqrt[3]{x}$', '\\sqrt{} atau \\sqrt[n]{}'],
    ['Pangkat', '$x^{2}$ atau $x^{n+1}$', 'Gunakan ^ untuk pangkat'],
    ['Subskrip', '$x_{1}$ atau $a_{ij}$', 'Gunakan _ untuk subskrip'],
    ['Sigma/Integral', '$\\sum_{i=1}^{n} x_i$', '\\sum, \\int, \\prod'],
];
foreach ($formulaRows as $r) {
    $tableFormula->addRow();
    $tableFormula->addCell(2500)->addText($r[0]);
    $tableFormula->addCell(3500)->addText($r[1], ['name' => 'Courier New', 'size' => 9]);
    $tableFormula->addCell(4000)->addText($r[2], ['italic' => true, 'size' => 9]);
}

$section->addTextBreak(1);
$section->addText("PENTING: Gunakan tanda dolar biasa ($) bukan equation editor bawaan Word. Jangan gunakan simbol matematika Word.", ['bold' => true, 'color' => 'CC0000', 'size' => 9]);
$section->addTextBreak(2);

$templates = [
    [
        'tipe' => 'pg',
        'soal' => 'Hasil dari $2^3 + \sqrt{16}$ adalah... (contoh soal dengan formula)',
        'opsi' => ['12', '10', '16', '8', '14'],
        'kunci' => 'A',
        'bobot' => '1'
    ],
    [
        'tipe' => 'pg',
        'soal' => 'Ibu kota negara Indonesia adalah...',
        'opsi' => ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Makassar'],
        'kunci' => 'A',
        'bobot' => '1'
    ],
    [
        'tipe' => 'pg_kompleks',
        'soal' => 'Manakah yang termasuk perangkat keras (hardware) komputer?',
        'opsi' => ['Mouse', 'Windows', 'Keyboard', 'Monitor', 'Chrome'],
        'kunci' => 'A,C,D',
        'bobot' => '1'
    ],
    [
        'tipe' => 'benar_salah',
        'soal' => 'Matahari terbit dari arah Barat. (Tulis B jika Benar, S jika Salah)',
        'kunci' => 'S',
        'bobot' => '1'
    ],
    [
        'tipe' => 'menjodohkan',
        'soal' => 'Pasangkan lambang sila pancasila berikut dengan benar.',
        'match' => [
            ['L' => 'Sila 1', 'R' => 'Bintang'],
            ['L' => 'Sila 2', 'R' => 'Rantai'],
            ['L' => 'Sila 3', 'R' => 'Pohon Beringin'],
            ['L' => 'Sila 4', 'R' => 'Kepala Banteng'],
            ['L' => 'Sila 5', 'R' => 'Padi dan Kapas'],
        ],
        'bobot' => '1'
    ],
    [
        'tipe' => 'isian',
        'soal' => 'Siapakah pencipta lagu Indonesia Raya?',
        'kunci' => 'WR Supratman',
        'bobot' => '1'
    ],
    [
        'tipe' => 'essay',
        'soal' => 'Jelaskan dampak pemanasan global bagi ekosistem laut!',
        'kunci' => '-',
        'bobot' => '1'
    ]
];

$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50,
    'unit' => 'pct'
];
$firstRowStyle = ['bgColor' => 'eeeeee'];

foreach ($templates as $t) {
    $table = $section->addTable($tableStyle);

    $table->addRow();
    $table->addCell(2500, $firstRowStyle)->addText("FIELD", ['bold' => true]);
    $table->addCell(7500, $firstRowStyle)->addText("ISI", ['bold' => true]);

    $table->addRow();
    $table->addCell(2500)->addText("TIPE", ['bold' => true]);
    $table->addCell(7500)->addText($t['tipe']);

    $table->addRow();
    $table->addCell(2500)->addText("SOAL", ['bold' => true]);
    $table->addCell(7500)->addText($t['soal']);

    if (isset($t['opsi'])) {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        foreach ($labels as $index => $label) {
            $table->addRow();
            $table->addCell(2500)->addText("OPSI_" . $label, ['bold' => true]);
            $table->addCell(7500)->addText($t['opsi'][$index] ?? '');
        }
    }

    if ($t['tipe'] === 'menjodohkan' && isset($t['match'])) {
        foreach ($t['match'] as $i => $pair) {
            $table->addRow();
            $table->addCell(2500)->addText("MATCH_L" . ($i + 1), ['bold' => true]);
            $table->addCell(7500)->addText($pair['L']);

            $table->addRow();
            $table->addCell(2500)->addText("MATCH_R" . ($i + 1), ['bold' => true]);
            $table->addCell(7500)->addText($pair['R']);
        }
    }

    if ($t['tipe'] !== 'menjodohkan') {
        $table->addRow();
        $table->addCell(2500)->addText("KUNCI", ['bold' => true]);
        $table->addCell(7500)->addText($t['kunci'] ?? '-');
    }

    $table->addRow();
    $table->addCell(2500)->addText("BOBOT", ['bold' => true]);
    $table->addCell(7500)->addText($t['bobot']);

    $section->addTextBreak(1);
}

$filename = "template_soal_cbt_" . date('Ymd_His') . ".docx";
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit;
