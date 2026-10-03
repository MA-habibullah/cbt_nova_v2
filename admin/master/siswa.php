<?php
session_start();
if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }

// --- HELPER: UPLOAD FOTO SISWA ---
function proses_upload_foto_siswa(?array $file): ?string {
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_msg'] = 'Terjadi kesalahan saat upload foto (kode: ' . $file['error'] . ').';
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['error_msg'] = 'Ukuran file foto maksimal 5 MB.';
        return null;
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        $_SESSION['error_msg'] = 'File upload tidak valid.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes)) {
        $_SESSION['error_msg'] = 'MIME type file foto tidak valid.';
        return null;
    }

    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $_SESSION['error_msg'] = 'File foto bukan gambar yang valid.';
        return null;
    }

    $mime = $image_info['mime'];
    $src = match($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default      => false,
    };

    if (!$src) {
        $_SESSION['error_msg'] = 'Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        return null;
    }

    // Resize ke maks 400×400px, pertahankan aspek rasio
    $orig_w = imagesx($src);
    $orig_h = imagesy($src);
    $max    = 400;
    if ($orig_w > $max || $orig_h > $max) {
        $ratio = min($max / $orig_w, $max / $orig_h);
        $new_w = (int) round($orig_w * $ratio);
        $new_h = (int) round($orig_h * $ratio);
    } else {
        $new_w = $orig_w;
        $new_h = $orig_h;
    }

    $dst = imagecreatetruecolor($new_w, $new_h);

    // Background putih
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    imagedestroy($src);

    $dir      = dirname(__FILE__, 3) . '/assets/uploads/foto_siswa/';
    $filename = 'siswa_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Simpan sebagai JPEG quality 80
    if (!imagejpeg($dst, $dir . $filename, 80)) {
        imagedestroy($dst);
        $_SESSION['error_msg'] = 'Gagal menyimpan foto. Periksa permission direktori uploads.';
        return null;
    }

    imagedestroy($dst);
    return $filename;
}

// --- CONFIGURATION: FILTER, SEARCH, PAGINATION ---
$limit  = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 50;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search   = trim($_GET['search'] ?? '');
$f_kelas  = $_GET['f_kelas'] ?? '';
$f_jenjang= $_GET['f_jenjang'] ?? '';
$f_sesi   = $_GET['f_sesi'] ?? '';
$f_agama  = $_GET['f_agama'] ?? '';
// Default filter status: '1' (Aktif), '0' (Non-Aktif & Alumni), atau 'all' (Semua)
$f_status = isset($_GET['f_status']) ? $_GET['f_status'] : '1'; 

// Membangun Query
$query_str = "SELECT s.*, k.nama_kelas, k.jenjang FROM cbt_students s 
              LEFT JOIN cbt_classes k ON s.class_id = k.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $query_str .= " AND (s.nama_lengkap LIKE ? OR s.nisn LIKE ? OR s.username LIKE ?)";
    $s = like_escape($search);
    $params = array_merge($params, ["%$s%", "%$s%", "%$s%"]);
}
if ($f_kelas !== '')   { $query_str .= " AND s.class_id = ?"; $params[] = $f_kelas; }
if ($f_jenjang !== '') { $query_str .= " AND k.jenjang = ?"; $params[] = $f_jenjang; }
if ($f_sesi !== '')    { $query_str .= " AND s.sesi = ?"; $params[] = $f_sesi; }
if ($f_agama !== '')   { $query_str .= " AND s.agama = ?"; $params[] = $f_agama; }
if ($f_status !== '' && $f_status !== 'all') { 
    $query_str .= " AND s.is_aktif = ?"; 
    $params[] = (int)$f_status; 
}

// Total Data untuk Pagination
$stmt_count = $pdo->prepare(str_replace("s.*, k.nama_kelas, k.jenjang", "COUNT(*)", $query_str));
$stmt_count->execute($params);
$totalData = (int)$stmt_count->fetchColumn();
$pages = max(1, (int)ceil($totalData / $limit));
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $limit;
}

// Ambil Data Akhir
$query_str .= " ORDER BY s.nama_lengkap ASC LIMIT $limit OFFSET $offset";
$stmt_data = $pdo->prepare($query_str);
$stmt_data->execute($params);
$listSiswa = $stmt_data->fetchAll();

