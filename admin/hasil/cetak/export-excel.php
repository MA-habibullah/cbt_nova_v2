<?php
ob_start();
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); 
    exit;
}

// Autoload composer if available
$vendor_autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

// Tangkap Parameter
$id_bank  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam_id  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$sesi     = isset($_GET['sesi']) ? trim($_GET['sesi']) : '';

// 1. AMBIL INFORMASI MAPEL DAN UJIAN UNTUK JUDUL SECARA AMAN
$nama_ujian_label = '-';
$nama_mapel_label = '-';

if ($exam_id) {
    $stmt_ex = $pdo->prepare("SELECT e.nama_mapel_ujian, e.bank_soal_id, s.nama_mapel, b.nama_bank_soal 
                              FROM cbt_exams e 
                              LEFT JOIN cbt_subjects s ON e.subject_id = s.id 
                              LEFT JOIN cbt_bank_soal b ON e.bank_soal_id = b.id 
                              WHERE e.id = ?");
    $stmt_ex->execute([$exam_id]);
    $ex_row = $stmt_ex->fetch(PDO::FETCH_ASSOC);
    if ($ex_row) {
        $nama_ujian_label = !empty($ex_row['nama_mapel_ujian']) ? $ex_row['nama_mapel_ujian'] : (!empty($ex_row['nama_bank_soal']) ? $ex_row['nama_bank_soal'] : '-');
        $nama_mapel_label = !empty($ex_row['nama_mapel']) ? $ex_row['nama_mapel'] : '-';
        if (!$id_bank && !empty($ex_row['bank_soal_id'])) {
            $id_bank = (int)$ex_row['bank_soal_id'];
        }
    }
}

if (($nama_ujian_label === '-' || $nama_mapel_label === '-') && $id_bank) {
    $stmt_mapel = $pdo->prepare("SELECT b.nama_bank_soal, s.nama_mapel
                                 FROM cbt_bank_soal b
                                 LEFT JOIN cbt_subjects s ON b.subject_id = s.id
                                 WHERE b.id = ?");
    $stmt_mapel->execute([$id_bank]);
    $info_mapel = $stmt_mapel->fetch(PDO::FETCH_ASSOC);
    if ($info_mapel) {
        if ($nama_ujian_label === '-') {
            $nama_ujian_label = !empty($info_mapel['nama_bank_soal']) ? $info_mapel['nama_bank_soal'] : '-';
        }
        if ($nama_mapel_label === '-') {
            $nama_mapel_label = !empty($info_mapel['nama_mapel']) ? $info_mapel['nama_mapel'] : '-';
        }
    }
}

// Tahun ajaran aktif
$stmt_ta = $pdo->query("SELECT tahun, semester FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1");
$tahun_ajaran = $stmt_ta ? $stmt_ta->fetch(PDO::FETCH_ASSOC) : null;
$str_ta = $tahun_ajaran ? $tahun_ajaran['tahun'] . ' ' . ucfirst($tahun_ajaran['semester']) : '-';

// Nama kelas
$nama_kelas_label = 'Semua Kelas';
if ($class_id) {
    $stmt_kelas = $pdo->prepare("SELECT jenjang, nama_kelas FROM cbt_classes WHERE id = ?");
    $stmt_kelas->execute([$class_id]);
    $kelas_row = $stmt_kelas->fetch(PDO::FETCH_ASSOC);
    if ($kelas_row) {
        $nama_kelas_label = $kelas_row['jenjang'] . ' - ' . $kelas_row['nama_kelas'];
    }
}

// 2. QUERY AMBIL DATA HASIL PESERTA
$query = "SELECT
            s.nisn, s.nama_lengkap, s.sesi, k.nama_kelas, 
            COALESCE(sub.nama_mapel, ?) as nama_mapel,
            (SELECT COUNT(*) FROM cbt_exam_questions eq WHERE eq.exam_id = p.exam_id) as total_soal,
            (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND sa.skor_didapat > 0) as jml_benar,
            (SELECT SUM(sa2.skor_didapat) FROM cbt_student_answers sa2 WHERE sa2.participant_id = p.id) as skor_siswa,
            (SELECT SUM(q.bobot_skor) FROM cbt_exam_questions eq2 JOIN cbt_questions q ON eq2.question_id = q.id WHERE eq2.exam_id = p.exam_id) as total_skor_maks,
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
            p.id AS participant_id,
            p.soal_ids,
            p.skor_akhir,
            p.nilai_objektif,
            p.nilai_esai,
            p.skor_status
          FROM cbt_exam_participants p
          JOIN cbt_students s ON p.student_id = s.id
          JOIN cbt_exams e ON p.exam_id = e.id
          LEFT JOIN cbt_subjects sub ON e.subject_id = sub.id
          LEFT JOIN cbt_classes k ON s.class_id = k.id
          WHERE 1=1";

$params = [$nama_mapel_label];
if ($exam_id) {
    $query .= " AND p.exam_id = ?";
    $params[] = $exam_id;
} elseif ($id_bank) {
    $query .= " AND e.bank_soal_id = ?";
    $params[] = $id_bank;
}
if ($class_id) { 
    $query .= " AND s.class_id = ?"; 
    $params[] = $class_id; 
}
if ($sesi !== '') { 
    $query .= " AND s.sesi = ?"; 
    $params[] = $sesi; 
}
$query .= " ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch question types if any participants have custom soal_ids
$all_q_ids = [];
foreach ($data as $d) {
    if (!empty($d['soal_ids'])) {
        $decoded = json_decode($d['soal_ids'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $qid) {
                $all_q_ids[(int)$qid] = true;
            }
        }
    }
}
$q_types_map = [];
if (!empty($all_q_ids)) {
    $q_id_keys = array_keys($all_q_ids);
    $ph_q = implode(',', array_fill(0, count($q_id_keys), '?'));
    $stmtQTypes = $pdo->prepare("SELECT id, tipe FROM cbt_questions WHERE id IN ($ph_q)");
    $stmtQTypes->execute($q_id_keys);
    foreach ($stmtQTypes->fetchAll(PDO::FETCH_ASSOC) as $qr) {
        $q_types_map[(int)$qr['id']] = $qr['tipe'];
    }
}

// 3. SUSUN ARRAY DATA BERSIH
$export_rows = [];
foreach ($data as $i => $d) {
    $tot_obj_row  = (int)($d['total_soal_obj']  ?? 0);
    $tot_esai_row = (int)($d['total_soal_esai'] ?? 0);

    // Jika peserta memiliki subset soal_ids, hitung total soal per tipe dari subsetnya
    if (!empty($d['soal_ids'])) {
        $decoded_sids = json_decode($d['soal_ids'], true);
        if (is_array($decoded_sids) && !empty($decoded_sids)) {
            $tot_obj_row  = 0;
            $tot_esai_row = 0;
            foreach ($decoded_sids as $sid) {
                $t = $q_types_map[(int)$sid] ?? 'pg';
                if ($t === 'essay') {
                    $tot_esai_row++;
                } else {
                    $tot_obj_row++;
                }
            }
        }
    }

    $bobot_obj_e  = (float)($d['bobot_obj_exam']  ?? 0);
    $bobot_ess_e  = (float)($d['bobot_essay_exam'] ?? 0);
    $skor_obj_v   = (float)($d['skor_objektif']   ?? 0);
    $skor_ess_v   = (float)($d['skor_essay']      ?? 0);

    $nilai_obj_x  = isset($d['nilai_objektif']) ? (float)$d['nilai_objektif'] : (($bobot_obj_e > 0) ? round($skor_obj_v / $bobot_obj_e * 100, 2) : 0.0);
    $nilai_esai_x = isset($d['nilai_esai']) ? (float)$d['nilai_esai'] : (($bobot_ess_e > 0) ? round($skor_ess_v / $bobot_ess_e * 100, 2) : 0.0);

    $has_obj_x = $bobot_obj_e > 0;
    $has_ess_x = $bobot_ess_e > 0;

    if (isset($d['skor_akhir']) && $d['skor_akhir'] !== null) {
        $nilai_akhir_x = (float)$d['skor_akhir'];
    } elseif ($has_obj_x && $has_ess_x) {
        $nilai_akhir_x = round(($nilai_obj_x * 0.5) + ($nilai_esai_x * 0.5), 2);
    } elseif ($has_obj_x) {
        $nilai_akhir_x = $nilai_obj_x;
    } elseif ($has_ess_x) {
        $nilai_akhir_x = $nilai_esai_x;
    } else {
        $nilai_akhir_x = 0.0;
    }

    $status_koreksi = ($d['skor_status'] ?? 'final') === 'pending' ? 'Belum Final' : 'Final';
    $benar_obj_str  = ($tot_obj_row > 0) ? ($d['jml_benar_obj'] . '/' . $tot_obj_row) : '-';
    $benar_esai_str = ($tot_esai_row > 0) ? ($d['jml_benar_esai'] . '/' . $tot_esai_row) : '-';

    $export_rows[] = [
        'nisn'           => (string)($d['nisn'] ?? ''),
        'nama_lengkap'   => (string)($d['nama_lengkap'] ?? '-'),
        'nama_kelas'     => (string)($d['nama_kelas'] ?? '-'),
        'nama_mapel'     => (string)($d['nama_mapel'] ?? $nama_mapel_label),
        'sesi'           => (string)($d['sesi'] ?? '-'),
        'benar_obj'      => $benar_obj_str,
        'benar_esai'     => $benar_esai_str,
        'nilai_obj'      => number_format($nilai_obj_x, 2, '.', ''),
        'nilai_esai'     => number_format($nilai_esai_x, 2, '.', ''),
        'nilai_akhir'    => number_format($nilai_akhir_x, 2, '.', ''),
        'status_koreksi' => $status_koreksi,
    ];
}

$safe_ujian = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_ujian_label);
$safe_kelas = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_kelas_label);
$filename_base = 'Rekap Nilai - ' . trim($safe_ujian) . ' - ' . trim($safe_kelas);

