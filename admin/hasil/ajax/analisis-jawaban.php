<?php
session_start();
// Gunakan path absolut yang lebih aman
require_once __DIR__ . '/../../../config/database.php';

// 1. Ambil Parameter
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. Ambil Data Ujian (Lakukan di awal agar variabel tersedia untuk seluruh halaman)
$stmt_exam = $pdo->prepare("
    SELECT e.*, b.nama_bank_soal, s.nama_mapel 
    FROM cbt_exams e 
    LEFT JOIN cbt_bank_soal b ON e.bank_soal_id = b.id 
    LEFT JOIN cbt_subjects s ON e.subject_id = s.id
    WHERE e.id = ?
");
$stmt_exam->execute([$exam_id]);
$exam = $stmt_exam->fetch();

$total_peserta = 0;
$list_soal = [];
$data_labels = [];
$data_benar  = [];
$data_salah  = [];

if ($exam) {
    // Hitung total peserta
    $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = ?");
    $stmt_p->execute([$exam_id]);
    $total_peserta = $stmt_p->fetchColumn();

    // Ambil daftar soal
    $stmt_s = $pdo->prepare("
        SELECT q.* FROM cbt_questions q 
        JOIN cbt_exam_questions eq ON q.id = eq.question_id 
        WHERE eq.exam_id = ? ORDER BY eq.order_number ASC
    ");
    $stmt_s->execute([$exam_id]);
    $list_soal = $stmt_s->fetchAll();

    // Persiapkan data untuk grafik
    foreach ($list_soal as $index => $s) {
        $data_labels[] = "Soal " . ($index + 1);
        
        // Hanya hitung benar/salah untuk tipe PG dan B/S
        if ($s['tipe'] == 'pg' || $s['tipe'] == 'benar_salah') {
            $st_b = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa 
                                    JOIN cbt_exam_participants p ON sa.participant_id = p.id 
                                    WHERE p.exam_id = ? AND sa.question_id = ? AND sa.is_correct = 1");
            $st_b->execute([$exam_id, $s['id']]);
            $benar = $st_b->fetchColumn();
            $data_benar[] = (int)$benar;
            $data_salah[] = (int)($total_peserta - $benar);
        } else {
            $data_benar[] = 0; 
            $data_salah[] = 0;
        }
    }
}

$tipe_nama = [
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
    <head>
        <?php include __DIR__ . '/../../../includes/header.php'; ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            .table th { font-size: 11px; text-transform: uppercase; background: #f8f9fa !important; letter-spacing: 0.5px; }
            .is-correct { background-color: #d1e7dd !important; color: #0f5132; font-weight: bold; border: 1px solid #badbcc; }
            .bg-distractor { background-color: #f8f9fa; border: 1px solid #eee; padding: 6px 10px; border-radius: 6px; margin-bottom: 4px; font-size: 12px; }
            .chart-container { position: relative; height: 300px; width: 100%; }
        </style>
    </head>
<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center w-100">
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <h5 class="mb-0 fw-bold">Analisis Sebaran Jawaban</h5>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php if (!$exam): ?>
                <div class="alert alert-danger">Ujian tidak ditemukan.</div>
            <?php else: ?>
                
                <div class="card border-0 shadow-sm mb-4 rounded-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Grafik Ketuntasan (Benar vs Salah)</h6>
                            
                            <a href="cetak-analisis.php?exam_id=<?= esc($exam_id) ?>" target="_blank" class="btn btn-danger btn-sm shadow-sm fw-bold">
                                <i class="fas fa-file-pdf me-2"></i> Cetak Analisis
                            </a>
                        </div>

                        <div class="chart-container" style="position: relative; height:300px;">
                            <canvas id="chartAnalisis"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr class="text-center">
                                    <th width="50">No</th>
                                    <th width="300">Butir Soal</th>
                                    <th>Sebaran Jawaban</th>
                                    <th width="100">Kosong</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_soal as $index => $s): ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?= $index + 1 ?></td>
                                        <td>
                                            <div class="mb-1"><?= $tipe_nama[$s['tipe']] ?></div>
                                            <div class="small text-muted"><?= strip_tags(substr($s['konten_soal'], 0, 100)) ?>...</div>
                                        </td>
                                        <td>
                                            <div class="row g-2">
                                                <?php 
                                                // Contoh panggil opsi jawaban
                                                $stmt_opt = $pdo->prepare("SELECT label, is_correct FROM cbt_question_options WHERE question_id = ? ORDER BY label ASC");
                                                $stmt_opt->execute([$s['id']]);
                                                while($opt = $stmt_opt->fetch()): 
                                                    // Hitung berapa siswa pilih ini
                                                    $st_h = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa JOIN cbt_exam_participants p ON sa.participant_id = p.id WHERE p.exam_id = ? AND sa.question_id = ? AND sa.jawaban_simpan = ?");
                                                    $st_h->execute([$exam_id, $s['id'], $opt['label']]);
                                                    $count = $st_h->fetchColumn();
                                                ?>
                                                    <div class="col-md-2">
                                                        <div class="text-center p-2 rounded border <?= $opt['is_correct'] ? 'is-correct' : 'bg-white' ?>">
                                                            <div class="small fw-bold"><?= $opt['label'] ?></div>
                                                            <div class="h6 mb-0"><?= $count ?></div>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $st_k = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa JOIN cbt_exam_participants p ON sa.participant_id = p.id WHERE p.exam_id = ? AND sa.question_id = ? AND (sa.jawaban_simpan IS NULL OR sa.jawaban_simpan = '')");
                                            $st_k->execute([$exam_id, $s['id']]);
                                            echo "<h5 class='mb-0'>".$st_k->fetchColumn()."</h5><small class='text-muted'>Siswa</small>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$chartJsonPayload = json_encode([
    'labels'  => $data_labels,
    'benar'   => $data_benar,
    'salah'   => $data_salah,
    'exam_id' => (int)$exam_id
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="chartDataJson">
<?= $chartJsonPayload ?>
</script>
<script>
    const _cData = JSON.parse(document.getElementById('chartDataJson').textContent || '{}');
    const ctx = document.getElementById('chartAnalisis').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: _cData.labels || [],
            datasets: [
                { label: 'Benar', data: _cData.benar || [], backgroundColor: '#198754' },
                { label: 'Salah', data: _cData.salah || [], backgroundColor: '#dc3545' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    function loadTableData() {
        $.get('ajax/analisis-jawaban-data.php', { exam_id: _cData.exam_id }, function(html) {
            $('#log-table-body').html(html);
        });
    }
</script>
</body>
</html>