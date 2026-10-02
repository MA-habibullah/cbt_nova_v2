<?php
require_once '../../config/database.php';

$current_dir  = 'sistem';
$current_page = 'system-info.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<style>
    .metric-card  { border: none; border-radius: 14px; transition: box-shadow .2s; }
    .metric-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08) !important; }
    .gauge-label  { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6c757d; }
    .gauge-val    { font-size: 2.2rem; font-weight: 800; line-height: 1.1; }
    .chart-wrap   { position: relative; height: 160px; }
    .progress     { height: 8px; border-radius: 4px; background: #e9ecef; }
    .badge-live { animation: blink 1.4s step-start infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .table-custom th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <!-- Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Pusat Kesehatan Server &amp; Sistem</h5>
                    <small class="text-muted">Monitoring menyeluruh resource, database, &amp; lingkungan PHP</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" id="btn-db-check">
                    <i class="fas fa-stethoscope me-1"></i> Cek Integritas DB
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm rounded-pill px-3 shadow-sm text-dark" id="btn-clean-sessions">
                    <i class="fas fa-broom me-1"></i> Bersihkan Sesi
                </button>
                <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm" id="btn-clear-opcache">
                    <i class="fas fa-bolt me-1"></i> Reset OPcache
                </button>
                <span class="badge bg-success badge-live px-3 py-2 ms-2" id="live-badge">
                    <i class="fas fa-circle me-1" style="font-size:.45rem;vertical-align:middle;"></i> LIVE
                </span>
                <small class="text-muted ms-1"><span id="last-update">–</span></small>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Error banner (hidden by default) -->
            <div id="fetch-error" class="alert alert-danger border-0 shadow-sm align-items-center mb-4 d-none">
                <i class="fas fa-exclamation-triangle me-3 fa-lg"></i>
                <div>Gagal mengambil data sistem.
                    <span id="fetch-error-msg" class="ms-1 small text-danger-emphasis"></span>
                </div>
            </div>

            <!-- Row 1: CPU + Memory -->
            <div class="row g-4 mb-4">
                <!-- CPU -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="gauge-label"><i class="fas fa-microchip me-1 text-primary"></i> CPU Load Average</div>
                                    <div class="gauge-val text-primary"><span id="cpu-val">–</span><span class="fs-5 fw-normal">%</span></div>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                    <i class="fas fa-server me-1"></i><span id="cpu-cores">–</span> Core
                                </span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-primary" id="cpu-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <div class="chart-wrap"><canvas id="cpuChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- Memory -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="gauge-label"><i class="fas fa-memory me-1 text-warning"></i> Memory (RAM) Usage</div>
                                    <div class="gauge-val text-warning"><span id="mem-val">–</span><span class="fs-5 fw-normal">%</span></div>
                                </div>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                                    <span id="mem-used">–</span>&thinsp;/&thinsp;<span id="mem-total">–</span> MB
                                </span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar" id="mem-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <div class="chart-wrap"><canvas id="memChart"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Storage + Active Users -->
            <div class="row g-4 mb-4">
                <!-- Storage -->
                <div class="col-xl-5">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="gauge-label mb-3"><i class="fas fa-hdd me-1 text-info"></i> Kapasitas Penyimpanan Server</div>
                            <div class="row align-items-center">
                                <div class="col-7">
                                    <div style="position:relative;height:190px;">
                                        <canvas id="storageChart"></canvas>
                                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                                            <div class="fw-bold text-info" style="font-size:1.6rem;" id="storage-pct">–%</div>
                                            <div class="text-muted" style="font-size:.65rem;">DIGUNAKAN</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-5 d-flex flex-column gap-2">
                                    <div class="bg-light p-2 rounded border">
                                        <div class="gauge-label text-danger" style="font-size:0.65rem;">Terpakai</div>
                                        <div class="fw-bold text-danger fs-6" id="storage-used">– GB</div>
                                    </div>
                                    <div class="bg-light p-2 rounded border">
                                        <div class="gauge-label text-success" style="font-size:0.65rem;">Tersedia</div>
                                        <div class="fw-bold text-success fs-6" id="storage-free">– GB</div>
                                    </div>
                                    <div class="bg-light p-2 rounded border">
                                        <div class="gauge-label text-secondary" style="font-size:0.65rem;">Total Disk</div>
                                        <div class="fw-bold text-secondary fs-6" id="storage-total">– GB</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Users -->
                <div class="col-xl-7">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="gauge-label">
                                    <i class="fas fa-users text-success me-1"></i> Pengguna Login Aktif (15 Menit Terakhir)
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                    <span id="total-active-count" class="fw-bold">–</span> online
                                    &nbsp;·&nbsp;<span id="active-pct-total" class="fw-bold">–</span>%
                                </span>
                            </div>

                            <!-- Siswa -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold"><i class="fas fa-user-graduate text-primary me-1"></i> Siswa Login</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-primary" id="active-siswa-count">–</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="active-siswa-pct">–%</span>
                                    </div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" id="active-siswa-bar" style="width:0%"></div>
                                </div>
                            </div>

                            <!-- Guru -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold"><i class="fas fa-chalkboard-teacher text-warning me-1"></i> Guru Login</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-warning" id="active-guru-count">–</span>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" id="active-guru-pct">–%</span>
                                    </div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" id="active-guru-bar" style="width:0%"></div>
                                </div>
                            </div>

                            <!-- Admin -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold"><i class="fas fa-user-shield text-danger me-1"></i> Admin / Proktor Login</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-danger" id="active-admin-count">–</span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" id="active-admin-pct">–%</span>
                                    </div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" id="active-admin-bar" style="width:0%"></div>
                                </div>
                            </div>

                            <!-- Total terdaftar -->
                            <div class="row g-2 pt-2">
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2 text-center border">
                                        <div class="fw-bold text-primary" id="total-siswa">–</div>
                                        <small class="text-muted" style="font-size:0.75rem;">Total Siswa</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2 text-center border">
                                        <div class="fw-bold text-warning" id="total-guru">–</div>
                                        <small class="text-muted" style="font-size:0.75rem;">Total Guru</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2 text-center border">
                                        <div class="fw-bold text-danger" id="total-admin">–</div>
                                        <small class="text-muted" style="font-size:0.75rem;">Total Admin</small>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Database & PHP Environment Health -->
            <div class="row g-4 mb-4">
                <!-- Database Health -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-database text-primary me-2"></i>Kesehatan Basis Data (MySQL / MariaDB)</h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="db-latency-badge"><i class="fas fa-tachometer-alt me-1"></i><span id="db-latency">–</span> ms</span>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted bg-light" width="40%">Versi Database Server</td>
                                            <td class="fw-bold" id="db-version">–</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Jumlah Tabel &amp; Ukuran</td>
                                            <td><span class="fw-bold" id="db-tables">–</span> tabel (<span class="fw-bold text-primary" id="db-size">–</span> MB)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Koneksi Aktif / Maksimal</td>
                                            <td><span class="fw-bold text-success" id="db-threads">–</span> / <span id="db-max-conn">–</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Uptime Server DB</td>
                                            <td class="fw-bold text-muted" id="db-uptime">–</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Status Koneksi PDO</td>
                                            <td><span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Terhubung &amp; Sehat</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHP Environment & OPcache -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fab fa-php text-primary me-2"></i>Lingkungan PHP &amp; Web Server</h6>
                            <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold" id="php-ver-badge">PHP <span id="php-version">–</span></span>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted bg-light" width="40%">Sistem Operasi &amp; Web Server</td>
                                            <td class="fw-bold"><span id="env-os">–</span> | <span id="env-server">–</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Server API (SAPI)</td>
                                            <td class="fw-bold text-dark" id="env-sapi">–</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Memory Limit / Exec Time</td>
                                            <td><span class="badge bg-secondary" id="env-mem-limit">–</span> / <span class="badge bg-secondary" id="env-exec-time">–</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Upload / Post Max Size</td>
                                            <td><span class="badge bg-light text-dark border" id="env-upload-size">–</span> / <span class="badge bg-light text-dark border" id="env-post-size">–</span> (input vars: <span id="env-input-vars">–</span>)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted bg-light">Status Zend OPcache</td>
                                            <td id="env-opcache-status">–</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 4: Directory Permissions & PHP Extensions -->
            <div class="row g-4">
                <!-- Directory Permissions -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-folder-open text-warning me-2"></i>Izin Akses Tulis Direktori (Storage Permissions)</h6>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="dir-table">
                                    <thead class="table-light table-custom">
                                        <tr>
                                            <th>Direktori</th>
                                            <th>Fungsi</th>
                                            <th class="text-center">Izin Tulis</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dir-tbody">
                                        <tr><td colspan="3" class="text-center text-muted py-3">Memuat status direktori...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHP Extensions Checklist -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-puzzle-piece text-success me-2"></i>Ekstensi Kritis PHP (Checklist Modul)</h6>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="ext-table">
                                    <thead class="table-light table-custom">
                                        <tr>
                                            <th>Ekstensi</th>
                                            <th>Kegunaan</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ext-tbody">
                                        <tr><td colspan="3" class="text-center text-muted py-3">Memuat checklist ekstensi...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /container -->
    </div><!-- /content -->
</div><!-- /wrapper -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

// ── Sidebar toggle ───────────────────────────────────────────────────────────
document.getElementById('menu-toggle').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('wrapper').classList.toggle('toggled');
});

