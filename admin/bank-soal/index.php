<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// --- 1. PROSES LOGIC (TAMBAH, EDIT, KUNCI/BUKA STATUS, HAPUS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses'])) {
    csrf_verify();
    $action = $_POST['proses'];

    if ($action == 'tambah') {
        $kode = trim($_POST['kode_bank_soal'] ?? '');
        $nama = trim($_POST['nama_bank_soal'] ?? '');
        $subj = (int)($_POST['subject_id'] ?? 0);
        $guru = (int)($_POST['teacher_id'] ?? 0);
        $jenj = trim($_POST['jenjang'] ?? '') ?: null;

        if ($kode === '' || $nama === '' || $subj === 0 || $guru === 0) {
            header("Location: index.php?msg=invalid"); exit;
        }
        try {
            $pdo->prepare("INSERT INTO cbt_bank_soal (kode_bank_soal, nama_bank_soal, subject_id, jenjang, teacher_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'aktif', NOW())")
                ->execute([$kode, $nama, $subj, $jenj, $guru]);
            log_activity("Tambah bank soal: $nama ($kode)", null, null, null, 'ujian');
            header("Location: index.php?msg=sukses"); exit;
        } catch (PDOException $e) {
            $code = $e->getCode() == 23000 ? 'duplicate' : 'error';
            header("Location: index.php?msg=$code"); exit;
        }
    }

    if ($action == 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_bank_soal'] ?? '');
        $nama = trim($_POST['nama_bank_soal'] ?? '');
        $subj = (int)($_POST['subject_id'] ?? 0);
        $guru = (int)($_POST['teacher_id'] ?? 0);
        $jenj = trim($_POST['jenjang'] ?? '') ?: null;

        if ($id === 0 || $kode === '' || $nama === '' || $subj === 0 || $guru === 0) {
            header("Location: index.php?msg=invalid"); exit;
        }
        try {
            $pdo->prepare("UPDATE cbt_bank_soal SET kode_bank_soal=?, nama_bank_soal=?, subject_id=?, jenjang=?, teacher_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$kode, $nama, $subj, $jenj, $guru, $id]);
            $pdo->prepare("UPDATE cbt_exams SET subject_id=? WHERE bank_soal_id=?")
                ->execute([$subj, $id]);
            log_activity("Update bank soal ID $id: $nama", null, null, null, 'ujian');
            header("Location: index.php?msg=updated"); exit;
        } catch (PDOException $e) {
            $code = $e->getCode() == 23000 ? 'duplicate' : 'error';
            header("Location: index.php?msg=$code"); exit;
        }
    }

    if ($action == 'toggle_status') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $new_status = ($_POST['current_status'] === 'aktif') ? 'nonaktif' : 'aktif';
            $pdo->prepare("UPDATE cbt_bank_soal SET status = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_status, $id]);
            log_activity("Toggle status bank soal ID $id → $new_status", null, null, null, 'ujian');
            header("Location: index.php?msg=status_changed"); exit;
        } catch (PDOException $e) {
            header("Location: index.php?msg=error"); exit;
        }
    }
}