// Data Master untuk Dropdown
$allKelas = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas")->fetchAll();
$allKelasAktif = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 AND nama_kelas != 'LULUS' ORDER BY jenjang, nama_kelas")->fetchAll();
$allSesi  = $pdo->query("SELECT * FROM cbt_sesi WHERE is_aktif = 1")->fetchAll();
$allJenjang = $pdo->query("SELECT DISTINCT jenjang FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang")->fetchAll(PDO::FETCH_COLUMN);
$listAgama = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'];

// Counter Statistik Status Siswa (Menggunakan Indeks idx_student_aktif)
$count_aktif  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_students WHERE is_aktif = 1")->fetchColumn();
$count_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM cbt_students WHERE is_aktif = 0")->fetchColumn();
$count_all    = $count_aktif + $count_nonaktif;

// --- PROSES SIMPAN SISWA BARU ---
if (isset($_POST['simpan'])) {
    $pass_input = !empty($_POST['password']) ? $_POST['password'] : $_POST['nisn'];
    $password_hash = password_hash($pass_input, PASSWORD_BCRYPT);
    $foto = proses_upload_foto_siswa($_FILES['foto'] ?? null);

    $stmt = $pdo->prepare("INSERT INTO cbt_students (nisn, nama_lengkap, username, password, kartu, class_id, sesi, agama, foto, is_aktif, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([
        $_POST['nisn'],
        $_POST['nama_lengkap'],
        $_POST['username'],
        $password_hash,
        $pass_input,
        $_POST['class_id'],
        $_POST['sesi'] ?? 1,
        $_POST['agama'] ?? 'Islam',
        $foto,
    ]);
    
    log_activity("Tambah siswa baru: " . $_POST['nama_lengkap'] . " (NISN: " . $_POST['nisn'] . ")", null, null, null, 'master');
    header("Location: siswa.php?f_status=1&msg=disimpan");
    exit;
}

// --- PROSES UPDATE SISWA ---
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $foto_baru = proses_upload_foto_siswa($_FILES['foto'] ?? null);

    if ($foto_baru !== null) {
        $stmt_old = $pdo->prepare("SELECT foto FROM cbt_students WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_foto = $stmt_old->fetchColumn();
        if ($old_foto) {
            $old_foto_path = dirname(__FILE__, 3) . '/assets/uploads/foto_siswa/' . $old_foto;
            if (file_exists($old_foto_path)) {
                @unlink($old_foto_path);
            }
        }
    }

    if (!empty($_POST['password'])) {
        $pass_baru = $_POST['password'];
        $password_hash = password_hash($pass_baru, PASSWORD_BCRYPT);

        if ($foto_baru !== null) {
            $sql = "UPDATE cbt_students SET nisn=?, nama_lengkap=?, username=?, class_id=?, sesi=?, agama=?, is_aktif=?, password=?, kartu=?, foto=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nisn'],
                $_POST['nama_lengkap'],
                $_POST['username'],
                $_POST['class_id'],
                $_POST['sesi'] ?? 1,
                $_POST['agama'] ?? 'Islam',
                $_POST['is_aktif'],
                $password_hash,
                $pass_baru,
                $foto_baru,
                $id,
            ]);
        } else {
            $sql = "UPDATE cbt_students SET nisn=?, nama_lengkap=?, username=?, class_id=?, sesi=?, agama=?, is_aktif=?, password=?, kartu=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nisn'],
                $_POST['nama_lengkap'],
                $_POST['username'],
                $_POST['class_id'],
                $_POST['sesi'] ?? 1,
                $_POST['agama'] ?? 'Islam',
                $_POST['is_aktif'],
                $password_hash,
                $pass_baru,
                $id,
            ]);
        }
    } else {
        if ($foto_baru !== null) {
            $sql = "UPDATE cbt_students SET nisn=?, nama_lengkap=?, username=?, class_id=?, sesi=?, agama=?, is_aktif=?, foto=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nisn'],
                $_POST['nama_lengkap'],
                $_POST['username'],
                $_POST['class_id'],
                $_POST['sesi'] ?? 1,
                $_POST['agama'] ?? 'Islam',
                $_POST['is_aktif'],
                $foto_baru,
                $id,
            ]);
        } else {
            $sql = "UPDATE cbt_students SET nisn=?, nama_lengkap=?, username=?, class_id=?, sesi=?, agama=?, is_aktif=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nisn'],
                $_POST['nama_lengkap'],
                $_POST['username'],
                $_POST['class_id'],
                $_POST['sesi'] ?? 1,
                $_POST['agama'] ?? 'Islam',
                $_POST['is_aktif'],
                $id,
            ]);
        }
    }
    
    log_activity("Update data siswa ID $id: " . $_POST['nama_lengkap'] . " (NISN: " . $_POST['nisn'] . ")", null, null, null, 'master');
    header("Location: siswa.php?f_status=" . urlencode($f_status) . "&msg=updated");
    exit;
}

