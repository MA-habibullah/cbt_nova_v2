<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

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
$total_soal = (int)$jml_soal->fetchColumn();

$jml_ujian_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE bank_soal_id = ? AND teacher_id = ?");
$jml_ujian_stmt->execute([$id_bank, $teacher_id]);
$total_ujian = (int)$jml_ujian_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<style>
    .menu-action-card {
        transition: all 0.25s ease-in-out;
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        border-radius: 1rem;
        background: #ffffff;
    }
    .menu-action-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.04) !important;
        border-color: rgba(148, 163, 184, 0.4) !important;
    }
    .menu-action-card.locked {
        opacity: 0.6;
        pointer-events: none;
    }
    .icon-box-modern {
        width: 58px;
        height: 58px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        font-size: 1.5rem;
    }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/guru/includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark"><?= esc($bank['nama_bank_soal']) ?></h5>
                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= esc($bank['kode_bank_soal']) ?></span>
                        </div>
                        <small class="text-muted"><?= esc($bank['kode_mapel']) ?> &bull; <?= esc($bank['nama_mapel']) ?> <?= !empty($bank['jenjang']) ? '&bull; Kelas ' . esc($bank['jenjang']) : '' ?></small>
                    </div>
                </div>
                <div>
                    <?php if($locked): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2 fw-semibold">
                            <i class="fas fa-lock me-1"></i> Terkunci Admin
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 fw-semibold">
                            <i class="fas fa-circle me-1 small"></i> Terbuka
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if($locked): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center p-3 mb-4">
                <div class="fs-3 text-danger me-3"><i class="fas fa-lock"></i></div>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Administrator telah menonaktifkan bank soal ini sementara. Fitur kelola dan upload butir soal dibekukan hingga dibuka kembali oleh admin.
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Metric Overview -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-secondary small fw-semibold text-uppercase">Total Butir Soal</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?= $total_soal ?></h3>
                            </div>
                            <div class="p-3 bg-primary-subtle text-primary rounded-3">
                                <i class="fas fa-file-lines fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-secondary small fw-semibold text-uppercase">Jadwal Ujian Aktif</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?= $total_ujian ?></h3>
                            </div>
                            <div class="p-3 bg-info-subtle text-info rounded-3">
                                <i class="fas fa-calendar-check fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-secondary small fw-semibold text-uppercase">Status Bank Soal</span>
                                <h4 class="fw-bold mb-0 mt-1 <?= $locked ? 'text-danger' : 'text-success' ?>">
                                    <?= $locked ? 'Terkunci' : 'Aktif / Terbuka' ?>
                                </h4>
                            </div>
                            <div class="p-3 <?= $locked ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> rounded-3">
                                <i class="fas <?= $locked ? 'fa-lock' : 'fa-check' ?> fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-secondary small fw-semibold text-uppercase">Dibuat Pada</span>
                                <h5 class="fw-bold mb-0 text-dark mt-1"><?= date('d M Y', strtotime($bank['created_at'])) ?></h5>
                            </div>
                            <div class="p-3 bg-warning-subtle text-warning rounded-3">
                                <i class="fas fa-clock fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Cards Grid -->
            <div class="row g-4">
                <!-- Data Soal -->
                <div class="col-xl-4 col-md-6">
                    <a href="soal.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-action-card shadow-sm p-3 <?= $locked ? 'locked' : '' ?>">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="icon-box-modern bg-primary-subtle text-primary mb-3"><i class="fas fa-file-lines"></i></div>
                                    <h5 class="fw-bold text-dark mb-1">Kelola Butir Soal</h5>
                                    <p class="text-secondary small mb-3">Tambah, edit, dan hapus butir soal (PG, Kompleks, Menjodohkan, Esai) & KaTeX.</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-layer-group me-1"></i> <?= $total_soal ?> Butir Tersedia
                                    </span>
                                    <span class="text-primary small fw-semibold">Buka Soal <i class="fas fa-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Upload Soal -->
                <div class="col-xl-4 col-md-6">
                    <a href="upload.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-action-card shadow-sm p-3 <?= $locked ? 'locked' : '' ?>">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="icon-box-modern bg-success-subtle text-success mb-3"><i class="fas fa-file-arrow-up"></i></div>
                                    <h5 class="fw-bold text-dark mb-1">Upload Soal (Excel/Word)</h5>
                                    <p class="text-secondary small mb-3">Import massal cepat via file spreadsheet Excel (.xlsx) atau Word (.docx).</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-file-excel me-1"></i> Import Cepat
                                    </span>
                                    <span class="text-success small fw-semibold">Upload File <i class="fas fa-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Backup & Restore -->
                <div class="col-xl-4 col-md-6">
                    <a href="backup.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-action-card shadow-sm p-3 <?= $locked ? 'locked' : '' ?>">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="icon-box-modern bg-warning-subtle text-warning mb-3"><i class="fas fa-box-archive"></i></div>
                                    <h5 class="fw-bold text-dark mb-1">Backup & Restore (.zip)</h5>
                                    <p class="text-secondary small mb-3">Unduh arsip soal dan gambar dalam format .zip atau pulihkan dari cadangan.</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-shield-halved me-1"></i> Cadangan Data
                                    </span>
                                    <span class="text-warning-emphasis small fw-semibold">Kelola ZIP <i class="fas fa-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Jadwal Ujian / Buat Ujian -->
                <div class="col-xl-4 col-md-6">
                    <a href="test.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-action-card shadow-sm p-3">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="icon-box-modern bg-danger-subtle text-danger mb-3"><i class="fas fa-calendar-plus"></i></div>
                                    <h5 class="fw-bold text-dark mb-1">Jadwal Ujian / Test</h5>
                                    <p class="text-secondary small mb-3">Atur durasi pengerjaan, konfigurasi acak soal, dan peserta kelas binaan.</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-clock me-1"></i> <?= $total_ujian ?> Jadwal
                                    </span>
                                    <span class="text-danger small fw-semibold">Atur Jadwal <i class="fas fa-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Hasil Test -->
                <div class="col-xl-4 col-md-6">
                    <a href="hasil.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-action-card shadow-sm p-3">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="icon-box-modern bg-info-subtle text-info mb-3"><i class="fas fa-chart-column"></i></div>
                                    <h5 class="fw-bold text-dark mb-1">Rekapitulasi Hasil & Nilai</h5>
                                    <p class="text-secondary small mb-3">Rekapitulasi skor siswa, koreksi esai guru, serta ekspor ke Excel dan PDF.</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-file-pdf me-1"></i> Laporan Nilai
                                    </span>
                                    <span class="text-info small fw-semibold">Lihat Rekap <i class="fas fa-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Daftar Hadir & Berita Acara -->
                <div class="col-xl-4 col-md-6">
                    <a href="cetak/kehadiran.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-action-card shadow-sm p-3">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="icon-box-modern bg-secondary-subtle text-secondary mb-3"><i class="fas fa-clipboard-user"></i></div>
                                    <h5 class="fw-bold text-dark mb-1">Daftar Hadir & Berita Acara</h5>
                                    <p class="text-secondary small mb-3">Cetak daftar hadir peserta dan berita acara ujian siap cetak lembar A4.</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-print me-1"></i> Format Cetak A4
                                    </span>
                                    <span class="text-secondary small fw-semibold">Cetak Dokumen <i class="fas fa-arrow-right ms-1"></i></span>
                                </div>
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