if (isset($_GET['hapus'])) {
    $hapus_id    = (int)$_GET['hapus'];
    $upload_dir  = dirname(__DIR__, 2) . '/assets/uploads/soal/';

    // Kumpulkan semua gambar dari soal dalam bank ini sebelum dihapus
    $qids = $pdo->prepare("SELECT id FROM cbt_questions WHERE bank_soal_id = ?");
    $qids->execute([$hapus_id]);
    $all_images = [];
    foreach ($qids->fetchAll(PDO::FETCH_COLUMN) as $qid) {
        $row = $pdo->prepare("SELECT konten_soal FROM cbt_questions WHERE id = ?");
        $row->execute([$qid]);
        $konten = $row->fetchColumn() ?: '';
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $konten, $m);
        foreach ($m[1] as $url) {
            if (strpos($url, 'assets/uploads/soal/') !== false) {
                $all_images[] = basename(parse_url($url, PHP_URL_PATH));
            }
        }
        $opts = $pdo->prepare("SELECT value_target FROM cbt_question_options WHERE question_id = ?");
        $opts->execute([$qid]);
        foreach ($opts->fetchAll(PDO::FETCH_COLUMN) as $val) {
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $val, $m2);
            foreach ($m2[1] as $url) {
                if (strpos($url, 'assets/uploads/soal/') !== false) {
                    $all_images[] = basename(parse_url($url, PHP_URL_PATH));
                }
            }
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM cbt_bank_soal WHERE id = ?")->execute([$hapus_id]);
        $pdo->commit();
        foreach (array_unique($all_images) as $fname) {
            $path = $upload_dir . $fname;
            if ($fname && file_exists($path)) @unlink($path);
        }
        log_activity("Hapus bank soal ID $hapus_id", null, null, null, 'ujian');
        header("Location: index.php?msg=deleted"); exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $code = $e->getCode() == 23000 ? 'error_fk' : 'error';
        header("Location: index.php?msg=$code"); exit;
    }
}

