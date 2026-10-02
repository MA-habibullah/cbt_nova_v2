<?php
require_once '../../config/database.php';

// PENENTU SIDEBAR AKTIF
$current_dir = 'sistem'; 
$current_page = 'backup.php';

$taAktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Ganti bagian folder penyimpanan backup dengan ini agar lebih informatif
$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

if (!is_writable($backupDir)) {
    echo "<script>alert('Peringatan: Folder backups tidak dapat ditulis! Cek izin folder (Permission).');</script>";
}

// Ambil daftar file backup yang ada
$files = array_diff(scandir($backupDir), array('.', '..'));
rsort($files); // Urutkan dari yang terbaru
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>
    <style>
        .card-stats { border-left: 4px solid #0d6efd; }
        .table th { font-size: 11px; text-transform: uppercase; color: #6c757d; }
    </style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-database me-2 text-primary"></i> Database Backup & Restore</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4 text-center">
                            <div class="bg-primary-subtle text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-cloud-download-alt fa-2x"></i>
                            </div>
                            <h5 class="fw-bold">Buat Backup Baru</h5>
                            <p class="small text-muted mb-4">Simpan seluruh data sistem (soal, user, hasil ujian) ke dalam file SQL.</p>
                            <button onclick="prosesBackup()" class="btn btn-primary w-100 fw-bold rounded-pill">
                                <i class="fas fa-play me-2"></i> Jalankan Backup
                            </button>
                        </div>
                    </div>

                    <!-- <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4 text-center">
                            <div class="bg-success-subtle text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-file-archive fa-2x"></i>
                            </div>
                            <h5 class="fw-bold">Full System Backup</h5>
                            <p class="small text-muted mb-4">Backup Database + Seluruh File Website (.zip)</p>
                            <button onclick="prosesBackupFull()" class="btn btn-success w-100 fw-bold rounded-pill">
                                <i class="fas fa-file-export me-2"></i> Jalankan Full Backup
                            </button>
                        </div>
                    </div> -->

                    <div class="card border-0 shadow-sm rounded-4 bg-dark text-white">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Informasi</h6>
                            <ul class="small opacity-75 ps-3">
                                <li>File backup disimpan di folder <code>/backups/</code></li>
                                <li>Gunakan file ini untuk pindah server atau recovery data.</li>
                                <li>Disarankan backup setiap selesai sesi ujian.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Riwayat Backup Database</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Nama File</th>
                                        <th>Ukuran</th>
                                        <th>Tanggal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($files)): ?>
                                        <tr><td colspan="4" class="p-5 text-center text-muted italic">Belum ada file backup tersedia.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($files as $file): 
                                            $filePath = $backupDir . $file;
                                            $size = round(filesize($filePath) / 1024, 2); // KB
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark font-monospace" style="font-size: 13px;"><?= $file ?></div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border fw-normal"><?= $size ?> KB</span></td>
                                            <td class="small text-muted"><?= date("d M Y H:i", filemtime($filePath)) ?></td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="download_backup.php?file=<?= esc(urlencode($file)) ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button onclick="hapusBackup('<?= esc($file) ?>')" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>
    function prosesBackup() {
        Swal.fire({
            title: 'Memulai Backup...',
            text: 'Harap tunggu sebentar, sistem sedang mengekspor database.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'proses_backup.php',
            type: 'POST',
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            },
            error: function(xhr, status, err) {
                Swal.fire('Gagal!', 'Terjadi kesalahan server. Periksa log PHP untuk detail.', 'error');
            }
        });
    }

    function hapusBackup(filename) {
        Swal.fire({
            title: 'Hapus File?',
            text: "File backup ini akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('hapus_backup.php', { file: filename }, function() {
                    location.reload();
                });
            }
        });
    }

    $(document).ready(function() {
        // 1. Perbaiki perilaku klik pada menu sidebar yang 'terkunci'
        $('.nav-link, .collapse-item').on('click', function(e) {
            var targetUrl = $(this).attr('href');

            // Jika link mengandung URL asli (bukan sekadar ID target collapse)
            if (targetUrl && targetUrl !== '#' && !targetUrl.startsWith('#')) {
                // Hentikan proses collapse Bootstrap agar tidak bentrok
                e.stopPropagation();
                // Paksa pindah halaman
                window.location.href = targetUrl;
            }
        });

        // 2. Bersihkan URL dari # yang tertinggal saat halaman dimuat
        if (window.location.hash) {
            var clean_uri = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search;
            window.history.replaceState({}, document.title, clean_uri);
        }
    });
    
    function prosesBackupFull() {
        Swal.fire({
            title: 'Sedang Mengompres File...',
            text: 'Proses ini memakan waktu tergantung besar ukuran file website Anda.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'proses_backup_full.php',
            type: 'POST',
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success').then(() => { location.reload(); });
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            }
        });
    }
</script>
</body>
</html>  