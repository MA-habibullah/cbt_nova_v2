<?php
// Sidebar Guru — style matches admin sidebar
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

$setting      = get_app_settings();
$nama_display = !empty($setting['nama_sekolah']) ? $setting['nama_sekolah'] : 'CBT NATIVE';
$logo_display = BASE_URL . 'assets/img/logo.png';
if (!empty($setting['logo'])) {
    $path_logo = dirname(__DIR__, 2) . '/assets/img/logo/' . $setting['logo'];
    if (file_exists($path_logo)) {
        $logo_display = BASE_URL . 'assets/img/logo/' . $setting['logo'];
    }
}

$is_bank_soal = ($current_dir === 'bank-soal');
$is_jadwal    = ($current_dir === 'jadwal');
$is_monitor   = ($current_dir === 'monitoring');
$is_hasil     = ($current_dir === 'hasil');
$is_display   = ($current_dir === 'display-publik');
?>

<nav id="sidebar" class="vh-100 sticky-top overflow-auto shadow-sm bg-white">
    <div class="p-4 d-flex align-items-center justify-content-between border-bottom mb-2">
        <div class="d-flex align-items-center">
            <img src="<?= esc($logo_display) ?>" width="35" class="me-2" alt="Logo" style="border-radius:6px;object-fit:cover;">
            <div>
                <h6 class="mb-0 fw-bold text-primary" style="font-size:.85rem;"><?= htmlspecialchars($nama_display) ?></h6>
                <small class="text-muted" style="font-size:.65rem;">Portal Guru</small>
            </div>
        </div>
        <button type="button" class="btn-close d-lg-none" id="btn-close-sidebar" aria-label="Tutup Menu" onclick="if(typeof closeSidebar==='function')closeSidebar()"></button>
    </div>


    <div class="nav flex-column px-2">

        <a href="<?= esc(BASE_URL) ?>guru/index.php"
           class="nav-link <?= ($current_page === 'index.php' && $current_dir === 'guru') ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt me-2"></i> <span>Dashboard</span>
        </a>

        <div class="sidebar-heading mt-3 mb-1 small text-muted px-3 text-uppercase fw-bold" style="font-size:.7rem;">Bank Soal</div>

        <a class="nav-link <?= $is_bank_soal ? 'active' : '' ?>"
           href="<?= esc(BASE_URL) ?>guru/bank-soal/index.php">
            <i class="fas fa-book-open me-2"></i> <span>Bank Soal</span>
        </a>

        <div class="sidebar-heading mt-3 mb-1 small text-muted px-3 text-uppercase fw-bold" style="font-size:.7rem;">Ujian</div>

        <a class="nav-link <?= $is_jadwal ? 'active' : '' ?>"
           href="<?= esc(BASE_URL) ?>guru/jadwal/index.php">
            <i class="fas fa-calendar-alt me-2"></i> <span>Jadwal Ujian</span>
        </a>

        <div class="sidebar-heading mt-3 mb-1 small text-muted px-3 text-uppercase fw-bold" style="font-size:.7rem;">Pengawasan</div>

        <a class="nav-link <?= $is_monitor ? 'active' : '' ?>"
           href="<?= esc(BASE_URL) ?>guru/monitoring/index.php">
            <i class="fas fa-desktop me-2"></i> <span>Monitoring Peserta</span>
        </a>

        <div class="sidebar-heading mt-3 mb-1 small text-muted px-3 text-uppercase fw-bold" style="font-size:.7rem;">Laporan</div>

        <a class="nav-link <?= $is_hasil ? 'active' : '' ?>"
           href="<?= esc(BASE_URL) ?>guru/hasil/index.php">
            <i class="fas fa-chart-bar me-2"></i> <span>Hasil Test</span>
        </a>

        <div class="sidebar-heading mt-3 mb-1 small text-muted px-3 text-uppercase fw-bold" style="font-size:.7rem;">Tampilan</div>

        <a class="nav-link <?= $is_display ? 'active' : '' ?>"
           href="<?= esc(BASE_URL) ?>guru/display-publik/index.php">
            <i class="fas fa-tv me-2"></i> <span>Layar Publik</span>
        </a>

        <hr class="my-2 mx-3">

        <a href="<?= esc(BASE_URL) ?>auth/logout.php" class="nav-link text-danger mb-5">
            <i class="fas fa-sign-out-alt me-2"></i> <span>Keluar</span>
        </a>

    </div>
</nav>

<script>
(function () {
    function isMobile() { return window.innerWidth < 992; }

    function openSidebar() {
        var s = document.getElementById('sidebar');
        var bd = document.getElementById('sidebar-backdrop');
        if (s) s.classList.add('sidebar-open');
        if (bd) bd.classList.add('show');
    }

    function closeSidebar() {
        var s = document.getElementById('sidebar');
        var bd = document.getElementById('sidebar-backdrop');
        if (s) s.classList.remove('sidebar-open');
        if (bd) bd.classList.remove('show');
    }

    function toggleSidebar() {
        if (isMobile()) {
            var s = document.getElementById('sidebar');
            if (s && s.classList.contains('sidebar-open')) { closeSidebar(); } else { openSidebar(); }
        } else {
            var w = document.getElementById('wrapper');
            if (w) w.classList.toggle('toggled');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Backdrop
        if (!document.getElementById('sidebar-backdrop')) {
            var bd = document.createElement('div');
            bd.id = 'sidebar-backdrop';
            document.body.appendChild(bd);
            bd.addEventListener('click', closeSidebar);
        }

        // Inject hamburger button if not present
        if (!document.getElementById('menu-toggle')) {
            var insertTarget = document.querySelector('#content .navbar .d-flex')
                            || document.querySelector('#content .navbar');
            if (insertTarget) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'menu-toggle';
                btn.className = 'btn btn-light border me-2 flex-shrink-0';
                var icon = document.createElement('i');
                icon.className = 'fas fa-bars';
                btn.appendChild(icon);
                insertTarget.insertBefore(btn, insertTarget.firstChild);
            }
        }

        // Close on resize to desktop
        window.addEventListener('resize', function () {
            if (!isMobile()) { closeSidebar(); }
        });
    });

    // Capture phase to override any existing jQuery click handlers
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#menu-toggle');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        }
    }, true);
})();
</script>
