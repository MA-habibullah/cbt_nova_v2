<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = $_SESSION['teacher_id'];
$id_bank    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil bank soal — harus milik guru ini
$stmt = $pdo->prepare("SELECT b.*, s.nama_mapel, s.kode_mapel FROM cbt_bank_soal b
    JOIN cbt_subjects s ON b.subject_id = s.id
    WHERE b.id = ? AND b.teacher_id = ?");
$stmt->execute([$id_bank, $teacher_id]);
$bank = $stmt->fetch();

if (!$bank) { header("Location: index.php"); exit; }

$locked    = ($bank['status'] === 'nonaktif');
$jml_soal  = $pdo->prepare("SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = ?");
$jml_soal->execute([$id_bank]);
$total_soal = $jml_soal->fetchColumn();

$jml_ujian_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE bank_soal_id = ? AND teacher_id = ?");
$jml_ujian_stmt->execute([$id_bank, $teacher_id]);
$total_ujian = $jml_ujian_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<style>
    .menu-card { transition: all .3s ease; border: none; border-radius: 15px; overflow: hidden; }
    .menu-card:hover { transform: translateY(-8px); box-shadow: 0 12px 24px rgba(0,0,0,.1); }
    .menu-card.locked { opacity: .55; pointer-events: none; }
    .icon-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin-bottom: 16px; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($bank['nama_bank_soal']) ?></h5>
                    <small class="text-muted"><?= htmlspecialchars($bank['kode_mapel']) ?> | <?= htmlspecialchars($bank['nama_mapel']) ?></small>
                </div>
                <?php if($locked): ?>
                    <span class="badge bg-danger ms-3 py-2 px-3"><i class="fas fa-lock me-1"></i> Dikunci Admin</span>
                <?php endif; ?>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if($locked): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-lock fa-2x me-3"></i>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Admin telah mengunci bank soal ini. Fitur tambah, edit, dan upload soal tidak dapat digunakan sementara.
                    Silakan hubungi admin untuk membuka kunci.
                </div>
            </div>
            <?php endif; ?>

            <!-- Info Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-primary border-4">
                        <small class="text-muted fw-bold" style="font-size:.7rem;">TOTAL SOAL</small>
                        <h3 class="fw-bold mb-0 text-primary"><?= $total_soal ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-warning border-4">
                        <small class="text-muted fw-bold" style="font-size:.7rem;">TOTAL UJIAN</small>
                        <h3 class="fw-bold mb-0 text-warning"><?= $total_ujian ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-<?= $locked?'danger':'success' ?> border-4">
                        <small class="text-muted fw-bold" style="font-size:.7rem;">STATUS BANK</small>
                        <h5 class="fw-bold mb-0 text-<?= $locked?'danger':'success' ?>"><?= $locked?'Terkunci':'Terbuka' ?></h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-info border-4">
                        <small class="text-muted fw-bold" style="font-size:.7rem;">DIBUAT</small>
                        <h6 class="fw-bold mb-0 text-info"><?= date('d M Y', strtotime($bank['created_at'])) ?></h6>
                    </div>
                </div>
            </div>

            <!-- Menu Cards -->
            <div class="row g-4">
                <!-- Data Soal -->
                <div class="col-xl-3 col-md-6">
                    <a href="soal.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-primary border-4 <?= $locked?'locked':'' ?>">
                            <div class="card-body p-4">
                                <div class="icon-box bg-primary-subtle text-primary"><i class="fas fa-file-lines fa-2x"></i></div>
                                <h5 class="fw-bold text-dark">Data Soal</h5>
                                <p class="text-muted small mb-0">Tambah, edit, hapus butir soal (PG, Essay, dll)</p>
                                <div class="mt-3 badge bg-primary"><?= $total_soal ?> Tersedia</div>
                                <?php if($locked): ?><div class="mt-1 badge bg-danger"><i class="fas fa-lock me-1"></i>Terkunci</div><?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Upload Soal -->
                <div class="col-xl-3 col-md-6">
                    <a href="upload.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-success border-4 <?= $locked?'locked':'' ?>">
                            <div class="card-body p-4">
                                <div class="icon-box bg-success-subtle text-success"><i class="fas fa-file-import fa-2x"></i></div>
                                <h5 class="fw-bold text-dark">Upload Soal</h5>
                                <p class="text-muted small mb-0">Import soal cepat via Excel (.xlsx) atau Word (.docx)</p>
                                <?php if($locked): ?><div class="mt-3 badge bg-danger"><i class="fas fa-lock me-1"></i>Terkunci</div><?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Backup & Restore -->
                <div class="col-xl-3 col-md-6">
                    <a href="backup.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-warning border-4 <?= $locked?'locked':'' ?>">
                            <div class="card-body p-4">
                                <div class="icon-box bg-warning-subtle text-warning"><i class="fas fa-database fa-2x"></i></div>
                                <h5 class="fw-bold text-dark">Backup & Restore</h5>
                                <p class="text-muted small mb-0">Download atau pulihkan paket soal (.zip)</p>
                                <?php if($locked): ?><div class="mt-3 badge bg-danger"><i class="fas fa-lock me-1"></i>Terkunci</div><?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Jadwal Ujian / Buat Ujian -->
                <div class="col-xl-3 col-md-6">
                    <a href="test.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-danger border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-danger-subtle text-danger"><i class="fas fa-calendar-plus fa-2x"></i></div>
                                <h5 class="fw-bold text-dark">Buat Ujian / Test</h5>
                                <p class="text-muted small mb-0">Atur durasi, konfigurasi soal, dan peserta siswa</p>
                                <div class="mt-3 badge bg-danger"><?= $total_ujian ?> Ujian</div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Hasil Test -->
                <div class="col-xl-3 col-md-6">
                    <a href="hasil.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-success border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-success-subtle text-success"><i class="fas fa-chart-bar fa-2x"></i></div>
                                <h5 class="fw-bold text-dark">Hasil Test</h5>
                                <p class="text-muted small mb-0">Rekapitulasi nilai & lembar jawaban siswa</p>
                                <div class="mt-3 badge bg-success"><?= $total_ujian ?> Ujian</div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Daftar Hadir & Berita Acara -->
                <div class="col-xl-3 col-md-6">
                    <a href="cetak/kehadiran.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-secondary border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-secondary-subtle text-secondary"><i class="fas fa-clipboard-list fa-2x"></i></div>
                                <h5 class="fw-bold text-dark">Daftar Hadir & Berita Acara</h5>
                                <p class="text-muted small mb-0">Cetak daftar hadir peserta & berita acara ujian</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e){e.preventDefault();$("#wrapper").toggleClass("toggled");});
</script>
</body>
</html>
