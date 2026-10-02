<?php
session_start();
require_once '../../config/database.php';
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <?php
        $msg = $_GET['msg'] ?? '';
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
        ?>
        <?php if ($msg === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Upload Berhasil!</h6>
                        <span><?= $count ?> soal berhasil diimpor ke dalam sistem.</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($msg === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-times-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Import Gagal!</h6>
                        <span>Terjadi kesalahan saat memproses file. Pastikan format file sesuai template.</span>
                        <?php if (!empty($_GET['error_info'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(urldecode($_GET['error_info'])) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($msg === 'error_data'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Data Tidak Lengkap!</h6>
                        <span>Pastikan file dipilih dan Bank Soal sudah benar.</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <h4 class="mb-0 fw-bold">Upload Soal via Excel / Word</h4>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">1. Unduh Template</h5>
                    <p class="text-muted small">Silakan unduh template di bawah ini dan isi data soal sesuai format agar tidak terjadi error saat sistem membaca file.</p>
                    
                    <div class="d-flex gap-2">
                        <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/generate_template_excel.php" class="btn btn-outline-success">
                            <i class="fas fa-file-excel me-2"></i> Template Excel (.xlsx)
                        </a>

                        <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/generate_template.php" class="btn btn-outline-primary">
                            <i class="fas fa-file-word me-2"></i> Template Word (.docx)
                        </a>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-danger">* Mohon jangan mengubah struktur tabel pada template Word agar proses pembacaan sistem tidak error.</small>
                    </div>
                    <div class="mt-3 p-3 bg-light border rounded">
                        <strong class="small"><i class="fas fa-square-root-alt me-1 text-primary"></i> Formula Matematika (LaTeX)</strong>
                        <p class="small text-muted mb-1 mt-1">Tulis notasi LaTeX langsung sebagai teks di kolom ISI/SOAL. Jangan gunakan equation editor bawaan Word.</p>
                        <ul class="small text-muted mb-0 ps-3">
                            <li>Inline: <code>$x^2 + y^2 = z^2$</code></li>
                            <li>Display: <code>$$\frac{-b \pm \sqrt{b^2-4ac}}{2a}$$</code></li>
                            <li>Pecahan: <code>$\frac{a}{b}$</code> &nbsp;&nbsp; Akar: <code>$\sqrt{x}$</code></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">2. Pilih File & Upload</h5>
                    <form action="../bank-soal/controllers/proses-upload-soal.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pilih File (Excel)</label>
                            <input type="file" name="file_soal" class="form-control" accept=".xlsx, .xls" required>
                        </div>

                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <strong>Catatan:</strong> Untuk soal yang mengandung gambar, disarankan untuk mengupload gambar secara manual melalui menu <em>Tambah Soal Manual</em> setelah proses import selesai.
                        </div>

                        <button type="submit" name="import_soal" class="btn btn-primary w-100 py-2 fw-bold">
                            <i class="fas fa-upload me-2"></i> Mulai Import Soal
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">3. Upload via Word (.docx)</h5>
                    <form action="../bank-soal/controllers/proses-upload-word.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pilih File (Word)</label>
                            <input type="file" name="file_soal" class="form-control" accept=".docx, .doc" required>
                        </div>

                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <strong>Catatan:</strong> Untuk soal yang mengandung gambar, disarankan untuk mengupload gambar secara manual melalui menu <em>Tambah Soal Manual</em> setelah proses import selesai.
                        </div>

                        <button type="submit" name="import_soal" class="btn btn-primary w-100 py-2 fw-bold">
                            <i class="fas fa-upload me-2"></i> Mulai Import Soal
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>