// --- PROSES NONAKTIFKAN SISWA (PENGGANTI HAPUS PERMANEN) ---
if (isset($_POST['action']) && $_POST['action'] === 'nonaktifkan') {
    $nonaktif_id = (int)($_POST['student_id'] ?? 0);
    if ($nonaktif_id > 0) {
        $st = $pdo->prepare("SELECT nama_lengkap, nisn FROM cbt_students WHERE id = ?");
        $st->execute([$nonaktif_id]);
        $sdata = $st->fetch();

        $stmt_nonaktif = $pdo->prepare("UPDATE cbt_students SET is_aktif = 0, updated_at = NOW() WHERE id = ?");
        $stmt_nonaktif->execute([$nonaktif_id]);

        log_activity("Nonaktifkan siswa: " . ($sdata['nama_lengkap'] ?? '-') . " (NISN: " . ($sdata['nisn'] ?? '-') . ")", null, null, null, 'master');
        header("Location: siswa.php?f_status=0&msg=nonaktif");
        exit;
    }
}
if (isset($_GET['nonaktifkan'])) {
    $nonaktif_id = (int)$_GET['nonaktifkan'];
    if ($nonaktif_id > 0) {
        $st = $pdo->prepare("SELECT nama_lengkap, nisn FROM cbt_students WHERE id = ?");
        $st->execute([$nonaktif_id]);
        $sdata = $st->fetch();

        $stmt_nonaktif = $pdo->prepare("UPDATE cbt_students SET is_aktif = 0, updated_at = NOW() WHERE id = ?");
        $stmt_nonaktif->execute([$nonaktif_id]);

        log_activity("Nonaktifkan siswa: " . ($sdata['nama_lengkap'] ?? '-') . " (NISN: " . ($sdata['nisn'] ?? '-') . ")", null, null, null, 'master');
        header("Location: siswa.php?f_status=0&msg=nonaktif");
        exit;
    }
}

