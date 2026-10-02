<?php
ob_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); 
    exit;
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
        $nama_kelas_label = (!empty($kelas_row['jenjang']) ? $kelas_row['jenjang'] . ' - ' : '') . $kelas_row['nama_kelas'];
    }
}

// 2. QUERY AMBIL DATA HASIL PESERTA
$query = "SELECT
            s.nisn, s.nama_lengkap, s.sesi, k.jenjang, k.nama_kelas, 
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
          LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
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
    $query .= " AND COALESCE(p.class_id, s.class_id) = ?"; 
    $params[] = $class_id; 
}
if ($sesi !== '') { 
    $query .= " AND s.sesi = ?"; 
    $params[] = $sesi; 
}
$query .= " ORDER BY k.jenjang ASC, k.nama_kelas ASC, s.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch question metadata if any participants have custom soal_ids
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
$q_meta_map = [];
if (!empty($all_q_ids)) {
    $q_id_keys = array_keys($all_q_ids);
    $ph_q = implode(',', array_fill(0, count($q_id_keys), '?'));
    $stmtQTypes = $pdo->prepare("SELECT id, tipe, bobot_skor FROM cbt_questions WHERE id IN ($ph_q)");
    $stmtQTypes->execute($q_id_keys);
    foreach ($stmtQTypes->fetchAll(PDO::FETCH_ASSOC) as $qr) {
        $q_meta_map[(int)$qr['id']] = $qr;
    }
}

// Inisialisasi XLSX Generator
$xlsx = new SimpleXLSXGen();

// Buat Style XF
$style_title = $xlsx->createStyle(['bold' => true, 'size' => 14, 'align' => 'center']);
$style_meta  = $xlsx->createStyle(['bold' => true, 'size' => 11, 'align' => 'left']);
$style_head  = $xlsx->createStyle(['bold' => true, 'size' => 10, 'bg' => 'D9EAD3', 'border' => true, 'align' => 'center']);

$style_cell_left   = $xlsx->createStyle(['size' => 10, 'border' => true, 'align' => 'left']);
$style_cell_center = $xlsx->createStyle(['size' => 10, 'border' => true, 'align' => 'center']);
$style_cell_bold_center = $xlsx->createStyle(['bold' => true, 'size' => 10, 'border' => true, 'align' => 'center']);

// 3. SUSUN BARIS DATA SHEET
$rows = [];

// Header Dokumen
$rows[] = [['v' => 'REKAPITULASI HASIL UJIAN', 's' => $style_title]];
$rows[] = [['v' => 'Nama Ujian   : ' . $nama_ujian_label, 's' => $style_meta]];
$rows[] = [['v' => 'Kelas        : ' . $nama_kelas_label, 's' => $style_meta]];
$rows[] = [['v' => 'Tahun Ajaran : ' . $str_ta, 's' => $style_meta]];
$rows[] = ['']; // Baris kosong pemisah

// Header Kolom Tabel
$rows[] = [
    ['v' => 'NO', 's' => $style_head],
    ['v' => 'NISN', 's' => $style_head],
    ['v' => 'NAMA SISWA', 's' => $style_head],
    ['v' => 'KELAS', 's' => $style_head],
    ['v' => 'MATA PELAJARAN', 's' => $style_head],
    ['v' => 'SESI', 's' => $style_head],
    ['v' => 'BENAR OBJ', 's' => $style_head],
    ['v' => 'BENAR ESAI', 's' => $style_head],
    ['v' => 'NILAI OBJEKTIF', 's' => $style_head],
    ['v' => 'NILAI ESAI', 's' => $style_head],
    ['v' => 'NILAI AKHIR (100)', 's' => $style_head],
    ['v' => 'STATUS KOREKSI', 's' => $style_head]
];

