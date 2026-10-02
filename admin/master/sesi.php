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
        <div class="container-fluid px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus me-2"></i> Tambah Sesi
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
                                <th>Nama Sesi</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th class="text-center">Siswa</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; foreach ($listSesi as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold"><?= $row['nama_sesi'] ?></td>
                                <td><span class="badge bg-light text-dark border"><?= date('H:i', strtotime($row['jam_mulai'])) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= date('H:i', strtotime($row['jam_selesai'])) ?></span></td>
                                <td class="text-center">
                                    <a href="assign-sesi.php" class="badge bg-info-subtle text-info border border-info-subtle text-decoration-none">
                                        <?= $row['jumlah_siswa'] ?> siswa
                                    </a>
                                </td>
                                <td class="text-center">
                                    <a href="?toggle=<?= esc($row['id']) ?>&s=<?= esc($row['is_aktif']) ?>" class="text-decoration-none">
                                        <?php if ($row['is_aktif'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3">Non-Aktif</span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <a href="assign-sesi.php" class="btn btn-sm btn-outline-info" title="Assign Siswa ke Sesi Ini">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($row['id']) ?>"
                                                data-nama="<?= esc($row['nama_sesi']) ?>"
                                                data-mulai="<?= esc($row['jam_mulai']) ?>"
                                                data-selesai="<?= esc($row['jam_selesai']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus sesi ini?')">
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
    <div class="modal-dialog">
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