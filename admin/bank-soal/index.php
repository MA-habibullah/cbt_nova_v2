<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// --- 1. PROSES LOGIC (TAMBAH, EDIT, KUNCI, HAPUS) ---
if (isset($_POST['proses'])) {
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
            $pdo->prepare("UPDATE cbt_bank_soal SET kode_bank_soal=?, nama_bank_soal=?, subject_id=?, jenjang=?, teacher_id=? WHERE id=?")
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
            $new_status = ($_POST['current_status'] == 'aktif') ? 'nonaktif' : 'aktif';
            $pdo->prepare("UPDATE cbt_bank_soal SET status = ? WHERE id = ?")
                ->execute([$new_status, (int)$_POST['id']]);
            log_activity("Toggle status bank soal ID " . (int)$_POST['id'] . " → $new_status", null, null, null, 'ujian');
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
        // Gambar di konten soal
        $row = $pdo->prepare("SELECT konten_soal FROM cbt_questions WHERE id = ?");
        $row->execute([$qid]);
        $konten = $row->fetchColumn() ?: '';
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $konten, $m);
        foreach ($m[1] as $url) {
            if (strpos($url, 'assets/uploads/soal/') !== false) {
                $all_images[] = basename(parse_url($url, PHP_URL_PATH));
            }
        }
        // Gambar di opsi jawaban
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

