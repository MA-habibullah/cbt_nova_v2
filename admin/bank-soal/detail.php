<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Ambil ID Bank Soal dari URL
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil Data Detail Bank Soal & Mapel
$stmt = $pdo->prepare("SELECT b.*, s.nama_mapel, s.kode_mapel 
                       FROM cbt_bank_soal b 
                       JOIN cbt_subjects s ON b.subject_id = s.id 
                       WHERE b.id = ?");
$stmt->execute([$id_bank]);
$bank = $stmt->fetch();

// Jika data tidak ditemukan, kembalikan ke halaman utama
if (!$bank) {
    header("Location: index.php");
    exit;
}

// Question count with prepared statement
$stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = ?");
$stmt_cnt->execute([$id_bank]);
$jml_soal = (int)$stmt_cnt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <style>
        .menu-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .icon-box {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 20px;
        }
        .hover-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(0,0,0,0.1);
        }
    </style>
<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h5 class="mb-0 fw-bold"><?= $bank['nama_bank_soal'] ?></h5>
                    <small class="text-muted"><?= $bank['kode_mapel'] ?> | <?= $bank['nama_mapel'] ?></small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            
            <?php if ($bank['status'] == 'nonaktif'): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-lock fa-2x me-3"></i>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Anda tidak dapat menambah atau mengedit soal karena status bank soal dinonaktifkan.
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-xl-3 col-md-6">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/soal.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-primary border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-primary-subtle text-primary">
                                    <i class="fas fa-file-lines fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Data Soal</h5>
                                <p class="text-muted small mb-0">Kelola 6 tipe soal (PG, Kompleks, Menjodohkan, dll)</p>
                                <div class="mt-3 badge bg-primary"><?= $jml_soal ?> Tersedia</div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="upload.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-success border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-success-subtle text-success">
                                    <i class="fas fa-file-import fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Upload Soal</h5>
                                <p class="text-muted small mb-0">Import soal cepat via file Excel (.xlsx) atau Word (.docx)</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/backup/bank-soal-backup.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-warning border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-warning-subtle text-warning">
                                    <i class="fas fa-database fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Backup & Restore</h5>
                                <p class="text-muted small mb-0">Amankan data atau pindahkan bank soal antar server</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="test.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-start border-danger border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-danger-subtle text-danger">
                                    <i class="fas fa-calendar-plus fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Buat Ujian / Test</h5>
                                <p class="text-muted small mb-0">Atur durasi, acak soal, dan jadwalkan ujian siswa</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <!--<a class="collapse-item" href="<?= esc(BASE_URL) ?>admin/tahun-ajaran.php">Tahun Ajaran</a>-->
                    <a class="collapse-item" href="<?= esc(BASE_URL) ?>admin/hasil/index.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm">
                            <div class="card-body p-4">
                                <div class="icon-box bg-info-subtle text-info">
                                    <i class="fas fa-poll-h fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Hasil Test</h5>
                                <p class="text-muted small mb-0">Lihat skor siswa, download Excel dan laporan PDF</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- <div class="col-xl-3 col-md-6">
                    <a href="<?= esc(BASE_URL) ?>admin/hasil/ajax/analisis-soal.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm">
                            <div class="card-body p-4">
                                <div class="icon-box bg-secondary-subtle text-secondary">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Analisa Soal</h5>
                                <p class="text-muted small mb-0">Analisis tingkat kesukaran dan daya pembeda soal</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="<?= esc(BASE_URL) ?>admin/hasil/ajax/analisis-jawaban.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm">
                            <div class="card-body p-4">
                                <div class="icon-box bg-dark-subtle text-dark">
                                    <i class="fas fa-list-check fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Analisa Jawaban</h5>
                                <p class="text-muted small mb-0">Detail jawaban setiap siswa per nomor soal</p>
                            </div>
                        </div>
                    </a>
                </div> -->

                <div class="col-xl-3 col-md-6">
                    <a href="<?= esc(BASE_URL) ?>admin/hasil/cetak/cetak-kehadiran.php?id=<?= esc($id_bank) ?>" class="text-decoration-none">
                        <div class="card h-100 menu-card shadow-sm border-bottom border-dark border-4">
                            <div class="card-body p-4">
                                <div class="icon-box bg-light border text-dark">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Daftar Hadir</h5>
                                <p class="text-muted small mb-0">Cetak Daftar Hadir dan Berita Acara</p>
                            </div>
                        </div>
                    </a>
                </div>

            </div> </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>