<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") { csrf_verify(); }

// --- PROSES SIMPAN ---
if (isset($_POST['simpan'])) {
    try {
        $nama_sesi   = $_POST['nama_sesi'];
        $jam_mulai   = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];
        $stmt = $pdo->prepare("INSERT INTO cbt_sesi (nama_sesi, jam_mulai, jam_selesai, is_aktif, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$nama_sesi, $jam_mulai, $jam_selesai]);
        log_activity("Tambah sesi: $nama_sesi ($jam_mulai–$jam_selesai)", null, null, null, 'master');
        header("Location: sesi.php?msg=disimpan");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: sesi.php?msg=$msg");
    }
    exit;
}

// --- PROSES UPDATE ---
if (isset($_POST['update'])) {
    try {
        $id          = $_POST['id'];
        $nama_sesi   = $_POST['nama_sesi'];
        $jam_mulai   = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];
        $stmt = $pdo->prepare("UPDATE cbt_sesi SET nama_sesi = ?, jam_mulai = ?, jam_selesai = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$nama_sesi, $jam_mulai, $jam_selesai, $id]);
        log_activity("Update sesi ID $id: $nama_sesi ($jam_mulai–$jam_selesai)", null, null, null, 'master');
        header("Location: sesi.php?msg=diupdate");
    } catch (\PDOException $e) {
        header("Location: sesi.php?msg=error");
    }
    exit;
}

// --- TOGGLE STATUS ---
if (isset($_GET['toggle'])) {
    try {
        $id = $_GET['toggle'];
        $s  = $_GET['s'];
        $new_status = ($s == 1) ? 0 : 1;
        $pdo->prepare("UPDATE cbt_sesi SET is_aktif = ? WHERE id = ?")->execute([$new_status, $id]);
        log_activity("Toggle status sesi ID $id → " . ($new_status ? 'aktif' : 'nonaktif'), null, null, null, 'master');
        header("Location: sesi.php");
    } catch (\PDOException $e) {
        header("Location: sesi.php?msg=error");
    }
    exit;
}

// --- PROSES HAPUS ---
if (isset($_GET['hapus'])) {
    try {
        $hapus_id = (int)$_GET['hapus'];
        $pdo->prepare("DELETE FROM cbt_sesi WHERE id = ?")->execute([$hapus_id]);
        log_activity("Hapus sesi ID $hapus_id", null, null, null, 'master');
        header("Location: sesi.php?msg=dihapus");
    } catch (\PDOException $e) {
        $msg = str_contains($e->getMessage(), 'foreign key') ? 'error_fk' : 'error';
        header("Location: sesi.php?msg=$msg");
    }
    exit;
}

// --- PAGINATION & LIMIT ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalData = $pdo->query("SELECT COUNT(*) FROM cbt_sesi")->fetchColumn();
$pages     = ceil($totalData / $limit);