// --- 2. FILTER & PAGINATION ---
$search     = trim($_GET['q'] ?? '');
$f_subject  = (int)($_GET['subject_id'] ?? 0);
$f_jenjang  = trim($_GET['jenjang'] ?? '');
$date_from  = trim($_GET['date_from'] ?? '');
$date_to    = trim($_GET['date_to'] ?? '');
$_show      = (int)($_GET['show'] ?? 10);
$limit      = in_array($_show, [10, 25, 50]) ? $_show : 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (b.nama_bank_soal LIKE ? OR b.kode_bank_soal LIKE ? OR s.nama_mapel LIKE ?)";
    $s = '%' . $search . '%';
    array_push($params, $s, $s, $s);
}
if ($f_subject) {
    $where .= " AND b.subject_id = ?";
    $params[] = $f_subject;
}
if ($f_jenjang !== '') {
    $where .= " AND b.jenjang = ?";
    $params[] = $f_jenjang;
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

// Hitung total
$count_stmt = $pdo->prepare("SELECT COUNT(*) $base_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

// Ambil data halaman ini
$data_stmt = $pdo->prepare("SELECT b.*, s.nama_mapel, s.kode_mapel, t.nama_lengkap as nama_guru,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id) as total_soal,
    (SELECT COUNT(DISTINCT p.student_id) FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id WHERE e.bank_soal_id = b.id) as total_siswa
    $base_sql ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$data_stmt->execute(array_merge($params, [$limit, $offset]));
$bank_soal = $data_stmt->fetchAll();

$has_filter = $search !== '' || $f_subject || $f_jenjang !== '' || $date_from !== '' || $date_to !== '';

$subjects = $pdo->query("SELECT * FROM cbt_subjects ORDER BY nama_mapel")->fetchAll();
$teachers = $pdo->query("SELECT * FROM cbt_teachers WHERE is_aktif = 1")->fetchAll();

function bsQuery(array $extra = []): string {
    global $search, $f_subject, $f_jenjang, $date_from, $date_to, $limit;
    $p = array_filter(array_merge([
        'q'          => $search,
        'subject_id' => $f_subject ?: '',
        'jenjang'    => $f_jenjang,
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

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold text-primary">Master Bank Soal</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php if (isset($_GET['msg'])): $m = $_GET['msg']; ?>
            <?php
                $alertClass = in_array($m, ['sukses','updated','deleted','status_changed']) ? 'success' : 'danger';
                $alertMsg = match($m) {
                    'sukses'         => 'Bank soal berhasil ditambahkan.',
                    'updated'        => 'Bank soal berhasil diperbarui.',
                    'deleted'        => 'Bank soal berhasil dihapus.',
                    'status_changed' => 'Status bank soal berhasil diubah.',
                    'duplicate'      => 'Kode bank soal sudah digunakan. Gunakan kode yang berbeda.',
                    'invalid'        => 'Data tidak valid. Pastikan semua field terisi.',
                    'error_fk'       => 'Tidak dapat menghapus bank soal yang masih digunakan oleh ujian aktif.',
                    default          => 'Terjadi kesalahan. Silakan coba lagi.',
                };
            ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show border-0 shadow-sm mb-4">
                <i class="fas fa-<?= $alertClass === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= $alertMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">Daftar Bank Soal</h4>
                <div class="d-flex gap-2">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/backup/bank-soal-backup.php" class="btn btn-outline-primary shadow-sm">
                        <i class="fas fa-database me-2"></i> Backup & Restore
                    </a>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="fas fa-plus-circle me-2"></i> Buat Bank Soal
                    </button>
                </div>
            </div>

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end" id="filterForm">
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">CARI</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="q" class="form-control" placeholder="Nama / kode / mapel..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">MATA PELAJARAN</label>
                            <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Mapel</option>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($f_subject == $s['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($s['nama_mapel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">JENJANG</label>
                            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Jenjang</option>
                                <option value="10" <?= esc($f_jenjang=='10'?'selected':'') ?>>Kelas 10</option>
                                <option value="11" <?= esc($f_jenjang=='11'?'selected':'') ?>>Kelas 11</option>
                                <option value="12" <?= esc($f_jenjang=='12'?'selected':'') ?>>Kelas 12</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">DARI TANGGAL</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">SAMPAI TANGGAL</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-6 col-md-3 col-lg-1">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">SHOW</label>
                            <select name="show" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="10"  <?= esc($limit==10  ? 'selected':'') ?>>10</option>
                                <option value="25"  <?= esc($limit==25  ? 'selected':'') ?>>25</option>
                                <option value="50"  <?= esc($limit==50  ? 'selected':'') ?>>50</option>
                            </select>
                        </div>
                        <?php if($has_filter): ?>
                        <div class="col-6 col-md-3 col-lg-auto">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm w-100" title="Reset"><i class="fas fa-times me-1"></i>Reset</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="py-3 px-4">Mata Pelajaran</th>
                                <th>Guru Pengampu</th>
                                <th>Jenjang</th>
                                <th>Jumlah Soal</th>
                                <th>Siswa Terpilih</th>
                                <th>Status</th>
                                <th>Tanggal Dibuat</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bank_soal as $b): ?>
                            <tr>
                                <td class="px-4">
                                    <div class="fw-bold text-dark"><?= $b['nama_bank_soal'] ?></div>
                                    <small class="text-muted"><?= $b['kode_mapel'] ?> - <?= $b['nama_mapel'] ?></small>
                                </td>
                                <td><?= $b['nama_guru'] ?></td>
                                <td>
                                    <?php if (!empty($b['jenjang'])): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3">Kelas <?= htmlspecialchars($b['jenjang']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border px-3">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info-subtle text-info border border-info-subtle px-3"><?= $b['total_soal'] ?> Soal</span></td>
                                <td>
                                    <span class="badge bg-subtle text-dark border border-warning-subtle px-3">
                                        <i class="fas fa-user-graduate me-1"></i> <?= $b['total_siswa'] ?> Peserta
                                    </span>
                                </td>
                                <td>
                                    <?php if ($b['status'] == 'aktif'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3">Terbuka</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3"><i class="fas fa-lock me-1"></i> Terkunci</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <a href="detail.php?id=<?= esc($b['id']) ?>" class="btn btn-sm btn-primary px-3" title="Masuk ke Bank Soal">
                                            <i class="fas fa-door-open me-1"></i> Masuk
                                        </a>
                                        <button class="btn btn-sm btn-outline btn-edit" data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= esc($b['id']) ?>" data-kode="<?= esc($b['kode_bank_soal']) ?>"
                                                data-nama="<?= esc($b['nama_bank_soal']) ?>" data-subject="<?= esc($b['subject_id']) ?>"
                                                data-teacher="<?= esc($b['teacher_id']) ?>" data-jenjang="<?= esc($b['jenjang']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="proses" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= esc($b['id']) ?>">
                                            <input type="hidden" name="current_status" value="<?= esc($b['status']) ?>">
                                            <button type="submit" class="btn btn-sm <?= esc($b['status'] == 'aktif' ? 'btn-outline-danger' : 'btn-success') ?>" title="<?= esc($b['status'] == 'aktif' ? 'Kunci' : 'Buka') ?>">
                                                <i class="fas <?= $b['status'] == 'aktif' ? 'fa-lock' : 'fa-unlock' ?>"></i>
                                            </button>
                                        </form>
                                        <a href="?hapus=<?= esc($b['id']) ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Hapus Bank Soal ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Info + Pagination -->
                <div class="px-3 py-2 bg-white border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= $total == 0 ? 0 : $offset + 1 ?></strong>–<strong><?= min($offset + $limit, $total) ?></strong>
                        dari <strong><?= $total ?></strong> bank soal
                        <?php if($has_filter): ?><span class="text-primary">(terfilter)</span><?php endif; ?>
                    </small>

                    <?php if($pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0 gap-1">
                            <li class="page-item <?= $page<=1 ? 'disabled':'' ?>">
                                <a class="page-link" href="?<?= esc(bsQuery(['page' => $page-1])) ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php
                            $prev = null;
                            foreach (range(1, $pages) as $i):
                                if ($i != 1 && $i != $pages && abs($i - $page) > 2) {
                                    if ($prev !== null && abs($prev - $i) == 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    $prev = $i; continue;
                                }
                            ?>
                            <li class="page-item <?= $page==$i ? 'active':'' ?>">
                                <a class="page-link" href="?<?= esc(bsQuery(['page' => $i])) ?>"><?= esc($i) ?></a>
                            </li>
                            <?php $prev = $i; endforeach; ?>
                            <li class="page-item <?= $page>=$pages ? 'disabled':'' ?>">
                                <a class="page-link" href="?<?= esc(bsQuery(['page' => $page+1])) ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Tambah Mata Pelajaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="proses" value="tambah">
                <div class="mb-3">
                    <label class="form-label fw-bold">Pilih Mata Pelajaran (Master)</label>
                    <select name="subject_id" class="form-select" required>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= esc($s['kode_mapel']) ?> - <?= esc($s['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Bank Soal</label>
                    <input type="text" name="nama_bank_soal" class="form-control" placeholder="Contoh: Matematika Wajib Kelas X" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Kode Bank Soal</label>
                    <input type="text" name="kode_bank_soal" class="form-control" placeholder="Contoh: MTK-W-10" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Guru Pengampu</label>
                    <select name="teacher_id" class="form-select" required>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?= esc($t['id']) ?>"><?= esc($t['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Jenjang</label>
                    <select name="jenjang" class="form-select">
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" class="btn btn-primary px-4">Simpan Bank Soal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Edit Bank Soal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="proses" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Mata Pelajaran</label>
                    <select name="subject_id" id="edit-subject" class="form-select" required>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= esc($s['kode_mapel']) ?> - <?= esc($s['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Bank Soal</label>
                    <input type="text" name="nama_bank_soal" id="edit-nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Kode Bank Soal</label>
                    <input type="text" name="kode_bank_soal" id="edit-kode" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Guru Pengampu</label>
                    <select name="teacher_id" id="edit-teacher" class="form-select" required>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?= esc($t['id']) ?>"><?= esc($t['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Jenjang</label>
                    <select name="jenjang" id="edit-jenjang" class="form-select">
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" class="btn btn-primary px-4 fw-bold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $('.btn-edit').on('click', function() {
        // Logika untuk mengisi modal edit ...
    });
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
<script>
$(document).ready(function() {
    $('.btn-edit').on('click', function() {
        $('#edit-id').val($(this).data('id'));
        $('#edit-kode').val($(this).data('kode'));
        $('#edit-nama').val($(this).data('nama'));
        $('#edit-subject').val($(this).data('subject'));
        $('#edit-teacher').val($(this).data('teacher'));
        $('#edit-jenjang').val($(this).data('jenjang'));
    });
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
});
</script>
</body>
</html>