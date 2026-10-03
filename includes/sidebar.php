<?php
/**
 * SIDEBAR LOGIC - CBT NATIVE
 */
require_once dirname(__DIR__) . '/config/database.php';

// 1. Inisialisasi Nilai Default (Penting agar tidak Undefined)
$nama_display = "CBT SMAN 11";
$logo_display = BASE_URL . "assets/img/logo/logo.png";

// 2. Ambil data dari database (dengan session cache 5 menit)
$setting = get_app_settings();
if (!empty($setting['nama_sekolah'])) {
    $nama_display = $setting['nama_sekolah'];
}
if (!empty($setting['logo'])) {
    $path_logo = dirname(__DIR__) . "/assets/img/logo/" . $setting['logo'];
    if (file_exists($path_logo)) {
        $logo_display = BASE_URL . "assets/img/logo/" . $setting['logo'];
    }
}

// 3. Logika Menu Aktif
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

if (!function_exists('menu_show')) {
    function menu_show($pages) {
        global $current_page;
        return in_array($current_page, $pages) ? 'show' : '';
    }
}

if (!function_exists('menu_active')) {
    function menu_active($pages) {
        global $current_page;
        return in_array($current_page, $pages) ? 'active' : '';
    }
}
?>

<nav id="sidebar" class="vh-100 sticky-top overflow-auto shadow-sm bg-white">
    <div class="p-4 d-flex align-items-center justify-content-between border-bottom mb-2">
        <div class="d-flex align-items-center">
            <img src="<?= esc($logo_display) ?>" width="35" class="me-2" alt="Logo"> 
            <h6 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($nama_display) ?></h6>
        </div>
        <button type="button" class="btn-close d-lg-none" id="btn-close-sidebar" aria-label="Tutup Menu" onclick="if(typeof closeSidebar==='function')closeSidebar()"></button>
    </div>


    <div class="nav flex-column px-2">
        <a href="<?= esc(BASE_URL) ?>admin/index.php" class="nav-link <?= esc(($current_page == 'index.php' && $current_dir == 'admin') ? 'active' : '') ?>">
            <i class="fas fa-th-large me-2"></i> <span>Dashboard</span>
        </a>
        <div class="sidebar-heading mt-3 mb-1 small text-muted px-3 text-uppercase fw-bold" style="font-size: 0.7rem;">Menu Utama</div>
        
        <?php $akademik_pages = ['tahun-ajaran.php', 'kelas.php', 'sesi.php', 'assign-sesi.php', 'kenaikan-kelas.php', 'lulus.php', 'mapel.php']; ?>
        <a class="nav-link justify-content-between d-flex align-items-center <?= menu_active($akademik_pages) ?>"
           data-bs-toggle="collapse" href="#menuAkademik" role="button" aria-expanded="<?= esc(menu_show($akademik_pages) === 'show' ? 'true' : 'false') ?>">
            <span><i class="fas fa-university me-2"></i> Akademik</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        <div class="collapse <?= menu_show($akademik_pages) ?>" id="menuAkademik">
            <div class="collapse-inner">
                <a class="collapse-item <?= ($current_page == 'tahun-ajaran.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/tahun-ajaran.php">Tahun Ajaran</a>
                <a class="collapse-item <?= ($current_page == 'kelas.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/kelas.php">Kelas</a>
                <a class="collapse-item <?= ($current_page == 'sesi.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/sesi.php">Sesi Ujian</a>
                <a class="collapse-item <?= ($current_page == 'assign-sesi.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/assign-sesi.php">Assign Sesi Siswa</a>
                <a class="collapse-item <?= ($current_page == 'kenaikan-kelas.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/kenaikan-kelas.php">Kenaikan Kelas</a>
                <a class="collapse-item <?= ($current_page == 'lulus.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/lulus.php">Kelulusan Siswa</a>
                <a class="collapse-item <?= ($current_page == 'mapel.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/master/mapel.php">Mata Pelajaran</a>
            </div>
        </div>

        <?php
        $pengguna_pages = ['data-admin.php', 'guru.php', 'siswa.php'];
        ?>
        <a class="nav-link justify-content-between d-flex align-items-center <?= menu_active($pengguna_pages) ?>"
           data-bs-toggle="collapse" href="#menuPengguna" role="button" aria-expanded="<?= esc(menu_show($pengguna_pages) === 'show' ? 'true' : 'false') ?>">
            <span><i class="fas fa-users-cog me-2"></i> Data Pengguna</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        <div class="collapse <?= menu_show($pengguna_pages) ?>" id="menuPengguna">
            <div class="collapse-inner">
                <a class="collapse-item <?= esc(($current_page == 'data-admin.php') ? 'active' : '') ?>" 
                href="<?= esc(BASE_URL) ?>admin/master/data-admin.php">Admin</a>
                <a class="collapse-item <?= ($current_page == 'guru.php') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/master/guru.php">Guru</a>
                <a class="collapse-item <?= ($current_page == 'siswa.php') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/master/siswa.php">Siswa</a>
            </div>
        </div>

        <?php
            $is_ujian_active = ($current_dir == 'bank-soal' || $current_dir == 'jadwal' || $current_page == 'cetak-kartu.php');
        ?>
        <a class="nav-link justify-content-between d-flex align-items-center <?= $is_ujian_active ? 'active' : '' ?>"
           data-bs-toggle="collapse" href="#menuUjian" role="button" aria-expanded="<?= esc($is_ujian_active ? 'true' : 'false') ?>">
            <span><i class="fas fa-edit me-2"></i> Ujian</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        <div class="collapse <?= $is_ujian_active ? 'show' : '' ?>" id="menuUjian">
            <div class="collapse-inner">
                <a class="collapse-item <?= ($current_dir == 'bank-soal') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/bank-soal/index.php">Bank Soal</a>
                
                <a class="collapse-item <?= ($current_dir == 'jadwal') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/jadwal/index.php">Jadwal Ujian</a>
                
                <a class="collapse-item <?= ($current_page == 'cetak-kartu.php') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/siswa/cetak-kartu.php">Cetak Kartu</a>
            </div>
        </div>

        <?php $monitor_pages = ['index.php', 'log-pelanggaran.php', 'device-lock.php']; ?>
        <a class="nav-link justify-content-between d-flex align-items-center <?= ($current_dir == 'monitoring') ? 'active' : '' ?>" 
           data-bs-toggle="collapse" href="#menuMonitoring" role="button" aria-expanded="<?= esc(($current_dir == 'monitoring') ? 'true' : 'false') ?>">
            <span><i class="fas fa-desktop me-2"></i> Monitoring</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        <div class="collapse <?= ($current_dir == 'monitoring') ? 'show' : '' ?>" id="menuMonitoring">
            <div class="collapse-inner">
                <a class="collapse-item <?= ($current_dir == 'monitoring' && $current_page == 'index.php') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/monitoring/index.php">Monitoring Peserta</a>
                
                <a class="collapse-item <?= ($current_page == 'log-pelanggaran.php') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/monitoring/log-pelanggaran.php">Log Pelanggaran</a>
                
                <a class="collapse-item <?= ($current_page == 'device-lock.php') ? 'active' : '' ?>" 
                href="<?= esc(BASE_URL) ?>admin/monitoring/device-lock.php">Device Lock</a>
            </div>
        </div>

        <!-- <?php $laporan_pages = ['nilai.php', 'analisis-soal.php', 'rekap-nilai.php']; ?>
        <a class="nav-link justify-content-between d-flex align-items-center <?= menu_active($laporan_pages) ?>" 
           data-bs-toggle="collapse" href="#menuLaporan" role="button" aria-expanded="<?= esc(in_array($current_page, $laporan_pages) ? 'true' : 'false') ?>">
            <span><i class="fas fa-print me-2"></i> Laporan</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        <div class="collapse <?= menu_show($laporan_pages) ?>" id="menuLaporan">
            <div class="collapse-inner">
                <a class="collapse-item <?= ($current_page == 'nilai.php') ? 'active' : '' ?>" 
                   href="<?= esc(BASE_URL) ?>admin/laporan/nilai.php">
                   <i class="fas fa-poll me-1 small"></i> Nilai Ujian</a>
                
                <a class="collapse-item <?= ($current_page == 'analisis-soal.php') ? 'active' : '' ?>" 
                   href="<?= esc(BASE_URL) ?>admin/laporan/analisis-soal.php">
                   <i class="fas fa-chart-pie me-1 small"></i> Analisis Soal</a>
                
                <a class="collapse-item <?= ($current_page == 'rekap-nilai.php') ? 'active' : '' ?>" 
                   href="<?= esc(BASE_URL) ?>admin/laporan/rekap-nilai.php">
                   <i class="fas fa-file-excel me-1 small"></i> Rekap Nilai</a>
            </div>
        </div> -->

        <?php 
            // Definisikan kontrol aktif untuk grup Sistem
            $is_sistem_active = ($current_dir == 'sistem' || $current_dir == 'setting' || $current_page == 'activity-log.php');
        ?>
        
        <a class="nav-link justify-content-between d-flex align-items-center <?= $is_sistem_active ? 'active' : '' ?>" 
           data-bs-toggle="collapse" 
           href="#menuSistem" 
           role="button" 
           aria-expanded="<?= $is_sistem_active ? 'true' : 'false' ?>"
           aria-controls="menuSistem">
            <span><i class="fas fa-cogs me-2"></i> Sistem</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        
        <div class="collapse <?= $is_sistem_active ? 'show' : '' ?>" id="menuSistem">
            <div class="collapse-inner">
                <a class="collapse-item <?= ($current_page == 'backup.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/sistem/backup.php">Backup Database</a>

                <a class="collapse-item <?= ($current_page == 'system-info.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/sistem/system-info.php">Informasi Sistem</a>

                <a class="collapse-item <?= ($current_dir == 'setting') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/setting/index.php">Settings Sekolah</a>

                <a class="collapse-item <?= ($current_page == 'activity-log.php') ? 'active' : '' ?>"
                href="<?= esc(BASE_URL) ?>admin/sistem/activity-log.php">Activity Log</a>
            </div>
        </div>

        <?php $is_display_publik = ($current_dir === 'display-publik'); ?>
        <a class="nav-link justify-content-between d-flex align-items-center <?= $is_display_publik ? 'active' : '' ?>"
           data-bs-toggle="collapse" href="#menuDisplayPublik" role="button"
           aria-expanded="<?= $is_display_publik ? 'true' : 'false' ?>"
           aria-controls="menuDisplayPublik">
            <span><i class="fas fa-tv me-2"></i> Tampilan Publik</span>
            <i class="fas fa-chevron-right small rotate-icon"></i>
        </a>
        <div class="collapse <?= $is_display_publik ? 'show' : '' ?>" id="menuDisplayPublik">
            <div class="collapse-inner">
                <a class="collapse-item <?= ($is_display_publik && $current_page === 'index.php') ? 'active' : '' ?>"
                   href="<?= esc(BASE_URL) ?>admin/display-publik/index.php">Pengaturan Layar</a>
            </div>
        </div>

        <hr class="my-2 mx-3">
        <a href="<?= esc(BASE_URL) ?>auth/logout.php" class="nav-link text-danger mb-5">
            <i class="fas fa-sign-out-alt me-2"></i> <span>Keluar</span>
        </a>

    </div>
</nav>

<style>
#sidebar .nav-link {
    color: #5a5c69;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    border-radius: 8px;
    margin: 0.1rem 0.5rem;
    transition: all 0.2s;
}

#sidebar .nav-link:hover, #sidebar .nav-link.active {
    background-color: #f8f9fc;
    color: #4e73df;
    font-weight: 600;
}

#sidebar .collapse-inner {
    padding: 0.5rem 0 0.5rem 1.5rem;
}

#sidebar .collapse-item {
    display: block;
    padding: 0.4rem 1rem;
    color: #858796;
    text-decoration: none;
    font-size: 0.85rem;
    border-left: 2px solid #e3e6f0;
}

#sidebar .collapse-item:hover, #sidebar .collapse-item.active {
    color: #4e73df;
    border-left: 2px solid #4e73df;
    background: transparent;
}

.rotate-icon {
    transition: transform 0.3s;
}

.nav-link[aria-expanded="true"] .rotate-icon {
    transform: rotate(90deg);
}

.sidebar-heading {
    letter-spacing: 0.1rem;
}
</style>

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