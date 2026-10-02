<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Ambil data Mata Pelajaran
try {
    $subjects = $pdo->query("SELECT * FROM cbt_subjects ORDER BY nama_mapel ASC")->fetchAll();
} catch (Exception $e) {
    $subjects = []; 
}

// 2. Ambil data guru dari tabel cbt_teachers
try {
    $teachers = $pdo->query("SELECT id, nama_lengkap FROM cbt_teachers WHERE is_aktif = 1 ORDER BY nama_lengkap ASC")->fetchAll();
    
    // Jika tabel ada tapi isinya kosong, berikan fallback agar tidak error
    if (!$teachers) {
        $teachers = [['id' => 1, 'nama_lengkap' => 'Administrator Utama']];
    }
} catch (Exception $e) {
    // Jika tabel cbt_teachers belum dibuat atau ada error lain
    $teachers = [['id' => 1, 'nama_lengkap' => 'Administrator Utama']];
}


?>

<!DOCTYPE html>
<html lang="id">
    <?php include dirname(__DIR__, 3) . '/includes/header.php'; ?>

    <style>
        .select2-container--bootstrap-5 .select2-selection {
            border-color: #0d6efd !important; /* Warna biru primer */
        }
    </style>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 3) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>

                <div>
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fas fa-file-archive me-2"></i> Paket Backup & Restore (.zip)
                    </h5>
                    <small class="text-muted">Kelola perpindahan data antar server</small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> Data dan Gambar berhasil dipulihkan (Restore)!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-primary text-white py-3 border-0">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-filter me-2"></i> Filter Backup Soal</h6>
                        </div>
                        <div class="card-body p-4">
                            <form action="../exports/export-soal.php" method="GET">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-uppercase">1. Pilih Mata Pelajaran</label>
                                    <select id="filter_mapel" class="form-select border-primary shadow-sm">
                                        <option value="">-- Semua Mata Pelajaran --</option>
                                        <?php foreach($subjects as $s): ?>
                                            <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_mapel']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-uppercase">2. Cari & Pilih Bank Soal</label>
                                    <select name="id" id="select_bank_soal" class="form-select" required>
                                        <option value="">-- Ketik Nama Mata Pelajaran atau Kode Soal --</option>
                                    </select>
                                    <small class="text-muted mt-2 d-block" style="font-size: 11px;">
                                        <i class="fas fa-info-circle"></i> Ketik nama soal untuk mencari di antara ribuan data secara real-time.
                                    </small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                                    <i class="fas fa-file-download me-2"></i> Download Paket ZIP
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-success text-white py-3 border-0">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-file-import me-2"></i> Restore Paket Soal + Gambar</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small">Unggah file <b>.zip</b> hasil backup. Sistem akan mengekstrak data soal dan mengembalikan gambar secara otomatis.</p>
                            
                            <form action="restore-soal.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="restore" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-uppercase">File Paket Backup (.zip)</label>
                                    <input type="file" name="backup_file" class="form-control border-success" accept=".zip" required>
                                </div>

                                <div class="row g-2 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase">Mapel Tujuan</label>
                                        <select name="subject_id" class="form-select form-select-sm" required>
                                            <option value="">-- Pilih Mapel --</option>
                                            <?php foreach($subjects as $s): ?>
                                                <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_mapel']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase">Guru Pemilik</label>
                                        <select name="teacher_id" class="form-select form-select-sm" required>
                                            <?php foreach($teachers as $t): ?>
                                                <option value="<?= esc($t['id']) ?>"><?= esc($t['nama_lengkap']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                                    <i class="fas fa-upload me-2"></i> Jalankan Restore Paket
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div> <div class="alert alert-info border-0 shadow-sm mt-4">
                <div class="d-flex">
                    <div class="me-3"><i class="fas fa-info-circle fa-2x"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1 text-uppercase">Penting:</h6>
                        <ul class="mb-0 small">
                            <li>Pastikan folder <code>assets/uploads/soal/</code> berstatus <b>Writable</b> (CHMOD 777).</li>
                            <li>Restore tidak menghapus data lama, melainkan menambah Bank Soal baru.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= esc(BASE_URL) ?>assets/vendor/select2/select2.min.js"></script>

<script>
$(document).ready(function() {
    // --- 1. LOGIKA NOTIFIKASI (SweetAlert2) ---
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const type = urlParams.get('type');

    if (status === 'error') {
        let title = 'Restore Gagal!';
        let text = 'Terjadi kesalahan sistem.';
        let icon = 'error';

        if (type === 'duplicate') {
            title = 'Duplikasi Data!';
            text = 'Kode Bank Soal sudah ada di database atau format file tidak sesuai (Kode Kosong). Silakan cek kembali file backup Anda.';
            icon = 'warning';
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showConfirmButton: true,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Saya Mengerti',
            customClass: {
                popup: 'shadow-sm border-0',
                title: 'fw-bold text-dark'
            }
        });
    }

    if (status === 'success') {
        Swal.fire({
            title: 'Berhasil!',
            text: 'Data soal dan gambar telah dipulihkan.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true
        });
    }


    // --- 2. INISIALISASI SELECT2 (Pencarian Bank Soal) ---
    if ($.fn.select2) {
        $('#select_bank_soal').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Pilih / Cari Bank Soal --',
            allowClear: true,
            width: '100%',
            ajax: {
                // Menggunakan BASE_URL agar path tidak pecah karena .htaccess
                url: '<?= BASE_URL ?>admin/bank-soal/ajax/get-filtered-soal.php', 
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        q: params.term, 
                        subject_id: $('#filter_mapel').val() 
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results 
                    };
                },
                cache: true
            }
        });
    }


    // --- 3. EVENT HANDLER ---
    // Reset Select2 jika dropdown Mapel diganti
    $('#filter_mapel').on('change', function() {
        $('#select_bank_soal').val(null).trigger('change');
    });

});
</script>

</body>
</html>