// Data Peserta
foreach ($data as $i => $d) {
    $tot_obj_row  = (int)($d['total_soal_obj']  ?? 0);
    $tot_esai_row = (int)($d['total_soal_esai'] ?? 0);
    $bobot_obj_e  = (float)($d['bobot_obj_exam']  ?? 0);
    $bobot_ess_e  = (float)($d['bobot_essay_exam'] ?? 0);

    // Jika peserta memiliki subset dinamis (soal_ids), hitung total soal & bobot dari subsetnya
    if (!empty($d['soal_ids'])) {
        $decoded_sids = json_decode($d['soal_ids'], true);
        if (is_array($decoded_sids) && !empty($decoded_sids)) {
            $tot_obj_row  = 0;
            $tot_esai_row = 0;
            $bobot_obj_e  = 0.0;
            $bobot_ess_e  = 0.0;
            foreach ($decoded_sids as $sid) {
                $q_info = $q_meta_map[(int)$sid] ?? ['tipe' => 'pg', 'bobot_skor' => 1];
                if ($q_info['tipe'] === 'essay') {
                    $tot_esai_row++;
                    $bobot_ess_e += (float)$q_info['bobot_skor'];
                } else {
                    $tot_obj_row++;
                    $bobot_obj_e += (float)$q_info['bobot_skor'];
                }
            }
        }
    }

    $skor_obj_v = (float)($d['skor_objektif'] ?? 0);
    $skor_ess_v = (float)($d['skor_essay'] ?? 0);

    // Hitung Nilai Objektif (prioritaskan kolom database, fallback ke formula scoring engine)
    if (isset($d['nilai_objektif']) && $d['nilai_objektif'] !== null) {
        $nilai_obj_x = (float)$d['nilai_objektif'];
    } else {
        $nilai_obj_x = ($bobot_obj_e > 0) ? round(($skor_obj_v / $bobot_obj_e) * 100, 2) : 0.0;
    }

    // Hitung Nilai Esai (prioritaskan kolom database, fallback ke formula scoring engine)
    if (isset($d['nilai_esai']) && $d['nilai_esai'] !== null) {
        $nilai_esai_x = (float)$d['nilai_esai'];
    } else {
        $nilai_esai_x = ($bobot_ess_e > 0) ? round(($skor_ess_v / $bobot_ess_e) * 100, 2) : 0.0;
    }

    $has_obj_x = $bobot_obj_e > 0;
    $has_ess_x = $bobot_ess_e > 0;

    // Hitung Nilai Akhir (prioritaskan kolom skor_akhir database)
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

    // Format kelas: pastikan string murni agar Excel TIDAK pernah mengonversi '10-1' menjadi tanggal
    $kelas_text = (!empty($d['jenjang']) ? $d['jenjang'] . ' - ' : '') . (string)($d['nama_kelas'] ?? '-');
    $nisn_text  = (string)($d['nisn'] ?? '');

    $rows[] = [
        ['v' => $i + 1, 's' => $style_cell_center],
        ['v' => $nisn_text, 't' => 's', 's' => $style_cell_center],
        ['v' => (string)($d['nama_lengkap'] ?? '-'), 't' => 's', 's' => $style_cell_left],
        ['v' => $kelas_text, 't' => 's', 's' => $style_cell_center],
        ['v' => (string)($d['nama_mapel'] ?? $nama_mapel_label), 't' => 's', 's' => $style_cell_left],
        ['v' => (string)($d['sesi'] ?? '-'), 't' => 's', 's' => $style_cell_center],
        ['v' => $benar_obj_str, 't' => 's', 's' => $style_cell_center],
        ['v' => $benar_esai_str, 't' => 's', 's' => $style_cell_center],
        ['v' => (float)number_format($nilai_obj_x, 2, '.', ''), 's' => $style_cell_center],
        ['v' => (float)number_format($nilai_esai_x, 2, '.', ''), 's' => $style_cell_center],
        ['v' => (float)number_format($nilai_akhir_x, 2, '.', ''), 's' => $style_cell_bold_center],
        ['v' => $status_koreksi, 't' => 's', 's' => $style_cell_center]
    ];
}

$merge_cells = [
    'A1:L1',
    'A2:L2',
    'A3:L3',
    'A4:L4'
];

$xlsx->addSheet($rows, 'Rekap Nilai', $merge_cells);

$safe_ujian = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_ujian_label);
$safe_kelas = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_kelas_label);
$filename   = 'Rekap Nilai - ' . trim($safe_ujian) . ' - ' . trim($safe_kelas) . '.xlsx';

$xlsx->downloadAs($filename);
exit;
