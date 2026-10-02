<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = $_SESSION['teacher_id'];
$id_bank    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify ownership
$stmt = $pdo->prepare("SELECT id, status FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?");
$stmt->execute([$id_bank, $teacher_id]);
$bank = $stmt->fetch();
if (!$bank) { header("Location: index.php"); exit; }

if ($bank['status'] === 'nonaktif') {
    header("Location: detail.php?id=$id_bank&msg=locked"); exit;
}

$msg   = $_GET['msg']   ?? '';
$count = (int)($_GET['count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Upload Soal via Excel / Word</h5>
                    <small class="text-muted">Import soal cepat ke bank soal</small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <div class="row justify-content-center">
                <div class="col-md-8">

                    <?php if ($msg === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
                        <i class="fas fa-check-circle fa-2x me-3 float-start"></i>
                        <strong>Upload Berhasil!</strong> <?= $count ?> soal berhasil diimpor.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif ($msg === 'error'): ?>
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
                        <i class="fas fa-times-circle fa-2x me-3 float-start"></i>
                        <strong>Import Gagal!</strong> Terjadi kesalahan saat memproses file.
                        <?php if (!empty($_GET['error_info'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(urldecode($_GET['error_info'])) ?></small>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif ($msg === 'error_data'): ?>
                    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4">
                        <i class="fas fa-exclamation-triangle fa-2x me-3 float-start"></i>
                        <strong>Data Tidak Lengkap!</strong> Pastikan file dipilih dan Bank Soal sudah benar.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Download Template -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">1. Unduh Template</h5>
                            <p class="text-muted small">Isi data soal sesuai format template agar tidak terjadi error saat import.</p>
                            <div class="d-flex gap-2">
                                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/generate_template_excel.php" class="btn btn-outline-success">
                                    <i class="fas fa-file-excel me-2"></i> Template Excel (.xlsx)
                                </a>
                                <a href="<?= esc(BASE_URL) ?>guru/bank-soal/exports/generate_template.php" class="btn btn-outline-primary">
                                    <i class="fas fa-file-word me-2"></i> Template Word (.docx)
                                </a>
                            </div>
                            <small class="text-danger mt-2 d-block">* Jangan ubah struktur tabel pada template Word.</small>
                            <div class="mt-3 p-3 bg-light border rounded">
                                <strong class="small"><i class="fas fa-square-root-alt me-1 text-primary"></i> Formula Matematika (LaTeX)</strong>
                                <p class="small text-muted mb-1 mt-1">Tulis notasi LaTeX langsung sebagai teks. Jangan gunakan equation editor bawaan Word.</p>
                                <ul class="small text-muted mb-0 ps-3">
                                    <li>Inline: <code>$x^2 + y^2 = z^2$</code></li>
                                    <li>Display: <code>$$\frac{-b \pm \sqrt{b^2-4ac}}{2a}$$</code></li>
                                    <li>Pecahan: <code>$\frac{a}{b}$</code> &nbsp;&nbsp; Akar: <code>$\sqrt{x}$</code></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Excel -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">2. Upload via Excel (.xlsx)</h5>
                            <form action="<?= esc(BASE_URL) ?>admin/bank-soal/controllers/proses-upload-soal.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Pilih File Excel</label>
                                    <input type="file" name="file_soal" class="form-control" accept=".xlsx,.xls" required>
                                </div>
                                <div class="alert alert-warning small">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Untuk soal bergambar, disarankan tambah manual setelah import selesai.
                                </div>
                                <button type="submit" name="import_soal" class="btn btn-primary w-100 py-2 fw-bold">
                                    <i class="fas fa-upload me-2"></i> Mulai Import Soal
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Upload Word -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">3. Upload via Word (.docx)</h5>
                            <form action="<?= esc(BASE_URL) ?>admin/bank-soal/controllers/proses-upload-word.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Pilih File Word</label>
                                    <input type="file" name="file_soal" class="form-control" accept=".docx,.doc" required>
                                </div>
                                <div class="alert alert-warning small">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Untuk soal bergambar, disarankan tambah manual setelah import selesai.
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
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>
