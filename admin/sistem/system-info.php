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
    .metric-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1) !important; }
    .gauge-label  { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6c757d; }
    .gauge-val    { font-size: 2.2rem; font-weight: 800; line-height: 1.1; }
    .chart-wrap   { position: relative; height: 180px; }
    .progress     { height: 7px; border-radius: 4px; background: #e9ecef; }
    .badge-live { animation: blink 1.4s step-start infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
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
                    <h5 class="mb-0 fw-bold text-primary">Informasi Sistem</h5>
                    <small class="text-muted">Resource server secara realtime</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success badge-live px-3 py-2" id="live-badge">
                    <i class="fas fa-circle me-1" style="font-size:.45rem;vertical-align:middle;"></i> LIVE
                </span>
                <small class="text-muted">Update: <span id="last-update">–</span></small>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Error banner (hidden by default via d-none) -->
            <div id="fetch-error" class="alert alert-danger border-0 shadow-sm align-items-center mb-4 d-none">
                <i class="fas fa-exclamation-triangle me-3 fa-lg"></i>
                <div>Gagal mengambil data sistem. Periksa log PHP untuk detail.
                    <span id="fetch-error-msg" class="ms-1 small text-danger-emphasis"></span>
                </div>
            </div>

            <!-- Row 1: CPU + Memory -->
            <div class="row g-4 mb-4">

                <!-- CPU -->
                <div class="col-xl-6">
                    <div class="card metric-card shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="gauge-label"><i class="fas fa-microchip me-1 text-primary"></i> CPU Load</div>
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
                    <div class="card metric-card shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="gauge-label"><i class="fas fa-memory me-1 text-warning"></i> Memory Usage</div>
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
            <div class="row g-4">

                <!-- Storage -->
                <div class="col-xl-5">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="gauge-label mb-3"><i class="fas fa-hdd me-1 text-info"></i> Storage Usage</div>
                            <div class="row align-items-center">
                                <div class="col-7">
                                    <div style="position:relative;height:210px;">
                                        <canvas id="storageChart"></canvas>
                                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                                            <div class="fw-bold text-info" style="font-size:1.7rem;" id="storage-pct">–%</div>
                                            <div class="text-muted" style="font-size:.65rem;">DIGUNAKAN</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-5 d-flex flex-column gap-3">
                                    <div>
                                        <div class="gauge-label text-danger">Terpakai</div>
                                        <div class="fs-5 fw-bold text-danger" id="storage-used">– GB</div>
                                    </div>
                                    <div>
                                        <div class="gauge-label text-success">Tersedia</div>
                                        <div class="fs-5 fw-bold text-success" id="storage-free">– GB</div>
                                    </div>
                                    <div>
                                        <div class="gauge-label text-secondary">Total</div>
                                        <div class="fs-5 fw-bold text-secondary" id="storage-total">– GB</div>
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
                                    <i class="fas fa-circle text-success me-1" style="font-size:.5rem;vertical-align:middle;"></i>
                                    Pengguna Login Aktif
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                    <span id="total-active-count" class="fw-bold">–</span> online
                                    &nbsp;·&nbsp;<span id="active-pct-total" class="fw-bold">–</span>%
                                    &nbsp;<span class="text-muted fw-normal" id="online-window-label" style="font-size:.7rem;">dalam 15 mnt</span>
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
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold"><i class="fas fa-user-shield text-danger me-1"></i> Admin Login</span>
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
                            <div class="gauge-label mb-2">Total Pengguna Terdaftar</div>
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-3 text-center border">
                                        <div class="fw-bold fs-5 text-primary" id="total-siswa">–</div>
                                        <div class="text-muted small"><i class="fas fa-user-graduate me-1"></i>Siswa</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-3 text-center border">
                                        <div class="fw-bold fs-5 text-warning" id="total-guru">–</div>
                                        <div class="text-muted small"><i class="fas fa-chalkboard-teacher me-1"></i>Guru</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-3 text-center border">
                                        <div class="fw-bold fs-5 text-danger" id="total-admin">–</div>
                                        <div class="text-muted small"><i class="fas fa-user-shield me-1"></i>Admin</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!-- /row 2 -->
        </div><!-- /container -->
    </div><!-- /content -->
</div><!-- /wrapper -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// ── Sidebar toggle ───────────────────────────────────────────────────────────
document.getElementById('menu-toggle').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('wrapper').classList.toggle('toggled');
});

