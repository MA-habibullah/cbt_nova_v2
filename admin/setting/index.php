<?php
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Ambil data setting
$stmt = $pdo->query("SELECT * FROM cbt_settings LIMIT 1");
$setting = $stmt->fetch();

/**
 * LOGIC PENCEGAHAN ERROR (NULL COALESCING)
 * Jika database kosong, kita siapkan string kosong agar tidak muncul Warning
 */
$nama_sekolah   = $setting['nama_sekolah'] ?? '';
$kepala_sekolah = $setting['kepala_sekolah'] ?? '';
$nip_kepala     = $setting['nip_kepala'] ?? '';
$alamat_sekolah = $setting['alamat_sekolah'] ?? '';
$kota           = $setting['kota'] ?? '';
$email_sekolah  = $setting['email_sekolah'] ?? '';
$judul_kartu    = $setting['judul_kartu'] ?? '';
$logo_db        = $setting['logo'] ?? '';

// Cek apakah data ada dan bukan merupakan string kosong atau null
if ($setting && !empty($setting['tgl_cetak']) && $setting['tgl_cetak'] !== '0000-00-00 00:00:00') {
    $time = strtotime($setting['tgl_cetak']);
    
    // Pastikan hasil strtotime valid (lebih dari tahun 1970)
    if ($time > 0) {
        $tgl_cetak_value = date('Y-m-d\TH:i', $time);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-school me-2"></i> Identitas Sekolah</h5>
        </nav>

        <div class="container-fluid px-4 py-4">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Pengaturan berhasil diperbarui!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="setting-proses.php" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-3 text-center border-end">
                                <label class="fw-bold mb-3 d-block">Logo Sekolah</label>
                                <?php 
                                    $preview = (!empty($logo_db)) ? BASE_URL . 'assets/img/logo/' . $logo_db : BASE_URL . 'assets/img/logo/logo.png';
                                ?>
                                <img src="<?= esc($preview) ?>" class="img-thumbnail mb-3" style="max-height: 150px; width: 150px; object-fit: contain;" id="previewLogo">
                                <input type="file" name="logo" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this)">
                                <small class="text-muted d-block mt-2">Format: JPG, PNG. Max 2MB</small>
                            </div>

                            <div class="col-md-9 ps-md-4">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="small fw-bold">NAMA SEKOLAH</label>
                                        <input type="text" name="nama_sekolah" class="form-control" value="<?= htmlspecialchars($nama_sekolah) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold">KEPALA SEKOLAH</label>
                                        <input type="text" name="kepala_sekolah" class="form-control" value="<?= htmlspecialchars($kepala_sekolah) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold">NIP KEPALA SEKOLAH</label>
                                        <input type="text" name="nip_kepala" class="form-control" value="<?= htmlspecialchars($nip_kepala) ?>" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="small fw-bold">ALAMAT SEKOLAH</label>
                                        <textarea name="alamat_sekolah" class="form-control" rows="2" required><?= htmlspecialchars($alamat_sekolah) ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold">KOTA / KABUPATEN</label>
                                        <input type="text" name="kota" class="form-control" value="<?= htmlspecialchars($kota) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold">EMAIL SEKOLAH</label>
                                        <input type="email" name="email_sekolah" class="form-control" value="<?= htmlspecialchars($email_sekolah) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold">JUDUL KARTU UJIAN</label>
                                        <input type="text" name="judul_kartu" class="form-control" value="<?= htmlspecialchars($judul_kartu) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold">TANGGAL CETAK LAPORAN</label>
                                        <input type="datetime-local" 
                                            name="tgl_cetak" 
                                            class="form-control" 
                                            value="<?= esc($tgl_cetak_value) ?>">
                                    </div>
                                </div>

                                <div class="mt-4 border-top pt-3 text-end">
                                    <button type="submit" name="update_setting" class="btn btn-primary px-5 fw-bold">
                                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewLogo').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>