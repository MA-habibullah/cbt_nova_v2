<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$teacher_id = (int)$_SESSION['teacher_id'];
date_default_timezone_set('Asia/Jakarta');
$current_hour = (int)date('H');

// Salam waktu
if ($current_hour >= 4 && $current_hour < 11) {
    $greeting = "Selamat Pagi";
    $greeting_icon = "fa-cloud-sun text-warning";
} elseif ($current_hour >= 11 && $current_hour < 15) {
    $greeting = "Selamat Siang";
    $greeting_icon = "fa-sun text-warning";
} elseif ($current_hour >= 15 && $current_hour < 18) {
    $greeting = "Selamat Sore";
    $greeting_icon = "fa-cloud-sun text-warning";
} else {
    $greeting = "Selamat Malam";
    $greeting_icon = "fa-moon text-primary";
}

// 1. Statistik Guru
$total_bank = $pdo->prepare("SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = ?");
$total_bank->execute([$teacher_id]);
$stat_bank = (int)$total_bank->fetchColumn();

$total_soal_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_questions q JOIN cbt_bank_soal b ON q.bank_soal_id = b.id WHERE b.teacher_id = ?");
$total_soal_stmt->execute([$teacher_id]);
$stat_soal = (int)$total_soal_stmt->fetchColumn();

$total_ujian_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ?");
$total_ujian_stmt->execute([$teacher_id]);
$stat_ujian = (int)$total_ujian_stmt->fetchColumn();

$aktif_ujian_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ? AND status = 'aktif'");
$aktif_ujian_stmt->execute([$teacher_id]);
$stat_aktif = (int)$aktif_ujian_stmt->fetchColumn();

