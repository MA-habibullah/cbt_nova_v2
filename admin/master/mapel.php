<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") { csrf_verify(); }

// --- 1. PROSES CRUD ---
if (isset($_POST['proses'])) {
    $action = $_POST['proses'];

    if ($action == 'tambah') {
        try {
            $stmt = $pdo->prepare("INSERT INTO cbt_subjects (nama_mapel, kode_mapel, is_aktif) VALUES (?, ?, 1)");
            $stmt->execute([$_POST['nama_mapel'], $_POST['kode_mapel']]);
            log_activity("Tambah mata pelajaran: " . $_POST['nama_mapel'] . " (" . $_POST['kode_mapel'] . ")", null, null, null, 'master');
            header("Location: mapel.php?msg=disimpan");
        } catch (\PDOException $e) {
            $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
            header("Location: mapel.php?msg=$msg");
        }
        exit;
    }

    if ($action == 'edit') {
        try {
            $stmt = $pdo->prepare("UPDATE cbt_subjects SET nama_mapel=?, kode_mapel=?, is_aktif=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$_POST['nama_mapel'], $_POST['kode_mapel'], $_POST['is_aktif'], $_POST['id']]);
            log_activity("Update mata pelajaran ID " . $_POST['id'] . ": " . $_POST['nama_mapel'], null, null, null, 'master');
            header("Location: mapel.php?msg=diupdate");
        } catch (\PDOException $e) {
            $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
            header("Location: mapel.php?msg=$msg");
        }
        exit;
    }
}

if (isset($_GET['hapus'])) {
    try {
        $hapus_id = (int)$_GET['hapus'];
        $pdo->prepare("DELETE FROM cbt_subjects WHERE id = ?")->execute([$hapus_id]);
        log_activity("Hapus mata pelajaran ID $hapus_id", null, null, null, 'master');
        header("Location: mapel.php?msg=dihapus");
    } catch (\PDOException $e) {
        $msg = str_contains($e->getMessage(), 'foreign key') ? 'error_fk' : 'error';
        header("Location: mapel.php?msg=$msg");
    }
    exit;
}

// --- 2. CONFIGURATION: FILTER & PAGINATION ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter default: Hanya yang aktif (1)
$f_status = isset($_GET['f_status']) ? $_GET['f_status'] : '1';

// Query Builder
$query_str = "SELECT * FROM cbt_subjects WHERE 1=1";
$params = [];

if ($f_status !== '') {
    $query_str .= " AND is_aktif = ?";
    $params[] = $f_status;
}

// Total Data untuk Pagination
$stmt_count = $pdo->prepare(str_replace("*", "COUNT(*)", $query_str));
$stmt_count->execute($params);
$totalData = $stmt_count->fetchColumn();
$pages = ceil($totalData / $limit);

// Ambil Data Akhir
$query_str .= " ORDER BY nama_mapel ASC LIMIT $limit OFFSET $offset";
$stmt_data = $pdo->prepare($query_str);
$stmt_data->execute($params);
$listMapel = $stmt_data->fetchAll();

// --- PROSES UPDATE STATUS CEPAT ---
if (isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    try {
        $new_status = ($_GET['current_status'] == '1') ? '0' : '1';
        $stmt = $pdo->prepare("UPDATE cbt_subjects SET is_aktif = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $_GET['toggle_id']]);
        header("Location: mapel.php?f_status=" . $f_status . "&msg=status_updated");
    } catch (\PDOException $e) {
        header("Location: mapel.php?msg=error");
    }
    exit;
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
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Manajemen Mata Pelajaran</h5>
        </nav>

        <?php $flash = $_GET['msg'] ?? ''; ?>
        <?php if ($flash === 'disimpan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Mata pelajaran baru berhasil ditambahkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'diupdate'): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data mata pelajaran berhasil diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'dihapus'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-trash me-2"></i> Mata pelajaran berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'status_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Status mata pelajaran berhasil diubah.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'duplicate'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Data duplikat. Kode atau nama mapel sudah ada.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error_fk'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Mapel tidak dapat dihapus karena masih digunakan pada ujian.
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
                            <i class="fas fa-plus me-2"></i> Tambah Mapel
                        </button>
                    </div>
                    
                    <form action="" method="GET" class="d-flex flex-wrap align-items-center gap-2">
                        <select name="f_status" class="form-select form-select-sm" style="width: 170px;" onchange="this.form.submit()">
                            <option value="" <?= esc($f_status === '' ? 'selected' : '') ?>>-- Semua Status --</option>
                            <option value="1" <?= esc($f_status === '1' ? 'selected' : '') ?>>Mapel Aktif</option>
                            <option value="0" <?= esc($f_status === '0' ? 'selected' : '') ?>>Mapel Non-Aktif</option>
                        </select>
                        <div class="d-flex align-items-center gap-1">
                            <label class="small text-muted mb-0 fw-medium">Tampilkan:</label>
                            <select name="limit" class="form-select form-select-sm" style="width: 80px;" onchange="this.form.submit()">
                                <option value="10" <?= esc($limit == 10 ? 'selected' : '') ?>>10</option>
                                <option value="25" <?= esc($limit == 25 ? 'selected' : '') ?>>25</option>
                                <option value="50" <?= esc($limit == 50 ? 'selected' : '') ?>>50</option>
                                <option value="100" <?= esc($limit == 100 ? 'selected' : '') ?>>100</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table Container -->
            <div class="card card-dashboard p-0 shadow-sm border-0 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-book-open text-primary"></i> Daftar Mata Pelajaran
                    </h6>
                    <span class="badge bg-primary-subtle text-primary font-monospace rounded-pill px-3">
                        Total: <?= number_format($totalData) ?> Mapel
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold">
                            <tr>
                                <th width="60" class="ps-4 text-center">No</th>
                                <th width="180">Kode Mapel</th>
                                <th>Nama Mata Pelajaran</th>
                                <th class="text-center" width="140">Status</th>
                                <th class="text-end pe-4" width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listMapel)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Data mata pelajaran tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php $no = $offset + 1; foreach($listMapel as $row): ?>
                                <tr>
                                    <td class="ps-4 text-center text-muted"><?= $no++ ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary font-monospace px-3 py-2"><?= htmlspecialchars($row['kode_mapel']) ?></span></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_mapel']) ?></td>
                                    <td class="text-center">
                                        <?php if($row['is_aktif']): ?>
                                            <a href="?toggle_id=<?= esc($row['id']) ?>&current_status=1&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>&page=<?= esc($page) ?>" 
                                               class="badge bg-success-subtle text-success rounded-pill px-3 py-2 text-decoration-none"
                                               onclick="return confirm('Non-aktifkan mata pelajaran ini?')">
                                                <i class="fas fa-check-circle me-1"></i> Aktif
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle_id=<?= esc($row['id']) ?>&current_status=0&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>&page=<?= esc($page) ?>" 
                                               class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 text-decoration-none"
                                               onclick="return confirm('Aktifkan mata pelajaran ini?')">
                                                <i class="fas fa-times-circle me-1"></i> Non-Aktif
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm">
                                            <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($row['id']) ?>"
                                                data-nama="<?= esc($row['nama_mapel']) ?>"
                                                data-kode="<?= esc($row['kode_mapel']) ?>"
                                                data-status="<?= esc($row['is_aktif']) ?>"
                                                title="Edit Mapel">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?')" title="Hapus">
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
                                <a class="page-link" href="?page=<?= esc($page - 1) ?>&limit=<?= esc($limit) ?>&f_status=<?= esc($f_status) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php for($i=1; $i<=$pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= esc($i) ?>&limit=<?= esc($limit) ?>&f_status=<?= esc($f_status) ?>"><?= esc($i) ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($page + 1) ?>&limit=<?= esc($limit) ?>&f_status=<?= esc($f_status) ?>">
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
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Tambah Mata Pelajaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="proses" value="tambah">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Kode Mapel</label>
                    <input type="text" name="kode_mapel" class="form-control" placeholder="Contoh: MTK-W" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" class="form-control" placeholder="Contoh: Matematika Wajib" required>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan Mapel</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Mata Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="proses" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Kode Mapel</label>
                    <input type="text" name="kode_mapel" id="edit-kode" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" id="edit-nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Status</label>
                    <select name="is_aktif" id="edit-status" class="form-select">
                        <option value="1">Aktif</option>
                        <option value="0">Non-Aktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning">Simpan Perubahan</button></div>
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
            $('#edit-kode').val($(this).data('kode'));
            $('#edit-status').val($(this).data('status'));
        });
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
    });
</script>
</body>
</html>