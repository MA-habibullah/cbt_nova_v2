<?php
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); exit;
}

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Tangkap Parameter
$id_bank  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam_id  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// 2. Ambil Info Bank Soal & Mapel
$info = null;
if ($id_bank) {
    $stmt_info = $pdo->prepare("SELECT b.nama_bank_soal, s.nama_mapel FROM cbt_bank_soal b LEFT JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ?");
    $stmt_info->execute([$id_bank]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
}

if (!$info && $exam_id) {
    $stmt_info_ex = $pdo->prepare("SELECT e.nama_mapel_ujian as nama_bank_soal, s.nama_mapel FROM cbt_exams e LEFT JOIN cbt_subjects s ON e.subject_id = s.id WHERE e.id = ?");
    $stmt_info_ex->execute([$exam_id]);
    $info = $stmt_info_ex->fetch(PDO::FETCH_ASSOC);
}

if (!$info) {
    $info = ['nama_bank_soal' => 'Ujian CBT', 'nama_mapel' => '-'];
}

// 3. Query Optimasi: Ambil data peserta sekaligus hitung total & benar dalam 1 query
$query = "SELECT
            p.id as p_id, p.exam_id, s.nama_lengkap, s.nisn, s.sesi, k.nama_kelas,
            sub.nama_mapel,
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
            (SELECT SUM(CASE WHEN q.tipe != 'essay' THEN q.bobot_skor ELSE 0 END)
             FROM cbt_exam_questions eq JOIN cbt_questions q ON eq.question_id = q.id
             WHERE eq.exam_id = p.exam_id) AS bobot_obj_exam,
            (SELECT SUM(CASE WHEN q2.tipe = 'essay' THEN q2.bobot_skor ELSE 0 END)
             FROM cbt_exam_questions eq2 JOIN cbt_questions q2 ON eq2.question_id = q2.id
             WHERE eq2.exam_id = p.exam_id) AS bobot_essay_exam,
            (SELECT SUM(sa3.skor_didapat) FROM cbt_student_answers sa3
             JOIN cbt_questions q3 ON sa3.question_id = q3.id
             WHERE sa3.participant_id = p.id AND q3.tipe != 'essay') AS skor_objektif,
            (SELECT SUM(sa4.skor_didapat) FROM cbt_student_answers sa4
             JOIN cbt_questions q4 ON sa4.question_id = q4.id
             WHERE sa4.participant_id = p.id AND q4.tipe = 'essay') AS skor_essay,
            p.skor_status
          FROM cbt_exam_participants p
          JOIN cbt_students s ON p.student_id = s.id
          JOIN cbt_exams e ON p.exam_id = e.id
          LEFT JOIN cbt_classes k ON s.class_id = k.id
          LEFT JOIN cbt_subjects sub ON e.subject_id = sub.id
          WHERE e.bank_soal_id = ?";

$params = [$id_bank];

if ($exam_id) { $query .= " AND p.exam_id = ?"; $params[] = $exam_id; }
if ($class_id) { $query .= " AND s.class_id = ?"; $params[] = $class_id; }

$query .= " ORDER BY s.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tahun ajaran aktif
$stmt_ta = $pdo->query("SELECT tahun, semester FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1");
$ta_row = $stmt_ta->fetch();
$str_ta = $ta_row ? $ta_row['tahun'] . ' ' . ucfirst($ta_row['semester']) : '-';

// Nama kelas
$nama_kelas_label = 'Semua Kelas';
if ($class_id) {
    $stmt_kelas = $pdo->prepare("SELECT jenjang, nama_kelas FROM cbt_classes WHERE id = ?");
    $stmt_kelas->execute([$class_id]);
    $kelas_row = $stmt_kelas->fetch();
    if ($kelas_row) $nama_kelas_label = $kelas_row['jenjang'] . ' - ' . $kelas_row['nama_kelas'];
}

// Nama ujian
$nama_ujian_label = $info['nama_bank_soal'] ?? '-';
if ($exam_id) {
    $stmt_ex = $pdo->prepare("SELECT nama_mapel_ujian FROM cbt_exams WHERE id = ?");
    $stmt_ex->execute([$exam_id]);
    $ex_row = $stmt_ex->fetch();
    if ($ex_row) $nama_ujian_label = $ex_row['nama_mapel_ujian'];
}

// 4. Siapkan HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
        .header { margin-bottom: 15px; border-bottom: 2px solid #444; padding-bottom: 8px; }
        .header h2 { margin: 0 0 5px 0; color: #000; font-size: 15px; text-align: center; }
        .header-info { font-size: 11px; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #666; padding: 4px 3px; }
        th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; text-align: center; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .footer { margin-top: 30px; width: 100%; }
        .ttd { float: right; width: 200px; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h2>REKAPITULASI HASIL UJIAN</h2>
    <p class="header-info">Nama Ujian &nbsp;&nbsp;&nbsp;: <b>'.$nama_ujian_label.'</b></p>
    <p class="header-info">Kelas &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <b>'.$nama_kelas_label.'</b></p>
    <p class="header-info">Tahun Ajaran : <b>'.$str_ta.'</b></p>
</div>

<table>
    <thead>
        <tr>
            <th width="4%">No</th>
            <th width="12%">NISN</th>
            <th>Nama Siswa</th>
            <th width="10%">Kelas</th>
            <th width="8%">Mapel</th>
            <th width="5%">Sesi</th>
            <th width="9%">Benar Obj</th>
            <th width="9%">Benar Esai</th>
            <th width="8%">Nilai Obj</th>
            <th width="8%">Nilai Esai</th>
            <th width="9%">Nilai Akhir</th>
            <th width="8%">Koreksi</th>
        </tr>
    </thead>
    <tbody>';

if (empty($results)) {
    $html .= '<tr><td colspan="12" class="text-center">Tidak ada data hasil ujian.</td></tr>';
} else {
    foreach ($results as $i => $r) {
        $total_soal_obj   = (int)$r['total_soal_obj'];
        $jml_benar_obj    = (int)$r['jml_benar_obj'];
        $total_soal_esai  = (int)$r['total_soal_esai'];
        $jml_benar_esai   = (int)$r['jml_benar_esai'];
        $benar_obj_str    = $jml_benar_obj . '/' . $total_soal_obj;
        $benar_esai_str   = $jml_benar_esai . '/' . $total_soal_esai;

        $bobot_obj_e  = (float)($r['bobot_obj_exam']  ?? 0);
        $bobot_ess_e  = (float)($r['bobot_essay_exam'] ?? 0);
        $skor_obj_v   = (float)($r['skor_objektif']   ?? 0);
        $skor_ess_v   = (float)($r['skor_essay']      ?? 0);
        $nilai_obj_x  = ($bobot_obj_e > 0) ? round($skor_obj_v / $bobot_obj_e * 100, 2) : 0.0;
        $nilai_esai_x = ($bobot_ess_e > 0) ? round($skor_ess_v / $bobot_ess_e * 100, 2) : 0.0;
        $has_obj_x    = $bobot_obj_e > 0;
        $has_ess_x    = $bobot_ess_e > 0;
        if ($has_obj_x && $has_ess_x)      { $nilai_akhir_x = round(($nilai_obj_x * 0.5) + ($nilai_esai_x * 0.5), 2); }
        elseif ($has_obj_x)                { $nilai_akhir_x = $nilai_obj_x; }
        elseif ($has_ess_x)                { $nilai_akhir_x = $nilai_esai_x; }
        else                               { $nilai_akhir_x = 0.0; }
        $status_koreksi = ($r['skor_status'] ?? 'final') === 'pending' ? 'Belum Final' : 'Final';

        $html .= '
        <tr>
            <td class="text-center">'.($i+1).'</td>
            <td class="text-center">'.$r['nisn'].'</td>
            <td>'.strtoupper($r['nama_lengkap']).'</td>
            <td class="text-center">'.$r['nama_kelas'].'</td>
            <td class="text-center">'.($r['nama_mapel'] ?? '-').'</td>
            <td class="text-center">'.($r['sesi'] ?? '-').'</td>
            <td class="text-center">'.$benar_obj_str.'</td>
            <td class="text-center">'.$benar_esai_str.'</td>
            <td class="text-center">'.($has_obj_x ? $nilai_obj_x : '-').'</td>
            <td class="text-center">'.($has_ess_x ? $nilai_esai_x : '-').'</td>
            <td class="text-center text-bold">'.$nilai_akhir_x.'</td>
            <td class="text-center">'.$status_koreksi.'</td>
        </tr>';
    }
}

$html .= '
    </tbody>
</table>

<div class="footer">
    <div class="ttd">
        <p>Dicetak pada: '.date('d/m/Y H:i').'</p>
        <br><br><br>
        <p class="text-bold">( __________________________ )</p>
        <p>Administrator / Guru Mapel</p>
    </div>
</div>

</body>
</html>';

// 5. Inisialisasi Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// 6. Output PDF
$safe_ujian = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_ujian_label);
$safe_kelas = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_kelas_label);
$filename = trim($safe_ujian) . ' - ' . trim($safe_kelas) . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;