// 4. ENGINE 1: PHPSPREADSHEET (Jika pustaka dan ZipArchive tersedia)
$can_use_phpspreadsheet = class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') && class_exists('\ZipArchive');

if ($can_use_phpspreadsheet) {
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Nilai');

        // Header Laporan
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'REKAPITULASI HASIL UJIAN');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'Nama Ujian   : ' . $nama_ujian_label);

        $sheet->mergeCells('A3:L3');
        $sheet->setCellValue('A3', 'Kelas        : ' . $nama_kelas_label);

        $sheet->mergeCells('A4:L4');
        $sheet->setCellValue('A4', 'Tahun Ajaran : ' . $str_ta);

        // Header Kolom Tabel (Baris 6)
        $headers = [
            'A6'=>'NO', 'B6'=>'NISN', 'C6'=>'NAMA SISWA', 'D6'=>'KELAS',
            'E6'=>'MATA PELAJARAN', 'F6'=>'SESI', 'G6'=>'BENAR OBJ', 'H6'=>'BENAR ESAI',
            'I6'=>'NILAI OBJEKTIF', 'J6'=>'NILAI ESAI', 'K6'=>'NILAI AKHIR (100)', 'L6'=>'STATUS KOREKSI'
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->getStyle('A6:L6')->getFont()->setBold(true);
        $sheet->getStyle('A6:L6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A6:L6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');

        $row_idx = 7;
        foreach ($export_rows as $i => $row) {
            $sheet->setCellValue('A'.$row_idx, $i + 1);
            $sheet->setCellValueExplicit('B'.$row_idx, $row['nisn'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C'.$row_idx, $row['nama_lengkap']);
            $sheet->setCellValue('D'.$row_idx, $row['nama_kelas']);
            $sheet->setCellValue('E'.$row_idx, $row['nama_mapel']);
            $sheet->setCellValue('F'.$row_idx, $row['sesi']);
            $sheet->setCellValueExplicit('G'.$row_idx, $row['benar_obj'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H'.$row_idx, $row['benar_esai'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('I'.$row_idx, (float)$row['nilai_obj']);
            $sheet->setCellValue('J'.$row_idx, (float)$row['nilai_esai']);
            $sheet->setCellValue('K'.$row_idx, (float)$row['nilai_akhir']);
            $sheet->setCellValue('L'.$row_idx, $row['status_koreksi']);
            $row_idx++;
        }

        // Auto-size kolom A-L
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $last_row = max(6, $row_idx - 1);
        $sheet->getStyle('A6:L' . $last_row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename_base . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (\Throwable $e) {
        // Jika PhpSpreadsheet gagal/error, fallback mulus ke Engine 2 (Universal Native Excel)
    }
}

// 5. ENGINE 2: UNIVERSAL SPREADSHEETML / EXCEL HTML FALLBACK (Zero Dependency, 100% Reliable)
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment;filename="' . $filename_base . '.xls"');
header('Cache-Control: max-age=0');
header('Pragma: public');

echo '<!DOCTYPE html>';
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="utf-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Rekap Nilai</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
echo '<style>';
echo 'body { font-family: Calibri, Arial, sans-serif; }';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #D9EAD3; color: #000; font-weight: bold; border: 1px solid #777; padding: 7px; text-align: center; }';
echo 'td { border: 1px solid #999; padding: 5px; vertical-align: middle; }';
echo '.text-center { text-align: center; }';
echo '.text-bold { font-weight: bold; }';
echo '.mso-text { mso-number-format:"\@"; }';
echo '.title { font-size: 14pt; font-weight: bold; text-align: center; }';
echo '</style></head><body>';

echo '<table>';
echo '<tr><td colspan="12" class="title">REKAPITULASI HASIL UJIAN</td></tr>';
echo '<tr><td colspan="12"><b>Nama Ujian &nbsp;&nbsp;:</b> ' . htmlspecialchars($nama_ujian_label) . '</td></tr>';
echo '<tr><td colspan="12"><b>Kelas &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</b> ' . htmlspecialchars($nama_kelas_label) . '</td></tr>';
echo '<tr><td colspan="12"><b>Tahun Ajaran :</b> ' . htmlspecialchars($str_ta) . '</td></tr>';
echo '<tr><td colspan="12">&nbsp;</td></tr>';

echo '<tr>';
echo '<th>NO</th><th>NISN</th><th>NAMA SISWA</th><th>KELAS</th>';
echo '<th>MATA PELAJARAN</th><th>SESI</th><th>BENAR OBJ</th><th>BENAR ESAI</th>';
echo '<th>NILAI OBJEKTIF</th><th>NILAI ESAI</th><th>NILAI AKHIR (100)</th><th>STATUS KOREKSI</th>';
echo '</tr>';

foreach ($export_rows as $i => $d) {
    echo '<tr>';
    echo '<td class="text-center">' . ($i + 1) . '</td>';
    echo '<td class="mso-text">' . htmlspecialchars($d['nisn']) . '</td>';
    echo '<td>' . htmlspecialchars($d['nama_lengkap']) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($d['nama_kelas']) . '</td>';
    echo '<td>' . htmlspecialchars($d['nama_mapel']) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($d['sesi']) . '</td>';
    echo '<td class="text-center mso-text">' . htmlspecialchars($d['benar_obj']) . '</td>';
    echo '<td class="text-center mso-text">' . htmlspecialchars($d['benar_esai']) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($d['nilai_obj']) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($d['nilai_esai']) . '</td>';
    echo '<td class="text-center text-bold">' . htmlspecialchars($d['nilai_akhir']) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($d['status_koreksi']) . '</td>';
    echo '</tr>';
}

echo '</table></body></html>';
exit;