// --- PROSES RESTORE / AKTIFKAN KEMBALI SISWA ---
if (isset($_POST['restore_siswa'])) {
    $restore_id   = (int)($_POST['student_id'] ?? 0);
    $new_class_id = (int)($_POST['class_id'] ?? 0);
    $new_sesi     = (int)($_POST['sesi'] ?? 1);

    if ($restore_id > 0 && $new_class_id > 0) {
        $chk = $pdo->prepare("SELECT id, nama_kelas, jenjang FROM cbt_classes WHERE id = ? AND is_aktif = 1");
        $chk->execute([$new_class_id]);
        $target_kelas = $chk->fetch();

        if ($target_kelas) {
            $stmt_restore = $pdo->prepare("UPDATE cbt_students SET is_aktif = 1, class_id = ?, sesi = ?, updated_at = NOW() WHERE id = ?");
            $stmt_restore->execute([$new_class_id, $new_sesi, $restore_id]);

            $st = $pdo->prepare("SELECT nama_lengkap, nisn FROM cbt_students WHERE id = ?");
            $st->execute([$restore_id]);
            $sdata = $st->fetch();

            log_activity("Restore status siswa ID $restore_id: " . ($sdata['nama_lengkap'] ?? '-') . " (NISN: " . ($sdata['nisn'] ?? '-') . ") ke kelas " . $target_kelas['jenjang'] . "-" . $target_kelas['nama_kelas'], null, null, null, 'master');
            header("Location: siswa.php?f_status=1&msg=restored");
            exit;
        }
    }
    header("Location: siswa.php?f_status=0&msg=restore_failed");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>

<style>
/* Custom Horizontal Scrollbar */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.table-responsive::-webkit-scrollbar {
    height: 7px;
}
.table-responsive::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm border-bottom">
            <button class="btn btn-light border" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <div class="ms-3 d-flex align-items-center">
                <i class="fas fa-user-graduate text-primary fs-5 me-2"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Manajemen Data Siswa</h5>
                    <small class="text-muted">Kelola data induk siswa, foto profil, sesi ujian, dan status keaktifan</small>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        <div class="px-4 pt-3">
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if ($flash === 'disimpan'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <strong>Berhasil!</strong> Data siswa baru berhasil ditambahkan.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'updated'): ?>
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <strong>Berhasil!</strong> Data siswa berhasil diperbarui.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'nonaktif'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-user-slash me-2"></i> <strong>Siswa Dinonaktifkan:</strong> Siswa berhasil dipindahkan ke daftar <strong>Siswa Non-Aktif</strong>. Seluruh data nilai dan riwayat ujian tetap aman dan dapat dipulihkan kapan saja.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'restored'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-user-check me-2"></i> <strong>Berhasil Dipulihkan!</strong> Status siswa telah aktif kembali dan ditempatkan pada kelas baru.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'restore_failed'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> Gagal memulihkan status siswa. Pastikan kelas aktif yang dipilih valid.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'pilih_file'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> Harap pilih file Excel terlebih dahulu.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'import_done'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Proses Import Siswa Selesai</h6>
                            <span>Berhasil menambahkan <strong><?= (int)($_GET['success'] ?? 0) ?></strong> siswa baru.</span>
                            <?php if (isset($_GET['skipped']) && (int)$_GET['skipped'] > 0): ?>
                                <div class="text-danger small mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong><?= (int)$_GET['skipped'] ?></strong> data dilewati karena NISN atau Username sudah terdaftar.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-times-circle me-2"></i> <strong>Gagal:</strong> <?= htmlspecialchars($_GET['detail'] ?? 'Terjadi kesalahan sistem.') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="container-fluid px-4 py-2">
            <!-- Action & Filter Toolbar -->
            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <!-- Status Filter Tabs -->
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?f_status=1<?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($f_kelas) ? '&f_kelas='.urlencode($f_kelas) : '' ?><?= !empty($f_jenjang) ? '&f_jenjang='.urlencode($f_jenjang) : '' ?>&limit=<?= esc($limit) ?>" 
                           class="btn btn-sm <?= $f_status === '1' ? 'btn-primary shadow-sm text-white fw-bold' : 'btn-light border text-secondary' ?> px-3 py-2 rounded-2">
                            <i class="fas fa-user-check me-1"></i> Siswa Aktif
                            <span class="badge <?= $f_status === '1' ? 'bg-white text-primary' : 'bg-primary-subtle text-primary' ?> ms-2"><?= number_format($count_aktif, 0, ',', '.') ?></span>
                        </a>
                        <a href="?f_status=0<?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($f_kelas) ? '&f_kelas='.urlencode($f_kelas) : '' ?><?= !empty($f_jenjang) ? '&f_jenjang='.urlencode($f_jenjang) : '' ?>&limit=<?= esc($limit) ?>" 
                           class="btn btn-sm <?= $f_status === '0' ? 'btn-secondary shadow-sm text-white fw-bold' : 'btn-light border text-secondary' ?> px-3 py-2 rounded-2">
                            <i class="fas fa-user-slash me-1"></i> Siswa Non-Aktif &amp; Alumni
                            <span class="badge <?= $f_status === '0' ? 'bg-white text-secondary' : 'bg-secondary-subtle text-secondary' ?> ms-2"><?= number_format($count_nonaktif, 0, ',', '.') ?></span>
                        </a>
                        <a href="?f_status=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($f_kelas) ? '&f_kelas='.urlencode($f_kelas) : '' ?><?= !empty($f_jenjang) ? '&f_jenjang='.urlencode($f_jenjang) : '' ?>&limit=<?= esc($limit) ?>" 
                           class="btn btn-sm <?= $f_status === 'all' ? 'btn-dark shadow-sm text-white fw-bold' : 'btn-light border text-secondary' ?> px-3 py-2 rounded-2">
                            <i class="fas fa-users me-1"></i> Semua Siswa
                            <span class="badge <?= $f_status === 'all' ? 'bg-white text-dark' : 'bg-light text-dark border' ?> ms-2"><?= number_format($count_all, 0, ',', '.') ?></span>
                        </a>
                    </div>
                    <!-- Action Buttons -->
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus me-1"></i> Tambah Siswa
                        </button>
                        <button class="btn btn-sm btn-success shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImport">
                            <i class="fas fa-file-excel me-1"></i> Import
                        </button>
                        <a href="<?= esc(BASE_URL) ?>admin/master-io/export_siswa.php" class="btn btn-sm btn-outline-primary shadow-sm fw-semibold">
                            <i class="fas fa-file-export me-1"></i> Export
                        </a>
                        <a href="<?= esc(BASE_URL) ?>admin/siswa/cetak-kartu.php" class="btn btn-sm btn-outline-info shadow-sm fw-semibold">
                            <i class="fas fa-id-card me-1"></i> Cetak Kartu
                        </a>
                    </div>
                </div>
                
                <!-- Filter Controls Row -->
                <form action="" method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="f_status" value="<?= esc($f_status) ?>">

                    <div class="col-lg-3 col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Ketik Nama, NISN, atau Username..." value="<?= esc($search) ?>">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <select name="f_jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Jenjang</option>
                            <?php foreach($allJenjang as $j): ?>
                                <option value="<?= esc($j) ?>" <?= ($f_jenjang == $j ? 'selected' : '') ?>>Jenjang <?= esc($j) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <select name="f_kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Kelas</option>
                            <?php foreach($allKelas as $k): ?>
                                <option value="<?= esc($k['id']) ?>" <?= ($f_kelas == $k['id'] ? 'selected' : '') ?>><?= esc($k['jenjang']) ?> - <?= esc($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <select name="f_agama" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Agama</option>
                            <?php foreach($listAgama as $a): ?>
                                <option value="<?= esc($a) ?>" <?= ($f_agama == $a ? 'selected' : '') ?>><?= esc($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <select name="f_sesi" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Sesi</option>
                            <?php foreach($allSesi as $s): ?>
                                <option value="<?= esc($s['id']) ?>" <?= ($f_sesi == $s['id'] ? 'selected' : '') ?>><?= esc($s['nama_sesi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="25" <?= ($limit==25 ? 'selected' : '') ?>>25</option>
                            <option value="50" <?= ($limit==50 ? 'selected' : '') ?>>50</option>
                            <option value="100" <?= ($limit==100 ? 'selected' : '') ?>>100</option>
                            <option value="200" <?= ($limit==200 ? 'selected' : '') ?>>200</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill fw-bold shadow-sm" title="Terapkan Filter">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="siswa.php?f_status=<?= urlencode($f_status) ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Main Table Card -->
            <div class="card card-dashboard p-0 shadow-sm border-0 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-user-graduate text-primary"></i> Daftar Siswa Terdaftar
                    </h6>
                    <span class="badge bg-primary-subtle text-primary font-monospace rounded-pill px-3">
                        Total: <?= number_format($totalData, 0, ',', '.') ?> Siswa
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="min-width: 1050px;">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">>
                            <tr>
                                <th class="text-center py-3" width="50">#</th>
                                <th class="text-center py-3 d-none d-md-table-cell" width="60">Foto</th>
                                <th class="py-3">Nama Lengkap & Identitas</th>
                                <th class="py-3">Kelas & Jenjang</th>
                                <th class="py-3 d-none d-sm-table-cell">Sesi Ujian</th>
                                <th class="py-3 d-none d-lg-table-cell">Agama</th>
                                <th class="py-3 text-center" width="120">Status</th>
                                <th class="text-center py-3" width="180">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listSiswa)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fas fa-user-graduate fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                                        <h6 class="fw-bold text-secondary mb-1">Tidak Ada Data Siswa</h6>
                                        <p class="small text-muted mb-0">Tidak ditemukan siswa yang cocok dengan kriteria pencarian atau filter yang dipilih.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $no = $offset + 1; foreach($listSiswa as $row): ?>
                            <tr>
                                <td class="text-center text-muted fw-bold small"><?= $no++ ?></td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php if (!empty($row['foto'])): ?>
                                        <img src="<?= esc(BASE_URL) ?>assets/uploads/foto_siswa/<?= htmlspecialchars($row['foto']) ?>" 
                                             class="rounded-circle border shadow-sm" 
                                             style="width:38px;height:38px;object-fit:cover;" 
                                             alt="<?= esc($row['nama_lengkap']) ?>"
                                             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['nama_lengkap']) ?>&background=random';">
                                    <?php else: ?>
                                        <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-bold" style="width:38px;height:38px;font-size:13px;">
                                             <?= strtoupper(mb_substr($row['nama_lengkap'], 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= esc($row['nama_lengkap']) ?></div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                        <span class="badge badge-soft-primary font-monospace">
                                            <i class="fas fa-id-card me-1"></i><?= esc($row['nisn']) ?>
                                        </span>
                                        <span class="text-muted font-monospace">
                                            <i class="fas fa-user text-secondary me-1"></i><?= esc($row['username']) ?>
                                        </span>
                                    </div>
                                    <!-- Sub-label untuk Tampilan Mobile (< 768px) -->
                                    <div class="d-md-none mt-1 small text-muted">
                                        <span class="badge bg-light text-dark border me-1"><i class="fas fa-clock text-secondary me-1"></i>Sesi <?= esc($row['sesi']) ?></span>
                                        <?php if (!empty($row['agama'])): ?><span class="text-secondary"><?= esc($row['agama']) ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ((int)$row['is_aktif'] === 1): ?>
                                        <span class="badge badge-soft-info px-2 py-1 rounded-2 fw-semibold">
                                            <i class="fas fa-graduation-cap me-1"></i><?= esc($row['jenjang'] ?? '-') ?> - <?= esc($row['nama_kelas'] ?? 'Tanpa Kelas') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-secondary px-2 py-1 rounded-2 fw-semibold">
                                            <i class="fas fa-graduation-cap me-1"></i><?= esc($row['nama_kelas'] === 'LULUS' ? 'Alumni (Lulus)' : (($row['jenjang'] ? $row['jenjang'].'-' : '') . ($row['nama_kelas'] ?? 'Alumni'))) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="fas fa-clock text-secondary me-1"></i>Sesi <?= esc($row['sesi']) ?>
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="badge bg-light text-secondary border px-2 py-1">
                                        <?= esc($row['agama'] ?? '-') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$row['is_aktif'] === 1): ?>
                                        <span class="badge badge-soft-success px-2 py-1 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i>Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger px-2 py-1 rounded-pill">
                                            <i class="fas fa-ban me-1"></i>Non-Aktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm shadow-sm" role="group">
                                        <?php if ((int)$row['is_aktif'] === 0): ?>
                                            <button type="button" class="btn btn-success btn-restore-siswa" 
                                                    data-bs-toggle="modal" data-bs-target="#modalRestore"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                    data-nisn="<?= esc($row['nisn']) ?>"
                                                    data-user="<?= esc($row['username']) ?>"
                                                    data-kelas="<?= (int)$row['class_id'] ?>"
                                                    data-sesi="<?= (int)$row['sesi'] ?>"
                                                    title="Pulihkan & Aktifkan Kembali">
                                                <i class="fas fa-undo me-1"></i> Pulihkan
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-outline-primary btn-edit-siswa" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($row['id']) ?>" 
                                                data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                data-nisn="<?= esc($row['nisn']) ?>" 
                                                data-user="<?= esc($row['username']) ?>"
                                                data-kelas="<?= esc($row['class_id']) ?>" 
                                                data-sesi="<?= esc($row['sesi']) ?>"
                                                data-agama="<?= esc($row['agama']) ?>" 
                                                data-status="<?= esc($row['is_aktif']) ?>"
                                                data-foto="<?= htmlspecialchars($row['foto'] ?? '') ?>"
                                                title="Edit Data Siswa">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>

                                        <?php if ((int)$row['is_aktif'] === 1): ?>
                                            <button type="button" class="btn btn-outline-warning text-dark btn-nonaktifkan"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                    data-nisn="<?= esc($row['nisn']) ?>"
                                                    title="Nonaktifkan Siswa (Pindahkan ke Siswa Non-Aktif)">
                                                <i class="fas fa-user-slash text-warning"></i> Nonaktifkan
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
                        Menampilkan <strong><?= count($listSiswa) ?></strong> dari <strong><?= number_format($totalData, 0, ',', '.') ?></strong> total data siswa
                        <?php if ($pages > 1): ?> (Halaman <?= $page ?> dari <?= $pages ?>)<?php endif; ?>
                    </div>
                    <?php if ($pages > 1): ?>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $base_params = http_build_query([
                            'limit'    => $limit,
                            'search'   => $search,
                            'f_kelas'  => $f_kelas,
                            'f_sesi'   => $f_sesi,
                            'f_jenjang' => $f_jenjang,
                            'f_agama'  => $f_agama,
                            'f_status' => $f_status,
                        ]);

                        // Previous button
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

                        // Next button
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

<!-- MODAL RESTORE / PULIHKAN SISWA -->
<div class="modal fade" id="modalRestore" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="restore_siswa" value="1">
            <input type="hidden" name="student_id" id="restore-id">
            
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-undo me-2"></i>Pulihkan Status Siswa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0 shadow-sm small mb-3">
                    <i class="fas fa-info-circle me-1"></i> Siswa yang dipulihkan akan kembali berstatus <strong>Aktif</strong>. Seluruh riwayat ujian, jawaban, nilai, dan akun login lama siswa tetap utuh dan aman.
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Nama Siswa</label>
                    <input type="text" id="restore-nama" class="form-control bg-light" readonly>
                </div>
                
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted">NISN</label>
                        <input type="text" id="restore-nisn" class="form-control bg-light" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted">Username</label>
                        <input type="text" id="restore-user" class="form-control bg-light" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-primary">Pilih Kelas Baru Penempatan <span class="text-danger">*</span></label>
                    <select name="class_id" id="restore-kelas" class="form-select border-primary" required>
                        <option value="">-- Pilih Kelas Aktif --</option>
                        <?php foreach($allKelasAktif as $k): ?>
                            <option value="<?= (int)$k['id'] ?>"><?= esc($k['jenjang']) ?> - <?= esc($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Pilih kelas aktif baru tempat siswa ini akan ditempatkan.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Pilih Sesi Ujian</label>
                    <select name="sesi" id="restore-sesi" class="form-select">
                        <?php foreach($allSesi as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= esc($s['nama_sesi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success px-4 shadow-sm fw-bold">
                    <i class="fas fa-check me-1"></i> Pulihkan & Aktifkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT SISWA -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i>Edit Data Siswa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" id="edit-nama" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">NISN <span class="text-danger">*</span></label>
                        <input type="text" name="nisn" id="edit-nisn" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Username Login <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit-user" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-danger">Ganti Password (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Kelas</label>
                        <select name="class_id" id="edit-kelas" class="form-select">
                            <?php foreach($allKelas as $k): ?>
                                <option value="<?= esc($k['id']) ?>"><?= esc($k['jenjang']) ?> - <?= esc($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Sesi Ujian</label>
                        <select name="sesi" id="edit-sesi" class="form-select">
                            <?php foreach($allSesi as $s): ?>
                                <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_sesi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Status Keaktifan</label>
                        <select name="is_aktif" id="edit-status" class="form-select">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif / Alumni</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Agama</label>
                        <select name="agama" id="edit-agama" class="form-select">
                            <option value="Islam">Islam</option>
                            <option value="Kristen">Kristen</option>
                            <option value="Katolik">Katolik</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Budha">Budha</option>
                            <option value="Konghucu">Konghucu</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Foto Profil</label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img id="edit-foto-preview" src="" alt="Foto" class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;display:none;">
                            <div id="edit-foto-placeholder" class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary fw-bold" style="width:64px;height:64px;font-size:20px;">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <input type="file" name="foto" id="edit-foto-input" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengubah foto. Format: JPG, PNG, WEBP (Maks. 5 MB)</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="update" class="btn btn-primary px-4 shadow-sm fw-bold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT SISWA -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <form action="../master-io/import_siswa.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-excel me-2"></i>Import Siswa dari Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <a href="<?= esc(BASE_URL) ?>admin/master-io/download_template.php" class="btn btn-sm btn-outline-success w-100 mb-3 fw-semibold">
                        <i class="fas fa-download me-1"></i> Download Format Template Excel
                    </a>
                    <label class="form-label fw-bold small">Pilih File (.xlsx / .xls)</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls" required>
                    <div class="form-text mt-2">Pastikan NISN dan Username tidak ganda dengan data yang sudah ada.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="import" class="btn btn-success px-4 fw-bold">Mulai Import</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH SISWA -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <form action="" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah Data Siswa Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama sesuai raport/ijazah" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">NISN <span class="text-danger">*</span></label>
                        <input type="text" name="nisn" class="form-control" placeholder="Contoh: 0012345678" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Username Login <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Username untuk login siswa" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-primary">Password (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Default: sama dengan NISN">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Kelas <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach($allKelas as $k): ?>
                                <option value="<?= esc($k['id']) ?>"><?= esc($k['jenjang']) ?> - <?= esc($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Sesi Ujian</label>
                        <select name="sesi" class="form-select">
                            <?php foreach($allSesi as $s): ?>
                                <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_sesi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Agama</label>
                        <select name="agama" class="form-select">
                            <option value="Islam">Islam</option>
                            <option value="Kristen">Kristen</option>
                            <option value="Katolik">Katolik</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Budha">Budha</option>
                            <option value="Konghucu">Konghucu</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Foto Profil <span class="text-muted fw-normal">(Opsional)</span></label>
                        <div class="mb-2" id="tambah-foto-preview-wrap" style="display:none;">
                            <img id="tambah-foto-preview" src="" alt="" class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;">
                        </div>
                        <input type="file" name="foto" id="tambah-foto-input" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Maks. 5 MB · Format didukung: JPG, PNG, WEBP</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="simpan" class="btn btn-primary px-4 shadow-sm fw-bold">Simpan Siswa</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var baseUrl = '<?= BASE_URL ?>';

    // Populate restore modal
    $(document).on('click', '.btn-restore-siswa', function() {
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var nisn = $(this).data('nisn');
        var user = $(this).data('user');
        var kelas = $(this).data('kelas');
        var sesi = $(this).data('sesi');

        $('#restore-id').val(id);
        $('#restore-nama').val(nama);
        $('#restore-nisn').val(nisn);
        $('#restore-user').val(user);
        $('#restore-sesi').val(sesi || 1);
        
        if (kelas && $('#restore-kelas option[value="' + kelas + '"]').length > 0) {
            $('#restore-kelas').val(kelas);
        } else {
            $('#restore-kelas').val('');
        }
    });

    // Populate edit modal
    $(document).on('click', '.btn-edit-siswa', function() {
        $('#edit-id').val($(this).data('id'));
        $('#edit-nama').val($(this).data('nama'));
        $('#edit-nisn').val($(this).data('nisn'));
        $('#edit-user').val($(this).data('user'));
        $('#edit-kelas').val($(this).data('kelas'));
        $('#edit-sesi').val($(this).data('sesi'));
        $('#edit-status').val($(this).data('status'));
        $('#edit-agama').val($(this).data('agama'));

        $('#edit-foto-input').val('');

        var foto = $(this).data('foto');
        if (foto) {
            $('#edit-foto-preview').attr('src', baseUrl + 'assets/uploads/foto_siswa/' + foto).show();
            $('#edit-foto-placeholder').hide();
        } else {
            $('#edit-foto-preview').hide().attr('src', '');
            $('#edit-foto-placeholder').show();
        }
    });

    // Handle SweetAlert2 confirmation for Nonaktifkan Siswa (replacing permanent delete)
    $(document).on('click', '.btn-nonaktifkan', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var nisn = $(this).data('nisn');

        Swal.fire({
            title: 'Nonaktifkan Siswa?',
            html: 'Siswa <b>' + $('<div>').text(nama).html() + '</b> (NISN: ' + $('<div>').text(nisn).html() + ') akan dipindahkan ke daftar <b>Siswa Non-Aktif</b>.<br><small class="text-muted mt-2 d-block">Seluruh riwayat ujian, jawaban, dan nilai tetap aman dan dapat diaktifkan kembali kapan saja.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-user-slash me-1"></i> Ya, Nonaktifkan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-warning text-dark fw-bold px-3',
                cancelButton: 'btn btn-secondary px-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'siswa.php';

                var inputCsrf = document.createElement('input');
                inputCsrf.type = 'hidden';
                inputCsrf.name = 'csrf_token';
                inputCsrf.value = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
                form.appendChild(inputCsrf);

                var inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'nonaktifkan';
                form.appendChild(inputAction);

                var inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'student_id';
                inputId.value = id;
                form.appendChild(inputId);

                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Preview photo in edit modal
    $('#edit-foto-input').on('change', function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#edit-foto-preview').attr('src', e.target.result).show();
                $('#edit-foto-placeholder').hide();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Preview photo in tambah modal
    $('#tambah-foto-input').on('change', function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#tambah-foto-preview').attr('src', e.target.result);
                $('#tambah-foto-preview-wrap').show();
            };
            reader.readAsDataURL(this.files[0]);
        }
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
