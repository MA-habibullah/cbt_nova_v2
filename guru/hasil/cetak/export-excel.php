<?php
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$exam_id  = isset($_GET['exam_id'])  ? (int)$_GET['exam_id']  : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$sesi     = isset($_GET['sesi'])     ? $_GET['sesi']           : '';

// Ambil info ujian
$stmt_info = $pdo->prepare(
    "SELECT e.nama_mapel_ujian, b.nama_bank_soal, s.nama_mapel
     FROM cbt_exams e
     JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
     JOIN cbt_subjects s ON b.subject_id = s.id
     WHERE e.id = ? AND e.teacher_id = ?"
);
$stmt_info->execute([$exam_id, $teacher_id]);
$info = $stmt_info->fetch();

// Tahun ajaran aktif
$stmt_ta = $pdo->query("SELECT tahun, semester FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1");
$tahun_ajaran = $stmt_ta->fetch();
$str_ta = $tahun_ajaran ? $tahun_ajaran['tahun'] . ' ' . ucfirst($tahun_ajaran['semester']) : '-';

// Nama kelas
$nama_kelas_label = 'Semua Kelas';
if ($class_id) {
    $stmt_kelas = $pdo->prepare("SELECT jenjang, nama_kelas FROM cbt_classes WHERE id = ?");
    $stmt_kelas->execute([$class_id]);
    $kelas_row = $stmt_kelas->fetch();
    if ($kelas_row) $nama_kelas_label = $kelas_row['jenjang'] . ' - ' . $kelas_row['nama_kelas'];
}

// Nama ujian
$nama_ujian_label = $info['nama_mapel_ujian'] ?? '-';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// HEADER LAPORAN (MULTI-ROW)
$sheet->mergeCells('A1:L1');
$sheet->setCellValue('A1', 'REKAPITULASI HASIL UJIAN');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:L2');
$sheet->setCellValue('A2', 'Nama Ujian   : ' . $nama_ujian_label);

$sheet->mergeCells('A3:L3');
$sheet->setCellValue('A3', 'Kelas        : ' . $nama_kelas_label);

$sheet->mergeCells('A4:L4');
$sheet->setCellValue('A4', 'Tahun Ajaran : ' . $str_ta);

// Header tabel (baris 6)
$headers = [
    'A6' => 'NO', 'B6' => 'NISN', 'C6' => 'NAMA SISWA', 'D6' => 'KELAS',
    'E6' => 'MATA PELAJARAN', 'F6' => 'SESI', 'G6' => 'BENAR OBJ', 'H6' => 'BENAR ESAI',
    'I6' => 'NILAI OBJEKTIF', 'J6' => 'NILAI ESAI', 'K6' => 'NILAI AKHIR (100)', 'L6' => 'STATUS KOREKSI'
];
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}
$sheet->getStyle('A6:L6')->getFont()->setBold(true);
$sheet->getStyle('A6:L6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A6:L6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');

// Query data — hanya ujian milik guru ini
$query = "SELECT
            s.nisn, s.nama_lengkap, s.sesi, k.nama_kelas, sub.nama_mapel,
            (SELECT SUM(sa3.skor_didapat) FROM cbt_student_answers sa3
             JOIN cbt_questions q3 ON sa3.question_id = q3.id
             WHERE sa3.participant_id = p.id AND q3.tipe != 'essay') as skor_objektif,
            (SELECT SUM(sa4.skor_didapat) FROM cbt_student_answers sa4
             JOIN cbt_questions q4 ON sa4.question_id = q4.id
             WHERE sa4.participant_id = p.id AND q4.tipe = 'essay') as skor_essay,
            (SELECT SUM(CASE WHEN q.tipe != 'essay' THEN q.bobot_skor ELSE 0 END)
             FROM cbt_exam_questions eq JOIN cbt_questions q ON eq.question_id = q.id
             WHERE eq.exam_id = p.exam_id) AS bobot_obj_exam,
            (SELECT SUM(CASE WHEN q2.tipe = 'essay' THEN q2.bobot_skor ELSE 0 END)
             FROM cbt_exam_questions eq2 JOIN cbt_questions q2 ON eq2.question_id = q2.id
             WHERE eq2.exam_id = p.exam_id) AS bobot_essay_exam,
            (SELECT COUNT(*) FROM cbt_exam_questions eq3
             JOIN cbt_questions q3 ON eq3.question_id = q3.id
             WHERE eq3.exam_id = p.exam_id AND q3.tipe != 'essay') AS total_soal_obj,
            (SELECT COUNT(*) FROM cbt_student_answers sa5
             JOIN cbt_questions q5 ON sa5.question_id = q5.id
             WHERE sa5.participant_id = p.id AND q5.tipe != 'essay' AND sa5.skor_didapat > 0) AS jml_benar_obj,
            (SELECT COUNT(*) FROM cbt_exam_questions eq4
             JOIN cbt_questions q4 ON eq4.question_id = q4.id
             WHERE eq4.exam_id = p.exam_id AND q4.tipe = 'essay') AS total_soal_esai,
            (SELECT COUNT(*) FROM cbt_student_answers sa6
             JOIN cbt_questions q6 ON sa6.question_id = q6.id
             WHERE sa6.participant_id = p.id AND q6.tipe = 'essay' AND sa6.skor_didapat > 0) AS jml_benar_esai,
            p.skor_status
          FROM cbt_exam_participants p
          JOIN cbt_students s ON p.student_id = s.id
          JOIN cbt_exams e ON p.exam_id = e.id
          JOIN cbt_subjects sub ON e.subject_id = sub.id
          LEFT JOIN cbt_classes k ON s.class_id = k.id
          WHERE e.teacher_id = ?";

