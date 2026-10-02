<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/config/database.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

$is_guru = ($_SESSION['role'] ?? '') === 'guru' && isset($_SESSION['teacher_id']);
if (!isset($_SESSION['admin_id']) && !$is_guru) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metode tidak diizinkan");
}

// 1. Ambil data dari POST dan FILES
$bank_soal_id = $_POST['bank_soal_id'] ?? null;
// Disesuaikan menjadi 'file_soal' sesuai hasil print_r Anda
$file_excel = $_FILES['file_soal'] ?? null; 

// 2. Validasi input awal & Magic Bytes MIME Type
if (!$bank_soal_id || !$file_excel || ($file_excel['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file_excel['tmp_name'] ?? '')) {
    header("Location: ../upload.php?id=$bank_soal_id&msg=error_data");
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file_excel['tmp_name']);
finfo_close($finfo);

$allowed_mimes = [
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/octet-stream',
    'application/zip'
];
$ext = strtolower(pathinfo($file_excel['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls']) || !in_array($mime, $allowed_mimes)) {
    header("Location: ../upload.php?id=$bank_soal_id&msg=invalid_format");
    exit;
}

try {
    // 3. Load File Excel — akses sel langsung agar RichText & format ikut terbaca
    $spreadsheet = IOFactory::load($file_excel['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();
    $highestRow  = $sheet->getHighestRow();

    $pdo->beginTransaction();

    // Helper: teks polos (untuk metadata: TIPE, BOBOT, KESULITAN, KUNCI)
    $txt = fn($col, $r) => trim((string)($sheet->getCell($col.$r)->getValue() ?? ''));

    $count = 0;
    for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
        $tipe_raw = $txt('A', $rowNumber);
        if (empty($tipe_raw)) continue;
        $tipe = normalize_tipe_soal($tipe_raw);

        $konten    = excelCellToHtml($sheet->getCell('B'.$rowNumber));
        $bobot     = (float)($txt('C', $rowNumber) ?: 1.00);
        $kesulitan = strtolower($txt('D', $rowNumber)) ?: 'sedang';
        $acak_opsi = ($txt('E', $rowNumber) === '0') ? 0 : 1;
        $kunci_raw = $txt('Q', $rowNumber);

        // A. Simpan ke tabel cbt_questions
        $stmtQ = $pdo->prepare("INSERT INTO cbt_questions (bank_soal_id, tipe, konten_soal, tingkat_kesulitan, acak_opsi, bobot_skor) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtQ->execute([$bank_soal_id, $tipe, $konten, $kesulitan, $acak_opsi, $bobot]);
        $question_id = $pdo->lastInsertId();

        // B. Simpan detail jawaban berdasarkan Tipe
        if (in_array($tipe, ['pg', 'pg_kompleks'])) {
            $options = [
                'A' => excelCellToHtml($sheet->getCell('F'.$rowNumber)),
                'B' => excelCellToHtml($sheet->getCell('G'.$rowNumber)),
                'C' => excelCellToHtml($sheet->getCell('H'.$rowNumber)),
                'D' => excelCellToHtml($sheet->getCell('I'.$rowNumber)),
                'E' => excelCellToHtml($sheet->getCell('J'.$rowNumber)),
            ];

            $kunci_array = explode(',', strtoupper(str_replace(' ', '', $kunci_raw)));

            foreach ($options as $label => $val) {
                if ($val === null || $val === '') continue;
                $is_correct = in_array($label, $kunci_array) ? 1 : 0;
                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                $stmtOpt->execute([$question_id, $label, $val, $is_correct]);
            }

        } elseif ($tipe === 'benar_salah') {
            $kunci_bs = strtoupper(trim($kunci_raw));
            $opsi_bs  = [['label' => 'B', 'teks' => 'Benar'], ['label' => 'S', 'teks' => 'Salah']];
            foreach ($opsi_bs as $idx => $opsi) {
                $template_label = ($idx === 0) ? 'A' : 'B';
                $is_correct = ($kunci_bs === $template_label) ? 1 : 0;
                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                $stmtOpt->execute([$question_id, $opsi['label'], $opsi['teks'], $is_correct]);
            }

        } elseif ($tipe === 'menjodohkan') {
            // K=MATCH_L1, L=MATCH_R1, M=MATCH_L2, N=MATCH_R2, O=MATCH_L3, P=MATCH_R3
            $match_pairs = [
                ['L' => excelCellToHtml($sheet->getCell('K'.$rowNumber)), 'R' => excelCellToHtml($sheet->getCell('L'.$rowNumber))],
                ['L' => excelCellToHtml($sheet->getCell('M'.$rowNumber)), 'R' => excelCellToHtml($sheet->getCell('N'.$rowNumber))],
                ['L' => excelCellToHtml($sheet->getCell('O'.$rowNumber)), 'R' => excelCellToHtml($sheet->getCell('P'.$rowNumber))],
            ];
            foreach ($match_pairs as $pair) {
                if (empty($pair['L']) || empty($pair['R'])) continue;
                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                $stmtOpt->execute([$question_id, $pair['L'], $pair['R'], 1]);
            }

        } elseif ($tipe === 'isian') {
            if ($kunci_raw !== '') {
                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                $stmtOpt->execute([$question_id, 'KUNCI', $kunci_raw, 1]);
            }

        } elseif ($tipe === 'essay') {
            if ($kunci_raw !== '') {
                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                $stmtOpt->execute([$question_id, 'RUBRIK', $kunci_raw, 1]);
            }
        }

        $count++;
    }

    $pdo->commit();
    log_activity("Upload soal Excel ke bank soal ID $bank_soal_id: $count soal berhasil diimpor", null, null, null, 'ujian');
    // Redirect Sukses
    $redirect = $is_guru ? BASE_URL . "guru/bank-soal/upload.php" : '../upload.php';
    header("Location: $redirect?id=$bank_soal_id&msg=success&count=$count");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_msg = urlencode($e->getMessage());
    $redirect = $is_guru ? BASE_URL . "guru/bank-soal/upload.php" : '../upload.php';
    header("Location: $redirect?id=$bank_soal_id&msg=error&error_info=$error_msg");
    exit;
}

/**
 * Normalize teks Excel: decode HTML entities lalu escape ulang sebagai HTML.
 */
function encodeExcelText(string $text): string {
    return htmlspecialchars(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ENT_NOQUOTES, 'UTF-8');
}

/**
 * Konversi sel Excel ke HTML, mempertahankan bold/italic/underline dan newline.
 * Mendukung sel RichText (multi-format) maupun teks polos.
 */
function excelCellToHtml(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): string {
    $value = $cell->getValue();

    if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
        $html = '';
        foreach ($value->getRichTextElements() as $el) {
            $text = encodeExcelText($el->getText());
            if ($el instanceof \PhpOffice\PhpSpreadsheet\RichText\Run) {
                $font = $el->getFont();
                if ($font) {
                    $ul = $font->getUnderline();
                    if ($ul && $ul !== 'none') $text = '<u>' . $text . '</u>';
                    if ($font->getItalic())    $text = '<em>' . $text . '</em>';
                    if ($font->getBold())      $text = '<strong>' . $text . '</strong>';
                }
            }
            $html .= $text;
        }
        return nl2br($html);
    }

    // Teks polos — encode dan ubah newline menjadi <br>
    return nl2br(encodeExcelText((string)($value ?? '')));
}