// --- 2. FILTER & PAGINATION CONFIGURATION ---
$search     = trim($_GET['q'] ?? '');
$f_subject  = (int)($_GET['subject_id'] ?? 0);
$f_jenjang  = trim($_GET['jenjang'] ?? '');
$f_status   = trim($_GET['status'] ?? '');
$date_from  = trim($_GET['date_from'] ?? '');
$date_to    = trim($_GET['date_to'] ?? '');
$_show      = (int)($_GET['show'] ?? 50);
$limit      = in_array($_show, [10, 25, 50, 100]) ? $_show : 50;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (b.nama_bank_soal LIKE ? OR b.kode_bank_soal LIKE ? OR s.nama_mapel LIKE ? OR t.nama_lengkap LIKE ?)";
    $s = '%' . like_escape($search) . '%';
    array_push($params, $s, $s, $s, $s);
}
if ($f_subject > 0) {
    $where .= " AND b.subject_id = ?";
    $params[] = $f_subject;
}
if ($f_jenjang !== '') {
    $where .= " AND b.jenjang = ?";
    $params[] = $f_jenjang;
}
if ($f_status !== '' && in_array($f_status, ['aktif', 'nonaktif'])) {
    $where .= " AND b.status = ?";
    $params[] = $f_status;
}
if (!empty($date_from)) {
    $where .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

$base_sql = "FROM cbt_bank_soal b
             JOIN cbt_subjects s ON b.subject_id = s.id
             JOIN cbt_teachers t ON b.teacher_id = t.id
             $where";

// Hitung total data
$count_stmt = $pdo->prepare("SELECT COUNT(*) $base_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$pages = $limit > 0 ? max(1, (int)ceil($total / $limit)) : 1;
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $limit;
}

// Ambil data bank soal beserta rincian jumlah soal dan peserta
$data_stmt = $pdo->prepare("SELECT b.*, s.nama_mapel, s.kode_mapel, t.nama_lengkap as nama_guru,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id) as total_soal,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id AND tipe = 'pg') as total_pg,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id AND tipe = 'essay') as total_essay,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id AND tipe NOT IN ('pg', 'essay')) as total_kompleks,
    (SELECT COUNT(DISTINCT p.student_id) FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id WHERE e.bank_soal_id = b.id) as total_siswa,
    (SELECT COUNT(*) FROM cbt_exams WHERE bank_soal_id = b.id) as total_jadwal
    $base_sql ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$data_stmt->execute(array_merge($params, [$limit, $offset]));
$bank_soal = $data_stmt->fetchAll();

$has_filter = $search !== '' || $f_subject || $f_jenjang !== '' || $f_status !== '' || $date_from !== '' || $date_to !== '';

$subjects = $pdo->query("SELECT * FROM cbt_subjects WHERE is_aktif = 1 ORDER BY nama_mapel")->fetchAll();
$teachers = $pdo->query("SELECT * FROM cbt_teachers WHERE is_aktif = 1 ORDER BY nama_lengkap")->fetchAll();

// --- METRIC CARDS STATISTIK ---
$metric_total_bank  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal")->fetchColumn();
$metric_bank_aktif  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal WHERE status = 'aktif'")->fetchColumn();
$metric_total_soal  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_questions")->fetchColumn();
$metric_bank_baru   = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

function bsQuery(array $extra = []): string {
    global $search, $f_subject, $f_jenjang, $f_status, $date_from, $date_to, $limit;
    $p = array_filter(array_merge([
        'q'          => $search,
        'subject_id' => $f_subject ?: '',
        'jenjang'    => $f_jenjang,
        'status'     => $f_status,
        'date_from'  => $date_from,
        'date_to'    => $date_to,
        'show'       => $limit,
    ], $extra), fn($v) => $v !== '' && $v !== null);
    return http_build_query($p);
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
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <div class="ms-3 d-flex align-items-center">
                <i class="fas fa-layer-group text-primary fs-5 me-2"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Manajemen Bank Soal</h5>
                    <small class="text-muted">Kelola paket butir soal, pemetaan mata pelajaran, jenjang, dan guru penyusun</small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 py-3">
            
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= esc(BASE_URL) ?>admin/dashboard/index.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Bank Soal</li>
                </ol>
            </nav>

            <!-- Flash Alerts -->
            <?php if (isset($_GET['msg'])): $m = $_GET['msg']; ?>
            <?php
                $alertClass = in_array($m, ['sukses','updated','deleted','status_changed']) ? 'success' : 'danger';
                $alertIcon = $alertClass === 'success' ? 'check-circle' : 'exclamation-triangle';
                $alertMsg = match($m) {
                    'sukses'         => 'Bank soal baru berhasil ditambahkan dan siap digunakan.',
                    'updated'        => 'Data bank soal berhasil diperbarui.',
                    'deleted'        => 'Bank soal beserta butir soal terkait berhasil dihapus.',
                    'status_changed' => 'Status akses bank soal berhasil diubah.',
                    'duplicate'      => 'Kode bank soal sudah digunakan. Harap gunakan kode yang berbeda.',
                    'invalid'        => 'Data form tidak valid. Pastikan semua field wajib terisi.',
                    'error_fk'       => 'Tidak dapat menghapus bank soal yang masih terhubung dengan jadwal ujian aktif.',
                    default          => 'Terjadi kesalahan pada sistem. Silakan coba kembali.',
                };
            ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fas fa-<?= $alertIcon ?> me-2"></i>
                <strong><?= $alertClass === 'success' ? 'Berhasil!' : 'Perhatian!' ?></strong> <?= $alertMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Metric Cards (Overview Statistik) -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Total Bank Soal</span>
                                <h3 class="fw-bold mb-0 mt-1 text-dark"><?= number_format($metric_total_bank, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-folder me-1 text-primary"></i>Semua paket soal</small>
                            </div>
                            <div class="rounded-3 p-3 bg-primary-subtle text-primary">
                                <i class="fas fa-database fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Bank Soal Aktif</span>
                                <h3 class="fw-bold mb-0 mt-1 text-success"><?= number_format($metric_bank_aktif, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-unlock me-1 text-success"></i>Terbuka & siap diujikan</small>
                            </div>
                            <div class="rounded-3 p-3 bg-success-subtle text-success">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Total Butir Soal</span>
                                <h3 class="fw-bold mb-0 mt-1 text-info"><?= number_format($metric_total_soal, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-list-ol me-1 text-info"></i>Seluruh tipe pertanyaan</small>
                            </div>
                            <div class="rounded-3 p-3 bg-info-subtle text-info">
                                <i class="fas fa-tasks fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Baru Minggu Ini</span>
                                <h3 class="fw-bold mb-0 mt-1 text-warning"><?= number_format($metric_bank_baru, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-calendar-week me-1 text-warning"></i>7 hari terakhir</small>
                            </div>
                            <div class="rounded-3 p-3 bg-warning-subtle text-warning">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Action Toolbar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes text-primary me-2"></i>Daftar Paket Bank Soal</h6>
                        <span class="badge bg-light text-secondary border px-2 py-1"><?= number_format($total, 0, ',', '.') ?> paket ditemukan</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?= esc(BASE_URL) ?>admin/bank-soal/backup/bank-soal-backup.php" class="btn btn-sm btn-outline-primary shadow-sm fw-semibold">
                            <i class="fas fa-file-archive me-1"></i> Backup & Restore
                        </a>
                        <button class="btn btn-sm btn-primary shadow-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus-circle me-1"></i> Buat Bank Soal
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end" id="filterForm">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-search me-1"></i>Pencarian</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Ketik Nama Bank, Kode, Guru, Mapel..." value="<?= esc($search) ?>">
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-book me-1"></i>Mata Pelajaran</label>
                            <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Mapel</option>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= ($f_subject == $s['id'] ? 'selected' : '') ?>>
                                        <?= esc($s['kode_mapel']) ?> - <?= esc($s['nama_mapel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-layer-group me-1"></i>Tingkat Jenjang</label>
                            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Jenjang</option>
                                <option value="10" <?= ($f_jenjang === '10' ? 'selected' : '') ?>>Kelas 10</option>
                                <option value="11" <?= ($f_jenjang === '11' ? 'selected' : '') ?>>Kelas 11</option>
                                <option value="12" <?= ($f_jenjang === '12' ? 'selected' : '') ?>>Kelas 12</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-toggle-on me-1"></i>Status Akses</label>
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?= ($f_status === 'aktif' ? 'selected' : '') ?>>Terbuka / Aktif</option>
                                <option value="nonaktif" <?= ($f_status === 'nonaktif' ? 'selected' : '') ?>>Terkunci / Non-Aktif</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-list me-1"></i>Baris</label>
                            <select name="show" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="10"  <?= ($limit == 10  ? 'selected' : '') ?>>10</option>
                                <option value="25"  <?= ($limit == 25  ? 'selected' : '') ?>>25</option>
                                <option value="50"  <?= ($limit == 50  ? 'selected' : '') ?>>50</option>
                                <option value="100" <?= ($limit == 100 ? 'selected' : '') ?>>100</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                                <i class="fas fa-filter me-1"></i> Terapkan
                            </button>
                            <?php if($has_filter): ?>
                                <a href="index.php" class="btn btn-light border btn-sm text-secondary" title="Reset Filter">
                                    <i class="fas fa-redo"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Main Data Table Card -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0" style="min-width: 950px;">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                            <tr>
                                <th class="text-center py-3" width="50">#</th>
                                <th class="py-3">Identitas Bank Soal</th>
                                <th class="py-3">Jenjang & Guru Pengampu</th>
                                <th class="py-3 text-center d-none d-md-table-cell">Komposisi Soal</th>
                                <th class="py-3 text-center d-none d-lg-table-cell">Peserta & Jadwal</th>
                                <th class="py-3 text-center" width="120">Status</th>
                                <th class="py-3 d-none d-xl-table-cell" width="140">Tanggal Dibuat</th>
                                <th class="text-center py-3" width="200">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bank_soal)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                                        <h6 class="fw-bold text-secondary mb-1">Belum Ada Bank Soal Ditemukan</h6>
                                        <p class="small text-muted mb-3">Tidak ada data bank soal yang sesuai dengan kriteria filter atau pencarian Anda.</p>
                                        <button class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                                            <i class="fas fa-plus-circle me-1"></i> Buat Bank Soal Baru
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $no = $offset + 1; foreach ($bank_soal as $b): ?>
                            <tr>
                                <td class="text-center text-muted fw-bold small"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= esc($b['nama_bank_soal']) ?></div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                        <span class="badge badge-soft-primary font-monospace">
                                            <i class="fas fa-barcode me-1"></i><?= esc($b['kode_bank_soal']) ?>
                                        </span>
                                        <span class="text-secondary">
                                            <i class="fas fa-book-open me-1"></i><?= esc($b['kode_mapel']) ?> - <?= esc($b['nama_mapel']) ?>
                                        </span>
                                    </div>
                                    <!-- Sub-label untuk Tampilan Mobile (< 768px) -->
                                    <div class="d-md-none mt-1 small text-muted">
                                        <span class="badge badge-soft-info me-1"><i class="fas fa-tasks me-1"></i><?= (int)$b['total_soal'] ?> Soal</span>
                                        <span class="badge bg-light text-dark border me-1"><i class="fas fa-user-friends me-1"></i><?= (int)$b['total_siswa'] ?> Siswa</span>
                                        <span class="badge bg-light text-secondary border"><i class="fas fa-calendar-alt me-1"></i><?= (int)$b['total_jadwal'] ?> Jadwal</span>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php if (!empty($b['jenjang'])): ?>
                                            <span class="badge badge-soft-info px-2 py-1 rounded-2 fw-semibold">
                                                <i class="fas fa-graduation-cap me-1"></i>Kelas <?= esc($b['jenjang']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border px-2 py-1 rounded-2">Semua Jenjang</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-2 small">
                                        <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary fw-bold" style="width:24px;height:24px;font-size:10px;">
                                            <?= strtoupper(mb_substr($b['nama_guru'], 0, 1)) ?>
                                        </div>
                                        <span class="text-dark fw-medium"><?= esc($b['nama_guru']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <div class="mb-1">
                                        <span class="badge badge-soft-primary px-2 py-1 fs-6 fw-bold">
                                            <?= (int)$b['total_soal'] ?> Soal
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-center gap-1 small text-muted" style="font-size:0.75rem;">
                                        <span title="Pilihan Ganda" class="badge bg-light text-dark border"><?= (int)$b['total_pg'] ?> PG</span>
                                        <span title="Essay / Uraian" class="badge bg-light text-dark border"><?= (int)$b['total_essay'] ?> Essay</span>
                                        <?php if ((int)$b['total_kompleks'] > 0): ?>
                                            <span title="Tipe Lainnya" class="badge bg-light text-dark border"><?= (int)$b['total_kompleks'] ?> Lain</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center d-none d-lg-table-cell">
                                    <div class="mb-1">
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <i class="fas fa-calendar-alt text-primary me-1"></i><?= (int)$b['total_jadwal'] ?> Jadwal
                                        </span>
                                    </div>
                                    <span class="badge bg-light text-secondary border px-2 py-1" style="font-size:0.75rem;">
                                        <i class="fas fa-user-graduate text-secondary me-1"></i><?= (int)$b['total_siswa'] ?> Peserta
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($b['status'] === 'aktif'): ?>
                                        <span class="badge badge-soft-success px-2 py-1 rounded-pill">
                                            <i class="fas fa-unlock me-1"></i>Terbuka
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger px-2 py-1 rounded-pill">
                                            <i class="fas fa-lock me-1"></i>Terkunci
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    <span class="text-muted small">
                                        <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <a href="detail.php?id=<?= esc($b['id']) ?>" class="btn btn-sm btn-primary fw-semibold px-2" title="Kelola Butir Soal">
                                            <i class="fas fa-list-check me-1"></i> Kelola Soal
                                        </a>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Aksi Lainnya">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <li>
                                                    <button type="button" class="dropdown-item btn-edit" 
                                                            data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                            data-id="<?= esc($b['id']) ?>" 
                                                            data-kode="<?= esc($b['kode_bank_soal']) ?>"
                                                            data-nama="<?= esc($b['nama_bank_soal']) ?>" 
                                                            data-subject="<?= esc($b['subject_id']) ?>"
                                                            data-teacher="<?= esc($b['teacher_id']) ?>" 
                                                            data-jenjang="<?= esc($b['jenjang']) ?>">
                                                        <i class="fas fa-edit text-primary me-2"></i> Edit Informasi
                                                    </button>
                                                </li>
                                                <li>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="proses" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?= esc($b['id']) ?>">
                                                        <input type="hidden" name="current_status" value="<?= esc($b['status']) ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas <?= $b['status'] === 'aktif' ? 'fa-lock text-warning' : 'fa-unlock text-success' ?> me-2"></i>
                                                            <?= $b['status'] === 'aktif' ? 'Kunci Akses' : 'Buka Akses' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a href="javascript:void(0)" class="dropdown-item text-danger btn-hapus-bank"
                                                       data-id="<?= esc($b['id']) ?>"
                                                       data-nama="<?= esc($b['nama_bank_soal']) ?>"
                                                       data-kode="<?= esc($b['kode_bank_soal']) ?>"
                                                       data-jadwal="<?= (int)$b['total_jadwal'] ?>"
                                                       data-soal="<?= (int)$b['total_soal'] ?>">
                                                        <i class="fas fa-trash-alt me-2"></i> Hapus Bank Soal
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Info + Pagination Bar -->
                <div class="card-footer bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
                    <small class="text-muted">
                        Menampilkan <strong><?= $total == 0 ? 0 : $offset + 1 ?></strong>–<strong><?= min($offset + $limit, $total) ?></strong>
                        dari <strong><?= number_format($total, 0, ',', '.') ?></strong> bank soal
                        <?php if($has_filter): ?><span class="badge bg-primary-subtle text-primary ms-1">Terfilter</span><?php endif; ?>
                    </small>

                    <?php if($pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(bsQuery(['page' => $page - 1])) ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>

                            <?php
                            $start_number = ($page > 3) ? $page - 2 : 1;
                            $end_number = ($page < ($pages - 2)) ? $page + 2 : $pages;

                            if ($start_number > 1) {
                                echo "<li class='page-item'><a class='page-link' href='?" . esc(bsQuery(['page' => 1])) . "'>1</a></li>";
                                if ($start_number > 2) {
                                    echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                }
                            }

                            for ($i = $start_number; $i <= $end_number; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= esc(bsQuery(['page' => $i])) ?>"><?= esc($i) ?></a>
                                </li>
                            <?php endfor;

                            if ($end_number < $pages) {
                                if ($end_number < $pages - 1) {
                                    echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                }
                                echo "<li class='page-item'><a class='page-link' href='?" . esc(bsQuery(['page' => $pages])) . "'>{$pages}</a></li>";
                            }
                            ?>

                            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(bsQuery(['page' => $page + 1])) ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH BANK SOAL -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="" method="POST" class="modal-content border-0 shadow" id="formTambah">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="proses" value="tambah">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Buat Bank Soal Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Pilih Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach($subjects as $s): ?>
                                <option value="<?= esc($s['id']) ?>"><?= esc($s['kode_mapel']) ?> - <?= esc($s['nama_mapel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Mata pelajaran mengacu pada data master kurikulum.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Tingkat Jenjang / Kelas</label>
                        <select name="jenjang" class="form-select">
                            <option value="">-- Semua Jenjang --</option>
                            <option value="10">Kelas 10 (Fase E)</option>
                            <option value="11">Kelas 11 (Fase F)</option>
                            <option value="12">Kelas 12 (Fase F+)</option>
                        </select>
                        <div class="form-text">Opsional jika bank soal ditujukan untuk jenjang spesifik.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Nama Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="nama_bank_soal" class="form-control" placeholder="Contoh: Penilaian Akhir Semester Ganjil - Matematika X" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Kode Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="kode_bank_soal" class="form-control font-monospace" placeholder="Contoh: PAS-MTK-10" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Guru Penyusun / Pengampu <span class="text-danger">*</span></label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru Pengampu --</option>
                            <?php foreach($teachers as $t): ?>
                                <option value="<?= esc($t['id']) ?>"><?= esc($t['nama_lengkap']) ?> (NIP: <?= esc($t['nip'] ?: '-') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnSubmitTambah">
                    <i class="fas fa-save me-1"></i> Simpan Bank Soal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT BANK SOAL -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="" method="POST" class="modal-content border-0 shadow" id="formEdit">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="proses" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Informasi Bank Soal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="subject_id" id="edit-subject" class="form-select" required>
                            <?php foreach($subjects as $s): ?>
                                <option value="<?= esc($s['id']) ?>"><?= esc($s['kode_mapel']) ?> - <?= esc($s['nama_mapel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Tingkat Jenjang</label>
                        <select name="jenjang" id="edit-jenjang" class="form-select">
                            <option value="">-- Semua Jenjang --</option>
                            <option value="10">Kelas 10 (Fase E)</option>
                            <option value="11">Kelas 11 (Fase F)</option>
                            <option value="12">Kelas 12 (Fase F+)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Nama Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="nama_bank_soal" id="edit-nama" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Kode Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="kode_bank_soal" id="edit-kode" class="form-control font-monospace" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Guru Penyusun / Pengampu <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="edit-teacher" class="form-select" required>
                            <?php foreach($teachers as $t): ?>
                                <option value="<?= esc($t['id']) ?>"><?= esc($t['nama_lengkap']) ?> (NIP: <?= esc($t['nip'] ?: '-') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnSubmitEdit">
                    <i class="fas fa-save me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Menu toggle sidebar
    $("#menu-toggle").click(function(e) { 
        e.preventDefault(); 
        $("#wrapper").toggleClass("toggled"); 
    });

    // Populate modal edit
    $('.btn-edit').on('click', function() {
        $('#edit-id').val($(this).data('id'));
        $('#edit-kode').val($(this).data('kode'));
        $('#edit-nama').val($(this).data('nama'));
        $('#edit-subject').val($(this).data('subject'));
        $('#edit-teacher').val($(this).data('teacher'));
        $('#edit-jenjang').val($(this).data('jenjang'));
    });

    // SweetAlert2 confirmation for bank soal deletion
    $('.btn-hapus-bank').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var kode = $(this).data('kode');
        var jadwal = parseInt($(this).data('jadwal') || 0);
        var soal = parseInt($(this).data('soal') || 0);

        var warningText = '';
        if (jadwal > 0) {
            warningText = '<div class="alert alert-danger text-start small mt-2 mb-0"><i class="fas fa-exclamation-triangle me-1"></i> <strong>Peringatan Kritis:</strong> Bank soal ini terhubung dengan <b>' + jadwal + ' jadwal ujian</b>. Menghapusnya dapat menyebabkan error integrity pada ujian terkait!</div>';
        } else {
            warningText = '<small class="text-muted d-block mt-2">Sebanyak <b>' + soal + ' butir soal</b> beserta lampiran gambar di dalamnya akan dihapus permanen.</small>';
        }

        Swal.fire({
            title: 'Hapus Bank Soal?',
            html: 'Apakah Anda yakin ingin menghapus bank soal <b>' + $('<div>').text(nama).html() + '</b> (<code>' + $('<div>').text(kode).html() + '</code>)?' + warningText,
            icon: jadwal > 0 ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Ya, Hapus Sekarang',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-danger fw-bold px-3',
                cancelButton: 'btn btn-secondary px-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?hapus=' + encodeURIComponent(id);
            }
        });
    });

    // Cegah double submit pada form modal
    $('#formTambah').on('submit', function() {
        $('#btnSubmitTambah').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...');
    });
    $('#formEdit').on('submit', function() {
        $('#btnSubmitEdit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...');
    });
});
</script>
</body>
</html>