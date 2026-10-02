<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = $_SESSION['teacher_id'];

// Proses tambah & edit bank soal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['proses'] ?? '';

    if ($action === 'tambah') {
        $kode = trim($_POST['kode_bank_soal'] ?? '');
        $nama = trim($_POST['nama_bank_soal'] ?? '');
        $subj = (int)($_POST['subject_id'] ?? 0);
        $jenj = trim($_POST['jenjang'] ?? '') ?: null;
        if ($kode === '' || $nama === '' || $subj === 0) {
            header("Location: index.php?msg=invalid"); exit;
        }
        try {
            $pdo->prepare("INSERT INTO cbt_bank_soal (kode_bank_soal, nama_bank_soal, subject_id, jenjang, teacher_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'aktif', NOW())")
                ->execute([$kode, $nama, $subj, $jenj, $teacher_id]);
            header("Location: index.php?msg=sukses"); exit;
        } catch (PDOException $e) {
            $code = $e->getCode() == 23000 ? 'duplicate' : 'error';
            header("Location: index.php?msg=$code"); exit;
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_bank_soal'] ?? '');
        $nama = trim($_POST['nama_bank_soal'] ?? '');
        $subj = (int)($_POST['subject_id'] ?? 0);
        $jenj = trim($_POST['jenjang'] ?? '') ?: null;
        $own  = $pdo->prepare("SELECT id FROM cbt_bank_soal WHERE id = ? AND teacher_id = ? AND status = 'aktif'");
        $own->execute([$id, $teacher_id]);
        if (!$own->fetch() || $kode === '' || $nama === '' || $subj === 0) {
            header("Location: index.php?msg=invalid"); exit;
        }
        try {
            $pdo->prepare("UPDATE cbt_bank_soal SET kode_bank_soal=?, nama_bank_soal=?, subject_id=?, jenjang=? WHERE id=? AND teacher_id=?")
                ->execute([$kode, $nama, $subj, $jenj, $id, $teacher_id]);
            $pdo->prepare("UPDATE cbt_exams SET subject_id=? WHERE bank_soal_id=?")
                ->execute([$subj, $id]);
            header("Location: index.php?msg=updated"); exit;
        } catch (PDOException $e) {
            $code = $e->getCode() == 23000 ? 'duplicate' : 'error';
            header("Location: index.php?msg=$code"); exit;
        }
    }

    if ($action === 'hapus') {
        $id         = (int)($_POST['id'] ?? 0);
        $upload_dir = dirname(__DIR__, 2) . '/assets/uploads/soal/';
        $own = $pdo->prepare("SELECT id FROM cbt_bank_soal WHERE id = ? AND teacher_id = ? AND status = 'aktif'");
        $own->execute([$id, $teacher_id]);
        if ($own->fetch()) {
            // Kumpulkan semua gambar sebelum dihapus dari DB
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
                header("Location: index.php?msg=deleted"); exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $code = $e->getCode() == 23000 ? 'error_fk' : 'error';
                header("Location: index.php?msg=$code"); exit;
            }
        }
        header("Location: index.php?msg=error"); exit;
    }
}

