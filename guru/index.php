<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Statistik
$total_bank  = $pdo->prepare("SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = ?");
$total_bank->execute([$teacher_id]);
$stat_bank = $total_bank->fetchColumn();

$total_soal_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_questions q JOIN cbt_bank_soal b ON q.bank_soal_id = b.id WHERE b.teacher_id = ?");
$total_soal_stmt->execute([$teacher_id]);
$stat_soal = $total_soal_stmt->fetchColumn();

$total_ujian_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ?");
$total_ujian_stmt->execute([$teacher_id]);
$stat_ujian = $total_ujian_stmt->fetchColumn();

$aktif_ujian_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ? AND status = 'aktif'");
$aktif_ujian_stmt->execute([$teacher_id]);
$stat_aktif = $aktif_ujian_stmt->fetchColumn();

// Bank soal terbaru
$recent_stmt = $pdo->prepare("SELECT b.*, s.nama_mapel,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id) as total_soal
    FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id
    WHERE b.teacher_id = ? ORDER BY b.created_at DESC LIMIT 5");
$recent_stmt->execute([$teacher_id]);
$recent_banks = $recent_stmt->fetchAll();

// Ujian aktif / mendatang
$upcoming_stmt = $pdo->prepare("SELECT e.*, s.nama_mapel FROM cbt_exams e JOIN cbt_subjects s ON e.subject_id = s.id
    WHERE e.teacher_id = ? AND e.status IN ('draft','aktif') ORDER BY e.mulai_pada ASC LIMIT 5");
$upcoming_stmt->execute([$teacher_id]);
$upcoming_exams = $upcoming_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../includes/header.php'; ?>
<style>
    body { font-family: 'Poppins', sans-serif; }
    .stat-card { border: none; border-radius: 12px; }
    #content { overflow-x: hidden; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Dashboard Guru</h5>
                    <small class="text-muted">Selamat datang, <?= htmlspecialchars($_SESSION['nama'] ?? '') ?></small>
                </div>
            </div>
            <span class="text-muted small d-none d-md-block"><i class="far fa-calendar me-1"></i><?= date('d F Y') ?></span>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-3 border-start border-primary border-4">
                        <small class="text-muted fw-bold text-uppercase" style="font-size:.7rem;">Bank Soal</small>
                        <h3 class="fw-bold mb-0 text-primary"><?= $stat_bank ?></h3>
                        <small class="text-muted">Paket soal</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-3 border-start border-success border-4">
                        <small class="text-muted fw-bold text-uppercase" style="font-size:.7rem;">Total Soal</small>
                        <h3 class="fw-bold mb-0 text-success"><?= $stat_soal ?></h3>
                        <small class="text-muted">Butir soal</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-3 border-start border-warning border-4">
                        <small class="text-muted fw-bold text-uppercase" style="font-size:.7rem;">Ujian Dibuat</small>
                        <h3 class="fw-bold mb-0 text-warning"><?= $stat_ujian ?></h3>
                        <small class="text-muted">Total ujian</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-3 border-start border-danger border-4">
                        <small class="text-muted fw-bold text-uppercase" style="font-size:.7rem;">Ujian Aktif</small>
                        <h3 class="fw-bold mb-0 text-danger"><?= $stat_aktif ?></h3>
                        <small class="text-muted">Sedang berjalan</small>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Bank Soal Terbaru -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-book-open me-2 text-primary"></i> Bank Soal Terbaru</h6>
                            <a href="<?= esc(BASE_URL) ?>guru/bank-soal/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Bank Soal</th>
                                        <th>Mapel</th>
                                        <th class="text-center">Soal</th>
                                        <th class="text-center">Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if(empty($recent_banks)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada bank soal.</td></tr>
                                <?php else: foreach($recent_banks as $b): ?>
                                    <tr>
                                        <td class="fw-bold small"><?= htmlspecialchars($b['nama_bank_soal']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($b['nama_mapel']) ?></td>
                                        <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle"><?= $b['total_soal'] ?></span></td>
                                        <td class="text-center">
                                            <?php if($b['status']=='aktif'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Terbuka</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fas fa-lock me-1"></i>Terkunci</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="<?= esc(BASE_URL) ?>guru/bank-soal/detail.php?id=<?= esc($b['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-door-open"></i></a></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Ujian Mendatang -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2 text-warning"></i> Ujian Aktif / Draft</h6>
                            <a href="<?= esc(BASE_URL) ?>guru/jadwal/index.php" class="btn btn-sm btn-outline-warning">Lihat Semua</a>
                        </div>
                        <div class="list-group list-group-flush">
                        <?php if(empty($upcoming_exams)): ?>
                            <div class="list-group-item text-center text-muted py-4">Tidak ada ujian aktif.</div>
                        <?php else: foreach($upcoming_exams as $e): ?>
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold small"><?= htmlspecialchars($e['nama_mapel_ujian']) ?></div>
                                        <small class="text-muted"><?= $e['mulai_pada'] ? date('d M Y H:i', strtotime($e['mulai_pada'])) : 'Belum dijadwalkan' ?></small>
                                    </div>
                                    <span class="badge <?= $e['status']=='aktif'?'bg-success':'bg-secondary' ?> ms-2">
                                        <?= strtoupper($e['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>
