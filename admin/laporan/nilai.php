<?php
session_start();
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$current_dir = 'laporan';
$current_page = 'nilai.php';

// 1. Inisialisasi Filter
$filter_date  = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_exam  = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';
$filter_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';

// 2. Ambil Daftar Ujian
$stmt_ex = $pdo->prepare("
    SELECT id, nama_mapel_ujian 
    FROM cbt_exams 
    WHERE DATE(mulai_pada) = ? 
    ORDER BY mulai_pada ASC
");
$stmt_ex->execute([$filter_date]);
$exams = $stmt_ex->fetchAll();

// 3. Ambil Daftar Kelas
$classes = $pdo->query("SELECT id, jenjang, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();

// 4. Query Utama
$results = [];
if ($filter_exam) {
    $query = "
        SELECT 
            p.id as participant_id, 
            p.exam_id,
            p.skor_akhir, 
            p.waktu_selesai,
            s.nama_lengkap, 
            s.nisn, 
            c.nama_kelas,
            (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND sa.skor_didapat > 0) as benar,
            (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND sa.skor_didapat = 0 AND sa.jawaban_simpan IS NOT NULL AND sa.jawaban_simpan != '') as salah,
            (SELECT COUNT(*) FROM cbt_student_answers sa WHERE sa.participant_id = p.id AND (sa.jawaban_simpan IS NULL OR sa.jawaban_simpan = '')) as kosong
        FROM cbt_exam_participants p
        JOIN cbt_students s ON p.student_id = s.id
        JOIN cbt_classes c ON s.class_id = c.id
        WHERE p.exam_id = ? AND p.status = 'finished'
    ";

    $params = [$filter_exam];
    if ($filter_class) {
        $query .= " AND s.class_id = ?";
        $params[] = $filter_class;
    }

    $query .= " ORDER BY s.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>
    <style>
        .filter-box { background: #fff; border-radius: 15px; border: none; }
        .stat-card { border-radius: 12px; transition: all 0.3s; }
        .table thead th { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        @media print { .no-print { display: none; } .card { border: none !important; box-shadow: none !important; } }
    </style>
    
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm no-print">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-poll me-2 text-primary"></i> Laporan Nilai Ujian</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <div class="card shadow-sm mb-4 filter-box no-print">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">1. Tanggal Pelaksanaan</label>
                            <input type="date" name="date" class="form-control" value="<?= esc($filter_date) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">2. Pilih Ujian</label>
                            <select name="exam_id" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Pilih Pelaksanaan Ujian --</option>
                                <?php foreach ($exams as $ex): ?>
                                    <option value="<?= esc($ex['id']) ?>" <?= esc($filter_exam == $ex['id'] ? 'selected' : '') ?>><?= esc($ex['nama_mapel_ujian']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">3. Filter Kelas</label>
                            <select name="class_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?= esc($cl['id']) ?>" <?= esc($filter_class == $cl['id'] ? 'selected' : '') ?>><?= esc($cl['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="nilai.php" class="btn btn-light border w-100 fw-bold">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($filter_exam && !empty($results)): 
                // PERBAIKAN: Menangani nilai null sebelum diproses array_column
                $scores = array_map(fn($val) => (float)($val ?? 0), array_column($results, 'skor_akhir'));
                $avg = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
            ?>
                <div class="row g-3 mb-4 no-print">
                    <div class="col-md-4">
                        <div class="card stat-card border-0 shadow-sm bg-primary text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><small class="opacity-75 d-block">Rata-rata Nilai</small><span class="h3 fw-bold mb-0"><?= number_format((float)$avg, 2) ?></span></div>
                                <i class="fas fa-chart-line fa-2x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card border-0 shadow-sm bg-success text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><small class="opacity-75 d-block">Nilai Tertinggi</small><span class="h3 fw-bold mb-0"><?= count($scores) > 0 ? max($scores) : 0 ?></span></div>
                                <i class="fas fa-trophy fa-2x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card border-0 shadow-sm bg-white text-dark p-3 text-center d-flex flex-row align-items-center justify-content-center gap-2">
                            <button onclick="window.print()" class="btn btn-danger fw-bold shadow-sm">
                                <i class="fas fa-print me-2"></i>Cetak
                            </button>
                            <a href="export_excel.php?exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_class) ?>" class="btn btn-success fw-bold shadow-sm">
                                <i class="fas fa-file-excel me-2"></i>Excel
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
                    <div class="table-responsive">
                        <table id="tableNilai" class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4" width="50">No</th>
                                    <th>Nama Siswa</th>
                                    <th class="text-center">Kelas</th>
                                    <th class="text-center">Benar</th>
                                    <th class="text-center">Salah</th>
                                    <th class="text-center">Kosong</th>
                                    <th class="text-center">Nilai Akhir</th>
                                    <th class="text-center">Selesai</th>
                                    <th class="text-center pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $row):
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
                                        $nilai_akhir = round($nilai_akhir, 2); // Bulatkan 2 desimal
                                    }
                                ?>
                                <tr>
                                    <td class="ps-4 text-center"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= $row['nama_lengkap'] ?></div>
                                        <small class="text-muted"><?= $row['nisn'] ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= $row['nama_kelas'] ?></span>
                                    </td>
                                    <td class="text-center text-success fw-bold"><?= $row['benar'] ?></td>
                                    <td class="text-center text-danger fw-bold"><?= $row['salah'] ?></td>
                                    <td class="text-center text-muted"><?= $row['kosong'] ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $nilai_akhir >= 75 ? 'bg-success' : 'bg-primary' ?> fs-6 rounded-pill px-3">
                                            <?= number_format((float)$nilai_akhir, 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-center small text-muted">
                                        <?= $row['waktu_selesai'] ? date('H:i', strtotime($row['waktu_selesai'])) : '--:--' ?> WIB
                                    </td>
                                    <td class="text-center pe-4">
                                        <a href="detail-siswa.php?id=<?= esc($row['participant_id']) ?>" 
                                        class="btn btn-sm btn-info text-white rounded-pill px-3 shadow-sm">
                                            <i class="fas fa-eye me-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($filter_exam): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-4 p-5 text-center">
                    <i class="fas fa-user-clock fa-3x mb-3 opacity-50"></i>
                    <h5>Belum ada data nilai.</h5>
                    <p class="mb-0">Mungkin ujian masih berlangsung atau belum ada siswa yang menyelesaikan ujian.</p>
                </div>
            <?php else: ?>
                <div class="text-center p-5 mt-5">
                    <i class="fas fa-filter fa-3x text-light mb-3"></i>
                    <h5 class="text-muted">Silakan pilih tanggal dan pelaksanaan ujian.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#tableNilai')) {
            $('#tableNilai').DataTable().destroy();
        }
        $('#tableNilai').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json" },
            "pageLength": 50
        });
    });
</script>
</body>
</html>