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
        $nama     = $_POST['nama_lengkap'];
        $username = $_POST['username'];
        $email    = $_POST['email'];
        $role     = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO cbt_admins (nama_lengkap, username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nama, $username, $email, $password, $role]);
        log_activity("Tambah admin: $nama ($role)", null, null, null, 'master');
        header("Location: data-admin.php?msg=disimpan");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: data-admin.php?msg=$msg");
    }
    exit;
}

// --- PROSES UPDATE ---
if (isset($_POST['update'])) {
    try {
        $id    = $_POST['id'];
        $nama  = $_POST['nama_lengkap'];
        $email = $_POST['email'];
        $role  = $_POST['role'];
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE cbt_admins SET nama_lengkap=?, email=?, role=?, password=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$nama, $email, $role, $pass, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE cbt_admins SET nama_lengkap=?, email=?, role=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$nama, $email, $role, $id]);
        }
        log_activity("Update admin ID $id: $nama ($role)", null, null, null, 'master');
        header("Location: data-admin.php?msg=diupdate");
    } catch (\PDOException $e) {
        $msg = $e->getCode() == '23000' ? 'duplicate' : 'error';
        header("Location: data-admin.php?msg=$msg");
    }
    exit;
}

// --- PROSES HAPUS ---
if (isset($_GET['hapus'])) {
    try {
        $hapus_id = (int)$_GET['hapus'];
        $pdo->prepare("DELETE FROM cbt_admins WHERE id = ?")->execute([$hapus_id]);
        log_activity("Hapus admin ID $hapus_id", null, null, null, 'master');
        header("Location: data-admin.php?msg=dihapus");
    } catch (\PDOException $e) {
        header("Location: data-admin.php?msg=error");
    }
    exit;
}

// --- PAGINATION & LIMIT ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalData = $pdo->query("SELECT COUNT(*) FROM cbt_admins")->fetchColumn();
$pages     = ceil($totalData / $limit);

$stmtAdmin = $pdo->prepare("SELECT * FROM cbt_admins ORDER BY role ASC, nama_lengkap ASC LIMIT ? OFFSET ?");
$stmtAdmin->bindValue(1, (int)$limit, PDO::PARAM_INT);
$stmtAdmin->bindValue(2, (int)$offset, PDO::PARAM_INT);
$stmtAdmin->execute();
$listAdmin = $stmtAdmin->fetchAll();
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
            <h5 class="ms-3 mb-0 fw-bold text-primary">Manajemen Pengguna Admin</h5>
        </nav>

        <?php $flash = $_GET['msg'] ?? ''; ?>
        <?php if ($flash === 'disimpan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Admin baru berhasil ditambahkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'diupdate'): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data admin berhasil diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'dihapus'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-trash me-2"></i> Admin berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'duplicate'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-times-circle me-2"></i> Gagal: Data duplikat. Username atau email sudah terdaftar.
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
                            <i class="fas fa-user-plus me-2"></i> Tambah Admin
                        </button>
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
                        <i class="fas fa-user-shield text-primary"></i> Daftar Pengguna Administrator &amp; Proktor
                    </h6>
                    <span class="badge bg-primary-subtle text-primary font-monospace rounded-pill px-3">
                        Total: <?= number_format($totalData) ?> Admin
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold">
                            <tr>
                                <th width="60" class="ps-4 text-center">No</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th class="text-center" width="140">Role Akses</th>
                                <th class="text-end pe-4" width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listAdmin)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Data pengguna admin tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php $no = $offset + 1; foreach ($listAdmin as $row): ?>
                                <tr>
                                    <td class="ps-4 text-center text-muted"><?= $no++ ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary font-monospace px-3 py-2"><?= htmlspecialchars($row['username']) ?></span></td>
                                    <td class="text-muted small"><?= htmlspecialchars($row['email'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $roleBadge = [
                                            'superadmin' => 'bg-danger-subtle text-danger',
                                            'admin'      => 'bg-primary-subtle text-primary',
                                            'proktor'    => 'bg-success-subtle text-success'
                                        ];
                                        $cls = $roleBadge[$row['role']] ?? 'bg-secondary-subtle text-secondary';
                                        ?>
                                        <span class="badge <?= $cls ?> rounded-pill px-3 py-2"><?= ucfirst($row['role']) ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm">
                                            <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                    data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                    data-id="<?= esc($row['id']) ?>"
                                                    data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                    data-email="<?= esc($row['email']) ?>"
                                                    data-role="<?= esc($row['role']) ?>"
                                                    title="Edit Admin">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna admin ini?')" title="Hapus">
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
                <h5 class="modal-title fw-bold">Tambah Pengguna Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                        <option value="proktor">Proktor</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="simpan" class="btn btn-primary">Simpan Admin</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit-nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit-email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" id="edit-role" class="form-select" required>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                        <option value="proktor">Proktor</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-danger">Ganti Password (Kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
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
            $('#edit-email').val($(this).data('email'));
            $('#edit-role').val($(this).data('role'));
        });
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#sidebar").toggleClass("show");
        });
    });
</script>
</body>
</html>