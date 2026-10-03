<?php
// 1. Inisialisasi Database & Session
require_once '../config/database.php';

// 2. Proteksi Halaman Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$today_start = date('Y-m-d') . ' 00:00:00';
$today_end   = date('Y-m-d') . ' 23:59:59';
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

// 3. Statistik Utama
$totalSiswa   = (int)$pdo->query("SELECT COUNT(*) FROM cbt_students WHERE is_aktif = 1")->fetchColumn();
$totalGuru    = (int)$pdo->query("SELECT COUNT(*) FROM cbt_teachers WHERE is_aktif = 1")->fetchColumn();
$ujianAktif   = (int)$pdo->query("SELECT COUNT(*) FROM cbt_exams WHERE status = 'aktif'")->fetchColumn();
$ujianSelesai = (int)$pdo->query("SELECT COUNT(*) FROM cbt_exams WHERE status = 'selesai'")->fetchColumn();
$totalBank    = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal")->fetchColumn();
$totalSoal    = (int)$pdo->query("SELECT COUNT(*) FROM cbt_questions")->fetchColumn();

// 4. Peserta yang sedang mengerjakan ujian hari ini
$stmtOnline = $pdo->prepare("
    SELECT s.nama_lengkap, c.nama_kelas, c.jenjang, e.nama_mapel_ujian, p.waktu_mulai
    FROM cbt_exam_participants p
    JOIN cbt_students s ON p.student_id = s.id
    JOIN cbt_classes c ON s.class_id = c.id
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.status = 'working'
      AND e.mulai_pada BETWEEN ? AND ?
    ORDER BY p.waktu_mulai DESC
    LIMIT 6
");
$stmtOnline->execute([$today_start, $today_end]);
$pesertaOnline = $stmtOnline->fetchAll();

$totalWorkingStmt = $pdo->prepare("
    SELECT COUNT(*) FROM cbt_exam_participants p 
    JOIN cbt_exams e ON p.exam_id = e.id 
    WHERE p.status = 'working' AND e.mulai_pada BETWEEN ? AND ?
");
$totalWorkingStmt->execute([$today_start, $today_end]);
$totalWorking = (int)$totalWorkingStmt->fetchColumn();

// 5. Data Chart: partisipan per jam yang mulai hari ini
$stmtChart = $pdo->prepare("
    SELECT HOUR(p.waktu_mulai) as jam, COUNT(*) as jumlah
    FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE e.mulai_pada BETWEEN ? AND ? AND p.waktu_mulai IS NOT NULL
    GROUP BY HOUR(p.waktu_mulai)
    ORDER BY jam ASC
");
$stmtChart->execute([$today_start, $today_end]);
$chartRaw = $stmtChart->fetchAll();

$chartLabels = [];
$chartData   = [];
$chartMap    = array_column($chartRaw, 'jumlah', 'jam');
for ($h = 6; $h <= 18; $h++) {
    $chartLabels[] = sprintf('%02d:00', $h);
    $chartData[]   = (int)($chartMap[$h] ?? 0);
}

// 6. Ujian Hari Ini / Terkini
$stmtTodayExams = $pdo->prepare("
    SELECT e.id, e.nama_mapel_ujian, e.mulai_pada, e.selesai_pada, e.status, e.durasi_menit,
           COALESCE(t.nama_lengkap, 'Admin') AS nama_guru,
           (SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = e.id) AS total_peserta,
           (SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = e.id AND status = 'finished') AS peserta_selesai,
           (SELECT COUNT(*) FROM cbt_exam_participants WHERE exam_id = e.id AND status = 'working') AS peserta_aktif
    FROM cbt_exams e
    LEFT JOIN cbt_teachers t ON e.teacher_id = t.id
    WHERE (e.mulai_pada BETWEEN ? AND ?) OR e.status = 'aktif'
    ORDER BY (e.status = 'aktif') DESC, e.mulai_pada DESC
    LIMIT 6
");
$stmtTodayExams->execute([$today_start, $today_end]);
$todayExams = $stmtTodayExams->fetchAll();

// 7. Info Sekolah & Disk
$setting      = $pdo->query("SELECT nama_sekolah FROM cbt_settings LIMIT 1")->fetch();
$nama_sekolah = $setting['nama_sekolah'] ?? 'CBT Online';

$disk_free  = @disk_free_space(".");
$disk_total = @disk_total_space(".");
$disk_pct   = ($disk_total && $disk_total > 0) ? round((($disk_total - $disk_free) / $disk_total) * 100, 1) : 0;
$disk_used_gb = ($disk_total && $disk_free) ? round(($disk_total - $disk_free) / 1073741824, 1) : 0;
$disk_total_gb = ($disk_total) ? round($disk_total / 1073741824, 1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
    <?php include '../includes/header.php'; ?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        --card-radius: 16px;
    }
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f4f6f9;
        color: #334155;
    }
    .hero-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
        border-radius: var(--card-radius);
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-glass-box {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: 12px;
        padding: 0.65rem 1rem;
        display: inline-block;
    }
    .hero-badge-glass {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #ffffff;
        font-weight: 500;
    }
    .hero-badge-online {
        background: rgba(16, 185, 129, 0.25);
        border: 1px solid rgba(52, 211, 153, 0.4);
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
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
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
        color: #0d6efd;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .avatar-initial {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
        color: #ffffff;
    }
</style>

<body>

<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light shadow-sm border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>

            <div class="d-none d-lg-block">
                <div class="fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                    <i class="fas fa-tachometer-alt text-primary"></i> Administrator Dashboard
                </div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></div>
                    <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($nama_sekolah) ?></small>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= esc(urlencode($_SESSION['nama'] ?? 'Admin')) ?>&background=1e3c72&color=fff&bold=true" class="rounded-circle shadow-sm border" width="38" height="38" alt="Avatar">
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Hero Welcome Card -->
            <div class="hero-banner p-4 p-md-4 mb-4 shadow-sm">
                <div class="row align-items-center position-relative" style="z-index: 1;">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge hero-badge-glass px-3 py-1 rounded-pill">
                                <i class="fas <?= $greeting_icon ?> me-1"></i> <?= $greeting ?>
                            </span>
                            <span class="badge hero-badge-online px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1">
                                <span class="live-dot" style="background-color:#4ade80;"></span> Server Online
                            </span>
                        </div>
                        <h4 class="fw-bold mb-1 text-white">Selamat Datang di Portal CBT Nova</h4>
                        <p class="mb-0 small" style="color: rgba(255, 255, 255, 0.85);">
                            Kelola jadwal ujian, bank soal, dan pantau aktivitas peserta ujian secara real-time untuk <strong><?= htmlspecialchars($nama_sekolah) ?></strong>.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <div class="hero-glass-box text-start">
                            <div style="color: rgba(255, 255, 255, 0.75); font-size: 0.72rem; font-weight: 500;">Waktu Server (WIB)</div>
                            <div class="fw-bold text-white" id="live-clock" style="font-size: 1.05rem; letter-spacing: 0.5px;">--:--:--</div>
                            <div style="color: rgba(255, 255, 255, 0.75); font-size: 0.75rem;" id="live-date">--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Ribbon -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="<?= esc(BASE_URL) ?>admin/jadwal/index.php" class="quick-action-btn">
                    <i class="fas fa-calendar-plus text-primary me-2"></i> Jadwal Ujian
                </a>
                <a href="<?= esc(BASE_URL) ?>admin/monitoring/index.php" class="quick-action-btn">
                    <i class="fas fa-desktop text-success me-2"></i> Live Monitoring
                </a>
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/index.php" class="quick-action-btn">
                    <i class="fas fa-folder-open text-warning me-2"></i> Bank Soal (<?= number_format($totalBank) ?>)
                </a>
                <a href="<?= esc(BASE_URL) ?>admin/master/siswa.php" class="quick-action-btn">
                    <i class="fas fa-user-graduate text-info me-2"></i> Data Siswa
                </a>
                <a href="<?= esc(BASE_URL) ?>admin/master/guru.php" class="quick-action-btn">
                    <i class="fas fa-chalkboard-teacher text-purple me-2" style="color: #8b5cf6;"></i> Data Guru
                </a>
                <a href="<?= esc(BASE_URL) ?>admin/sistem/system-info.php" class="quick-action-btn">
                    <i class="fas fa-server text-danger me-2"></i> Kesehatan Server
                </a>
            </div>

            <!-- 4 Stat Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Siswa Aktif</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalSiswa) ?></h3>
                                <small class="text-success small fw-medium"><i class="fas fa-check-circle me-1"></i>Terdaftar Aktif</small>
                            </div>
                            <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Guru Aktif</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalGuru) ?></h3>
                                <small class="text-success small fw-medium"><i class="fas fa-chalkboard me-1"></i>Pengajar CBT</small>
                            </div>
                            <div class="stat-icon-wrapper bg-success-subtle text-success">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Ujian Aktif</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($ujianAktif) ?></h3>
                                <small class="text-warning small fw-medium"><i class="fas fa-clock me-1"></i>Sedang Berjalan</small>
                            </div>
                            <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                                <i class="fas fa-file-signature"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="card dash-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Ujian Selesai</small>
                                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($ujianSelesai) ?></h3>
                                <small class="text-info small fw-medium"><i class="fas fa-database me-1"></i><?= number_format($totalSoal) ?> Butir Soal</small>
                            </div>
                            <div class="stat-icon-wrapper bg-info-subtle text-info">
                                <i class="fas fa-check-double"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content: Charts & Real-time Info -->
            <div class="row g-4 mb-4">
                <!-- Left Column (8 cols): Chart + Today Exams -->
                <div class="col-lg-8">
                    <!-- Aktivitas Peserta Chart -->
                    <div class="card dash-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Aktivitas Peserta Ujian Hari Ini</h6>
                                <small class="text-muted">Jumlah siswa yang memulai ujian berdasarkan jam (06:00 - 18:00 WIB)</small>
                            </div>
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-pill small">
                                <i class="far fa-calendar-alt text-muted me-1"></i> <?= date('d M Y') ?>
                            </span>
                        </div>
                        <div style="height: 270px;">
                            <canvas id="examChart"></canvas>
                        </div>
                    </div>

                    <!-- Jadwal Ujian Hari Ini -->
                    <div class="card dash-card overflow-hidden">
                        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-clock text-warning me-2"></i>Ujian Berjalan &amp; Jadwal Hari Ini</h6>
                            </div>
                            <a href="<?= esc(BASE_URL) ?>admin/jadwal/index.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                Kelola Semua Jadwal <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">Nama Ujian / Mapel</th>
                                        <th>Guru Pengampu</th>
                                        <th>Waktu</th>
                                        <th class="text-center">Peserta</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($todayExams)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-calendar-times fa-2x mb-2 text-secondary opacity-25 d-block"></i>
                                                Tidak ada jadwal ujian aktif atau dijadwalkan hari ini.
                                            </td>
                                        </tr>
                                    <?php else: foreach ($todayExams as $ex): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark small"><?= htmlspecialchars($ex['nama_mapel_ujian']) ?></div>
                                                <small class="text-muted"><i class="fas fa-stopwatch me-1"></i><?= $ex['durasi_menit'] ?> Menit</small>
                                            </td>
                                            <td class="small text-muted">
                                                <i class="fas fa-user-tie text-secondary me-1"></i><?= htmlspecialchars($ex['nama_guru']) ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= $ex['mulai_pada'] ? date('H:i', strtotime($ex['mulai_pada'])) : '-' ?> - 
                                                <?= $ex['selesai_pada'] ? date('H:i', strtotime($ex['selesai_pada'])) : '-' ?> WIB
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                                    <?= (int)$ex['peserta_selesai'] ?> / <?= (int)$ex['total_peserta'] ?> Selesai
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($ex['status'] === 'aktif'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                        <span class="live-dot me-1"></span> Berjalan
                                                    </span>
                                                <?php elseif ($ex['status'] === 'draft'): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Draft</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Selesai</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="<?= esc(BASE_URL) ?>admin/monitoring/index.php?exam_id=<?= esc($ex['id']) ?>" class="btn btn-sm btn-outline-primary" title="Live Monitor">
                                                    <i class="fas fa-desktop"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column (4 cols): Live Students & Server Health Widget -->
                <div class="col-lg-4">
                    <!-- Live Students Online Card -->
                    <div class="card dash-card p-4 mb-4 d-flex flex-column" style="min-height: 380px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="live-dot"></span>
                                <h6 class="fw-bold text-dark m-0">Sedang Mengerjakan</h6>
                            </div>
                            <?php if ($totalWorking > 0): ?>
                                <span id="working-badge" class="badge bg-success rounded-pill px-2.5 py-1.5"><?= $totalWorking ?> Aktif</span>
                            <?php else: ?>
                                <span id="working-badge" class="badge bg-secondary rounded-pill px-2.5 py-1.5">Tidak ada</span>
                            <?php endif; ?>
                        </div>

                        <div id="peserta-list" class="list-group list-group-flush flex-grow-1 overflow-auto pe-1" style="max-height: 290px;">
                            <?php if (empty($pesertaOnline)): ?>
                                <div class="text-center text-muted py-5 my-auto">
                                    <i class="fas fa-user-clock fa-3x mb-2 text-secondary opacity-25 d-block"></i>
                                    <div class="small fw-medium">Tidak ada peserta yang aktif mengerjakan saat ini</div>
                                </div>
                            <?php else: ?>
                                <?php 
                                $bg_colors = ['bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-dark'];
                                $idx = 0;
                                foreach ($pesertaOnline as $p): 
                                    $inisial = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $p['nama_lengkap']))));
                                    $inisial = substr($inisial, 0, 2);
                                    $color = $bg_colors[$idx % count($bg_colors)];
                                    $idx++;
                                ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial <?= $color ?> me-2.5">
                                                <?= htmlspecialchars($inisial) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark small mb-0"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                                                <small class="text-muted" style="font-size: 0.72rem;">
                                                    <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($p['jenjang'] . ' ' . $p['nama_kelas']) ?></span>
                                                    <?= htmlspecialchars($p['nama_mapel_ujian']) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.65rem;">
                                            <i class="fas fa-pencil-alt me-1"></i>Aktif
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <a href="<?= esc(BASE_URL) ?>admin/monitoring/index.php" class="btn btn-outline-primary btn-sm w-100 mt-3 rounded-pill">
                            <i class="fas fa-desktop me-1"></i> Buka Monitoring Lengkap
                        </a>
                    </div>

                    <!-- Mini Server Storage & System Card -->
                    <div class="card dash-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark m-0"><i class="fas fa-server text-secondary me-2"></i>Status Server</h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                PHP <?= phpversion() ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Kapasitas Penyimpanan Disk</span>
                                <span class="fw-bold text-dark"><?= $disk_pct ?>%</span>
                            </div>
                            <div class="progress" style="height: 7px; border-radius: 4px;">
                                <div class="progress-bar <?= $disk_pct > 85 ? 'bg-danger' : ($disk_pct > 70 ? 'bg-warning' : 'bg-primary') ?>" 
                                     role="progressbar" 
                                     style="width: <?= $disk_pct ?>%" 
                                     aria-valuenow="<?= $disk_pct ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1 text-muted" style="font-size: 0.72rem;">
                                <span>Terpakai: <?= $disk_used_gb ?> GB</span>
                                <span>Total: <?= $disk_total_gb ?> GB</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <small class="text-muted">Kesehatan Sistem</small>
                            <a href="<?= esc(BASE_URL) ?>admin/sistem/system-info.php" class="btn btn-light btn-sm text-primary fw-medium py-1 px-2.5 rounded-pill border" style="font-size: 0.75rem;">
                                Cek Lengkap <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