// 2. Bank Soal Terbaru
$recent_stmt = $pdo->prepare("
    SELECT b.*, s.nama_mapel,
        (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id) as total_soal
    FROM cbt_bank_soal b 
    JOIN cbt_subjects s ON b.subject_id = s.id
    WHERE b.teacher_id = ? 
    ORDER BY b.created_at DESC 
    LIMIT 5
");
$recent_stmt->execute([$teacher_id]);
$recent_banks = $recent_stmt->fetchAll();

// 3. Ujian Aktif / Mendatang / Terkini
$upcoming_stmt = $pdo->prepare("
    SELECT e.*, s.nama_mapel,
        (SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = e.id) as total_peserta,
        (SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = e.id AND status = 'working') as peserta_aktif,
        (SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = e.id AND status = 'finished') as peserta_selesai
    FROM cbt_exams e 
    JOIN cbt_subjects s ON e.subject_id = s.id
    WHERE e.teacher_id = ? 
    ORDER BY (e.status = 'aktif') DESC, e.mulai_pada DESC, e.created_at DESC 
    LIMIT 5
");
$upcoming_stmt->execute([$teacher_id]);
$upcoming_exams = $upcoming_stmt->fetchAll();

// 4. Ringkasan Hasil Nilai Ujian Terbaru
$results_stmt = $pdo->prepare("
    SELECT e.id as exam_id, e.nama_mapel_ujian, s.nama_mapel,
        COUNT(p.id) as total_selesai,
        AVG(COALESCE(p.skor_akhir, p.nilai_objektif, 0)) as rata_rata,
        MAX(COALESCE(p.skor_akhir, p.nilai_objektif, 0)) as nilai_max,
        MIN(COALESCE(p.skor_akhir, p.nilai_objektif, 0)) as nilai_min
    FROM cbt_exams e
    JOIN cbt_subjects s ON e.subject_id = s.id
    JOIN cbt_exam_participants p ON e.id = p.exam_id
    WHERE e.teacher_id = ? AND p.status = 'finished'
    GROUP BY e.id, e.nama_mapel_ujian, s.nama_mapel
    ORDER BY e.id DESC
    LIMIT 4
");

$results_stmt->execute([$teacher_id]);
$recent_results = $results_stmt->fetchAll();

// Info Sekolah
$setting_stmt = $pdo->query("SELECT nama_sekolah FROM cbt_settings LIMIT 1");
$setting_row  = $setting_stmt ? $setting_stmt->fetch() : null;
$nama_sekolah = $setting_row['nama_sekolah'] ?? 'CBT Nova';
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../includes/header.php'; ?>
<style>
    :root {
        --card-radius: 16px;
    }
    body { 
        font-family: 'Poppins', sans-serif; 
        background-color: #f4f6f9;
        color: #334155;
    }
    .hero-teacher-banner {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #3b82f6 100%);
        border-radius: var(--card-radius);
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .hero-teacher-banner::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-glass-box {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.28);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: 12px;
        padding: 0.65rem 1rem;
        display: inline-block;
    }
    .hero-badge-glass {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #ffffff;
        font-weight: 500;
    }
    .dash-card {
        border: 1px solid rgba(0,0,0,0.04);
        border-radius: var(--card-radius);
        background: #ffffff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dash-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.06);
    }
    .stat-icon-wrapper {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }
    .quick-action-btn {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #334155;
        border-radius: 12px;
        padding: 0.65rem 1rem;
        font-weight: 500;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .quick-action-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .live-dot {
        width: 9px;
        height: 9px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse-green 2s infinite;
    }
    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
</style>

<body>
<div class="d-flex" id="wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border shadow-sm me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.05rem;">Dashboard Guru</h5>
                    <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($nama_sekolah) ?></small>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold text-dark small"><?= htmlspecialchars($_SESSION['nama'] ?? 'Guru Pengajar') ?></div>
                    <small class="text-muted" style="font-size: 0.72rem;">Pengajar CBT</small>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= esc(urlencode($_SESSION['nama'] ?? 'Guru')) ?>&background=2563eb&color=fff&bold=true" class="rounded-circle shadow-sm border" width="38" height="38" alt="Avatar">
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Hero Welcome Card -->
            <div class="hero-teacher-banner p-4 mb-4 shadow-sm">
                <div class="row align-items-center position-relative" style="z-index: 1;">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge hero-badge-glass px-3 py-1 rounded-pill">
                                <i class="fas <?= $greeting_icon ?> me-1"></i> <?= $greeting ?>
                            </span>
                            <span class="badge hero-badge-glass px-2.5 py-1 rounded-pill">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Panel Pengajar
                            </span>
                        </div>
                        <h4 class="fw-bold mb-1 text-white">Selamat Datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Bapak/Ibu Guru') ?></h4>
                        <p class="mb-0 small" style="color: rgba(255, 255, 255, 0.85);">
                            Kelola bank soal, publikasikan jadwal ujian, dan pantau hasil evaluasi belajar siswa dengan cepat dan akurat.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <div class="hero-glass-box text-start">
                            <div style="color: rgba(255, 255, 255, 0.75); font-size: 0.72rem; font-weight: 500;">Hari &amp; Tanggal</div>
                            <div class="fw-bold text-white" id="live-time" style="font-size: 1.05rem; letter-spacing: 0.5px;">--:--:-- WIB</div>
                            <div style="color: rgba(255, 255, 255, 0.75); font-size: 0.75rem;" id="live-date">--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Ribbon -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="<?= esc(BASE_URL) ?>guru/bank-soal/index.php" class="quick-action-btn">
                    <i class="fas fa-folder-plus text-primary me-2"></i> Kelola Bank Soal
                </a>
                <a href="<?= esc(BASE_URL) ?>guru/jadwal/index.php" class="quick-action-btn">
                    <i class="fas fa-calendar-plus text-success me-2"></i> Jadwal Ujian Baru
                </a>
                <a href="<?= esc(BASE_URL) ?>guru/monitoring/index.php" class="quick-action-btn">
                    <i class="fas fa-desktop text-warning me-2"></i> Monitoring Peserta
                </a>
                <a href="<?= esc(BASE_URL) ?>guru/hasil/index.php" class="quick-action-btn">
                    <i class="fas fa-chart-line text-info me-2"></i> Hasil &amp; Rekap Nilai
                </a>
                <a href="<?= esc(BASE_URL) ?>guru/display-publik/index.php" class="quick-action-btn">
                    <i class="fas fa-tv text-purple me-2" style="color:#8b5cf6;"></i> Layar Publik
                </a>
            </div>

            <!-- 4 Stat Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Bank Soal</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($stat_bank) ?></h3>
                                <small class="text-primary small fw-medium"><i class="fas fa-layer-group me-1"></i>Paket Soal Anda</small>
                            </div>
                            <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                                <i class="fas fa-book-open"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Total Soal</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($stat_soal) ?></h3>
                                <small class="text-success small fw-medium"><i class="fas fa-check-circle me-1"></i>Butir Pertanyaan</small>
                            </div>
                            <div class="stat-icon-wrapper bg-success-subtle text-success">
                                <i class="fas fa-list-check"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Ujian Dibuat</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($stat_ujian) ?></h3>
                                <small class="text-warning small fw-medium"><i class="fas fa-calendar-check me-1"></i>Riwayat Ujian</small>
                            </div>
                            <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Ujian Aktif</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($stat_aktif) ?></h3>
                                <small class="text-danger small fw-medium">
                                    <?php if ($stat_aktif > 0): ?>
                                        <span class="live-dot me-1" style="background-color:#ef4444;"></span> Sedang Berjalan
                                    <?php else: ?>
                                        <i class="fas fa-pause-circle me-1"></i> Tidak Ada
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="stat-icon-wrapper bg-danger-subtle text-danger">
                                <i class="fas fa-play-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content: Bank Soal & Jadwal Ujian -->
            <div class="row g-4">
                <!-- Left Column (7 cols): Bank Soal Terbaru & Ringkasan Nilai -->
                <div class="col-lg-7">
                    <!-- Bank Soal Card -->
                    <div class="card dash-card mb-4 overflow-hidden">
                        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-book-open me-2 text-primary"></i> Bank Soal Terbaru Anda</h6>
                            <a href="<?= esc(BASE_URL) ?>guru/bank-soal/index.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                Lihat Semua <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">Nama Bank Soal</th>
                                        <th>Mata Pelajaran</th>
                                        <th class="text-center">Jumlah Soal</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if(empty($recent_banks)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-folder-open fa-2x mb-2 text-secondary opacity-25 d-block"></i>
                                            Belum ada bank soal yang dibuat.
                                        </td>
                                    </tr>
                                <?php else: foreach($recent_banks as $b): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold small text-dark"><?= htmlspecialchars($b['nama_bank_soal']) ?></div>
                                            <small class="text-muted"><i class="far fa-clock me-1"></i><?= date('d M Y', strtotime($b['created_at'])) ?></small>
                                        </td>
                                        <td class="small text-muted">
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($b['nama_mapel']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                                <?= (int)$b['total_soal'] ?> Butir
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if($b['status'] == 'aktif'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Terbuka</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill"><i class="fas fa-lock me-1"></i>Terkunci</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= esc(BASE_URL) ?>guru/bank-soal/detail.php?id=<?= esc($b['id']) ?>" class="btn btn-sm btn-outline-primary" title="Buka Bank Soal">
                                                <i class="fas fa-folder-open"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Ringkasan Hasil Nilai Terkini -->
                    <div class="card dash-card overflow-hidden">
                        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-award me-2 text-warning"></i> Ringkasan Nilai Ujian Terkini</h6>
                            <a href="<?= esc(BASE_URL) ?>guru/hasil/index.php" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                                Rekap Lengkap <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">Nama Ujian</th>
                                        <th class="text-center">Peserta Selesai</th>
                                        <th class="text-center">Rata-Rata</th>
                                        <th class="text-center">Min / Max</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if(empty($recent_results)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-clipboard-check fa-2x mb-2 text-secondary opacity-25 d-block"></i>
                                            Belum ada hasil ujian yang selesai dikerjakan siswa.
                                        </td>
                                    </tr>
                                <?php else: foreach($recent_results as $res): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold small text-dark"><?= htmlspecialchars($res['nama_mapel_ujian']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($res['nama_mapel']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                                <?= (int)$res['total_selesai'] ?> Siswa
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold text-dark">
                                            <?= number_format((float)$res['rata_rata'], 1) ?>
                                        </td>
                                        <td class="text-center small text-muted">
                                            <?= number_format((float)$res['nilai_min'], 0) ?> / <?= number_format((float)$res['nilai_max'], 0) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= esc(BASE_URL) ?>guru/hasil/index.php?exam_id=<?= esc($res['exam_id']) ?>" class="btn btn-sm btn-outline-warning" title="Lihat Nilai">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column (5 cols): Jadwal Ujian & Pusat Panduan -->
                <div class="col-lg-5">
                    <!-- Jadwal Ujian Card -->
                    <div class="card dash-card mb-4 overflow-hidden">
                        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-success"></i> Jadwal Ujian Aktif &amp; Terkini</h6>
                            <a href="<?= esc(BASE_URL) ?>guru/jadwal/index.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                Kelola Jadwal <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="list-group list-group-flush">
                        <?php if(empty($upcoming_exams)): ?>
                            <div class="list-group-item text-center text-muted py-5">
                                <i class="fas fa-calendar-times fa-3x mb-2 text-secondary opacity-25 d-block"></i>
                                Tidak ada jadwal ujian aktif atau tersimpan.
                            </div>
                        <?php else: foreach($upcoming_exams as $e): ?>
                            <div class="list-group-item px-4 py-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <div class="fw-bold small text-dark mb-0"><?= htmlspecialchars($e['nama_mapel_ujian']) ?></div>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="far fa-clock me-1"></i>
                                            <?= $e['mulai_pada'] ? date('d M Y, H:i', strtotime($e['mulai_pada'])) : 'Belum dijadwalkan' ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($e['status'] === 'aktif'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                <span class="live-dot me-1"></span> AKTIF
                                            </span>
                                        <?php elseif ($e['status'] === 'draft'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">DRAFT</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">SELESAI</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-light">
                                    <small class="text-muted" style="font-size: 0.72rem;">
                                        Peserta: <strong class="text-dark"><?= (int)$e['peserta_selesai'] ?>/<?= (int)$e['total_peserta'] ?></strong> Selesai
                                        <?php if ((int)$e['peserta_aktif'] > 0): ?>
                                            &bull; <span class="text-success fw-bold"><?= (int)$e['peserta_aktif'] ?> Mengerjakan</span>
                                        <?php endif; ?>
                                    </small>
                                    <div class="d-flex gap-1">
                                        <a href="<?= esc(BASE_URL) ?>guru/monitoring/index.php?exam_id=<?= esc($e['id']) ?>" class="btn btn-xs btn-outline-primary py-0.5 px-2 rounded-pill" style="font-size: 0.72rem;">
                                            <i class="fas fa-desktop me-1"></i> Pantau
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Panduan Cepat Card -->
                    <div class="card dash-card p-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="stat-icon-wrapper bg-primary-subtle text-primary" style="width:40px;height:40px;font-size:1.1rem;">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark m-0">Tips &amp; Panduan Praktis CBT</h6>
                                <small class="text-muted">Kemudahan membuat soal &amp; ujian</small>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-2.5 small text-muted">
                            <div class="p-2.5 bg-light rounded-3 border">
                                <span class="fw-bold text-dark d-block mb-0.5"><i class="fas fa-file-word text-primary me-1"></i> Import Soal Cepat</span>
                                Gunakan template Microsoft Word (.docx) atau Excel (.xlsx) untuk mengunggah puluhan soal sekaligus lengkap dengan gambar dan kunci jawaban.
                            </div>
                            <div class="p-2.5 bg-light rounded-3 border">
                                <span class="fw-bold text-dark d-block mb-0.5"><i class="fas fa-square-root-variable text-success me-1"></i> Rumus Matematika (LaTeX)</span>
                                Tuliskan rumus matematika atau sains di antara tanda <code class="text-primary">$...$</code> (misal: <code class="text-primary">$\sqrt{x^2+y^2}$</code>) agar dirender secara otomatis.
                            </div>
                            <div class="p-2.5 bg-light rounded-3 border">
                                <span class="fw-bold text-dark d-block mb-0.5"><i class="fas fa-file-excel text-success me-1"></i> Rekap Nilai Instan</span>
                                Nilai siswa otomatis terkalkulasi saat selesai ujian. Anda bisa langsung mengunduh hasil dalam format Excel &amp; PDF di menu Hasil Test.
                            </div>
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
$(document).ready(function() {
    $("#menu-toggle").click(function(e) { 
        e.preventDefault(); 
        $("#wrapper").toggleClass("toggled"); 
    });

    // Live Jam & Tanggal
    function updateClock() {
        const now = new Date();
        const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const elDate = document.getElementById('live-date');
        const elTime = document.getElementById('live-time');
        if (elDate) elDate.innerText = now.toLocaleDateString('id-ID', opts);
        if (elTime) elTime.innerText = now.toLocaleTimeString('id-ID') + ' WIB';
    }
    setInterval(updateClock, 1000);
    updateClock();
});
</script>
</body>
</html>
