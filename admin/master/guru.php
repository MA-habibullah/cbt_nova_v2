<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") { csrf_verify(); }

// --- PROSES SIMPAN ---
if (isset($_POST['simpan'])) {
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO cbt_teachers (nip, nama_lengkap, username, password, is_aktif, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$_POST['nip'], $_POST['nama_lengkap'], $_POST['username'], $password]);
    log_activity("Tambah guru baru: " . $_POST['nama_lengkap'] . " (NIP: " . $_POST['nip'] . ")", null, null, null, 'master');
    header("Location: guru.php?msg=disimpan");
    exit;
}

// --- PROSES UPDATE ---
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $sql = "UPDATE cbt_teachers SET nip=?, nama_lengkap=?, username=?, password=?, is_aktif=?, updated_at=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$_POST['nip'], $_POST['nama_lengkap'], $_POST['username'], $password, $_POST['is_aktif'], $id]);
    } else {
        $sql = "UPDATE cbt_teachers SET nip=?, nama_lengkap=?, username=?, is_aktif=?, updated_at=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$_POST['nip'], $_POST['nama_lengkap'], $_POST['username'], $_POST['is_aktif'], $id]);
    }
    log_activity("Update data guru ID $id: " . $_POST['nama_lengkap'] . " (NIP: " . $_POST['nip'] . ")", null, null, null, 'master');
    header("Location: guru.php?msg=updated");
    exit;
}

// --- KONFIGURASI DATA ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search   = $_GET['search'] ?? '';
$f_status = isset($_GET['f_status']) ? $_GET['f_status'] : '1';

$query_str = "SELECT * FROM cbt_teachers WHERE 1=1";
$params = [];

if ($search) {
    $query_str .= " AND nama_lengkap LIKE ?";
    $params[] = "%" . like_escape($search) . "%";
}
if ($f_status !== '') {
    $query_str .= " AND is_aktif = ?";
    $params[] = $f_status;
}

// Total untuk Pagination
$stmt_count = $pdo->prepare(str_replace("*", "COUNT(*)", $query_str));
$stmt_count->execute($params);
$totalData = $stmt_count->fetchColumn();
$pages = ceil($totalData / $limit);

$query_str .= " ORDER BY nama_lengkap ASC LIMIT $limit OFFSET $offset";
$stmt_data = $pdo->prepare($query_str);
$stmt_data->execute($params);
$listGuru = $stmt_data->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <h5 class="mb-0 fw-bold">Manajemen Data Guru</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'import_done'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Proses Import Selesai</h6>
                            <span>Berhasil menambahkan <strong><?= $_GET['success'] ?? 0 ?></strong> guru baru.</span>
                            <?php if (isset($_GET['skipped']) && $_GET['skipped'] > 0): ?>
                                <div class="text-danger small mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong><?= $_GET['skipped'] ?></strong> data dilewati karena Username sudah terdaftar.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Cari Nama Guru..." value="<?= esc($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="f_status" class="form-select">
                                <option value="1" <?= esc($f_status == '1'?'selected':'') ?>>Guru Aktif</option>
                                <option value="0" <?= esc($f_status == '0'?'selected':'') ?>>Guru Non-Aktif</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="limit" class="form-select" onchange="this.form.submit()">
                                <option value="10" <?= esc($limit==10?'selected':'') ?>>10 Baris</option>
                                <option value="50" <?= esc($limit==50?'selected':'') ?>>50 Baris</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="guru.php" class="btn btn-light border"><i class="fas fa-sync"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus me-2"></i> Tambah Guru
                </button>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImport">
                    <i class="fas fa-file-import me-1"></i> Import
                </button>
                <a href="<?= esc(BASE_URL) ?>admin/master-io/export_guru.php" class="btn btn-outline-primary">
                    <i class="fas fa-file-export me-1"></i> Export
                </a>
            </div>

            <div class="card border-0 shadow-sm p-3">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>No</th>
                            <th>NIP</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=$offset+1; foreach($listGuru as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="text-primary fw-bold"><?= $row['nip'] ?></td>
                            <td class="fw-bold"><?= $row['nama_lengkap'] ?></td>
                            <td><span class="badge bg-light text-muted border"><?= $row['username'] ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary btn-edit-guru" 
                                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                                        data-id="<?= esc($row['id']) ?>" data-nip="<?= esc($row['nip']) ?>"
                                        data-nama="<?= esc($row['nama_lengkap']) ?>" data-user="<?= esc($row['username']) ?>"
                                        data-status="<?= esc($row['is_aktif']) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
<div class="d-flex justify-content-between align-items-center mt-3">
    <p class="text-muted small">Menampilkan <?= count($listGuru) ?> dari <?= $totalData ?> data guru</p>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= esc($page - 1) ?>&search=<?= esc($search) ?>&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>">Previous</a>
            </li>

            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= esc($i) ?>&search=<?= esc($search) ?>&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>"><?= esc($i) ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= esc($page + 1) ?>&search=<?= esc($search) ?>&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>">Next</a>
            </li>
        </ul>
    </nav>
</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit Data Guru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="id" id="edit-id">
                <div class="col-12">
                    <label class="form-label small fw-bold">NIP</label>
                    <input type="text" name="nip" id="edit-nip" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit-nama" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" name="username" id="edit-user" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-danger">Ganti Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="is_aktif" id="edit-status" class="form-select">
                        <option value="1">Aktif</option>
                        <option value="0">Non-Aktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Tambah Guru Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label small fw-bold">NIP</label>
                    <input type="text" name="nip" class="form-control" placeholder="Contoh: 1980...">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="simpan" class="btn btn-success">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="../master-io/import_guru.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-primary"><i class="fas fa-file-import me-2"></i>Import Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-1"></i> Gunakan file Excel (.xlsx) dengan urutan kolom:<br>
                    <strong>NIP, Nama Lengkap, Username, Password</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Pilih File Excel</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx" required>
                </div>
                <div class="text-center">
                    <a href="../master-io/export_guru.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>Download Template Guru
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="import" class="btn btn-primary btn-sm px-4">
                    <i class="fas fa-upload me-1"></i>Proses Import
                </button>
            </div>
        </form>
    </div>
</div>

<!-- <div class="d-flex justify-content-between align-items-center mt-3">
    <p class="text-muted small">Menampilkan <?= count($listGuru) ?> dari <?= $totalData ?> data guru</p>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= esc($page - 1) ?>&search=<?= esc($search) ?>&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>">Previous</a>
            </li>

            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= esc($i) ?>&search=<?= esc($search) ?>&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>"><?= esc($i) ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= esc($page + 1) ?>&search=<?= esc($search) ?>&f_status=<?= esc($f_status) ?>&limit=<?= esc($limit) ?>">Next</a>
            </li>
        </ul>
    </nav>
</div>-->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $('.btn-edit-guru').on('click', function() {
        $('#edit-id').val($(this).data('id'));
        $('#edit-nip').val($(this).data('nip'));
        $('#edit-nama').val($(this).data('nama'));
        $('#edit-user').val($(this).data('user'));
        $('#edit-status').val($(this).data('status'));
    });
</script>
</body>
</html>