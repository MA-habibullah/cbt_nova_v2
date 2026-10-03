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
        $nama_kelas = $_POST['nama_kelas'];
        $jenjang    = $_POST['jenjang'];
        $stmt = $pdo->prepare("INSERT INTO cbt_classes (nama_kelas, jenjang, is_aktif, created_at) VALUES (?, ?, 1, NOW())");
        $stmt->execute([$nama_kelas, $jenjang]);
        log_activity("Tambah kelas: $jenjang-$nama_kelas", null, null, null, 'master');
        header("Location: kelas.php?msg=disimpan");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: kelas.php?msg=$msg");
    }
    exit;
}

// --- PROSES UPDATE STATUS (Menggunakan 'is_aktif') ---
if (isset($_GET['toggle_status'])) {
    try {
        $id = $_GET['toggle_status'];
        $current_status = $_GET['current'];
        $new_status = ($current_status == 1) ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE cbt_classes SET is_aktif = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        log_activity("Toggle status kelas ID $id → " . ($new_status ? 'aktif' : 'nonaktif'), null, null, null, 'master');
        header("Location: kelas.php?msg=status_updated");
    } catch (\PDOException $e) {
        header("Location: kelas.php?msg=error");
    }
    exit;
}

// --- PROSES HAPUS ---
if (isset($_GET['hapus'])) {
    try {
        $id = $_GET['hapus'];
        $stmt = $pdo->prepare("DELETE FROM cbt_classes WHERE id = ?");
        $stmt->execute([$id]);
        log_activity("Hapus kelas ID $id", null, null, null, 'master');
        header("Location: kelas.php?msg=dihapus");
    } catch (\PDOException $e) {
        $msg = str_contains($e->getMessage(), 'foreign key') ? 'error_fk' : 'error';
        header("Location: kelas.php?msg=$msg");
    }
    exit;
}

// --- CONFIGURATION: PAGINATION & FILTER ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// --- QUERY DENGAN FILTER (is_aktif) ---
$query_str = "SELECT * FROM cbt_classes WHERE 1=1";
$params = [];

if ($filter_status !== '') {
    $query_str .= " AND is_aktif = ?";
    $params[] = $filter_status;
}

// Hitung Total Data
$stmt_count = $pdo->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $query_str));
$stmt_count->execute($params);
$totalData = $stmt_count->fetchColumn();
$pages = ceil($totalData / $limit);

// Ambil Data Terurut berdasarkan Jenjang
$query_str .= " ORDER BY jenjang ASC, nama_kelas ASC LIMIT $limit OFFSET $offset";
$stmt_data = $pdo->prepare($query_str);
$stmt_data->execute($params);
$listKelas = $stmt_data->fetchAll();

