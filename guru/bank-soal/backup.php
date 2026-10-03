<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = $_SESSION['teacher_id'];
$id_bank    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify ownership
$stmt = $pdo->prepare("SELECT b.*, s.nama_mapel FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ? AND b.teacher_id = ?");
$stmt->execute([$id_bank, $teacher_id]);
$bank = $stmt->fetch();
if (!$bank) { header("Location: index.php"); exit; }

$locked   = ($bank['status'] === 'nonaktif');
$subjects = $pdo->query("SELECT * FROM cbt_subjects ORDER BY nama_mapel ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/guru/includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-box-archive text-primary me-2"></i> Paket Backup & Restore (.zip)</h5>
                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= esc($bank['kode_bank_soal']) ?></span>
                        </div>
                        <small class="text-muted"><?= esc($bank['nama_bank_soal']) ?> &bull; <?= esc($bank['nama_mapel']) ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if ($locked): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center p-3 mb-4">
                <div class="fs-3 text-danger me-3"><i class="fas fa-lock"></i></div>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Admin telah mengunci bank soal ini. Fitur Backup dan Restore dibekukan sementara hingga dibuka kembali.
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-<?= $_GET['status']==='success'?'success':'danger' ?> border-0 shadow-sm rounded-4 mb-4 p-3 alert-dismissible fade show d-flex align-items-center">
                <div class="fs-3 me-3 text-<?= $_GET['status']==='success'?'success':'danger' ?>">
                    <i class="fas fa-<?= $_GET['status']==='success'?'check-circle':'times-circle' ?>"></i>
                </div>
                <div>
                    <?php if ($_GET['status']==='success'): ?>
                        <strong class="d-block">Restore Berhasil!</strong> Data soal dan stimulus gambar berhasil dipulihkan.
                    <?php else: ?>
                        <strong class="d-block">Restore Gagal!</strong> <?= $_GET['type']==='duplicate' ? 'Kode Bank Soal sudah ada di database.' : 'Terjadi kesalahan sistem saat memproses berkas zip.' ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Backup / Download -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white h-100 <?= $locked ? 'opacity-50' : '' ?>">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                    <i class="fas fa-file-download fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Ekspor / Backup Paket Soal</h6>
                                    <small class="text-muted">Unduh paket arsip soal beserta gambar</small>
                                </div>
                            </div>
                            <?php if ($locked): ?><span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1"><i class="fas fa-lock me-1"></i>Terkunci</span><?php endif; ?>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <p class="text-secondary small mb-3">Unduh seluruh butir soal beserta seluruh file gambar stimulus dari bank soal ini dalam satu arsip terkompresi <code>.zip</code>.</p>
                                <div class="p-3 bg-light rounded-3 border mb-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1 mb-1"><?= esc($bank['kode_bank_soal']) ?></span>
                                            <h6 class="fw-bold text-dark mb-0"><?= esc($bank['nama_bank_soal']) ?></h6>
                                        </div>
                                        <span class="badge bg-secondary-subtle text-secondary"><?= esc($bank['nama_mapel']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <?php if (!$locked): ?>
                                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/export-soal.php?id=<?= esc($id_bank) ?>"
                                   class="btn btn-primary w-100 fw-semibold py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-download"></i> Unduh Paket ZIP Soal
                                </a>
                                <?php else: ?>
                                <button class="btn btn-secondary w-100 fw-semibold py-2 rounded-3" disabled>
                                    <i class="fas fa-lock me-2"></i> Tidak Tersedia (Terkunci)
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Restore -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white h-100 <?= $locked ? 'opacity-50' : '' ?>">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                    <i class="fas fa-file-import fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Restore Paket Soal (.zip)</h6>
                                    <small class="text-muted">Ekstrak otomatis paket arsip soal</small>
                                </div>
                            </div>
                            <?php if ($locked): ?><span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1"><i class="fas fa-lock me-1"></i>Terkunci</span><?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-secondary small mb-3">Upload berkas <code>.zip</code> hasil backup. Sistem akan membuat entri bank soal baru dan mengekstrak gambar secara otomatis.</p>
                            <?php if (!$locked): ?>
                            <form action="backup/restore-soal.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="restore" value="1">
                                <input type="hidden" name="bank_id_back" value="<?= esc($id_bank) ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary text-uppercase">File Paket Backup (.zip)</label>
                                    <input type="file" name="backup_file" class="form-control rounded-3 py-2 border-secondary-subtle" accept=".zip" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-secondary text-uppercase">Mata Pelajaran Tujuan</label>
                                    <select name="subject_id" class="form-select rounded-3 py-2 border-secondary-subtle" required>
                                        <option value="">-- Pilih Mapel --</option>
                                        <?php foreach ($subjects as $s): ?>
                                        <option value="<?= esc($s['id']) ?>" <?= esc($s['id']==$bank['subject_id']?'selected':'') ?>>
                                            <?= htmlspecialchars($s['nama_mapel']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100 fw-semibold py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-cloud-arrow-up"></i> Jalankan Restore
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-secondary w-100 fw-semibold py-2 rounded-3 mt-4" disabled>
                                <i class="fas fa-lock me-2"></i> Tidak Tersedia (Terkunci)
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info border-0 shadow-sm rounded-4 mt-4 bg-primary-subtle text-primary-emphasis p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="fs-3"><i class="fas fa-circle-info"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Catatan Penting:</h6>
                        <ul class="mb-0 small ps-3">
                            <li>Proses restore tidak menghapus data bank soal yang ada, melainkan membuat <strong>Bank Soal Baru</strong> secara terpisah.</li>
                            <li>Pastikan folder media <code>assets/uploads/soal/</code> berstatus Writable.</li>
                        </ul>
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
