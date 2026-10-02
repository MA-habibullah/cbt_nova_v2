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

$listAdmin = $pdo->query("SELECT * FROM cbt_admins ORDER BY role ASC, nama_lengkap ASC LIMIT $limit OFFSET $offset")->fetchAll();
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
        <div class="container-fluid px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-user-plus me-2"></i> Tambah Admin
                </button>
                
                <form action="" method="GET" class="d-flex align-items-center">
                    <label class="me-2 small fw-bold text-muted">Baris:</label>
                    <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 70px;">
                        <option value="5" <?= esc($limit == 5 ? 'selected' : '') ?>>5</option>
                        <option value="10" <?= esc($limit == 10 ? 'selected' : '') ?>>10</option>
                        <option value="25" <?= esc($limit == 25 ? 'selected' : '') ?>>25</option>
                    </select>
                </form>
            </div>

            <div class="card card-dashboard p-4 shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th width="60">No</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; foreach ($listAdmin as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-dark"><?= $row['nama_lengkap'] ?></td>
                                <td><span class="badge bg-light text-muted border"><?= $row['username'] ?></span></td>
                                <td><?= $row['email'] ?></td>
                                <td>
                                    <?php 
                                    $roleBadge = [
                                        'superadmin' => 'bg-danger',
                                        'admin' => 'bg-primary',
                                        'proktor' => 'bg-success'
                                    ];
                                    ?>
                                    <span class="badge <?= $roleBadge[$row['role']] ?> px-3"><?= ucfirst($row['role']) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($row['id']) ?>"
                                                data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                data-email="<?= esc($row['email']) ?>"
                                                data-role="<?= esc($row['role']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus pengguna ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-end">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($i) ?>&limit=<?= esc($limit) ?>"><?= esc($i) ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
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
    <div class="modal-dialog">
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