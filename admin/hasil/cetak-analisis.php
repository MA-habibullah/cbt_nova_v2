<?php
require_once '../../config/database.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Ambil Data Ujian & Mapel
$stmt = $pdo->prepare("
    SELECT e.*, b.nama_bank_soal, s.nama_mapel 
    FROM cbt_exams e 
    LEFT JOIN cbt_bank_soal b ON e.bank_soal_id = b.id 
    LEFT JOIN cbt_subjects s ON e.subject_id = s.id
    WHERE e.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) die("Data tidak ditemukan.");

// Ambil Daftar Soal
$stmt_s = $pdo->prepare("
    SELECT q.* FROM cbt_questions q 
    JOIN cbt_exam_questions eq ON q.id = eq.question_id 
    WHERE eq.exam_id = ? ORDER BY eq.order_number ASC
");
$stmt_s->execute([$exam_id]);
$list_soal = $stmt_s->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Analisis - <?= esc($exam['nama_mapel_ujian']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; background: white; color: black; }
        .kop-surat { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .table th { background-color: #eee !important; color: black; font-size: 12px; }
        .table td { font-size: 12px; }
        .badge-correct { font-weight: bold; text-decoration: underline; color: #198754; }
        @media print {
            .no-print { display: none; }
            body { margin: 1cm; }
            .card { border: none !important; shadow: none !important; }
        }
    </style>
</head>
<body>

<div class="container mt-3">
    <div class="no-print mb-4 d-flex justify-content-between">
        <a href="analisis-jawaban.php?exam_id=<?= esc($exam_id) ?>" class="btn btn-secondary btn-sm">Kembali</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Cetak Laporan</button>
    </div>

    <div class="kop-surat text-center">
        <h4 class="mb-0">LAPORAN ANALISIS BUTIR SOAL</h4>
        <h5 class="mb-0 text-uppercase"><?= $exam['nama_mapel_ujian'] ?></h5>
        <p class="mb-0">Mata Pelajaran: <?= $exam['nama_mapel'] ?> | Bank Soal: <?= $exam['nama_bank_soal'] ?></p>
    </div>

    <table class="table table-bordered align-middle">
        <thead>
            <tr class="text-center">
                <th width="40">No</th>
                <th>Butir Soal</th>
                <th width="300">Sebaran Jawaban (Jumlah Siswa)</th>
                <th width="80">Kosong</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list_soal as $index => $s): ?>
            <tr>
                <td class="text-center"><?= $index + 1 ?></td>
                <td>
                    <div class="fw-bold mb-1">[<?= strtoupper($s['tipe']) ?>]</div>
                    <div style="font-size: 11px;"><?= strip_tags($s['konten_soal']) ?></div>
                </td>
                <td>
                    <div class="row g-1 text-center">
                        <?php 
                        $stmt_opt = $pdo->prepare("SELECT label, is_correct FROM cbt_question_options WHERE question_id = ? ORDER BY label ASC");
                        $stmt_opt->execute([$s['id']]);
                        while($opt = $stmt_opt->fetch()):
                            $st_h = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa JOIN cbt_exam_participants p ON sa.participant_id = p.id WHERE p.exam_id = ? AND sa.question_id = ? AND sa.jawaban_simpan = ?");
                            $st_h->execute([$exam_id, $s['id'], $opt['label']]);
                            $count = $st_h->fetchColumn();
                        ?>
                        <div class="col-2 border m-1 py-1 <?= $opt['is_correct'] ? 'bg-light fw-bold text-success' : '' ?>">
                            <div style="font-size: 9px;"><?= $opt['label'] ?></div>
                            <div class="fw-bold"><?= $count ?></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </td>
                <td class="text-center">
                    <?php 
                    $st_k = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa JOIN cbt_exam_participants p ON sa.participant_id = p.id WHERE p.exam_id = ? AND sa.question_id = ? AND (sa.jawaban_simpan IS NULL OR sa.jawaban_simpan = '')");
                    $st_k->execute([$exam_id, $s['id']]);
                    echo $st_k->fetchColumn();
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row mt-5">
        <div class="col-8"></div>
        <div class="col-4 text-center">
            <p>Dicetak pada: <?= date('d/m/Y H:i') ?></p>
            <br><br><br>
            <p class="fw-bold">__________________________</p>
            <p>Admin / Proktor Ujian</p>
        </div>
    </div>
</div>

</body>
</html>