// ── Chart factory ────────────────────────────────────────────────────────────
const MAX_PTS = 30;

function makeLineChart(canvasId, color) {
    return new Chart(document.getElementById(canvasId), {
        type: 'line',
        data: {
            labels: Array(MAX_PTS).fill(''),
            datasets: [{
                data: Array(MAX_PTS).fill(null),
                borderColor: color,
                backgroundColor: color.replace(')', ', 0.08)').replace('rgb', 'rgba'),
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 300 },
            plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
            scales: {
                x: { grid: { display: false }, ticks: { display: false } },
                y: {
                    min: 0, max: 100,
                    grid: { color: '#f0f0f0' },
                    ticks: { font: { size: 10 }, callback: v => v + '%' }
                }
            }
        }
    });
}

const cpuChart = makeLineChart('cpuChart', 'rgb(13,110,253)');
const memChart = makeLineChart('memChart', 'rgb(255,193,7)');

// ── Storage Doughnut ─────────────────────────────────────────────────────────
const storageChart = new Chart(document.getElementById('storageChart'), {
    type: 'doughnut',
    data: {
        labels: ['Terpakai', 'Tersedia'],
        datasets: [{
            data: [0, 1],
            backgroundColor: ['#0dcaf0', '#e9ecef'],
            borderWidth: 0,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} GB` } }
        }
    }
});

function pushPoint(chart, value) {
    const ds = chart.data.datasets[0].data;
    ds.push(value);
    if (ds.length > MAX_PTS) ds.shift();
    chart.update('none');
}

function setBar(id, pct, colorClass) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.width = Math.min(pct, 100) + '%';
    el.className = 'progress-bar ' + colorClass;
}

function cpuColor(p) {
    return p >= 85 ? 'bg-danger' : p >= 60 ? 'bg-warning' : 'bg-primary';
}
function memColor(p) {
    return p >= 85 ? 'bg-danger' : p >= 60 ? 'bg-danger' : 'bg-warning';
}

function formatUptime(seconds) {
    if (!seconds) return '-';
    const d = Math.floor(seconds / (3600*24));
    const h = Math.floor((seconds % (3600*24)) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${d} hari, ${h} jam, ${m} mnt`;
}

// ── Fetch & update ────────────────────────────────────────────────────────────
const DATA_URL = 'system-info-data.php';

function fetchData() {
    fetch(DATA_URL, { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(d => {
            if (d.error) throw new Error(d.error);

            const errEl = document.getElementById('fetch-error');
            errEl.classList.add('d-none');
            errEl.classList.remove('d-flex');

            // 1. CPU
            const cpu = parseFloat(d.cpu) || 0;
            document.getElementById('cpu-val').textContent   = cpu;
            document.getElementById('cpu-cores').textContent = d.cpu_cores ?? '?';
            setBar('cpu-bar', cpu, cpuColor(cpu));
            pushPoint(cpuChart, cpu);

            // 2. Memory
            const mem = d.memory || {};
            const mp  = parseFloat(mem.percent) || 0;
            document.getElementById('mem-val').textContent   = mp;
            document.getElementById('mem-used').textContent  = (mem.used  || 0).toLocaleString();
            document.getElementById('mem-total').textContent = (mem.total || 0).toLocaleString();
            setBar('mem-bar', mp, memColor(mp));
            pushPoint(memChart, mp);

            // 3. Storage
            const st = d.storage || {};
            storageChart.data.datasets[0].data = [st.used || 0, st.free || 0];
            storageChart.update('none');
            document.getElementById('storage-pct').textContent   = (st.percent || 0) + '%';
            document.getElementById('storage-used').textContent  = (st.used  || 0) + ' GB';
            document.getElementById('storage-free').textContent  = (st.free  || 0) + ' GB';
            document.getElementById('storage-total').textContent = (st.total || 0) + ' GB';

            // 4. Active Users
            const au = d.active_users || {};
            document.getElementById('total-active-count').textContent = au.total_active ?? 0;
            document.getElementById('active-pct-total').textContent   = au.percent_total ?? 0;

            document.getElementById('active-siswa-count').textContent = au.active_siswa ?? 0;
            document.getElementById('active-siswa-pct').textContent   = (au.percent_siswa ?? 0) + '%';
            setBar('active-siswa-bar', au.percent_siswa ?? 0, 'bg-primary');

            document.getElementById('active-guru-count').textContent  = au.active_guru ?? 0;
            document.getElementById('active-guru-pct').textContent    = (au.percent_guru ?? 0) + '%';
            setBar('active-guru-bar', au.percent_guru ?? 0, 'bg-warning');

            document.getElementById('active-admin-count').textContent = au.active_admin ?? 0;
            document.getElementById('active-admin-pct').textContent   = (au.percent_admin ?? 0) + '%';
            setBar('active-admin-bar', au.percent_admin ?? 0, 'bg-danger');

            document.getElementById('total-siswa').textContent = (au.total_siswa ?? 0).toLocaleString();
            document.getElementById('total-guru').textContent  = (au.total_guru ?? 0).toLocaleString();
            document.getElementById('total-admin').textContent = (au.total_admin ?? 0).toLocaleString();

            // 5. Database Health
            const db = d.database || {};
            document.getElementById('db-version').textContent   = db.version || '-';
            document.getElementById('db-latency').textContent   = db.latency_ms ?? '-';
            document.getElementById('db-tables').textContent    = db.table_count ?? '-';
            document.getElementById('db-size').textContent      = db.size_mb ?? '-';
            document.getElementById('db-threads').textContent   = db.threads_connected ?? '-';
            document.getElementById('db-max-conn').textContent  = db.max_connections ?? '-';
            document.getElementById('db-uptime').textContent    = formatUptime(db.uptime_sec);

            // 6. PHP Environment
            const env = d.environment || {};
            document.getElementById('php-version').textContent     = env.php_version || '-';
            document.getElementById('env-os').textContent          = env.os_name || '-';
            document.getElementById('env-server').textContent      = env.server_software || '-';
            document.getElementById('env-sapi').textContent        = env.php_sapi || '-';
            document.getElementById('env-mem-limit').textContent   = env.memory_limit || '-';
            document.getElementById('env-exec-time').textContent   = env.max_execution_time || '-';
            document.getElementById('env-upload-size').textContent = env.upload_max_filesize || '-';
            document.getElementById('env-post-size').textContent   = env.post_max_size || '-';
            document.getElementById('env-input-vars').textContent  = env.max_input_vars || '-';

            const $opEl = $('#env-opcache-status');
            if (env.opcache_enabled) {
                $opEl.html(`<span class="badge bg-success"><i class="fas fa-check me-1"></i>Aktif</span> (Hit Rate: <b>${env.opcache_hit_rate}%</b>, Mem: <b>${env.opcache_used_mb} MB</b>)`);
            } else {
                $opEl.html(`<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>Nonaktif / CLI</span>`);
            }

            // 7. Directory Permissions Checklist Table
            if (Array.isArray(d.directories)) {
                let dirHtml = '';
                d.directories.forEach(dir => {
                    const statusBadge = dir.writable
                        ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Writable</span>'
                        : (dir.exists
                            ? '<span class="badge bg-warning text-dark"><i class="fas fa-lock me-1"></i>Read Only</span>'
                            : '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Missing</span>');
                    dirHtml += `
                        <tr>
                            <td><code>${dir.path}</code></td>
                            <td class="small text-muted">${dir.label}</td>
                            <td class="text-center">${statusBadge}</td>
                        </tr>
                    `;
                });
                $('#dir-tbody').html(dirHtml);
            }

            // 8. PHP Extensions Checklist Table
            if (env.extensions && Array.isArray(env.extensions)) {
                let extHtml = '';
                env.extensions.forEach(ext => {
                    const extBadge = ext.status
                        ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Tersedia</span>'
                        : '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Missing</span>';
                    extHtml += `
                        <tr>
                            <td class="fw-bold"><code>${ext.ext}</code></td>
                            <td class="small text-muted">${ext.description}</td>
                            <td class="text-center">${extBadge}</td>
                        </tr>
                    `;
                });
                $('#ext-tbody').html(extHtml);
            }

            // Timestamp
            document.getElementById('last-update').textContent = d.timestamp;
        })
        .catch(err => {
            const errEl = document.getElementById('fetch-error');
            errEl.classList.remove('d-none');
            errEl.classList.add('d-flex');
            document.getElementById('fetch-error-msg').textContent = '(' + err.message + ')';
            console.error('[system-info] fetch error:', err);
        });
}

// ── Maintenance Actions Handlers ─────────────────────────────────────────────
$('#btn-clear-opcache').on('click', function() {
    Swal.fire({
        title: 'Reset Zend OPcache?',
        text: 'Cache kompilasi PHP akan dibersihkan dan di-reload segar.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-bolt me-1"></i> Reset Sekarang',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#198754'
    }).then(res => {
        if (!res.isConfirmed) return;
        $.post(DATA_URL, { action: 'clear_opcache', csrf_token: CSRF_TOKEN }, function(r) {
            Swal.fire(r.status === 'success' ? 'Berhasil' : 'Info', r.message, r.status === 'success' ? 'success' : 'info');
            fetchData();
        }, 'json');
    });
});

$('#btn-clean-sessions').on('click', function() {
    Swal.fire({
        title: 'Bersihkan Sesi Kedaluwarsa?',
        text: 'Sesi online yang tidak aktif lebih dari 24 jam akan dihapus dari database.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-broom me-1"></i> Bersihkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#f59e0b'
    }).then(res => {
        if (!res.isConfirmed) return;
        $.post(DATA_URL, { action: 'clean_sessions', csrf_token: CSRF_TOKEN }, function(r) {
            Swal.fire(r.status === 'success' ? 'Berhasil' : 'Gagal', r.message, r.status === 'success' ? 'success' : 'error');
            fetchData();
        }, 'json');
    });
});

$('#btn-db-check').on('click', function() {
    Swal.fire({
        title: 'Memeriksa Integritas Database...',
        text: 'Menjalankan perintah CHECK TABLE pada seluruh tabel kritis.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(DATA_URL, { action: 'db_check', csrf_token: CSRF_TOKEN }, function(r) {
        if (r.status === 'success' && r.data) {
            let tableReport = '<div class="table-responsive" style="max-height:300px; text-align:left;"><table class="table table-sm table-bordered"><thead><tr class="table-light"><th>Tabel</th><th>Status</th></tr></thead><tbody>';
            for (const [tbl, st] of Object.entries(r.data)) {
                const badge = st === 'OK' ? '<span class="badge bg-success">OK</span>' : `<span class="badge bg-warning text-dark">${st}</span>`;
                tableReport += `<tr><td><code>${tbl}</code></td><td class="text-center">${badge}</td></tr>`;
            }
            tableReport += '</tbody></table></div>';

            Swal.fire({
                icon: 'success',
                title: 'Diagnostik Database Selesai',
                html: tableReport,
                confirmButtonColor: '#4e73df'
            });
        } else {
            Swal.fire('Gagal', r.message || 'Terjadi kesalahan saat memeriksa database', 'error');
        }
    }, 'json');
});

// Start immediately, then repeat every 5 seconds
fetchData();
setInterval(fetchData, 5000);
</script>
</body>
</html>

