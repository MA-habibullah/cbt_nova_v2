<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 3) . '/config/database.php';

// 0. Proteksi Halaman
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    header("Location: " . BASE_URL . "index.php"); 
    exit;
}

// 1. Identifikasi Bank Soal
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info bank soal & mapel
$stmt_info = $pdo->prepare("SELECT b.nama_bank_soal, b.kode_bank_soal, s.nama_mapel 
                             FROM cbt_bank_soal b 
                             JOIN cbt_subjects s ON b.subject_id = s.id 
                             WHERE b.id = ?");
$stmt_info->execute([$id_bank]);
$info_bank = $stmt_info->fetch();

if (!$info_bank) {
    header("Location: ../index.php");
    exit;
}

// 2. Ambil Data Master untuk Dropdown
$stmt_ex = $pdo->prepare("SELECT id, nama_mapel_ujian FROM cbt_exams WHERE bank_soal_id = ? ORDER BY id DESC");
$stmt_ex->execute([$id_bank]);
$exams = $stmt_ex->fetchAll();

$classes  = $pdo->query("SELECT id, jenjang, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
$listSesi = $pdo->query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id")->fetchAll();

// 3. Tangkap Filter
$filter_exam  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$filter_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$filter_sesi  = (isset($_GET['sesi']) && $_GET['sesi'] !== '') ? $_GET['sesi'] : 'all'; 

$list_students = [];
$nama_kelas_aktif = "";

if ($filter_exam && $filter_class) {
    $st_cls = $pdo->prepare("SELECT nama_kelas FROM cbt_classes WHERE id = ?");
    $st_cls->execute([$filter_class]);
    $nama_kelas_aktif = $st_cls->fetchColumn();

    $query = "SELECT s.nisn, s.nama_lengkap, k.jenjang, k.nama_kelas, s.sesi 
              FROM cbt_exam_participants p
              JOIN cbt_students s ON p.student_id = s.id
              JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
              WHERE p.exam_id = ? AND COALESCE(p.class_id, s.class_id) = ?";
    
    $params = [$filter_exam, $filter_class];

    if ($filter_sesi !== 'all') {
        $query .= " AND s.sesi = ?";
        $params[] = (int)$filter_sesi;
    }

    $query .= " ORDER BY s.sesi ASC, s.nama_lengkap ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $list_students = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 3) . '/includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 3) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark">Daftar Hadir & Berita Acara</h5>
                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= esc($info_bank['kode_bank_soal']) ?></span>
                        </div>
                        <small class="text-muted"><?= esc($info_bank['nama_mapel']) ?> &bull; <?= esc($info_bank['nama_bank_soal']) ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <!-- Filter Toolbar Card -->
            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0 rounded-4 bg-white">
                <div class="card-body p-2">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <input type="hidden" name="id" value="<?= esc($id_bank) ?>">

                        <div class="col-lg-4 col-md-6">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">1. Pilih Jadwal Ujian</label>
                            <select name="exam_id" class="form-select rounded-3 py-2 border-secondary-subtle" required onchange="this.form.submit()">
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach($exams as $e): ?>
                                    <option value="<?= esc($e['id']) ?>" <?= esc($filter_exam == $e['id'] ? 'selected' : '') ?>>
                                        <?= esc($e['nama_mapel_ujian']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">2. Kelas</label>
                            <select name="class_id" class="form-select rounded-3 py-2 border-secondary-subtle" required onchange="this.form.submit()">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>" <?= esc($filter_class == $c['id'] ? 'selected' : '') ?>>
                                        Kelas <?= esc($c['jenjang']) ?> - <?= esc($c['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">3. Sesi</label>
                            <select name="sesi" class="form-select rounded-3 py-2 border-secondary-subtle" onchange="this.form.submit()">
                                <option value="all" <?= esc($filter_sesi === 'all' ? 'selected' : '') ?>>Semua Sesi</option>
                                <?php foreach ($listSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($filter_sesi == $s['id'] ? 'selected' : '') ?>><?= htmlspecialchars($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary rounded-3 py-2 w-100 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-1">
                                <i class="fas fa-eye"></i> Tampilkan
                            </button>
                            <?php if($filter_exam && $filter_class): ?>
                                <a href="cetak-administrasi.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_class) ?>&sesi=<?= esc($filter_sesi) ?>" 
                                target="_blank" class="btn btn-danger rounded-3 py-2 w-100 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-1">
                                    <i class="fas fa-file-pdf"></i> Cetak PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if($filter_exam && $filter_class): ?>
                <div class="card card-dashboard p-0 shadow-sm border-0 rounded-4 overflow-hidden bg-white mb-4">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-clipboard-user text-primary fs-5"></i>
                            <h6 class="mb-0 fw-bold text-dark">Pratinjau Peserta: <?= esc($nama_kelas_aktif) ?></h6>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fw-semibold">
                            Total: <?= count($list_students) ?> Peserta Terdaftar
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="min-width: 800px;">
                            <thead class="table-light text-secondary small text-uppercase fw-semibold">
                                <tr>
                                    <th class="ps-4" style="width: 60px;">No</th>
                                    <th style="width: 180px;">Nomor Ujian (NISN)</th>
                                    <th>Nama Lengkap Peserta</th>
                                    <th style="width: 150px;">Kelas</th>
                                    <th class="text-center" style="width: 120px;">Sesi</th>
                                    <th class="text-center" style="width: 140px;">Status Adm</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_students)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-user-slash fa-3x mb-3 d-block opacity-25"></i>
                                                Belum ada siswa yang di-setting untuk ujian ini di kelas tersebut.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($list_students as $i => $s): ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?= $i+1 ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= esc($s['nisn']) ?></span>
                                        </td>
                                        <td class="fw-semibold text-dark text-uppercase"><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary"><?= esc($s['jenjang']) ?> - <?= esc($s['nama_kelas']) ?></span></td>
                                        <td class="text-center">
                                            <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-3 py-1 fw-semibold">Sesi <?= esc($s['sesi']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold">
                                                <i class="fas fa-check-circle me-1"></i> Terdaftar
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm border p-5">
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle d-inline-flex mb-3">
                        <i class="fas fa-filter fa-3x"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Pilih Jadwal Ujian dan Kelas</h5>
                    <p class="text-muted small mb-0">Gunakan filter di atas untuk menampilkan daftar hadir dan mencetak dokumen administrasi ujian.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>