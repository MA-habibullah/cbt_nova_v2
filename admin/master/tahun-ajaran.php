<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") { csrf_verify(); }

// --- PROSES SIMPAN / TAMBAH ---
if (isset($_POST['simpan'])) {
    try {
        $tahun = $_POST['tahun'];
        $semester = $_POST['semester'];
        $stmt = $pdo->prepare("INSERT INTO cbt_tahun_ajaran (tahun, semester, is_aktif) VALUES (?, ?, 0)");
        $stmt->execute([$tahun, $semester]);
        log_activity("Tambah tahun ajaran: $tahun Semester $semester", null, null, null, 'master');
        header("Location: tahun-ajaran.php?msg=disimpan");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: tahun-ajaran.php?msg=$msg");
    }
    exit;
}

// --- PROSES AKTIFKAN (HANYA SATU) ---
if (isset($_GET['aktifkan'])) {
    try {
        $id = $_GET['aktifkan'];
        $pdo->beginTransaction();
        $pdo->query("UPDATE cbt_tahun_ajaran SET is_aktif = 0");
        $stmt = $pdo->prepare("UPDATE cbt_tahun_ajaran SET is_aktif = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        log_activity("Aktifkan tahun ajaran ID $id", null, null, null, 'master');
        header("Location: tahun-ajaran.php?msg=diaktifkan");
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: tahun-ajaran.php?msg=error");
    }
    exit;
}

// --- PROSES HAPUS ---
if (isset($_GET['hapus'])) {
    try {
        $id = (int)$_GET['hapus'];
        $stmt = $pdo->prepare("DELETE FROM cbt_tahun_ajaran WHERE id = ?");
        $stmt->execute([$id]);
        log_activity("Hapus tahun ajaran ID $id", null, null, null, 'master');
        header("Location: tahun-ajaran.php?msg=dihapus");
    } catch (\PDOException $e) {
        $msg = str_contains($e->getMessage(), 'foreign key') ? 'error_fk' : 'error';
        header("Location: tahun-ajaran.php?msg=$msg");
    }
    exit;
}

// --- SETTING PAGINATION & LIMIT ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default 10 baris
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

// --- HITUNG TOTAL DATA UNTUK PAGINATION ---
$totalData = $pdo->query("SELECT COUNT(*) FROM cbt_tahun_ajaran")->fetchColumn();
$pages     = ceil($totalData / $limit);

// --- QUERY DENGAN SORTING KHUSUS & LIMIT ---
// Sorting: Tahun DESC (Terbaru), Semester DESC (Genap baru Ganjil)
$sql = "SELECT * FROM cbt_tahun_ajaran 
        ORDER BY tahun DESC, semester DESC 
        LIMIT $start, $limit";
$listTahun = $pdo->query($sql)->fetchAll();

// --- PROSES UPDATE DATA ---
if (isset($_POST['update'])) {
    try {
        $id       = $_POST['id'];
        $tahun    = $_POST['tahun'];
        $semester = $_POST['semester'];
        $stmt = $pdo->prepare("UPDATE cbt_tahun_ajaran SET tahun = ?, semester = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$tahun, $semester, $id]);
        log_activity("Update tahun ajaran ID $id: $tahun Semester $semester", null, null, null, 'master');
        header("Location: tahun-ajaran.php?msg=diupdate");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: tahun-ajaran.php?msg=$msg");
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body>

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light shadow-sm border" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Manajemen Tahun Ajaran</h5>
        </nav>

<?php $flash = $_GET['msg'] ?? ''; ?>
        <?php if ($flash === 'disimpan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Tahun ajaran baru berhasil ditambahkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'diupdate'): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data tahun ajaran berhasil diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'dihapus'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-trash me-2"></i> Tahun ajaran berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'diaktifkan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Tahun ajaran berhasil diaktifkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'duplicate'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Tahun ajaran dengan semester tersebut sudah ada.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error_fk'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Tahun ajaran tidak dapat dihapus karena masih digunakan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan. Silakan coba lagi.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
<div class="container-fluid px-4 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-2"></i> Tambah Tahun Ajaran
        </button>
        
        <form action="" method="GET" class="d-flex align-items-center">
            <label class="me-2 small fw-bold text-muted">Tampilkan:</label>
            <select name="limit" class="form-select form-select-sm shadow-sm" onchange="this.form.submit()" style="width: 80px;">
                <option value="5" <?= esc($limit == 5 ? 'selected' : '') ?>>5</option>
                <option value="10" <?= esc($limit == 10 ? 'selected' : '') ?>>10</option>
                <option value="25" <?= esc($limit == 25 ? 'selected' : '') ?>>25</option>
                <option value="50" <?= esc($limit == 50 ? 'selected' : '') ?>>50</option>
            </select>
        </form>
    </div>

        <div class="card card-dashboard p-4 shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th width="50">No</th>
                            <th>Tahun Ajaran</th>
                            <th>Semester</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($listTahun)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Data tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php $no = $start + 1; foreach ($listTahun as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-dark"><?= $row['tahun'] ?></td>
                                <td>
                                    <span class="badge <?= $row['semester'] == 'genap' ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' ?> px-3">
                                        <?= ucfirst($row['semester']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['is_aktif'] == 1): ?>
                                        <span class="badge bg-success shadow-sm px-3"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-3">Non-Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <button class="btn btn-sm btn-white text-primary border btn-edit-ta" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditTA"
                                                data-id="<?= esc($row['id']) ?>"
                                                data-tahun="<?= esc($row['tahun']) ?>"
                                                data-semester="<?= esc($row['semester']) ?>"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <?php if ($row['is_aktif'] == 0): ?>
                                            <a href="?aktifkan=<?= esc($row['id']) ?>" class="btn btn-sm btn-white text-success border" title="Aktifkan">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-white text-danger border" onclick="return confirm('Hapus data ini?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <p class="small text-muted m-0">
                    Menampilkan <?= $start + 1 ?> sampai <?= min($start + $limit, $totalData) ?> dari <?= $totalData ?> data
                </p>
                <nav>
                    <ul class="pagination pagination-sm m-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link shadow-sm" href="?page=<?= esc($page - 1) ?>&limit=<?= esc($limit) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link shadow-sm" href="?page=<?= esc($i) ?>&limit=<?= esc($limit) ?>"><?= esc($i) ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                            <a class="page-link shadow-sm" href="?page=<?= esc($page + 1) ?>&limit=<?= esc($limit) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditTA" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Tahun Ajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-ta-id">
                    
                    <div class="mb-3">
                        <label class="form-label">Tahun (Contoh: 2025/2026)</label>
                        <input type="text" name="tahun" id="edit-ta-tahun" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester</label>
                        <select name="semester" id="edit-ta-semester" class="form-select" required>
                            <option value="ganjil">Ganjil</option>
                            <option value="genap">Genap</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Tahun Ajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tahun (Contoh: 2025/2026)</label>
                        <input type="text" name="tahun" class="form-control" placeholder="2025/2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-select" required>
                            <option value="ganjil">Ganjil</option>
                            <option value="genap">Genap</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan" class="btn btn-primary">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#sidebar").toggleClass("show");
    });

        $(document).ready(function() {
            // Fungsi untuk memindahkan data ke Modal Edit
            $('.btn-edit-ta').on('click', function() {
                const id       = $(this).data('id');
                const tahun    = $(this).data('tahun');
                const semester = $(this).data('semester');

                $('#edit-ta-id').val(id);
                $('#edit-ta-tahun').val(tahun);
                $('#edit-ta-semester').val(semester);
            });
        });

</script>
</body>
</html>