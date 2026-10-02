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

// 3. Statistik Utama
$totalSiswa   = $pdo->query("SELECT COUNT(*) FROM cbt_students WHERE is_aktif = 1")->fetchColumn();
$totalGuru    = $pdo->query("SELECT COUNT(*) FROM cbt_teachers WHERE is_aktif = 1")->fetchColumn();
$ujianAktif   = $pdo->query("SELECT COUNT(*) FROM cbt_exams WHERE status = 'aktif'")->fetchColumn();
$ujianSelesai = $pdo->query("SELECT COUNT(*) FROM cbt_exams WHERE status = 'selesai'")->fetchColumn();

// 4. Peserta yang sedang mengerjakan ujian hari ini
$stmtOnline = $pdo->prepare("
    SELECT s.nama_lengkap, c.nama_kelas, c.jenjang, e.nama_mapel_ujian
    FROM cbt_exam_participants p
    JOIN cbt_students s ON p.student_id = s.id
    JOIN cbt_classes c ON s.class_id = c.id
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.status = 'working'
      AND e.mulai_pada BETWEEN ? AND ?
    ORDER BY p.waktu_mulai DESC
    LIMIT 8
");
$stmtOnline->execute([$today_start, $today_end]);
$pesertaOnline = $stmtOnline->fetchAll();
$totalWorking  = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id WHERE p.status = 'working' AND e.mulai_pada BETWEEN ? AND ?");
$totalWorking->execute([$today_start, $today_end]);
$totalWorking  = $totalWorking->fetchColumn();

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

// Buat array jam 6-18 dengan default 0
$chartLabels = [];
$chartData   = [];
$chartMap    = array_column($chartRaw, 'jumlah', 'jam');
for ($h = 6; $h <= 18; $h++) {
    $chartLabels[] = sprintf('%02d:00', $h);
    $chartData[]   = (int)($chartMap[$h] ?? 0);
}

// 6. Info Sekolah
$setting      = $pdo->query("SELECT nama_sekolah FROM cbt_settings LIMIT 1")->fetch();
$nama_sekolah = $setting['nama_sekolah'] ?? 'CBT Online';
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../includes/header.php'; ?>

<body>

<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light shadow-sm border" id="menu-toggle"><i class="fas fa-bars"></i></button>

            <div class="ms-3 d-none d-lg-block">
                <div class="fw-bold text-primary" id="current-date" style="font-size: 0.9rem;"></div>
                <div class="text-muted" id="current-time" style="font-size: 0.8rem; margin-top: -3px;"></div>
            </div>

            <div class="ms-auto d-flex align-items-center">
                <div class="d-flex align-items-center">
                    <div class="text-end me-3 d-none d-md-block">
                        <small class="d-block fw-bold text-dark"><?= htmlspecialchars($_SESSION['nama']) ?></small>
                        <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($nama_sekolah) ?></small>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?= esc(urlencode($_SESSION['nama'])) ?>&background=4e73df&color=fff" class="rounded-circle shadow-sm" width="40">
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark m-0">Dashboard Overview</h4>
            </div>

            <!-- Statistik Utama -->
            <div class="row g-4 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 p-4 shadow-sm h-100 bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="opacity-75">Siswa Aktif</small>
                                <h2 class="fw-bold mb-0 mt-1"><?= number_format($totalSiswa) ?></h2>
                            </div>
                            <i class="fas fa-user-graduate fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 p-4 shadow-sm h-100 bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="opacity-75">Guru Aktif</small>
                                <h2 class="fw-bold mb-0 mt-1"><?= number_format($totalGuru) ?></h2>
                            </div>
                            <i class="fas fa-chalkboard-teacher fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 p-4 shadow-sm h-100 bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="opacity-75">Ujian Aktif</small>
                                <h2 class="fw-bold mb-0 mt-1"><?= number_format($ujianAktif) ?></h2>
                            </div>
                            <i class="fas fa-file-signature fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 p-4 shadow-sm h-100 bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="opacity-75">Ujian Selesai</small>
                                <h2 class="fw-bold mb-0 mt-1"><?= number_format($ujianSelesai) ?></h2>
                            </div>
                            <i class="fas fa-check-double fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart & Peserta Online -->
            <div class="row g-4 mb-5">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm p-4 h-100 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold m-0">Aktivitas Peserta Ujian Hari Ini</h6>
                            <small class="text-muted"><?= date('d M Y') ?></small>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="examChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm p-4 h-100 bg-white d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0">Sedang Mengerjakan</h6>
                            <?php if ($totalWorking > 0): ?>
                                <span id="working-badge" class="badge bg-success rounded-pill"><?= $totalWorking ?> Aktif</span>
                            <?php else: ?>
                                <span id="working-badge" class="badge bg-secondary rounded-pill">Tidak ada</span>
                            <?php endif; ?>
                        </div>

                        <div id="peserta-list" class="list-group list-group-flush flex-grow-1 overflow-auto" style="max-height: 280px;">
                            <?php if (empty($pesertaOnline)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-user-clock fa-2x mb-2 opacity-25"></i>
                                    <div class="small">Tidak ada peserta aktif saat ini</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pesertaOnline as $p): ?>
                                    <?php
                                    $inisial = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $p['nama_lengkap']))));
                                    $inisial = substr($inisial, 0, 2);
                                    ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0 mb-1">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.7rem; flex-shrink: 0;">
                                                <?= htmlspecialchars($inisial) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold small"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                                                <small class="text-muted" style="font-size: 0.7rem;">
                                                    <?= htmlspecialchars($p['jenjang'] . ' ' . $p['nama_kelas']) ?> &bull;
                                                    <?= htmlspecialchars($p['nama_mapel_ujian']) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: 0.65rem; flex-shrink: 0;">Aktif</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <a href="<?= esc(BASE_URL) ?>admin/monitoring/index.php" class="btn btn-outline-primary btn-sm w-100 mt-3">
                            <i class="fas fa-desktop me-1"></i> Monitoring Peserta
                        </a>
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

    const _admChart = JSON.parse(document.getElementById('adminChartDataJson').textContent || '{}');
    // Chart — data awal dari PHP
    const ctx = document.getElementById('examChart').getContext('2d');
    const examChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: _admChart.labels || [],
            datasets: [{
                label: 'Peserta Mulai Ujian',
                data: _admChart.data || [],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.y + ' peserta'
                    }
                }
            }
        }
    });

    // Polling dashboard data setiap 10 detik
    function refreshDashboard() {
        fetch('<?= BASE_URL ?>admin/ajax-dashboard.php', { credentials: 'same-origin' })
            .then(res => res.ok ? res.json() : Promise.reject(res.status))
            .then(d => {
                // Update chart
                examChart.data.datasets[0].data = d.chart_data;
                examChart.update('none');

                // Update badge peserta working
                const badge = document.getElementById('working-badge');
                if (d.total_working > 0) {
                    badge.className = 'badge bg-success rounded-pill';
                    badge.textContent = d.total_working + ' Aktif';
                } else {
                    badge.className = 'badge bg-secondary rounded-pill';
                    badge.textContent = 'Tidak ada';
                }

                // Update daftar peserta
                const $list = $('#peserta-list');
                if (d.peserta.length === 0) {
                    $list.html(`
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-user-clock fa-2x mb-2 opacity-25"></i>
                            <div class="small">Tidak ada peserta aktif saat ini</div>
                        </div>`);
                } else {
                    const esc = s => $('<div>').text(s || '').html();
                    const htmlItems = d.peserta.map(p => `
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0 mb-1">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center fw-bold"
                                     style="width:36px;height:36px;font-size:0.7rem;flex-shrink:0;">
                                    ${esc(p.inisial)}
                                </div>
                                <div>
                                    <div class="fw-bold small">${esc(p.nama)}</div>
                                    <small class="text-muted" style="font-size:0.7rem;">
                                        ${esc(p.kelas)} &bull; ${esc(p.mapel)}
                                    </small>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success rounded-pill" style="font-size:0.65rem;flex-shrink:0;">Aktif</span>
                        </div>`).join('');
                    $list.html(htmlItems);
                }
            })
            .catch(() => {}); // Gagal diam — jangan ganggu UI
    }

    setInterval(refreshDashboard, 10000);

    // Jam real-time
    function updateClock() {
        const now = new Date();
        const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-date').innerText = now.toLocaleDateString('id-ID', opts);
        document.getElementById('current-time').innerText = now.toLocaleTimeString('id-ID') + ' WIB';
    }
    setInterval(updateClock, 1000);
    updateClock();
});
</script>
</body>
</html>