$params = [$teacher_id];
if ($exam_id)  { $query .= " AND p.exam_id = ?";   $params[] = $exam_id; }
if ($class_id) { $query .= " AND s.class_id = ?";  $params[] = $class_id; }
if ($sesi)     { $query .= " AND s.sesi = ?";       $params[] = $sesi; }
$query .= " ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Isi data
$row = 7;
foreach ($data as $i => $d) {
    $bobot_obj_e  = (float)($d['bobot_obj_exam']  ?? 0);
    $bobot_ess_e  = (float)($d['bobot_essay_exam'] ?? 0);
    $skor_obj_v   = (float)($d['skor_objektif']   ?? 0);
    $skor_ess_v   = (float)($d['skor_essay']      ?? 0);

    $nilai_obj_x  = ($bobot_obj_e > 0) ? round($skor_obj_v / $bobot_obj_e * 100, 2) : 0.0;
    $nilai_esai_x = ($bobot_ess_e > 0) ? round($skor_ess_v / $bobot_ess_e * 100, 2) : 0.0;

    $has_obj_x = $bobot_obj_e > 0;
    $has_ess_x = $bobot_ess_e > 0;

    if ($has_obj_x && $has_ess_x) {
        $nilai_akhir_x = round(($nilai_obj_x * 0.5) + ($nilai_esai_x * 0.5), 2);
    } elseif ($has_obj_x) {
        $nilai_akhir_x = $nilai_obj_x;
    } elseif ($has_ess_x) {
        $nilai_akhir_x = $nilai_esai_x;
    } else {
        $nilai_akhir_x = 0.0;
    }

    $status_koreksi = ($d['skor_status'] ?? 'final') === 'pending' ? 'Belum Final' : 'Final';

    $benar_obj_str  = $d['jml_benar_obj']  . '/' . $d['total_soal_obj'];
    $benar_esai_str = $d['jml_benar_esai'] . '/' . $d['total_soal_esai'];

    $sheet->setCellValue('A' . $row, $i + 1);
    $sheet->setCellValueExplicit('B' . $row, $d['nisn'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $row, $d['nama_lengkap']);
    $sheet->setCellValue('D' . $row, $d['nama_kelas']);
    $sheet->setCellValue('E' . $row, $d['nama_mapel']);
    $sheet->setCellValue('F' . $row, $d['sesi']);
    $sheet->setCellValueExplicit('G' . $row, $benar_obj_str, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('H' . $row, $benar_esai_str, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('I' . $row, $nilai_obj_x);
    $sheet->setCellValue('J' . $row, $nilai_esai_x);
    $sheet->setCellValue('K' . $row, $nilai_akhir_x);
    $sheet->setCellValue('L' . $row, $status_koreksi);
    $row++;
}

foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
foreach (['I', 'J', 'K', 'L'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(false);
    $sheet->getColumnDimension($col)->setWidth(18);
}
$sheet->getStyle('A6:L' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$safe_ujian = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_ujian_label);
$safe_kelas = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_kelas_label);
$filename = trim($safe_ujian) . ' - ' . trim($safe_kelas) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