// Filter & Pagination
$search    = trim($_GET['q'] ?? '');
$f_subject = (int)($_GET['subject_id'] ?? 0);
$f_jenjang = trim($_GET['jenjang'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
$_show     = (int)($_GET['show'] ?? 10);
$limit     = in_array($_show, [10, 25, 50]) ? $_show : 10;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $limit;

$where  = "WHERE b.teacher_id = ?";
$params = [$teacher_id];

if (!empty($search)) {
    $where .= " AND (b.nama_bank_soal LIKE ? OR b.kode_bank_soal LIKE ? OR s.nama_mapel LIKE ?)";
    $s = '%' . like_escape($search) . '%';
    array_push($params, $s, $s, $s);
}
if ($f_subject) { $where .= " AND b.subject_id = ?"; $params[] = $f_subject; }
if ($f_jenjang !== '') { $where .= " AND b.jenjang = ?"; $params[] = $f_jenjang; }
if (!empty($date_from)) { $where .= " AND DATE(b.created_at) >= ?"; $params[] = $date_from; }
if (!empty($date_to))   { $where .= " AND DATE(b.created_at) <= ?"; $params[] = $date_to; }

$base_sql = "FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id $where";

$cnt = $pdo->prepare("SELECT COUNT(*) $base_sql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

$stmt = $pdo->prepare("SELECT b.*, s.nama_mapel,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = b.id) as total_soal
    $base_sql ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$limit, $offset]));
$banks = $stmt->fetchAll();

$has_filter = $search !== '' || $f_subject || $f_jenjang !== '' || $date_from !== '' || $date_to !== '';
$subjects   = $pdo->query("SELECT * FROM cbt_subjects ORDER BY nama_mapel")->fetchAll();

function gbsQ(array $extra = []): string {
    global $search, $f_subject, $f_jenjang, $date_from, $date_to, $limit;
    $p = array_filter(array_merge([
        'q' => $search, 'subject_id' => $f_subject ?: '', 'jenjang' => $f_jenjang,
        'date_from' => $date_from, 'date_to' => $date_to, 'show' => $limit,
    ], $extra), fn($v) => $v !== '' && $v !== null);
    return http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold text-primary">Bank Soal Saya</h5>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if (isset($_GET['msg'])): $m = $_GET['msg']; ?>
            <?php
                $alertClass = in_array($m, ['sukses','updated','deleted']) ? 'success' : 'danger';
                $alertMsg = match($m) {
                    'sukses'    => 'Bank soal berhasil ditambahkan.',
                    'updated'   => 'Bank soal berhasil diperbarui.',
                    'deleted'   => 'Bank soal berhasil dihapus.',
                    'locked'    => 'Bank soal dikunci oleh admin, tidak dapat diubah.',
                    'invalid'   => 'Data tidak valid atau akses ditolak.',
                    'duplicate' => 'Kode bank soal sudah digunakan. Gunakan kode yang berbeda.',
                    'error_fk'  => 'Tidak dapat menghapus bank soal yang masih digunakan oleh ujian aktif.',
                    default     => 'Terjadi kesalahan. Silakan coba lagi.',
                };
            ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show border-0 shadow-sm mb-4">
                <i class="fas fa-<?= $alertClass === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= $alertMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">CARI</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="q" class="form-control" placeholder="Nama / kode / mapel..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">MATA PELAJARAN</label>
                            <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Mapel</option>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($f_subject==$s['id']?'selected':'') ?>><?= htmlspecialchars($s['nama_mapel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">JENJANG</label>
                            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Jenjang</option>
                                <option value="10" <?= esc($f_jenjang=='10'?'selected':'') ?>>Kelas 10</option>
                                <option value="11" <?= esc($f_jenjang=='11'?'selected':'') ?>>Kelas 11</option>
                                <option value="12" <?= esc($f_jenjang=='12'?'selected':'') ?>>Kelas 12</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">DARI TANGGAL</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">SAMPAI TANGGAL</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted" style="font-size:.7rem;">TAMPILKAN</label>
                            <select name="show" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="10"  <?= esc($limit==10?'selected':'') ?>>10</option>
                                <option value="25"  <?= esc($limit==25?'selected':'') ?>>25</option>
                                <option value="50"  <?= esc($limit==50?'selected':'') ?>>50</option>
                            </select>
                        </div>
                        <?php if($has_filter): ?>
                        <div class="col-md-1">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-times"></i> Reset</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-muted"><i class="fas fa-book-open me-2"></i> Daftar Bank Soal</h6>
                    <div class="d-flex align-items-center gap-3">
                        <small class="text-muted">
                            <?= $total ?> bank soal ditemukan
                            <?= $has_filter ? '<span class="text-primary">(terfilter)</span>' : '' ?>
                        </small>
                        <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus me-1"></i> Tambah Bank Soal
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="py-3 px-4">Nama Bank Soal</th>
                                <th>Mata Pelajaran</th>
                                <th>Jenjang</th>
                                <th class="text-center">Jumlah Soal</th>
                                <th class="text-center">Status</th>
                                <th>Dibuat</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($banks)): ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada bank soal.</td></tr>
                            <?php else: foreach($banks as $b): ?>
                            <tr>
                                <td class="px-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['nama_bank_soal']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($b['kode_bank_soal']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($b['nama_mapel']) ?></td>
                                <td>
                                    <?php if (!empty($b['jenjang'])): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3">Kelas <?= htmlspecialchars($b['jenjang']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border px-3">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3"><?= $b['total_soal'] ?> Soal</span>
                                </td>
                                <td class="text-center">
                                    <?php if($b['status']=='aktif'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3">Terbuka</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3"><i class="fas fa-lock me-1"></i> Terkunci</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="detail.php?id=<?= esc($b['id']) ?>" class="btn btn-sm btn-primary shadow-sm">
                                            <i class="fas fa-door-open me-1"></i> Masuk
                                        </a>
                                        <?php if($b['status'] === 'aktif'): ?>
                                        <button class="btn btn-sm btn-outline-secondary btn-edit"
                                            data-id="<?= esc($b['id']) ?>"
                                            data-kode="<?= htmlspecialchars($b['kode_bank_soal'], ENT_QUOTES) ?>"
                                            data-nama="<?= htmlspecialchars($b['nama_bank_soal'], ENT_QUOTES) ?>"
                                            data-subject="<?= esc($b['subject_id']) ?>"
                                            data-jenjang="<?= htmlspecialchars($b['jenjang'] ?? '', ENT_QUOTES) ?>"
                                            data-bs-toggle="modal" data-bs-target="#modalEdit"
                                            title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-hapus"
                                            data-id="<?= esc($b['id']) ?>"
                                            data-nama="<?= htmlspecialchars($b['nama_bank_soal'], ENT_QUOTES) ?>"
                                            title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="px-3 py-2 bg-white border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= $total==0?0:$offset+1 ?></strong>–<strong><?= min($offset+$limit,$total) ?></strong>
                        dari <strong><?= $total ?></strong>
                    </small>
                    <?php if($pages > 1): ?>
                    <nav><ul class="pagination pagination-sm mb-0 gap-1">
                        <li class="page-item <?= $page<=1?'disabled':'' ?>">
                            <a class="page-link" href="?<?= esc(gbsQ(['page'=>$page-1])) ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php $prev=null; foreach(range(1,$pages) as $i):
                            if($i!=1 && $i!=$pages && abs($i-$page)>2){
                                if($prev!==null && abs($prev-$i)==1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                $prev=$i; continue;
                            } ?>
                        <li class="page-item <?= $page==$i?'active':'' ?>">
                            <a class="page-link" href="?<?= esc(gbsQ(['page'=>$i])) ?>"><?= esc($i) ?></a>
                        </li>
                        <?php $prev=$i; endforeach; ?>
                        <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
                            <a class="page-link" href="?<?= esc(gbsQ(['page'=>$page+1])) ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul></nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="proses" value="tambah">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i> Tambah Bank Soal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kode Bank Soal <span class="text-danger">*</span></label>
                    <input type="text" name="kode_bank_soal" class="form-control" placeholder="Contoh: BS-MTK-2025" required maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Bank Soal <span class="text-danger">*</span></label>
                    <input type="text" name="nama_bank_soal" class="form-control" placeholder="Contoh: Matematika Kelas X Semester 1" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= htmlspecialchars($s['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jenjang</label>
                    <select name="jenjang" class="form-select">
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-1"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="proses" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-pen me-2 text-secondary"></i> Edit Bank Soal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kode Bank Soal <span class="text-danger">*</span></label>
                    <input type="text" name="kode_bank_soal" id="editKode" class="form-control" required maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Bank Soal <span class="text-danger">*</span></label>
                    <input type="text" name="nama_bank_soal" id="editNama" class="form-control" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select name="subject_id" id="editSubject" class="form-select" required>
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= htmlspecialchars($s['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jenjang</label>
                    <select name="jenjang" id="editJenjang" class="form-select">
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-1"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Form Hapus (hidden) -->
<form method="POST" id="formHapus" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
    <input type="hidden" name="proses" value="hapus">
    <input type="hidden" name="id" id="hapusId">
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $("#menu-toggle").click(function(e){e.preventDefault();$("#wrapper").toggleClass("toggled");});

    // Isi form edit dari data-attribute tombol
    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editId').value      = this.dataset.id;
            document.getElementById('editKode').value    = this.dataset.kode;
            document.getElementById('editNama').value    = this.dataset.nama;
            document.getElementById('editSubject').value = this.dataset.subject;
            document.getElementById('editJenjang').value = this.dataset.jenjang || '';
        });
    });

    // Konfirmasi hapus
    document.querySelectorAll('.btn-hapus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var nama = this.dataset.nama;
            var id   = this.dataset.id;
            Swal.fire({
                title: 'Hapus Bank Soal?',
                html: 'Bank soal <b>' + nama + '</b> dan semua soal di dalamnya akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(function(result) {
                if (result.isConfirmed) {
                    document.getElementById('hapusId').value = id;
                    document.getElementById('formHapus').submit();
                }
            });
        });
    });
</script>
</body>
</html>
