<?php
ob_start();
ini_set('memory_limit', '512M');
set_time_limit(120);

require_once dirname(__DIR__, 3) . '/config/database.php';
global $pdo;

// Cek autentikasi: Khusus Guru (atau Admin)
$is_admin = isset($_SESSION['admin_id']) || (($_SESSION['role'] ?? '') === 'admin');
$is_guru  = isset($_SESSION['teacher_id']) || (($_SESSION['role'] ?? '') === 'guru');

if (!$is_admin && !$is_guru) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$teacher_id = $is_guru ? (int)($_SESSION['teacher_id'] ?? 0) : 0;

// Autoload composer jika ada
$vendor_autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

// 1. Tangkap Parameter
$id_bank  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam_id  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$sesi     = isset($_GET['sesi']) ? trim($_GET['sesi']) : '';

// 2. Ambil Informasi Ujian & Mapel
$nama_ujian_label = '-';
$nama_mapel_label = '-';

if ($exam_id) {
    $stmt_ex = $pdo->prepare("SELECT e.nama_mapel_ujian, e.bank_soal_id, e.teacher_id, s.nama_mapel, b.nama_bank_soal 
                              FROM cbt_exams e 
                              LEFT JOIN cbt_subjects s ON e.subject_id = s.id 
                              LEFT JOIN cbt_bank_soal b ON e.bank_soal_id = b.id 
                              WHERE e.id = ?");
    $stmt_ex->execute([$exam_id]);
    $ex_row = $stmt_ex->fetch(PDO::FETCH_ASSOC);
    if ($ex_row) {
        if ($is_guru && $teacher_id > 0 && (int)$ex_row['teacher_id'] !== $teacher_id) {
            die("Akses ditolak: Anda tidak memiliki akses untuk mencetak hasil ujian ini.");
        }
        $nama_ujian_label = !empty($ex_row['nama_mapel_ujian']) ? $ex_row['nama_mapel_ujian'] : (!empty($ex_row['nama_bank_soal']) ? $ex_row['nama_bank_soal'] : '-');
        $nama_mapel_label = !empty($ex_row['nama_mapel']) ? $ex_row['nama_mapel'] : '-';
        if (!$id_bank && !empty($ex_row['bank_soal_id'])) {
            $id_bank = (int)$ex_row['bank_soal_id'];
        }
    }
}

if (($nama_ujian_label === '-' || $nama_mapel_label === '-') && $id_bank) {
    $stmt_mapel = $pdo->prepare("SELECT b.nama_bank_soal, b.teacher_id, s.nama_mapel
                                 FROM cbt_bank_soal b
                                 LEFT JOIN cbt_subjects s ON b.subject_id = s.id
                                 WHERE b.id = ?");
    $stmt_mapel->execute([$id_bank]);
    $info_mapel = $stmt_mapel->fetch(PDO::FETCH_ASSOC);
    if ($info_mapel) {
        if ($is_guru && $teacher_id > 0 && (int)$info_mapel['teacher_id'] !== $teacher_id) {
            die("Akses ditolak: Anda tidak memiliki akses untuk mencetak hasil bank soal ini.");
        }
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

// 3. Query Data Peserta
auto_finalize_expired_participants($pdo, $exam_id, $id_bank);

$query = "SELECT
            p.id as p_id, p.exam_id, p.soal_ids, s.nama_lengkap, s.nisn, s.sesi, k.jenjang, k.nama_kelas,
            COALESCE(sub.nama_mapel, ?) as nama_mapel,
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
            p.skor_akhir,
            p.nilai_objektif,
            p.nilai_esai,
            p.skor_status
          FROM cbt_exam_participants p
          JOIN cbt_students s ON p.student_id = s.id
          JOIN cbt_exams e ON p.exam_id = e.id
          LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
          LEFT JOIN cbt_subjects sub ON e.subject_id = sub.id
          WHERE 1=1";

$params = [$nama_mapel_label];

if ($exam_id) {
    $query .= " AND p.exam_id = ?";
    $params[] = $exam_id;
} elseif ($id_bank) {
    $query .= " AND e.bank_soal_id = ?";
    $params[] = $id_bank;
}

if ($is_guru && $teacher_id > 0) {
    $query .= " AND e.teacher_id = ?";
    $params[] = $teacher_id;
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
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch question types if any participants have custom soal_ids
$all_q_ids = [];
foreach ($results as $d) {
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

// 4. Susun Dokumen HTML
$html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Hasil Ujian - ' . htmlspecialchars($nama_ujian_label) . '</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 15mm 12mm 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h2 {
            margin: 0 0 4px 0;
            font-size: 16px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #94a3b8;
            padding: 5px 4px;
            font-size: 9.5px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-bold { font-weight: bold; }
        .bg-pass { color: #15803d; font-weight: bold; }
        .bg-fail { color: #b91c1c; font-weight: bold; }
        .footer-ttd {
            margin-top: 25px;
            width: 100%;
            page-break-inside: avoid;
        }
        .ttd-box {
            float: right;
            width: 220px;
            text-align: center;
            font-size: 10px;
        }
        .no-print {
            display: none;
        }
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="header">
    <h2>REKAPITULASI HASIL UJIAN CBT</h2>
    <table class="meta-table">
        <tr>
            <td width="15%"><strong>Nama Ujian</strong></td>
            <td width="35%">: ' . htmlspecialchars($nama_ujian_label) . '</td>
            <td width="15%"><strong>Tahun Ajaran</strong></td>
            <td width="35%">: ' . htmlspecialchars($str_ta) . '</td>
        </tr>
        <tr>
            <td><strong>Mata Pelajaran</strong></td>
            <td>: ' . htmlspecialchars($nama_mapel_label) . '</td>
            <td><strong>Kelas / Sesi</strong></td>
            <td>: ' . htmlspecialchars($nama_kelas_label) . ($sesi !== '' ? ' (Sesi ' . htmlspecialchars($sesi) . ')' : '') . '</td>
        </tr>
    </table>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th width="3%">No</th>
            <th width="11%">NISN</th>
            <th width="24%">Nama Siswa</th>
            <th width="10%">Kelas</th>
            <th width="5%">Sesi</th>
            <th width="8%">Benar Obj</th>
            <th width="8%">Benar Esai</th>
            <th width="8%">Nilai Obj</th>
            <th width="8%">Nilai Esai</th>
            <th width="8%">Nilai Akhir</th>
            <th width="7%">Koreksi</th>
        </tr>
    </thead>
    <tbody>';

if (empty($results)) {
    $html .= '<tr><td colspan="11" class="text-center" style="padding: 20px; color: #64748b;">Tidak ada data peserta ujian untuk kriteria ini.</td></tr>';
} else {
    foreach ($results as $i => $r) {
        $total_soal_obj   = (int)$r['total_soal_obj'];
        $jml_benar_obj    = (int)$r['jml_benar_obj'];
        $total_soal_esai  = (int)$r['total_soal_esai'];
        $jml_benar_esai   = (int)$r['jml_benar_esai'];

        // Jika peserta memiliki subset soal_ids, hitung total soal per tipe dari subsetnya
        if (!empty($r['soal_ids'])) {
            $decoded_sids = json_decode($r['soal_ids'], true);
            if (is_array($decoded_sids) && !empty($decoded_sids)) {
                $total_soal_obj  = 0;
                $total_soal_esai = 0;
                foreach ($decoded_sids as $sid) {
                    $t = $q_types_map[(int)$sid] ?? 'pg';
                    if ($t === 'essay') {
                        $total_soal_esai++;
                    } else {
                        $total_soal_obj++;
                    }
                }
            }
        }

        $benar_obj_str    = ($total_soal_obj > 0) ? ($jml_benar_obj . '/' . $total_soal_obj) : '-';
        $benar_esai_str   = $total_soal_esai > 0 ? ($jml_benar_esai . '/' . $total_soal_esai) : '-';

        $bobot_obj_e  = (float)($r['bobot_obj_exam']  ?? 0);
        $bobot_ess_e  = (float)($r['bobot_essay_exam'] ?? 0);
        $has_obj_x    = $bobot_obj_e > 0;
        $has_ess_x    = $bobot_ess_e > 0;

        // Gunakan nilai dari DB (yang dihitung engine scoring)
        $nilai_obj_x  = isset($r['nilai_objektif']) ? (float)$r['nilai_objektif'] : (($bobot_obj_e > 0) ? round((float)$r['skor_objektif'] / $bobot_obj_e * 100, 2) : 0.0);
        $nilai_esai_x = isset($r['nilai_esai']) ? (float)$r['nilai_esai'] : (($bobot_ess_e > 0) ? round((float)$r['skor_essay'] / $bobot_ess_e * 100, 2) : 0.0);
        $nilai_akhir_x = isset($r['skor_akhir']) ? (float)$r['skor_akhir'] : ($has_obj_x && $has_ess_x ? round(($nilai_obj_x * 0.5) + ($nilai_esai_x * 0.5), 2) : ($has_obj_x ? $nilai_obj_x : ($has_ess_x ? $nilai_esai_x : 0.0)));

        $status_koreksi = ($r['skor_status'] ?? 'final') === 'pending' ? 'Belum Final' : 'Final';

        $kelas_display  = !empty($r['jenjang']) ? $r['jenjang'] . ' - ' . ($r['nama_kelas'] ?? '-') : ($r['nama_kelas'] ?? '-');
        $html .= '
        <tr>
            <td class="text-center">' . ($i + 1) . '</td>
            <td class="text-center">' . htmlspecialchars($r['nisn'] ?? '-') . '</td>
            <td class="text-left text-bold">' . htmlspecialchars(strtoupper($r['nama_lengkap'])) . '</td>
            <td class="text-center">' . htmlspecialchars($kelas_display) . '</td>
            <td class="text-center">' . htmlspecialchars($r['sesi'] ?? '-') . '</td>
            <td class="text-center">' . $benar_obj_str . '</td>
            <td class="text-center">' . $benar_esai_str . '</td>
            <td class="text-center">' . ($has_obj_x ? number_format($nilai_obj_x, 2) : '-') . '</td>
            <td class="text-center">' . ($has_ess_x ? number_format($nilai_esai_x, 2) : '-') . '</td>
            <td class="text-center text-bold ' . ($nilai_akhir_x >= 75 ? 'bg-pass' : 'bg-fail') . '">' . number_format($nilai_akhir_x, 2) . '</td>
            <td class="text-center">' . $status_koreksi . '</td>
        </tr>';
    }
}

$html .= '
    </tbody>
</table>

<div class="footer-ttd">
    <div class="ttd-box">
        <p>Dicetak pada: ' . date('d/m/Y H:i') . ' WIB</p>
        <br><br><br>
        <p class="text-bold">( __________________________ )</p>
        <p>Guru Pengampu</p>
    </div>
</div>

</body>
</html>';

// 5. Eksekusi Render PDF dengan Fallback HTML Print Aman
$safe_ujian = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_ujian_label);
$safe_kelas = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $nama_kelas_label);
$filename   = trim($safe_ujian) . ' - ' . trim($safe_kelas) . '.pdf';

try {
    if (!class_exists('Dompdf\Dompdf')) {
        throw new Exception("Class Dompdf tidak ditemukan.");
    }

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $options->set('tempDir', sys_get_temp_dir());

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    if (headers_sent()) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    } else {
        $dompdf->stream($filename, ["Attachment" => false]);
        exit;
    }

} catch (\Throwable $e) {
    // FALLBACK ENGINE: Jika Dompdf error/memory limit di server, tampilkan halaman print HTML profesional
    if (ob_get_length()) {
        ob_end_clean();
    }

    $fallback_toolbar = '
    <div class="no-print" style="position: fixed; top: 12px; right: 15px; z-index: 9999; background: #0f172a; color: white; padding: 8px 16px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-family: sans-serif; font-size: 13px; display: flex; gap: 10px; align-items: center;">
        <span><strong>Tampilan Cetak Siap Print / PDF</strong></span>
        <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-weight: bold;">Cetak / Simpan PDF</button>
        <button onclick="window.close()" style="background: #64748b; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">Tutup</button>
    </div>
    <script>
        window.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() { window.print(); }, 600);
        });
    </script>';

    $html_fallback = str_replace('</body>', $fallback_toolbar . '</body>', $html);
    echo $html_fallback;
    exit;
}
