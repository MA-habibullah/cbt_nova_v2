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

    // Isi background putih (JPEG tidak support transparansi)
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
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$f_kelas = $_GET['f_kelas'] ?? '';
$f_jenjang = $_GET['f_jenjang'] ?? '';
$f_sesi = $_GET['f_sesi'] ?? '';
$f_agama = $_GET['f_agama'] ?? '';
// Default filter status aktif (1), atau alumni (0), atau semua ('all')
$f_status = isset($_GET['f_status']) ? $_GET['f_status'] : '1'; 

// Membangun Query
$query_str = "SELECT s.*, k.nama_kelas, k.jenjang FROM cbt_students s 
              LEFT JOIN cbt_classes k ON s.class_id = k.id WHERE 1=1";
$params = [];

if ($search) {
    $query_str .= " AND (s.nama_lengkap LIKE ? OR s.nisn LIKE ? OR s.username LIKE ?)";
    $s = like_escape($search);
    $params = array_merge($params, ["%$s%", "%$s%", "%$s%"]);
}
if ($f_kelas) { $query_str .= " AND s.class_id = ?"; $params[] = $f_kelas; }
if ($f_jenjang) { $query_str .= " AND k.jenjang = ?"; $params[] = $f_jenjang; }
if ($f_sesi)  { $query_str .= " AND s.sesi = ?"; $params[] = $f_sesi; }
if ($f_agama) { $query_str .= " AND s.agama = ?"; $params[] = $f_agama; }
if ($f_status !== '' && $f_status !== 'all') { 
    $query_str .= " AND s.is_aktif = ?"; 
    $params[] = (int)$f_status; 
}

// Total Data untuk Pagination
$stmt_count = $pdo->prepare(str_replace("s.*, k.nama_kelas, k.jenjang", "COUNT(*)", $query_str));
$stmt_count->execute($params);
$totalData = $stmt_count->fetchColumn();
$pages = ceil($totalData / $limit);

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
$count_alumni = (int)$pdo->query("SELECT COUNT(*) FROM cbt_students WHERE is_aktif = 0")->fetchColumn();
$count_all    = $count_aktif + $count_alumni;

// --- PROSES SIMPAN ---
if (isset($_POST['simpan'])) {
    // 1. Tentukan password asli (dari input atau default NISN)
    $pass_input = !empty($_POST['password']) ? $_POST['password'] : $_POST['nisn'];

    // 2. Hash password untuk keamanan login
    $password_hash = password_hash($pass_input, PASSWORD_BCRYPT);

    // 3. Proses upload foto (opsional, finfo_file / getimagesize verified)
    $foto = proses_upload_foto_siswa($_FILES['foto'] ?? null);

    // 4. Simpan ke database (password = hash, kartu = teks asli)
    $stmt = $pdo->prepare("INSERT INTO cbt_students (nisn, nama_lengkap, username, password, kartu, class_id, sesi, agama, foto, is_aktif, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([
        $_POST['nisn'],
        $_POST['nama_lengkap'],
        $_POST['username'],
        $password_hash, // Kolom password
        $pass_input,    // Kolom kartu (baru)
        $_POST['class_id'],
        $_POST['sesi'],
        $_POST['agama'],
        $foto,          // Kolom foto (null jika tidak diupload)
    ]);
    
    log_activity("Tambah siswa baru: " . $_POST['nama_lengkap'] . " (NISN: " . $_POST['nisn'] . ")", null, null, null, 'master');
    header("Location: siswa.php?msg=disimpan");
    exit;
}

