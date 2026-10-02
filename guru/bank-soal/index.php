<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

// --- 1. PROSES TAMBAH, EDIT, DAN HAPUS BANK SOAL (GURU SCOPE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['proses'] ?? '';

    if ($action === 'tambah') {
        $kode = trim($_POST['kode_bank_soal'] ?? '');
        $nama = trim($_POST['nama_bank_soal'] ?? '');
        $subj = (int)($_POST['subject_id'] ?? 0);
        $jenj = trim($_POST['jenjang'] ?? '') ?: null;

        if ($kode === '' || $nama === '' || $subj === 0) {
            header("Location: index.php?msg=invalid");
            exit;
        }
        try {
            $pdo->prepare("INSERT INTO cbt_bank_soal (kode_bank_soal, nama_bank_soal, subject_id, jenjang, teacher_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'aktif', NOW())")
                ->execute([$kode, $nama, $subj, $jenj, $teacher_id]);
            log_activity("Guru tambah bank soal: $nama ($kode)", $teacher_id, 'guru', null, 'ujian');
            header("Location: index.php?msg=sukses");
            exit;
        } catch (PDOException $e) {
            $code = $e->getCode() == 23000 ? 'duplicate' : 'error';
            header("Location: index.php?msg=$code");
            exit;
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_bank_soal'] ?? '');
        $nama = trim($_POST['nama_bank_soal'] ?? '');
        $subj = (int)($_POST['subject_id'] ?? 0);
        $jenj = trim($_POST['jenjang'] ?? '') ?: null;

        $own = $pdo->prepare("SELECT id FROM cbt_bank_soal WHERE id = ? AND teacher_id = ? AND status = 'aktif'");
        $own->execute([$id, $teacher_id]);
        if (!$own->fetch() || $kode === '' || $nama === '' || $subj === 0) {
            header("Location: index.php?msg=invalid");
            exit;
        }
        try {
            $pdo->prepare("UPDATE cbt_bank_soal SET kode_bank_soal=?, nama_bank_soal=?, subject_id=?, jenjang=?, updated_at=NOW() WHERE id=? AND teacher_id=?")
                ->execute([$kode, $nama, $subj, $jenj, $id, $teacher_id]);
            $pdo->prepare("UPDATE cbt_exams SET subject_id=? WHERE bank_soal_id=?")
                ->execute([$subj, $id]);
            log_activity("Guru update bank soal ID $id: $nama", $teacher_id, 'guru', null, 'ujian');
            header("Location: index.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            $code = $e->getCode() == 23000 ? 'duplicate' : 'error';
            header("Location: index.php?msg=$code");
            exit;
        }
    }

    if ($action === 'hapus') {
        $id         = (int)($_POST['id'] ?? 0);
        $upload_dir = dirname(__DIR__, 2) . '/assets/uploads/soal/';

        $own = $pdo->prepare("SELECT id FROM cbt_bank_soal WHERE id = ? AND teacher_id = ? AND status = 'aktif'");
        $own->execute([$id, $teacher_id]);
        if ($own->fetch()) {
            $qids = $pdo->prepare("SELECT id FROM cbt_questions WHERE bank_soal_id = ?");
            $qids->execute([$id]);
            $all_images = [];
            foreach ($qids->fetchAll(PDO::FETCH_COLUMN) as $qid) {
                $row = $pdo->prepare("SELECT konten_soal FROM cbt_questions WHERE id = ?");
                $row->execute([$qid]);
                $konten = $row->fetchColumn() ?: '';
                preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $konten, $m);
                foreach ($m[1] as $url) {
                    if (strpos($url, 'assets/uploads/soal/') !== false)
                        $all_images[] = basename(parse_url($url, PHP_URL_PATH));
                }
                $opts = $pdo->prepare("SELECT value_target FROM cbt_question_options WHERE question_id = ?");
                $opts->execute([$qid]);
                foreach ($opts->fetchAll(PDO::FETCH_COLUMN) as $val) {
                    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $val, $m2);
                    foreach ($m2[1] as $url) {
                        if (strpos($url, 'assets/uploads/soal/') !== false)
                            $all_images[] = basename(parse_url($url, PHP_URL_PATH));
                    }
                }
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM cbt_question_options WHERE question_id IN (SELECT id FROM cbt_questions WHERE bank_soal_id = ?)")->execute([$id]);
                $pdo->prepare("DELETE FROM cbt_questions WHERE bank_soal_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?")->execute([$id, $teacher_id]);
                $pdo->commit();
                foreach (array_unique($all_images) as $fname) {
                    $path = $upload_dir . $fname;
                    if ($fname && file_exists($path)) @unlink($path);
                }
                log_activity("Guru hapus bank soal ID $id", $teacher_id, 'guru', null, 'ujian');
                header("Location: index.php?msg=deleted");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $code = $e->getCode() == 23000 ? 'error_fk' : 'error';
                header("Location: index.php?msg=$code");
                exit;
            }
        }
        header("Location: index.php?msg=error");
        exit;
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

$where  = "WHERE b.teacher_id = ?";
$params = [$teacher_id];

if (!empty($search)) {
    $where .= " AND (b.nama_bank_soal LIKE ? OR b.kode_bank_soal LIKE ? OR s.nama_mapel LIKE ?)";
    $s = '%' . like_escape($search) . '%';
    array_push($params, $s, $s, $s);
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

$base_sql = "FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id $where";

// Hitung total
$cnt = $pdo->prepare("SELECT COUNT(*) $base_sql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = $limit > 0 ? max(1, (int)ceil($total / $limit)) : 1;
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $limit;
}

// Ambil data bank soal guru
$stmt = $pdo->prepare("SELECT b.*, s.nama_mapel, s.kode_mapel,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id) as total_soal,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id AND tipe = 'pg') as total_pg,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id AND tipe = 'essay') as total_essay,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id AND tipe NOT IN ('pg', 'essay')) as total_kompleks,
    (SELECT COUNT(DISTINCT p.student_id) FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id WHERE e.bank_soal_id = b.id) as total_siswa,
    (SELECT COUNT(*) FROM cbt_exams WHERE bank_soal_id = b.id) as total_jadwal
    $base_sql ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$limit, $offset]));