$listSesi = $pdo->query("
    SELECT s.*, COUNT(st.id) as jumlah_siswa
    FROM cbt_sesi s
    LEFT JOIN cbt_students st ON st.sesi = s.id AND st.is_aktif = 1
    GROUP BY s.id ORDER BY s.jam_mulai ASC
    LIMIT $limit OFFSET $offset
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body>

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Konfigurasi Sesi Ujian</h5>
        </nav>

        <?php $flash = $_GET['msg'] ?? ''; ?>
        <?php if ($flash === 'disimpan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Sesi baru berhasil ditambahkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'diupdate'): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data sesi berhasil diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'dihapus'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-trash me-2"></i> Sesi berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'duplicate'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Data duplikat. Sesi dengan nama tersebut sudah ada.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error_fk'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Sesi tidak dapat dihapus karena masih digunakan oleh siswa.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan. Silakan coba lagi.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="container-fluid px-4 pt-4 pb-4">
            <!-- Action & Filter Toolbar -->
            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus me-2"></i> Tambah Sesi
                        </button>
                        <a href="assign-sesi.php" class="btn btn-outline-info shadow-sm">
                            <i class="fas fa-user-check me-2"></i> Atur Pembagian Sesi
                        </a>
                    </div>
                    
                    <form action="" method="GET" class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0 fw-medium">Tampilkan:</label>
                        <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 80px;">
                            <option value="5" <?= esc($limit == 5 ? 'selected' : '') ?>>5</option>
                            <option value="10" <?= esc($limit == 10 ? 'selected' : '') ?>>10</option>
                            <option value="25" <?= esc($limit == 25 ? 'selected' : '') ?>>25</option>
                            <option value="50" <?= esc($limit == 50 ? 'selected' : '') ?>>50</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Data Table Container -->
            <div class="card card-dashboard p-0 shadow-sm border-0 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-clock text-primary"></i> Daftar Sesi Waktu Ujian
                    </h6>
                    <span class="badge bg-primary-subtle text-primary font-monospace rounded-pill px-3">
                        Total: <?= number_format($totalData) ?> Sesi
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold">
                            <tr>
                                <th width="60" class="ps-4 text-center">No</th>
                                <th>Nama Sesi</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th class="text-center">Peserta Terdaftar</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listSesi)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Data sesi belum tersedia.</td></tr>
                            <?php else: ?>
                                <?php $no = $offset + 1; foreach ($listSesi as $row): ?>
                                <tr>
                                    <td class="ps-4 text-center text-muted"><?= $no++ ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_sesi']) ?></td>
                                    <td><span class="badge bg-light text-dark font-monospace border px-3"><?= date('H:i', strtotime($row['jam_mulai'])) ?> WIB</span></td>
                                    <td><span class="badge bg-light text-dark font-monospace border px-3"><?= date('H:i', strtotime($row['jam_selesai'])) ?> WIB</span></td>
                                    <td class="text-center">
                                        <a href="assign-sesi.php" class="badge bg-info-subtle text-info-emphasis rounded-pill px-3 py-2 text-decoration-none shadow-sm">
                                            <i class="fas fa-users me-1"></i> <?= (int)$row['jumlah_siswa'] ?> Siswa
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <a href="?toggle=<?= esc($row['id']) ?>&s=<?= esc($row['is_aktif']) ?>" class="text-decoration-none" title="Klik untuk ubah status">
                                            <?php if ($row['is_aktif'] == 1): ?>
                                                <span class="badge bg-success-subtle text-success rounded-pill px-3"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3"><i class="fas fa-times-circle me-1"></i> Non-Aktif</span>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm">
                                            <a href="assign-sesi.php" class="btn btn-sm btn-outline-info" title="Assign Siswa ke Sesi Ini">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                    data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                    data-id="<?= esc($row['id']) ?>"
                                                    data-nama="<?= esc($row['nama_sesi']) ?>"
                                                    data-mulai="<?= esc($row['jam_mulai']) ?>"
                                                    data-selesai="<?= esc($row['jam_selesai']) ?>"
                                                    title="Edit Sesi">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus sesi ujian ini?')" title="Hapus">
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

                <?php if ($totalData > 0): ?>
                <div class="card-footer bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center border-top">
                    <div class="text-muted small">
                        Menampilkan <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + $limit, $totalData) ?></strong> dari <strong><?= $totalData ?></strong> data
                    </div>
                    <?php if ($pages > 1): ?>
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($page - 1) ?>&limit=<?= esc($limit) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= esc($i) ?>&limit=<?= esc($limit) ?>"><?= esc($i) ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($page + 1) ?>&limit=<?= esc($limit) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Sesi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Sesi</label>
                    <input type="text" name="nama_sesi" class="form-control" placeholder="Contoh: Sesi 1" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="jam_mulai" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="jam_selesai" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="simpan" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Sesi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label class="form-label">Nama Sesi</label>
                    <input type="text" name="nama_sesi" id="edit-nama" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="jam_mulai" id="edit-mulai" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="jam_selesai" id="edit-selesai" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('.btn-edit').on('click', function() {
            $('#edit-id').val($(this).data('id'));
            $('#edit-nama').val($(this).data('nama'));
            $('#edit-mulai').val($(this).data('mulai'));
            $('#edit-selesai').val($(this).data('selesai'));
        });
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#sidebar").toggleClass("show");
        });
    });
</script>
</body>
</html>