// --- PROSES UPDATE ---
if (isset($_POST['update'])) {
    $id = $_POST['id'];

    // Proses upload foto baru (opsional, finfo_file / getimagesize verified)
    $foto_baru = proses_upload_foto_siswa($_FILES['foto'] ?? null);

    // Jika ada foto baru: ambil foto lama untuk dihapus, lalu tentukan nilai foto untuk disimpan
    if ($foto_baru !== null) {
        $stmt_old = $pdo->prepare("SELECT foto FROM cbt_students WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_foto = $stmt_old->fetchColumn();
        if ($old_foto) {
            $old_foto_path = dirname(__FILE__, 3) . '/assets/uploads/foto_siswa/' . $old_foto;
            if (file_exists($old_foto_path)) {
                unlink($old_foto_path);
            }
        }
    }

    if (!empty($_POST['password'])) {
        // Jika admin mengisi password baru: Update hash dan plain text
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
                $_POST['sesi'],
                $_POST['agama'],
                $_POST['is_aktif'],
                $password_hash,
                $pass_baru, // Update kolom kartu
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
                $_POST['sesi'],
                $_POST['agama'],
                $_POST['is_aktif'],
                $password_hash,
                $pass_baru, // Update kolom kartu
                $id,
            ]);
        }
    } else {
        // Jika password dikosongkan (tidak diubah)
        if ($foto_baru !== null) {
            $sql = "UPDATE cbt_students SET nisn=?, nama_lengkap=?, username=?, class_id=?, sesi=?, agama=?, is_aktif=?, foto=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nisn'],
                $_POST['nama_lengkap'],
                $_POST['username'],
                $_POST['class_id'],
                $_POST['sesi'],
                $_POST['agama'],
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
                $_POST['sesi'],
                $_POST['agama'],
                $_POST['is_aktif'],
                $id,
            ]);
        }
    }
    
    log_activity("Update data siswa ID $id: " . $_POST['nama_lengkap'] . " (NISN: " . $_POST['nisn'] . ")", null, null, null, 'master');
    header("Location: siswa.php?msg=updated");
    exit;
}