// ── CPU cores (set from server data on first fetch) ──────────────────────────
document.getElementById('cpu-cores').textContent = '…';

// ── Chart factory ────────────────────────────────────────────────────────────
const MAX_PTS = 30;

function makeLineChart(canvasId, color) {
    return new Chart(document.getElementById(canvasId), {
        type: 'line',
        data: {
            labels:   Array(MAX_PTS).fill(''),
            datasets: [{
                data:            Array(MAX_PTS).fill(null),
                borderColor:     color,
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

// ── Helpers ──────────────────────────────────────────────────────────────────
function pushPoint(chart, value) {
    const ds = chart.data.datasets[0].data;
    ds.push(value);
    if (ds.length > MAX_PTS) ds.shift();
    chart.update('none'); // skip animation on rolling update
}

function setBar(id, pct, colorClass) {
    const el = document.getElementById(id);
    el.style.width = Math.min(pct, 100) + '%';
    el.className = 'progress-bar ' + colorClass;
}

function cpuColor(p) {
    return p >= 85 ? 'bg-danger' : p >= 60 ? 'bg-warning' : 'bg-primary';
}
function memColor(p) {
    return p >= 85 ? 'bg-danger' : p >= 60 ? 'bg-danger' : 'bg-warning';
}

// ── Fetch & update ────────────────────────────────────────────────────────────
// Relative URL — browser otomatis pakai protokol (http/https) yang sama
// dengan halaman saat ini, menghindari mixed-content error saat pakai domain
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

            // — CPU —
            const cpu = parseFloat(d.cpu) || 0;
            document.getElementById('cpu-val').textContent    = cpu;
            document.getElementById('cpu-cores').textContent  = d.cpu_cores ?? '?';
            setBar('cpu-bar', cpu, cpuColor(cpu));
            pushPoint(cpuChart, cpu);

            // — Memory —
            const mem = d.memory;
            const mp  = parseFloat(mem.percent) || 0;
            document.getElementById('mem-val').textContent   = mp;
            document.getElementById('mem-used').textContent  = (mem.used  || 0).toLocaleString();
            document.getElementById('mem-total').textContent = (mem.total || 0).toLocaleString();
            setBar('mem-bar', mp, memColor(mp));
            pushPoint(memChart, mp);

            // — Storage —
            const st = d.storage;
            storageChart.data.datasets[0].data = [st.used || 0, st.free || 0];
            storageChart.update('none');
            document.getElementById('storage-pct').textContent   = (st.percent || 0) + '%';
            document.getElementById('storage-used').textContent  = (st.used  || 0) + ' GB';
            document.getElementById('storage-free').textContent  = (st.free  || 0) + ' GB';
            document.getElementById('storage-total').textContent = (st.total || 0) + ' GB';

            // — Active Users —
            const au = d.active_users;
            document.getElementById('total-active-count').textContent  = au.total_active;
            document.getElementById('active-pct-total').textContent    = au.percent_total;
            document.getElementById('online-window-label').textContent = `dalam ${au.online_window} mnt terakhir`;

            document.getElementById('active-siswa-count').textContent = au.active_siswa;
            document.getElementById('active-siswa-pct').textContent   = au.percent_siswa + '%';
            setBar('active-siswa-bar', au.percent_siswa, 'bg-primary');

            document.getElementById('active-guru-count').textContent  = au.active_guru;
            document.getElementById('active-guru-pct').textContent    = au.percent_guru + '%';
            setBar('active-guru-bar', au.percent_guru, 'bg-warning');

            document.getElementById('active-admin-count').textContent = au.active_admin;
            document.getElementById('active-admin-pct').textContent   = au.percent_admin + '%';
            setBar('active-admin-bar', au.percent_admin, 'bg-danger');

            document.getElementById('total-siswa').textContent = au.total_siswa;
            document.getElementById('total-guru').textContent  = au.total_guru;
            document.getElementById('total-admin').textContent = au.total_admin;

            // — Timestamp —
            document.getElementById('last-update').textContent = d.timestamp;
        })
        .catch(err => {
            const errEl = document.getElementById('fetch-error');
            errEl.classList.remove('d-none');
            errEl.classList.add('d-flex');
            document.getElementById('fetch-error-msg').textContent = '(' + err.message + ')';
            console.error('[system-info] fetch error:', err, 'URL:', DATA_URL);
        });
}

// Start immediately, then repeat every 5 seconds
fetchData();
setInterval(fetchData, 5000);
</script>
</body>
</html>