$adminChartJsonPayload = json_encode([
    'labels' => $chartLabels,
    'data'   => $chartData
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="adminChartDataJson">
<?= $adminChartJsonPayload ?>
</script>

<script>
$(document).ready(function() {
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
    });

    // Inisialisasi Chart Aktivitas
    const _admChart = JSON.parse(document.getElementById('adminChartDataJson').textContent || '{}');
    const ctx = document.getElementById('examChart').getContext('2d');
    
    // Gradient fill untuk line chart
    const gradient = ctx.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(30, 60, 114, 0.25)');
    gradient.addColorStop(1, 'rgba(42, 82, 152, 0.0)');

    const examChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: _admChart.labels || [],
            datasets: [{
                label: 'Peserta Mulai Ujian',
                data: _admChart.data || [],
                borderColor: '#1e3c72',
                backgroundColor: gradient,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#1e3c72',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { size: 12, family: 'Poppins' },
                    bodyFont: { size: 12, family: 'Poppins' },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y + ' peserta mulai ujian'
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            }
        }
    });

    // Polling data real-time setiap 10 detik
    function refreshDashboard() {
        fetch('<?= BASE_URL ?>admin/ajax-dashboard.php', { credentials: 'same-origin' })
            .then(res => res.ok ? res.json() : Promise.reject(res.status))
            .then(d => {
                // Update chart
                if (d.chart_data) {
                    examChart.data.datasets[0].data = d.chart_data;
                    examChart.update('none');
                }

                // Update badge peserta working
                const badge = document.getElementById('working-badge');
                if (badge) {
                    if (d.total_working > 0) {
                        badge.className = 'badge bg-success rounded-pill px-2.5 py-1.5';
                        badge.textContent = d.total_working + ' Aktif';
                    } else {
                        badge.className = 'badge bg-secondary rounded-pill px-2.5 py-1.5';
                        badge.textContent = 'Tidak ada';
                    }
                }

                // Update daftar peserta
                const $list = $('#peserta-list');
                if (d.peserta.length === 0) {
                    $list.html(`
                        <div class="text-center text-muted py-5 my-auto">
                            <i class="fas fa-user-clock fa-3x mb-2 text-secondary opacity-25 d-block"></i>
                            <div class="small fw-medium">Tidak ada peserta yang aktif mengerjakan saat ini</div>
                        </div>`);
                } else {
                    const esc = s => $('<div>').text(s || '').html();
                    const colors = ['bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-dark'];
                    const htmlItems = d.peserta.map((p, i) => `
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="avatar-initial ${colors[i % colors.length]} me-2.5" style="width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;color:#fff;">
                                    ${esc(p.inisial)}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small mb-0">${esc(p.nama)}</div>
                                    <small class="text-muted" style="font-size:0.72rem;">
                                        <span class="badge bg-light text-dark border me-1">${esc(p.kelas)}</span>
                                        ${esc(p.mapel)}
                                    </small>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size:0.65rem;">
                                <i class="fas fa-pencil-alt me-1"></i>Aktif
                            </span>
                        </div>`).join('');
                    $list.html(htmlItems);
                }
            })
            .catch(() => {});
    }

    setInterval(refreshDashboard, 10000);

    // Live Jam & Tanggal
    function updateClock() {
        const now = new Date();
        const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const elDate = document.getElementById('live-date');
        const elTime = document.getElementById('live-clock');
        if (elDate) elDate.innerText = now.toLocaleDateString('id-ID', opts);
        if (elTime) elTime.innerText = now.toLocaleTimeString('id-ID') + ' WIB';
    }
    setInterval(updateClock, 1000);
    updateClock();
});
</script>
</body>
</html>