// --- PROSES RESTORE / AKTIFKAN KEMBALI SISWA ---
if (isset($_POST['restore_siswa'])) {
    $restore_id   = (int)($_POST['student_id'] ?? 0);
    $new_class_id = (int)($_POST['class_id'] ?? 0);
    $new_sesi     = (int)($_POST['sesi'] ?? 1);

    if ($restore_id > 0 && $new_class_id > 0) {
        // Validasi kelas target valid dan aktif
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

// --- PROSES HAPUS ---
if (isset($_GET['hapus'])) {
    $hapus_id = (int)$_GET['hapus'];
    $row_del = $pdo->prepare("SELECT nama_lengkap, nisn, foto FROM cbt_students WHERE id = ?");
    $row_del->execute([$hapus_id]);
    $del = $row_del->fetch();
    $pdo->prepare("DELETE FROM cbt_students WHERE id = ?")->execute([$hapus_id]);
    // Hapus file foto dari disk jika ada
    if (!empty($del['foto'])) {
        $foto_path = dirname(__FILE__, 3) . '/assets/uploads/foto_siswa/' . $del['foto'];
        if (file_exists($foto_path)) {
            unlink($foto_path);
        }
    }
    log_activity("Hapus siswa: " . ($del['nama_lengkap'] ?? '-') . " (NISN: " . ($del['nisn'] ?? '-') . ")", null, null, null, 'master');
    header("Location: siswa.php?msg=dihapus");
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
            <button class="btn btn-light border" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Manajemen Data Siswa</h5>
        </nav>
        <?php $flash = $_GET['msg'] ?? ''; ?>
        <?php if ($flash === 'disimpan'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data siswa baru berhasil ditambahkan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'updated'): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> Data siswa berhasil diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'restored'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-user-check me-2"></i> <strong>Berhasil!</strong> Status siswa berhasil dipulihkan menjadi aktif dan ditempatkan pada kelas baru.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'restore_failed'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> Gagal memulihkan status siswa. Pastikan kelas baru yang dipilih valid.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'dihapus'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-trash me-2"></i> Data siswa berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($flash === 'pilih_file'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-0" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> Harap pilih file Excel terlebih dahulu.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash === 'import_done'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Proses Import Siswa Selesai</h6>
                        <span>Berhasil menambahkan <strong><?= $_GET['success'] ?? 0 ?></strong> siswa baru.</span>
                        <?php if (isset($_GET['skipped']) && $_GET['skipped'] > 0): ?>
                            <div class="text-danger small mt-1">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong><?= $_GET['skipped'] ?></strong> data dilewati karena NISN atau Username sudah ada di database.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($flash === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fas fa-times-circle me-2"></i> <strong>Gagal Import:</strong> <?= htmlspecialchars($_GET['detail'] ?? 'Terjadi kesalahan.') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="container-fluid px-4 pt-4">

            <!-- Navigasi Tab Status Siswa -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <a href="?f_status=1<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-sm <?= $f_status === '1' ? 'btn-primary shadow-sm' : 'btn-white border text-secondary' ?> fw-semibold px-3 py-2 rounded-3">
                    <i class="fas fa-user-check me-1"></i> Siswa Aktif
                    <span class="badge <?= $f_status === '1' ? 'bg-white text-primary' : 'bg-primary-subtle text-primary' ?> ms-2"><?= $count_aktif ?></span>
                </a>
                <a href="?f_status=0<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-sm <?= $f_status === '0' ? 'btn-dark shadow-sm' : 'btn-white border text-secondary' ?> fw-semibold px-3 py-2 rounded-3">
                    <i class="fas fa-graduation-cap me-1"></i> Siswa Alumni / Lulus
                    <span class="badge <?= $f_status === '0' ? 'bg-white text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-2"><?= $count_alumni ?></span>
                </a>
                <a href="?f_status=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-sm <?= $f_status === 'all' ? 'btn-secondary shadow-sm' : 'btn-white border text-secondary' ?> fw-semibold px-3 py-2 rounded-3">
                    <i class="fas fa-users me-1"></i> Semua Siswa
                    <span class="badge <?= $f_status === 'all' ? 'bg-white text-dark' : 'bg-light text-dark border' ?> ms-2"><?= $count_all ?></span>
                </a>
            </div>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form action="" method="GET" class="row g-3 align-items-end">
                        <div class="col-lg-2 col-md-4">
                            <label class="small fw-bold">Pencarian</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Nama/NISN..." value="<?= esc($search) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="small fw-bold">Jenjang</label>
                            <select name="f_jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Jenjang</option>
                                <?php foreach($allJenjang as $j): ?>
                                    <option value="<?= esc($j) ?>" <?= esc($f_jenjang == $j ? 'selected' : '') ?>><?= esc($j) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="small fw-bold">Kelas</label>
                            <select name="f_kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Kelas</option>
                                <?php foreach($allKelas as $k): ?>
                                    <option value="<?= esc($k['id']) ?>" <?= esc($f_kelas == $k['id']?'selected':'') ?>><?= esc($k['jenjang']) ?>-<?= esc($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-2">
                            <label class="small fw-bold">Agama</label>
                            <select name="f_agama" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <?php foreach($listAgama as $a): ?>
                                    <option value="<?= esc($a) ?>" <?= esc($f_agama == $a ? 'selected' : '') ?>><?= esc($a) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-2">
                            <label class="small fw-bold">Sesi</label>
                            <select name="f_sesi" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Sesi</option>
                                <?php foreach($allSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($f_sesi == $s['id']?'selected':'') ?>><?= esc($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="small fw-bold">Status Siswa</label>
                            <select name="f_status" class="form-select form-select-sm fw-bold <?= $f_status === '0' ? 'bg-dark text-white' : ($f_status === 'all' ? 'bg-secondary-subtle' : 'bg-primary-subtle text-primary') ?>" onchange="this.form.submit()">
                                <option value="1" <?= esc($f_status == '1'?'selected':'') ?>>Siswa Aktif (<?= $count_aktif ?>)</option>
                                <option value="0" <?= esc($f_status == '0'?'selected':'') ?>>Alumni / Lulus (<?= $count_alumni ?>)</option>
                                <option value="all" <?= esc($f_status == 'all'?'selected':'') ?>>Semua Siswa (<?= $count_all ?>)</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold"><i class="fas fa-filter me-1"></i> Filter</button>
                            <a href="siswa.php" class="btn btn-light border" title="Reset Filter"><i class="fas fa-sync"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-3">
                <div class="d-flex gap-2">
                    <button class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="fas fa-plus me-2"></i> Tambah
                    </button>
                    <button class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                        <i class="fas fa-file-import me-2"></i> Import
                    </button>
                    <a href="<?= esc(BASE_URL) ?>admin/master-io/export_siswa.php" class="btn btn-outline-primary shadow-sm">
                        <i class="fas fa-file-export me-2"></i> Export
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">Baris:</span>
                    <select class="form-select form-select-sm" style="width: 75px;" onchange="changeLimit(this.value)" id="limitSelect">
                        <option value="10" <?= esc($limit==10?'selected':'') ?>>10</option>
                        <option value="50" <?= esc($limit==50?'selected':'') ?>>50</option>
                        <option value="100" <?= esc($limit==100?'selected':'') ?>>100</option>
                    </select>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th width="50">No</th>
                                <th width="70">Foto</th>
                                <th>Nama Siswa</th>
                                <th>Agama</th>
                                <th>NISN / Username</th>
                                <th>Kelas & Status</th>
                                <th>Sesi</th>
                                <th class="text-center" width="180">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listSiswa)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-user-graduate fa-3x mb-2 text-secondary opacity-50 d-block"></i>
                                    Tidak ada data siswa ditemukan untuk kriteria ini.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $no=$offset+1; foreach($listSiswa as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="text-center">
                                    <?php if (!empty($row['foto'])): ?>
                                        <img src="<?= esc(BASE_URL) ?>assets/uploads/foto_siswa/<?= htmlspecialchars($row['foto']) ?>" class="rounded-circle border" style="width:40px;height:40px;object-fit:cover;" alt="Foto">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= esc($row['nama_lengkap']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= esc($row['agama'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <div class="small fw-bold text-primary"><?= esc($row['nisn']) ?></div>
                                    <div class="small text-muted"><?= esc($row['username']) ?></div>
                                </td>
                                <td>
                                    <?php if ((int)$row['is_aktif'] === 1): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <?= esc($row['jenjang'] ?? '-') ?>-<?= esc($row['nama_kelas'] ?? 'Tanpa Kelas') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white">
                                            <i class="fas fa-graduation-cap me-1"></i><?= esc($row['nama_kelas'] === 'LULUS' ? 'Alumni (Lulus)' : (($row['jenjang'] ? $row['jenjang'].'-' : '') . ($row['nama_kelas'] ?? 'Alumni'))) ?>
                                        </span>
                                        <div class="mt-1">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:0.68rem;">Non-Aktif</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary">Sesi <?= esc($row['sesi']) ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <?php if ((int)$row['is_aktif'] === 0): ?>
                                            <button type="button" class="btn btn-sm btn-success btn-restore-siswa" 
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
                                        <button type="button" class="btn btn-sm btn-white border btn-edit-siswa" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($row['id']) ?>" data-nama="<?= esc($row['nama_lengkap']) ?>"
                                                data-nisn="<?= esc($row['nisn']) ?>" data-user="<?= esc($row['username']) ?>"
                                                data-kelas="<?= esc($row['class_id']) ?>" data-sesi="<?= esc($row['sesi']) ?>"
                                                data-agama="<?= esc($row['agama']) ?>" data-status="<?= esc($row['is_aktif']) ?>"
                                                data-foto="<?= htmlspecialchars($row['foto'] ?? '') ?>"
                                                title="Edit Siswa">
                                            <i class="fas fa-edit text-primary"></i>
                                        </button>
                                        <a href="?hapus=<?= esc($row['id']) ?>" class="btn btn-sm btn-white border text-danger" onclick="return confirm('Hapus siswa ini?')" title="Hapus Siswa">
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
                
                <nav class="mt-3 d-flex justify-content-between align-items-center">
    <div class="small text-muted">
        Menampilkan <?= count($listSiswa) ?> dari <?= $totalData ?> total data
    </div>
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

        // Tombol Previous
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        echo "<li class='page-item $prevDisabled'><a class='page-link' href='?page=".($page-1)."&$base_params'>&laquo;</a></li>";

        // Batasi tampilan nomor halaman (misal: 2 sebelum dan 2 sesudah halaman aktif)
        $start_number = ($page > 3) ? $page - 2 : 1;
        $end_number = ($page < ($pages - 2)) ? $page + 2 : $pages;

        if ($start_number > 1) {
            echo "<li class='page-item'><a class='page-link' href='?page=1&$base_params'>1</a></li>";
            echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }

        for ($i = $start_number; $i <= $end_number; $i++): ?>
            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= esc($i) ?>&<?= esc($base_params) ?>"><?= esc($i) ?></a>
            </li>
        <?php endfor;

        if ($end_number < $pages) {
            echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
            echo "<li class='page-item'><a class='page-link' href='?page=$pages&$base_params'>$pages</a></li>";
        }

        // Tombol Next
        $nextDisabled = ($page >= $pages) ? 'disabled' : '';
        echo "<li class='page-item $nextDisabled'><a class='page-link' href='?page=".($page+1)."&$base_params'>&raquo;</a></li>";
        ?>
    </ul>
</nav>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RESTORE / PULIHKAN SISWA -->
<div class="modal fade" id="modalRestore" tabindex="-1">
    <div class="modal-dialog">
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
                    <i class="fas fa-info-circle me-1"></i> Siswa yang dipulihkan akan kembali berstatus <strong>Aktif</strong>. Seluruh riwayat ujian, jawaban, dan akun login lama siswa tetap utuh dan aman.
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Siswa</label>
                    <input type="text" id="restore-nama" class="form-control bg-light" readonly>
                </div>
                
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small">NISN</label>
                        <input type="text" id="restore-nisn" class="form-control bg-light" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small">Username</label>
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
                    <label class="form-label fw-bold small">Pilih Sesi Ujian</label>
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

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
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
                        <label class="form-label fw-bold small">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit-nama" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">NISN</label>
                        <input type="text" name="nisn" id="edit-nisn" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Username</label>
                        <input type="text" name="username" id="edit-user" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-danger">Ganti Password</label>
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
                        <label class="form-label fw-bold small">Sesi</label>
                        <select name="sesi" id="edit-sesi" class="form-select">
                            <?php foreach($allSesi as $s): ?>
                                <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_sesi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Status</label>
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
                            <img id="edit-foto-preview" src="" alt="Foto" class="rounded-circle border" style="width:72px;height:72px;object-fit:cover;display:none;">
                            <i id="edit-foto-placeholder" class="fas fa-user-circle fa-4x text-secondary"></i>
                        </div>
                        <input type="file" name="foto" id="edit-foto-input" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengubah foto. Maks. 5 MB · Otomatis dikompres & diresize ke 400×400px</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="update" class="btn btn-primary px-4 shadow-sm">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="../master-io/import_siswa.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Import dari Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <a href="<?= esc(BASE_URL) ?>admin/master-io/download_template.php" class="btn btn-sm btn-outline-info w-100 mb-3">
                        <i class="fas fa-download me-1"></i> Download Format Excel
                    </a>
                    <label class="form-label fw-bold small">Pilih File (.xlsx)</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="import" class="btn btn-success px-4">Mulai Import</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah Data Siswa Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama sesuai ijazah" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">NISN</label>
                        <input type="text" name="nisn" class="form-control" placeholder="Contoh: 0012345678" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Username untuk login" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-primary">Password (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Default: NISN">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Kelas</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach($allKelas as $k): ?>
                                <option value="<?= esc($k['id']) ?>"><?= esc($k['jenjang']) ?> - <?= esc($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Sesi</label>
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
                            <img id="tambah-foto-preview" src="" alt="" class="rounded-circle border" style="width:72px;height:72px;object-fit:cover;">
                        </div>
                        <input type="file" name="foto" id="tambah-foto-input" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Maks. 5 MB · Otomatis dikompres & diresize ke 400×400px</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="simpan" class="btn btn-primary px-4 shadow-sm">Simpan Siswa</button>
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
        
        // Cek jika option kelas lama ada di dropdown kelas aktif
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

        // Reset file input
        $('#edit-foto-input').val('');

        // Show current photo or placeholder
        var foto = $(this).data('foto');
        if (foto) {
            $('#edit-foto-preview').attr('src', baseUrl + 'assets/uploads/foto_siswa/' + foto).show();
            $('#edit-foto-placeholder').hide();
        } else {
            $('#edit-foto-preview').hide().attr('src', '');
            $('#edit-foto-placeholder').show();
        }
    });

    // Preview new photo in edit modal
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

// Ganti jumlah baris sambil mempertahankan semua filter aktif
function changeLimit(val) {
    var url = new URL(window.location.href);
    url.searchParams.set('limit', val);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}
</script>
</body>
</html>
