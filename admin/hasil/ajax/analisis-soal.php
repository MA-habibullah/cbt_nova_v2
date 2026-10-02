<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Ambil ID Ujian
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// 2. Query Info Ujian & Mapel
$stmt_exam = $pdo->prepare("
    SELECT e.*, b.nama_bank_soal, s.nama_mapel 
    FROM cbt_exams e 
    JOIN cbt_bank_soal b ON e.bank_soal_id = b.id 
    JOIN cbt_subjects s ON b.subject_id = s.id 
    WHERE e.id = ?
");
$stmt_exam->execute([$exam_id]);
$exam = $stmt_exam->fetch();

// 3. Ambil Peserta & Soal (Hanya jika ujian ditemukan)
$total_peserta = 0;
$list_soal = [];
if ($exam) {
    // Hitung total peserta terdaftar (siapa saja, bukan cuma yang sudah selesai)
    $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = ?");
    $stmt_p->execute([$exam_id]);
    $total_peserta = $stmt_p->fetchColumn();

    // Ambil daftar soal
    $stmt_s = $pdo->prepare("SELECT id, konten_soal, tipe, bobot_skor FROM cbt_questions WHERE bank_soal_id = ? ORDER BY id ASC");
    $stmt_s->execute([$exam['bank_soal_id']]);
    $list_soal = $stmt_s->fetchAll();
}

$tipe_label = [
    'pg'            => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">PG</span>',
    'pg_kompleks'   => '<span class="badge bg-info-subtle text-info border border-info-subtle">PGK</span>',
    'isian'         => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Isian</span>',
    'benar_salah'   => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">B/S</span>',
    'menjodohkan'   => '<span class="badge bg-dark-subtle text-dark border border-dark-subtle">Jodoh</span>',
    'essay'         => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Essay</span>'
];
?>

<!DOCTYPE html>
<html lang="id">
    <?php include dirname(__DIR__, 3) . '/includes/header.php'; ?>

    <style>
        .progress { height: 8px; border-radius: 10px; background-color: #eee; }
        .table th { font-size: 11px; text-transform: uppercase; background: #f8f9fa !important; color: #555; }
        .empty-state { padding: 100px 0; text-align: center; color: #ccc; }
    </style>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 3) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center w-100">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <h5 class="mb-0 fw-bold">Analisis Butir Soal</h5>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4">
            
            <?php if (!$exam): ?>
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <i class="fas fa-search fa-4x mb-3"></i>
                        <h4>Ujian Tidak Ditemukan</h4>
                        <p>Silakan kembali dan pilih jadwal ujian yang valid.</p>
                        <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-primary px-4 mt-2">Kembali ke Daftar Hasil</a>
                    </div>
                </div>
            <?php else: ?>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <span class="badge bg-primary mb-2">Informasi Ujian</span>
                                <h4 class="fw-bold text-dark"><?= strtoupper($exam['nama_mapel_ujian']) ?></h4>
                                <p class="text-muted mb-0"><?= $exam['nama_mapel'] ?> &bull; Bank Soal: <?= $exam['nama_bank_soal'] ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 text-center bg-white">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <h2 class="fw-bold text-primary mb-0"><?= $total_peserta ?></h2>
                                <small class="text-muted fw-bold">Peserta Terdaftar</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold text-muted"><i class="fas fa-list-check me-2"></i>Parameter Tingkat Kesukaran</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-center">
                                    <th width="60">No</th>
                                    <th width="100">Tipe</th>
                                    <th class="text-start">Butir Soal</th>
                                    <th width="100">Bobot</th>
                                    <th width="100">Rata Skor</th>
                                    <th width="200">Indeks P</th>
                                    <th width="100">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list_soal)): ?>
                                    <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada soal dalam bank soal ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($list_soal as $i => $s): 
                                        // Hitung skor masuk dari cbt_student_answers
                                        $st_skor = $pdo->prepare("
                                            SELECT SUM(a.skor_didapat) FROM cbt_student_answers a
                                            JOIN cbt_exam_participants p ON a.participant_id = p.id
                                            WHERE a.question_id = ? AND p.exam_id = ?
                                        ");
                                        $st_skor->execute([$s['id'], $exam_id]);
                                        $total_skor = (float)$st_skor->fetchColumn();

                                        $rata_rata = ($total_peserta > 0) ? ($total_skor / $total_peserta) : 0;
                                        $indeks_p = ($s['bobot_skor'] > 0) ? ($rata_rata / $s['bobot_skor']) : 0;
                                        $persen_p = $indeks_p * 100;

                                        // Kriteria
                                        if ($persen_p <= 30) { $krt = "Sukar"; $clr = "danger"; }
                                        elseif ($persen_p <= 70) { $krt = "Sedang"; $clr = "warning"; }
                                        else { $krt = "Mudah"; $clr = "success"; }
                                    ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?= $i + 1 ?></td>
                                        <td class="text-center"><?= $tipe_label[$s['tipe']] ?? $s['tipe'] ?></td>
                                        <td>
                                            <div class="small text-dark text-truncate" style="max-width: 350px;">
                                                <?= strip_tags($s['konten_soal']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold"><?= $s['bobot_skor'] ?></td>
                                        <td class="text-center text-primary fw-bold"><?= round($rata_rata, 2) ?></td>
                                        <td>
                                            <div class="progress mb-1"><div class="progress-bar bg-<?= $clr ?>" style="width: <?= $persen_p ?>%"></div></div>
                                            <div class="d-flex justify-content-between" style="font-size: 9px;">
                                                <span>0%</span><span class="fw-bold"><?= round($persen_p, 1) ?>%</span><span>100%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $clr ?>-subtle text-<?= $clr ?> border border-<?= $clr ?>-subtle px-3"><?= $krt ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-white border-start border-4 border-info shadow-sm rounded">
                    <h6 class="fw-bold small mb-1">Informasi Analisis:</h6>
                    <p class="small text-muted mb-0">Indeks kesukaran dihitung berdasarkan perbandingan rata-rata skor peserta terhadap bobot maksimal soal. Jika peserta belum mengerjakan, nilai akan otomatis 0 (Sukar).</p>
                </div>

            <?php endif; ?>
        </div>
        
        <footer class="bg-white text-center py-3 border-top mt-5">
            <small class="text-muted">CBT Native System &copy; 2026</small>
        </footer>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>