// --- PROSES UPDATE DATA ---
if (isset($_POST['update'])) {
    try {
        $id         = $_POST['id'];
        $nama_kelas = $_POST['nama_kelas'];
        $jenjang    = $_POST['jenjang'];
        $stmt = $pdo->prepare("UPDATE cbt_classes SET nama_kelas = ?, jenjang = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$nama_kelas, $jenjang, $id]);
        log_activity("Update kelas ID $id: $jenjang-$nama_kelas", null, null, null, 'master');
        header("Location: kelas.php?msg=diupdate");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: kelas.php?msg=$msg");
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
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Data Kelas</h5>
        </nav>

        <?php $flash = $_GET['msg'] ?? ''; ?>
        <?php if ($flash === 'disimpan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Kelas baru berhasil ditambahkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'diupdate'): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data kelas berhasil diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'dihapus'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-trash me-2"></i> Data kelas berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'status_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Status kelas berhasil diubah.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'duplicate'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Data duplikat. Kelas dengan nama tersebut sudah ada.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error_fk'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Kelas tidak dapat dihapus karena masih digunakan oleh siswa.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan. Silakan coba lagi.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="container-fluid px-4 pt-4">

            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKelas">
                            <i class="fas fa-plus me-2"></i> Tambah Kelas
                        </button>
                        <a href="<?= esc(BASE_URL) ?>admin/master-io/export_kelas.php" class="btn btn-outline-success shadow-sm">
                            <i class="fas fa-file-excel me-2"></i> Ref ID Kelas
                        </a>
                    </div>
                    <form action="" method="GET" class="d-flex flex-wrap align-items-center gap-2">
                        <input type="hidden" name="limit" value="<?= esc($limit) ?>">
                        <select name="filter_status" class="form-select form-select-sm" style="width: 170px;" onchange="this.form.submit()">
                            <option value="">-- Semua Status --</option>
                            <option value="1" <?= esc($filter_status === '1' ? 'selected' : '') ?>>Aktif</option>
                            <option value="0" <?= esc($filter_status === '0' ? 'selected' : '') ?>>Non-Aktif</option>
                        </select>
                        <div class="d-flex align-items-center gap-1">
                            <label class="small text-muted mb-0">Limit:</label>
                            <select name="limit" class="form-select form-select-sm" style="width: 80px;" onchange="this.form.submit()">
                                <option value="5" <?= esc($limit == 5 ? 'selected' : '') ?>>5</option>
                                <option value="10" <?= esc($limit == 10 ? 'selected' : '') ?>>10</option>
                                <option value="25" <?= esc($limit == 25 ? 'selected' : '') ?>>25</option>
                                <option value="50" <?= esc($limit == 50 ? 'selected' : '') ?>>50</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table Container -->
            <div class="card card-dashboard p-0 shadow-sm border-0 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-chalkboard text-primary"></i> Daftar Rombel &amp; Tingkat Kelas
                    </h6>
                    <span class="badge bg-primary-subtle text-primary font-monospace rounded-pill px-3">
                        Total: <?= number_format($totalData) ?> Kelas
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold">
                            <tr>
                                <th width="60" class="ps-4 text-center">No</th>
                                <th>Jenjang</th>
                                <th>Nama Kelas</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($listKelas)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Data kelas tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php $no = $offset + 1; foreach ($listKelas as $row): ?>
                                <tr>
                                    <td class="ps-4 text-center text-muted"><?= $no++ ?></td>
                                    <td><span class="badge bg-info-subtle text-info-emphasis font-monospace px-3">Tingkat <?= $row['jenjang'] ?></span></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                    <td class="text-center">
                                        <?php if ($row['is_aktif'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3"><i class="fas fa-times-circle me-1"></i> Non-Aktif</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm">
                                            <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEdit"
                                                    data-id="<?= esc($row['id']) ?>"
                                                    data-nama="<?= esc($row['nama_kelas']) ?>"
                                                    data-jenjang="<?= esc($row['jenjang']) ?>"
                                                    title="Edit Kelas">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <a href="?toggle_status=<?= esc($row['id']) ?>&current=<?= esc($row['is_aktif']) ?>&filter_status=<?= esc($filter_status) ?>&limit=<?= esc($limit) ?>&page=<?= esc($page) ?>"
                                               class="btn btn-sm <?= $row['is_aktif'] == 1 ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                               title="<?= $row['is_aktif'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="fas fa-power-off"></i>
                                            </a>

                                            <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus kelas ini?')" title="Hapus">
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
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($page - 1) ?>&limit=<?= esc($limit) ?>&filter_status=<?= esc($filter_status) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= esc($i) ?>&limit=<?= esc($limit) ?>&filter_status=<?= esc($filter_status) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($page + 1) ?>&limit=<?= esc($limit) ?>&filter_status=<?= esc($filter_status) ?>">
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
            
            <div class="modal fade" id="modalEdit" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <form action="" method="POST" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Data Kelas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="edit-id">
                            
                            <div class="mb-3">
                                <label class="form-label">Jenjang / Tingkat</label>
                                <select name="jenjang" id="edit-jenjang" class="form-select" required>
                                    <option value="10">10 (Sepuluh)</option>
                                    <option value="11">11 (Sebelas)</option>
                                    <option value="12">12 (Dua Belas)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Kelas</label>
                                <input type="text" name="nama_kelas" id="edit-nama" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="update" class="btn btn-primary shadow-sm">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal fade" id="modalTambahKelas" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <form action="" method="POST" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Tambah Data Kelas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Jenjang / Tingkat</label>
                                <select name="jenjang" class="form-select" required>
                                    <option value="10">10 (Sepuluh)</option>
                                    <option value="11">11 (Sebelas)</option>
                                    <option value="12">12 (Dua Belas)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Kelas</label>
                                <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: Merdeka 1" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="simpan" class="btn btn-primary">Simpan Kelas</button>
                        </div>
                    </form>
                </div>
            </div>

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
        // Logika Passing Data ke Modal Edit
        $('.btn-edit').on('click', function() {
            const id      = $(this).data('id');
            const nama    = $(this).data('nama');
            const jenjang = $(this).data('jenjang');

            $('#edit-id').val(id);
            $('#edit-nama').val(nama);
            $('#edit-jenjang').val(jenjang);
        });
    });
</script>
</body>
</html>