$banks = $stmt->fetchAll();

$has_filter = $search !== '' || $f_subject || $f_jenjang !== '' || $f_status !== '' || $date_from !== '' || $date_to !== '';
$subjects   = $pdo->query("SELECT * FROM cbt_subjects WHERE is_aktif = 1 ORDER BY nama_mapel")->fetchAll();

// --- METRIC CARDS STATISTIK (GURU SCOPE) ---
$metric_total_bank = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = $teacher_id")->fetchColumn();
$metric_bank_aktif = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = $teacher_id AND status = 'aktif'")->fetchColumn();
$metric_total_soal = (int)$pdo->query("SELECT COUNT(*) FROM cbt_questions q JOIN cbt_bank_soal b ON q.bank_soal_id = b.id WHERE b.teacher_id = $teacher_id")->fetchColumn();
$metric_bank_baru  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_bank_soal WHERE teacher_id = $teacher_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

function gbsQ(array $extra = []): string {
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

<div class="d-flex" id="wrapper" style="overflow-x: hidden;">
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100" style="min-width: 0; max-width: 100%; overflow-x: hidden;">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm border-bottom">
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <div class="ms-3 d-flex align-items-center">
                <i class="fas fa-layer-group text-primary fs-5 me-2"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Bank Soal Saya</h5>
                    <small class="text-muted">Kelola paket bank soal, penulisan butir soal, dan pemetaan ujian</small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 py-3">
            
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= esc(BASE_URL) ?>guru/index.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Bank Soal Saya</li>
                </ol>
            </nav>

            <!-- Flash Alerts -->
            <?php if (isset($_GET['msg'])): $m = $_GET['msg']; ?>
            <?php
                $alertClass = in_array($m, ['sukses','updated','deleted']) ? 'success' : 'danger';
                $alertIcon = $alertClass === 'success' ? 'check-circle' : 'exclamation-triangle';
                $alertMsg = match($m) {
                    'sukses'    => 'Bank soal baru berhasil ditambahkan dan siap diisi butir soal.',
                    'updated'   => 'Informasi bank soal berhasil diperbarui.',
                    'deleted'   => 'Bank soal beserta seluruh butir soal berhasil dihapus.',
                    'locked'    => 'Bank soal dikunci oleh admin dan tidak dapat diubah.',
                    'invalid'   => 'Data tidak valid atau Anda tidak memiliki hak akses ke bank soal ini.',
                    'duplicate' => 'Kode bank soal sudah digunakan. Harap gunakan kode unik lainnya.',
                    'error_fk'  => 'Tidak dapat menghapus bank soal yang sudah terhubung dengan jadwal ujian aktif.',
                    default     => 'Terjadi kesalahan pada sistem. Silakan coba kembali.',
                };
            ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fas fa-<?= $alertIcon ?> me-2"></i>
                <strong><?= $alertClass === 'success' ? 'Berhasil!' : 'Perhatian!' ?></strong> <?= $alertMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Metric Cards (Overview Statistik Guru) -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Bank Soal Saya</span>
                                <h3 class="fw-bold mb-0 mt-1 text-dark"><?= number_format($metric_total_bank, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-folder me-1 text-primary"></i>Total paket soal Anda</small>
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
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Status Terbuka</span>
                                <h3 class="fw-bold mb-0 mt-1 text-success"><?= number_format($metric_bank_aktif, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-unlock me-1 text-success"></i>Dapat diedit & diujikan</small>
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
                                <small class="text-muted"><i class="fas fa-list-ol me-1 text-info"></i>Soal yang Anda susun</small>
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
                                <small class="text-muted"><i class="fas fa-calendar-week me-1 text-warning"></i>Dibuat 7 hari terakhir</small>
                            </div>
                            <div class="rounded-3 p-3 bg-warning-subtle text-warning">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes text-primary me-2"></i>Daftar Paket Bank Soal</h6>
                        <span class="badge bg-light text-secondary border px-2 py-1"><?= number_format($total, 0, ',', '.') ?> paket ditemukan</span>
                    </div>
                    <button class="btn btn-sm btn-primary shadow-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="fas fa-plus-circle me-1"></i> Buat Bank Soal
                    </button>
                </div>
            </div>

            <!-- Unified Filter Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end" id="filterForm">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-search me-1"></i>Pencarian</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Ketik Nama Bank, Kode, Mapel..." value="<?= esc($search) ?>">
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
                    <table class="table table-hover align-middle mb-0" style="min-width: 1050px;">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                            <tr>
                                <th class="text-center py-3" width="50">#</th>
                                <th class="py-3">Identitas Bank Soal</th>
                                <th class="py-3">Jenjang Kelas</th>
                                <th class="py-3 text-center">Komposisi Soal</th>
                                <th class="py-3 text-center">Peserta & Jadwal</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="py-3">Tanggal Dibuat</th>
                                <th class="text-center py-3" width="220">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($banks)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                                        <h6 class="fw-bold text-secondary mb-1">Belum Ada Bank Soal Ditemukan</h6>
                                        <p class="small text-muted mb-3">Mulai buat paket bank soal pertama Anda untuk pelaksanaan ujian.</p>
                                        <button class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                                            <i class="fas fa-plus-circle me-1"></i> Buat Bank Soal Baru
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $no = $offset + 1; foreach ($banks as $b): ?>
                            <tr>
                                <td class="text-center text-muted fw-bold small"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= esc($b['nama_bank_soal']) ?></div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                            <i class="fas fa-barcode me-1"></i><?= esc($b['kode_bank_soal']) ?>
                                        </span>
                                        <span class="text-secondary">
                                            <i class="fas fa-book-open me-1"></i><?= esc($b['kode_mapel']) ?> - <?= esc($b['nama_mapel']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($b['jenjang'])): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 rounded-2 fw-semibold">
                                            <i class="fas fa-graduation-cap me-1"></i>Kelas <?= esc($b['jenjang']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-2 py-1 rounded-2">Semua Jenjang</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="mb-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-6 fw-bold">
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
                                <td class="text-center">
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
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                            <i class="fas fa-unlock me-1"></i>Terbuka
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">
                                            <i class="fas fa-lock me-1"></i>Terkunci
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <a href="detail.php?id=<?= esc($b['id']) ?>" class="btn btn-sm btn-primary fw-semibold px-2" title="Kelola Butir Soal">
                                            <i class="fas fa-list-check me-1"></i> Kelola Soal
                                        </a>
                                        
                                        <?php if ($b['status'] === 'aktif'): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Aksi">
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
                                                            data-jenjang="<?= esc($b['jenjang']) ?>">
                                                        <i class="fas fa-edit text-primary me-2"></i> Edit Informasi
                                                    </button>
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
                                        <?php endif; ?>
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
                                <a class="page-link" href="?<?= esc(gbsQ(['page' => $page - 1])) ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>

                            <?php
                            $start_number = ($page > 3) ? $page - 2 : 1;
                            $end_number = ($page < ($pages - 2)) ? $page + 2 : $pages;

                            if ($start_number > 1) {
                                echo "<li class='page-item'><a class='page-link' href='?" . esc(gbsQ(['page' => 1])) . "'>1</a></li>";
                                if ($start_number > 2) {
                                    echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                }
                            }

                            for ($i = $start_number; $i <= $end_number; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= esc(gbsQ(['page' => $i])) ?>"><?= esc($i) ?></a>
                                </li>
                            <?php endfor;

                            if ($end_number < $pages) {
                                if ($end_number < $pages - 1) {
                                    echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                }
                                echo "<li class='page-item'><a class='page-link' href='?" . esc(gbsQ(['page' => $pages])) . "'>{$pages}</a></li>";
                            }
                            ?>

                            <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(gbsQ(['page' => $page + 1])) ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH BANK SOAL (GURU) -->
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
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Tingkat Jenjang / Kelas</label>
                        <select name="jenjang" class="form-select">
                            <option value="">-- Semua Jenjang --</option>
                            <option value="10">Kelas 10 (Fase E)</option>
                            <option value="11">Kelas 11 (Fase F)</option>
                            <option value="12">Kelas 12 (Fase F+)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Nama Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="nama_bank_soal" class="form-control" placeholder="Contoh: Penilaian Harian 1 - Matematika X" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Kode Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="kode_bank_soal" class="form-control font-monospace" placeholder="Contoh: PH1-MTK-10" required>
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

<!-- MODAL EDIT BANK SOAL (GURU) -->
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
        $('#edit-jenjang').val($(this).data('jenjang'));
    });

    // SweetAlert2 confirmation for deletion
    $('.btn-hapus-bank').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var kode = $(this).data('kode');
        var jadwal = parseInt($(this).data('jadwal') || 0);
        var soal = parseInt($(this).data('soal') || 0);

        var warningText = '';
        if (jadwal > 0) {
            warningText = '<div class="alert alert-danger text-start small mt-2 mb-0"><i class="fas fa-exclamation-triangle me-1"></i> <strong>Peringatan:</strong> Bank soal ini terhubung ke <b>' + jadwal + ' jadwal ujian</b>. Hapus ujian terlebih dahulu!</div>';
        } else {
            warningText = '<small class="text-muted d-block mt-2">Sebanyak <b>' + soal + ' butir soal</b> di dalamnya akan dihapus permanen.</small>';
        }

        Swal.fire({
            title: 'Hapus Bank Soal?',
            html: 'Apakah Anda yakin ingin menghapus bank soal <b>' + $('<div>').text(nama).html() + '</b> (<code>' + $('<div>').text(kode).html() + '</code>)?' + warningText,
            icon: jadwal > 0 ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-danger fw-bold px-3',
                cancelButton: 'btn btn-secondary px-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'index.php';

                var inputCsrf = document.createElement('input');
                inputCsrf.type = 'hidden';
                inputCsrf.name = 'csrf_token';
                inputCsrf.value = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
                form.appendChild(inputCsrf);

                var inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'proses';
                inputAction.value = 'hapus';
                form.appendChild(inputAction);

                var inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id';
                inputId.value = id;
                form.appendChild(inputId);

                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Double submit protection
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
