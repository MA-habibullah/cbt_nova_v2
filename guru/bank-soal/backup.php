<?php
session_start();
require_once '../../config/database.php';

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
<?php include '../../includes/header.php'; ?>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-database me-2"></i> Backup & Restore</h5>
                    <small class="text-muted"><?= htmlspecialchars($bank['nama_bank_soal']) ?></small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if ($locked): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-lock fa-2x me-3"></i>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Admin telah mengunci bank soal ini. Backup dan Restore tidak dapat digunakan sementara.
                    Silakan hubungi admin untuk membuka kunci.
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-<?= $_GET['status']==='success'?'success':'danger' ?> border-0 shadow-sm mb-4 alert-dismissible fade show">
                <?php if ($_GET['status']==='success'): ?>
                    <i class="fas fa-check-circle me-2"></i> Data soal berhasil dipulihkan (Restore)!
                <?php else: ?>
                    <i class="fas fa-times-circle me-2"></i>
                    <?= $_GET['type']==='duplicate' ? 'Kode Bank Soal sudah ada di database.' : 'Restore gagal.' ?>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Backup / Download -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100 <?= $locked ? 'opacity-50' : '' ?>">
                        <div class="card-header bg-primary text-white py-3 border-0">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-file-download me-2"></i> Backup / Download Paket
                                <?php if ($locked): ?><span class="badge bg-danger ms-2"><i class="fas fa-lock me-1"></i>Terkunci</span><?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small">Unduh seluruh data soal beserta gambar dari bank soal ini dalam format <b>.zip</b>.</p>
                            <div class="p-3 bg-light rounded border mb-4">
                                <div class="fw-bold"><?= htmlspecialchars($bank['nama_bank_soal']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($bank['nama_mapel']) ?></small>
                            </div>
                            <?php if (!$locked): ?>
                            <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/export-soal.php?id=<?= esc($id_bank) ?>"
                               class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                                <i class="fas fa-file-download me-2"></i> Download Paket ZIP
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary w-100 fw-bold py-2" disabled>
                                <i class="fas fa-lock me-2"></i> Tidak Tersedia (Terkunci)
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Restore -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100 <?= $locked ? 'opacity-50' : '' ?>">
                        <div class="card-header bg-success text-white py-3 border-0">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-file-import me-2"></i> Restore Paket Soal
                                <?php if ($locked): ?><span class="badge bg-danger ms-2"><i class="fas fa-lock me-1"></i>Terkunci</span><?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small">Upload file <b>.zip</b> hasil backup. Sistem akan membuat bank soal baru dari paket tersebut.</p>
                            <?php if (!$locked): ?>
                            <form action="backup/restore-soal.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="restore" value="1">
                                <input type="hidden" name="bank_id_back" value="<?= esc($id_bank) ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-uppercase">File Paket Backup (.zip)</label>
                                    <input type="file" name="backup_file" class="form-control border-success" accept=".zip" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-uppercase">Mata Pelajaran Tujuan</label>
                                    <select name="subject_id" class="form-select" required>
                                        <option value="">-- Pilih Mapel --</option>
                                        <?php foreach ($subjects as $s): ?>
                                        <option value="<?= esc($s['id']) ?>" <?= esc($s['id']==$bank['subject_id']?'selected':'') ?>>
                                            <?= htmlspecialchars($s['nama_mapel']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                                    <i class="fas fa-upload me-2"></i> Jalankan Restore
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-secondary w-100 fw-bold py-2 mt-4" disabled>
                                <i class="fas fa-lock me-2"></i> Tidak Tersedia (Terkunci)
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info border-0 shadow-sm mt-4">
                <div class="d-flex">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Catatan Penting:</h6>
                        <ul class="mb-0 small">
                            <li>Restore tidak menghapus data lama, melainkan membuat <b>Bank Soal baru</b>.</li>
                            <li>Pastikan folder <code>assets/uploads/soal/</code> berstatus <b>Writable</b>.</li>
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
