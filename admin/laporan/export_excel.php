<?php
session_start();
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    exit("Unauthorized");
}

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : '';

if (!$exam_id) exit("Pilih Ujian Terlebih Dahulu");

// 1. Ambil Info Ujian untuk Nama File
$stmtExam = $pdo->prepare("SELECT nama_mapel_ujian FROM cbt_exams WHERE id = ?");
$stmtExam->execute([$exam_id]);
$exam_info = $stmtExam->fetch();
$nama_file = "Nilai_" . str_replace(' ', '_', $exam_info['nama_mapel_ujian']) . "_" . date('Ymd_His') . ".xls";

// 2. Query Data Nilai
$query = "
    SELECT 
        s.nama_lengkap, s.nisn, c.nama_kelas,
        p.skor_akhir, p.waktu_selesai, p.exam_id, p.id as participant_id,
        (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND sa.skor_didapat > 0) as benar,
        (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND sa.skor_didapat = 0 AND sa.jawaban_simpan IS NOT NULL AND sa.jawaban_simpan != '') as salah,
        (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND (sa.jawaban_simpan IS NULL OR sa.jawaban_simpan = '')) as kosong
    FROM cbt_exam_participants p
    JOIN cbt_students s ON p.student_id = s.id
    JOIN cbt_classes c ON s.class_id = c.id
    WHERE p.exam_id = ? AND p.status = 'finished'
";

$params = [$exam_id];
if ($class_id) {
    $query .= " AND s.class_id = ?";
    $params[] = $class_id;
}
$query .= " ORDER BY s.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll();

// 3. Header untuk Download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$nama_file");
header("Pragma: no-cache");
header("Expires: 0");
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="8" style="font-size: 16px; font-weight: bold;">LAPORAN NILAI HASIL UJIAN</th>
        </tr>
        <tr>
            <th colspan="8" style="font-size: 14px;">Mata Pelajaran: <?= $exam_info['nama_mapel_ujian'] ?></th>
        </tr>
        <tr>
            <th colspan="8">Tanggal Cetak: <?= date('d/m/Y H:i') ?></th>
        </tr>
        <tr></tr>
        <tr style="background-color: #f2f2f2; font-weight: bold;">
            <th width="50">No</th>
            <th width="200">Nama Lengkap</th>
            <th width="100">NISN</th>
            <th width="100">Kelas</th>
            <th width="80">Benar</th>
            <th width="80">Salah</th>
            <th width="80">Kosong</th>
            <th width="100">Nilai Akhir</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $index => $row):
            // --- LOGIKA KALKULASI NILAI ---
                                
            // 1. Hitung total soal yang harus dikerjakan pada ujian ini
            $stmt_q = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_questions WHERE exam_id = ?");
            $stmt_q->execute([$row['exam_id']]);
            $total_soal = $stmt_q->fetchColumn();

            // 2. Hitung jumlah soal yang dijawab benar (skor_didapat > 0)
            $stmt_ans = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers WHERE participant_id = ? AND skor_didapat > 0");
            $stmt_ans->execute([$row['participant_id']]);
            $jml_benar = $stmt_ans->fetchColumn();

            // 3. Hitung Nilai Akhir
            $nilai_akhir = 0;
            if($total_soal > 0) {
                $nilai_akhir = ($jml_benar / $total_soal) * 100;
            }
        ?>
        <tr>
            <td align="center"><?= $index + 1 ?></td>
            <td><?= strtoupper($row['nama_lengkap']) ?></td>
            <td align="center">'<?= $row['nisn'] ?></td> <td align="center"><?= $row['nama_kelas'] ?></td>
            <td align="center"><?= $row['benar'] ?></td>
            <td align="center"><?= $row['salah'] ?></td>
            <td align="center"><?= $row['kosong'] ?></td>
            <td align="center" style="font-weight: bold; mso-number-format:0\.00;"><?= round((float)$nilai_akhir, 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>