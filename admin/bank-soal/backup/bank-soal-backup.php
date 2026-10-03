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
    if (!$teachers) {
        $teachers = [['id' => 1, 'nama_lengkap' => 'Administrator Utama']];
    }
} catch (Exception $e) {
    $teachers = [['id' => 1, 'nama_lengkap' => 'Administrator Utama']];
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 3) . '/includes/header.php'; ?>

<style>
    .select2-container--bootstrap-5 .select2-selection {
        border-color: #cbd5e1 !important;
        border-radius: 0.5rem !important;
        min-height: 42px !important;
        display: flex !important;
        align-items: center !important;
    }
    .select2-container--bootstrap-5 .select2-selection:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15) !important;
    }
</style>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 3) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-file-archive text-primary me-2"></i> Paket Backup & Restore (.zip)
                        </h5>
                        <small class="text-muted">Kelola ekspor dan pemulihan data butir soal beserta stimulus gambar antar server</small>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 alert-dismissible fade show d-flex align-items-center">
                    <div class="me-3 fs-4 text-success"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <strong class="d-block">Restore Berhasil!</strong>
                        Data soal dan stimulus gambar berhasil dipulihkan secara utuh ke database.
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <!-- Card Backup / Download -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-3">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                <i class="fas fa-file-download fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Ekspor / Backup Paket Soal</h6>
                                <small class="text-muted">Unduh paket arsip soal beserta gambar</small>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <form action="../exports/export-soal.php" method="GET">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary text-uppercase">1. Filter Mata Pelajaran</label>
                                    <select id="filter_mapel" class="form-select rounded-3 py-2 border-secondary-subtle">
                                        <option value="">-- Semua Mata Pelajaran --</option>
                                        <?php foreach($subjects as $s): ?>
                                            <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_mapel']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-secondary text-uppercase">2. Pilih Bank Soal</label>
                                    <select name="id" id="select_bank_soal" class="form-select" required>
                                        <option value="">-- Ketik Nama Mata Pelajaran atau Kode Soal --</option>
                                    </select>
                                    <div class="d-flex align-items-center gap-1 text-muted mt-2 small">
                                        <i class="fas fa-circle-info text-primary"></i>
                                        <span>Ketik nama mapel atau kode bank soal untuk pencarian instan.</span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-semibold py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-download"></i> Unduh Paket ZIP Soal
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card Restore -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-3">
                            <div class="p-2 bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                <i class="fas fa-file-import fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Restore Paket Soal + Gambar</h6>
                                <small class="text-muted">Ekstrak otomatis paket arsip soal dari server lain</small>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-secondary small mb-3">Unggah file <code>.zip</code> hasil backup. Sistem akan mengekstrak butir soal dan memulihkan seluruh file gambar stimulus secara otomatis.</p>
                            
                            <form action="restore-soal.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="restore" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary text-uppercase">File Paket Backup (.zip)</label>
                                    <input type="file" name="backup_file" class="form-control rounded-3 py-2 border-secondary-subtle" accept=".zip" required>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary text-uppercase">Mapel Tujuan</label>
                                        <select name="subject_id" class="form-select rounded-3 py-2 border-secondary-subtle" required>
                                            <option value="">-- Pilih Mapel --</option>
                                            <?php foreach($subjects as $s): ?>
                                                <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_mapel']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary text-uppercase">Guru Pengampu</label>
                                        <select name="teacher_id" class="form-select rounded-3 py-2 border-secondary-subtle" required>
                                            <?php foreach($teachers as $t): ?>
                                                <option value="<?= esc($t['id']) ?>"><?= esc($t['nama_lengkap']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success w-100 fw-semibold py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-cloud-arrow-up"></i> Jalankan Pemulihan (Restore)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div> 
            
            <!-- Alert Info -->
            <div class="alert alert-info border-0 shadow-sm rounded-4 mt-4 bg-primary-subtle text-primary-emphasis p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="fs-3"><i class="fas fa-circle-info"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Informasi Penting:</h6>
                        <ul class="mb-0 small ps-3">
                            <li>Pastikan direktori <code>assets/uploads/soal/</code> memiliki izin tulis (Writable / CHMOD 777).</li>
                            <li>Proses restore tidak akan menimpa data lama, melainkan membuat entri <strong>Bank Soal Baru</strong> secara terisolasi.</li>
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
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const type = urlParams.get('type');

    if (status === 'error') {
        let title = 'Restore Gagal!';
        let text = 'Terjadi kesalahan sistem saat memproses berkas zip.';
        let icon = 'error';

        if (type === 'duplicate') {
            title = 'Duplikasi Data!';
            text = 'Kode Bank Soal sudah terdaftar di database. Silakan ganti kode pada file atau di sistem.';
            icon = 'warning';
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            confirmButtonColor: '#3b82f6',
            confirmButtonText: 'Saya Mengerti',
            customClass: {
                popup: 'shadow rounded-4 border-0',
                title: 'fw-bold text-dark'
            }
        });
    }

    if (status === 'success') {
        Swal.fire({
            title: 'Berhasil!',
            text: 'Data soal dan stimulus gambar telah berhasil dipulihkan.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true,
            customClass: {
                popup: 'shadow rounded-4 border-0'
            }
        });
    }

    if ($.fn.select2) {
        $('#select_bank_soal').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Pilih / Cari Bank Soal --',
            allowClear: true,
            width: '100%',
            ajax: {
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

    $('#filter_mapel').on('change', function() {
        $('#select_bank_soal').val(null).trigger('change');
    });
});
</script>

</body>
</html>