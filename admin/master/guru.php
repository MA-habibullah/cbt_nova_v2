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

// --- PROSES SIMPAN GURU BARU ---
if (isset($_POST['simpan'])) {
    $nip = trim($_POST['nip'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    // Cek username unik
    $chk = $pdo->prepare("SELECT id FROM cbt_teachers WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) {
        header("Location: guru.php?msg=username_exists");
        exit;
    }

    $password = password_hash($password_raw, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO cbt_teachers (nip, nama_lengkap, username, password, is_aktif, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$nip, $nama_lengkap, $username, $password]);

    log_activity("Tambah guru baru: $nama_lengkap (NIP: " . ($nip ?: '-') . ")", null, null, null, 'master');
    header("Location: guru.php?f_status=1&msg=disimpan");
    exit;
}

// --- PROSES UPDATE DATA GURU ---
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $nip = trim($_POST['nip'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $is_aktif = (int)($_POST['is_aktif'] ?? 1);

    // Cek username unik untuk id selain dirinya
    $chk = $pdo->prepare("SELECT id FROM cbt_teachers WHERE username = ? AND id != ?");
    $chk->execute([$username, $id]);
    if ($chk->fetch()) {
        header("Location: guru.php?msg=username_exists");
        exit;
    }

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $sql = "UPDATE cbt_teachers SET nip=?, nama_lengkap=?, username=?, password=?, is_aktif=?, updated_at=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$nip, $nama_lengkap, $username, $password, $is_aktif, $id]);
    } else {
        $sql = "UPDATE cbt_teachers SET nip=?, nama_lengkap=?, username=?, is_aktif=?, updated_at=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$nip, $nama_lengkap, $username, $is_aktif, $id]);
    }

    log_activity("Update data guru ID $id: $nama_lengkap (NIP: " . ($nip ?: '-') . ")", null, null, null, 'master');
    header("Location: guru.php?f_status=" . urlencode($_GET['f_status'] ?? '1') . "&msg=updated");
    exit;
}

// --- PROSES TOGGLE STATUS (AKTIF / NON-AKTIF) ---
if (isset($_POST['action']) && in_array($_POST['action'], ['nonaktifkan', 'aktifkan'])) {
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $new_status = ($_POST['action'] === 'aktifkan') ? 1 : 0;

    if ($teacher_id > 0) {
        $st = $pdo->prepare("SELECT nama_lengkap, nip FROM cbt_teachers WHERE id = ?");
        $st->execute([$teacher_id]);
        $gdata = $st->fetch();

        $pdo->prepare("UPDATE cbt_teachers SET is_aktif = ?, updated_at = NOW() WHERE id = ?")->execute([$new_status, $teacher_id]);

        $action_label = ($new_status === 1) ? "Aktivasi kembali" : "Nonaktifkan";
        log_activity("$action_label guru: " . ($gdata['nama_lengkap'] ?? '-') . " (NIP: " . ($gdata['nip'] ?? '-') . ")", null, null, null, 'master');

        $flash_msg = ($new_status === 1) ? "restored" : "nonaktif";
        header("Location: guru.php?f_status=" . ($new_status === 1 ? '1' : '0') . "&msg=$flash_msg");
        exit;
    }
}

// --- KONFIGURASI FILTER, SEARCH & PAGINATION ---
$limit  = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 50;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search   = trim($_GET['search'] ?? '');
// Default filter: '1' (Aktif), '0' (Non-Aktif), atau 'all' (Semua)
$f_status = isset($_GET['f_status']) ? $_GET['f_status'] : '1';

// Membangun Query
$query_str = "SELECT t.*,
              (SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = t.id) AS total_bank_soal,
              (SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = t.id) AS total_ujian
              FROM cbt_teachers t WHERE 1=1";
$params = [];

if ($search !== '') {
    $query_str .= " AND (t.nama_lengkap LIKE ? OR t.nip LIKE ? OR t.username LIKE ?)";
    $s = like_escape($search);
    $params = array_merge($params, ["%$s%", "%$s%", "%$s%"]);
}
if ($f_status !== '' && $f_status !== 'all') {
    $query_str .= " AND t.is_aktif = ?";
    $params[] = (int)$f_status;
}

// Total untuk Pagination
$stmt_count = $pdo->prepare(str_replace("t.*,\n              (SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = t.id) AS total_bank_soal,\n              (SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = t.id) AS total_ujian", "COUNT(*)", $query_str));
$stmt_count->execute($params);
$totalData = (int)$stmt_count->fetchColumn();
$pages = max(1, (int)ceil($totalData / $limit));
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $limit;
}

// Ambil Data Akhir
$query_str .= " ORDER BY t.nama_lengkap ASC LIMIT $limit OFFSET $offset";
$stmt_data = $pdo->prepare($query_str);
$stmt_data->execute($params);
$listGuru = $stmt_data->fetchAll();

// Counter Statistik Guru
$count_aktif  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_teachers WHERE is_aktif = 1")->fetchColumn();
$count_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM cbt_teachers WHERE is_aktif = 0")->fetchColumn();
$count_all    = $count_aktif + $count_nonaktif;
?>

<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm border-bottom">
            <button class="btn btn-light border" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <div class="ms-3 d-flex align-items-center">
                <i class="fas fa-chalkboard-teacher text-primary fs-5 me-2"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Manajemen Data Guru</h5>
                    <small class="text-muted">Kelola akun guru pengajar, hak akses pembuatan bank soal, dan jadwal ujian</small>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        <div class="px-4 pt-3">
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if ($flash === 'disimpan'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <strong>Berhasil!</strong> Data guru baru berhasil ditambahkan.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'updated'): ?>
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <strong>Berhasil!</strong> Data guru berhasil diperbarui.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'nonaktif'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-user-slash me-2"></i> <strong>Guru Dinonaktifkan:</strong> Status guru telah diubah menjadi Non-Aktif. Seluruh bank soal dan ujian buatan guru tetap tersimpan aman.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'restored'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-user-check me-2"></i> <strong>Berhasil Dipulihkan!</strong> Akun guru telah diaktifkan kembali.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'username_exists'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <strong>Gagal:</strong> Username sudah digunakan oleh guru lain. Harap gunakan username yang berbeda.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'import_done'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Proses Import Guru Selesai</h6>
                            <span>Berhasil menambahkan <strong><?= (int)($_GET['success'] ?? 0) ?></strong> guru baru.</span>
                            <?php if (isset($_GET['skipped']) && (int)$_GET['skipped'] > 0): ?>
                                <div class="text-danger small mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong><?= (int)$_GET['skipped'] ?></strong> data dilewati karena Username sudah terdaftar di database.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="container-fluid px-4 py-2">

            <!-- Navigasi Tab Status Guru (Card Navigation) -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?f_status=1<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" 
                           class="btn btn-sm <?= $f_status === '1' ? 'btn-primary shadow-sm text-white fw-bold' : 'btn-light border text-secondary' ?> px-3 py-2 rounded-2">
                            <i class="fas fa-user-check me-1"></i> Guru Aktif
                            <span class="badge <?= $f_status === '1' ? 'bg-white text-primary' : 'bg-primary-subtle text-primary' ?> ms-2"><?= number_format($count_aktif, 0, ',', '.') ?></span>
                        </a>
                        <a href="?f_status=0<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" 
                           class="btn btn-sm <?= $f_status === '0' ? 'btn-secondary shadow-sm text-white fw-bold' : 'btn-light border text-secondary' ?> px-3 py-2 rounded-2">
                            <i class="fas fa-user-slash me-1"></i> Guru Non-Aktif
                            <span class="badge <?= $f_status === '0' ? 'bg-white text-secondary' : 'bg-secondary-subtle text-secondary' ?> ms-2"><?= number_format($count_nonaktif, 0, ',', '.') ?></span>
                        </a>
                        <a href="?f_status=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" 
                           class="btn btn-sm <?= $f_status === 'all' ? 'btn-dark shadow-sm text-white fw-bold' : 'btn-light border text-secondary' ?> px-3 py-2 rounded-2">
                            <i class="fas fa-users me-1"></i> Semua Guru
                            <span class="badge <?= $f_status === 'all' ? 'bg-white text-dark' : 'bg-light text-dark border' ?> ms-2"><?= number_format($count_all, 0, ',', '.') ?></span>
                        </a>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus me-1"></i> Tambah Guru
                        </button>
                        <button class="btn btn-sm btn-success shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImport">
                            <i class="fas fa-file-excel me-1"></i> Import
                        </button>
                        <a href="<?= esc(BASE_URL) ?>admin/master-io/export_guru.php" class="btn btn-sm btn-outline-primary shadow-sm fw-semibold">
                            <i class="fas fa-file-export me-1"></i> Export
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter Bar Box -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form action="" method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="f_status" value="<?= esc($f_status) ?>">
                        <input type="hidden" name="limit" value="<?= esc($limit) ?>">

                        <div class="col-lg-8 col-md-7">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-search me-1"></i>Pencarian Guru</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Ketik Nama Guru, NIP, atau Username..." value="<?= esc($search) ?>">
                        </div>
                        <div class="col-lg-4 col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                                <i class="fas fa-filter me-1"></i> Terapkan
                            </button>
                            <a href="guru.php?f_status=<?= urlencode($f_status) ?>" class="btn btn-light border btn-sm text-secondary" title="Reset Filter">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-list-ul text-primary me-2"></i>Daftar Guru Pengajar</h6>
                        <span class="badge bg-light text-secondary border px-2 py-1"><?= number_format($totalData, 0, ',', '.') ?> guru ditemukan</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0 fw-medium">Tampilkan per halaman:</label>
                        <select class="form-select form-select-sm shadow-none" style="width: 85px;" onchange="changeLimit(this.value)" id="limitSelect">
                            <option value="25" <?= ($limit==25 ? 'selected' : '') ?>>25</option>
                            <option value="50" <?= ($limit==50 ? 'selected' : '') ?>>50</option>
                            <option value="100" <?= ($limit==100 ? 'selected' : '') ?>>100</option>
                            <option value="200" <?= ($limit==200 ? 'selected' : '') ?>>200</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                            <tr>
                                <th class="text-center py-3" width="50">#</th>
                                <th class="text-center py-3" width="70">Avatar</th>
                                <th class="py-3">Nama Lengkap & NIP</th>
                                <th class="py-3">Username Login</th>
                                <th class="py-3 text-center">Bank Soal</th>
                                <th class="py-3 text-center">Jadwal Ujian</th>
                                <th class="py-3">Status</th>
                                <th class="text-center py-3" width="180">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listGuru)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fas fa-chalkboard-teacher fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                                        <h6 class="fw-bold text-secondary mb-1">Tidak Ada Data Guru</h6>
                                        <p class="small text-muted mb-0">Tidak ditemukan guru yang cocok dengan filter atau kata kunci pencarian.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $no = $offset + 1; foreach($listGuru as $row): ?>
                            <tr>
                                <td class="text-center text-muted fw-bold small"><?= $no++ ?></td>
                                <td class="text-center">
                                    <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-bold shadow-sm" style="width:42px;height:42px;font-size:14px;">
                                        <?= strtoupper(mb_substr($row['nama_lengkap'], 0, 2)) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= esc($row['nama_lengkap']) ?></div>
                                    <div class="mt-1">
                                        <?php if (!empty($row['nip'])): ?>
                                            <span class="badge bg-light text-secondary border font-monospace">
                                                <i class="fas fa-id-badge me-1"></i>NIP: <?= esc($row['nip']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">NIP Belum Diisi</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-dark border font-monospace px-2 py-1">
                                        <i class="fas fa-user text-primary me-1"></i><?= esc($row['username']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                        <i class="fas fa-folder-open me-1"></i><?= (int)$row['total_bank_soal'] ?> Bank Soal
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <i class="fas fa-calendar-alt me-1"></i><?= (int)$row['total_ujian'] ?> Ujian
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$row['is_aktif'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i>Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">
                                            <i class="fas fa-ban me-1"></i>Non-Aktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm shadow-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-edit-guru" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($row['id']) ?>" 
                                                data-nip="<?= esc($row['nip']) ?>"
                                                data-nama="<?= esc($row['nama_lengkap']) ?>" 
                                                data-user="<?= esc($row['username']) ?>"
                                                data-status="<?= esc($row['is_aktif']) ?>"
                                                title="Edit Data Guru">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>

                                        <?php if ((int)$row['is_aktif'] === 1): ?>
                                            <button type="button" class="btn btn-outline-warning text-dark btn-toggle-status"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                    data-action="nonaktifkan"
                                                    title="Nonaktifkan Akun Guru">
                                                <i class="fas fa-user-slash text-warning"></i> Nonaktifkan
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-success btn-toggle-status"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                    data-action="aktifkan"
                                                    title="Aktifkan Kembali Akun Guru">
                                                <i class="fas fa-user-check me-1"></i> Aktifkan
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Pagination Bar -->
                <div class="card-footer bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
                    <div class="small text-muted">
                        Menampilkan <strong><?= count($listGuru) ?></strong> dari <strong><?= number_format($totalData, 0, ',', '.') ?></strong> total guru
                        <?php if ($pages > 1): ?> (Halaman <?= $page ?> dari <?= $pages ?>)<?php endif; ?>
                    </div>
                    <?php if ($pages > 1): ?>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $base_params = http_build_query([
                            'limit'    => $limit,
                            'search'   => $search,
                            'f_status' => $f_status,
                        ]);

                        $prevDisabled = ($page <= 1) ? 'disabled' : '';
                        $prevPage = max(1, $page - 1);
                        echo "<li class='page-item $prevDisabled'><a class='page-link' href='?page={$prevPage}&{$base_params}'><i class='fas fa-chevron-left'></i></a></li>";

                        $start_number = ($page > 3) ? $page - 2 : 1;
                        $end_number = ($page < ($pages - 2)) ? $page + 2 : $pages;

                        if ($start_number > 1) {
                            echo "<li class='page-item'><a class='page-link' href='?page=1&{$base_params}'>1</a></li>";
                            if ($start_number > 2) {
                                echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                            }
                        }

                        for ($i = $start_number; $i <= $end_number; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= esc($i) ?>&<?= esc($base_params) ?>"><?= esc($i) ?></a>
                            </li>
                        <?php endfor;

                        if ($end_number < $pages) {
                            if ($end_number < $pages - 1) {
                                echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                            }
                            echo "<li class='page-item'><a class='page-link' href='?page={$pages}&{$base_params}'>{$pages}</a></li>";
                        }

                        $nextDisabled = ($page >= $pages) ? 'disabled' : '';
                        $nextPage = min($pages, $page + 1);
                        echo "<li class='page-item $nextDisabled'><a class='page-link' href='?page={$nextPage}&{$base_params}'><i class='fas fa-chevron-right'></i></a></li>";
                        ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT GURU -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i>Edit Data Guru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="id" id="edit-id">
                <div class="col-12">
                    <label class="form-label small fw-bold">NIP (Nomor Induk Pegawai)</label>
                    <input type="text" name="nip" id="edit-nip" class="form-control" placeholder="Kosongkan jika tidak ada NIP">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Nama Lengkap & Gelar <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" id="edit-nama" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username Login <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="edit-user" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-danger">Ganti Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tetap" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Status Keaktifan</label>
                    <select name="is_aktif" id="edit-status" class="form-select">
                        <option value="1">Aktif</option>
                        <option value="0">Non-Aktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="update" class="btn btn-primary px-4 fw-bold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH GURU -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah Guru Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label small fw-bold">NIP (Nomor Induk Pegawai)</label>
                    <input type="text" name="nip" class="form-control" placeholder="Contoh: 198001012005011001 (Opsional)">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Nama Lengkap & Gelar <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Dra. Hj. Siti Aminah, M.Pd" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username Login <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="Username login" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Password Login <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Password login" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="simpan" class="btn btn-primary px-4 fw-bold">Simpan Guru</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT GURU -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="../master-io/import_guru.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-excel me-2"></i>Import Data Guru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small border-0 shadow-sm mb-3">
                    <i class="fas fa-info-circle me-1"></i> Gunakan file Excel (.xlsx) dengan urutan kolom:<br>
                    <strong>NIP, Nama Lengkap, Username, Password</strong>
                </div>
                <div class="mb-3">
                    <a href="../master-io/export_guru.php" class="btn btn-sm btn-outline-success w-100 mb-3 fw-semibold">
                        <i class="fas fa-download me-1"></i> Download Format Template Guru (.xlsx)
                    </a>
                    <label class="form-label small fw-bold">Pilih File Excel</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="import" class="btn btn-success px-4 fw-bold">
                    <i class="fas fa-upload me-1"></i> Mulai Import
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Populate modal edit guru
    $('.btn-edit-guru').on('click', function() {
        $('#edit-id').val($(this).data('id'));
        $('#edit-nip').val($(this).data('nip'));
        $('#edit-nama').val($(this).data('nama'));
        $('#edit-user').val($(this).data('user'));
        $('#edit-status').val($(this).data('status'));
    });

    // SweetAlert2 confirmation for status toggle
    $('.btn-toggle-status').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var action = $(this).data('action');

        var isDeactivating = (action === 'nonaktifkan');
        var title = isDeactivating ? 'Nonaktifkan Akun Guru?' : 'Aktifkan Kembali Akun Guru?';
        var text = isDeactivating 
            ? 'Akun guru <b>' + $('<div>').text(nama).html() + '</b> akan dinonaktifkan.<br><small class="text-muted">Seluruh bank soal dan riwayat ujian buatan guru tetap aman tersimpan.</small>'
            : 'Akun guru <b>' + $('<div>').text(nama).html() + '</b> akan diaktifkan kembali sehingga dapat login ke sistem CBT.';
        var confirmText = isDeactivating ? '<i class="fas fa-user-slash me-1"></i> Ya, Nonaktifkan' : '<i class="fas fa-user-check me-1"></i> Ya, Aktifkan';
        var confirmColor = isDeactivating ? '#ffc107' : '#198754';

        Swal.fire({
            title: title,
            html: text,
            icon: isDeactivating ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: isDeactivating ? 'btn btn-warning text-dark fw-bold px-3' : 'btn btn-success fw-bold px-3',
                cancelButton: 'btn btn-secondary px-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'guru.php';

                var inputCsrf = document.createElement('input');
                inputCsrf.type = 'hidden';
                inputCsrf.name = 'csrf_token';
                inputCsrf.value = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
                form.appendChild(inputCsrf);

                var inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = action;
                form.appendChild(inputAction);

                var inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'teacher_id';
                inputId.value = id;
                form.appendChild(inputId);

                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

// Ganti limit sambil mempertahankan query filter
function changeLimit(val) {
    var url = new URL(window.location.href);
    url.searchParams.set('limit', val);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}
</script>
</body>
</html>