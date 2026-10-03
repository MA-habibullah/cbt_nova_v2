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
        <div class="container-fluid px-4 pt-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="small fw-bold">Filter Status</label>
                            <select name="f_status" class="form-select bg-primary-subtle fw-bold">
                                <option value="1" <?= esc($f_status == '1'?'selected':'') ?>>Mapel Aktif</option>
                                <option value="0" <?= esc($f_status == '0'?'selected':'') ?>>Mapel Non-Aktif</option>
                                <option value="" <?= esc($f_status == ''?'selected':'') ?>>Semua Mapel</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Limit</label>
                            <select name="limit" class="form-select">
                                <option value="10" <?= esc($limit==10?'selected':'') ?>>10</option>
                                <option value="50" <?= esc($limit==50?'selected':'') ?>>50</option>
                                <option value="100" <?= esc($limit==100?'selected':'') ?>>100</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Terapkan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mb-3">
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus-circle me-2"></i> Tambah Mapel
                </button>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="60" class="ps-4">No</th>
                                <th>Kode Mapel</th>
                                <th>Nama Mata Pelajaran</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; foreach($listMapel as $row): ?>
                            <tr>
                                <td class="ps-4"><?= $no++ ?></td>
                                <td><span class="badge bg-dark px-3"><?= $row['kode_mapel'] ?></span></td>
                                <td class="fw-bold"><?= $row['nama_mapel'] ?></td>
                                <td>
                                    <?php if($row['is_aktif']): ?>
                                        <a href="?toggle_id=<?= esc($row['id']) ?>&current_status=1&f_status=<?= esc($f_status) ?>" 
                                        class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 text-decoration-none"
                                        onclick="return confirm('Non-aktifkan mata pelajaran ini?')">
                                            <i class="fas fa-check-circle me-1"></i> Aktif
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle_id=<?= esc($row['id']) ?>&current_status=0&f_status=<?= esc($f_status) ?>" 
                                        class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 text-decoration-none"
                                        onclick="return confirm('Aktifkan mata pelajaran ini?')">
                                            <i class="fas fa-times-circle me-1"></i> Non-Aktif
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <button class="btn btn-sm btn-outline-warning btn-edit" 
                                            data-bs-toggle="modal" data-bs-target="#modalEdit"
                                            data-id="<?= esc($row['id']) ?>"
                                            data-nama="<?= esc($row['nama_mapel']) ?>"
                                            data-kode="<?= esc($row['kode_mapel']) ?>"
                                            data-status="<?= esc($row['is_aktif']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('Hapus mapel ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($listMapel)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Tidak ada data mata pelajaran.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 d-flex justify-content-between align-items-center">
                    <small class="text-muted">Menampilkan <?= count($listMapel) ?> dari <?= $totalData ?> mapel</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for($i=1; $i<=$pages; $i++): ?>
                                <li class="page-item <?= ($page == $i)?'active':'' ?>">
                                    <a class="page-link" href="?page=<?= esc($i) ?>&limit=<?= esc($limit) ?>&f_status=<?= esc($f_status) ?>"><?= esc($i) ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
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