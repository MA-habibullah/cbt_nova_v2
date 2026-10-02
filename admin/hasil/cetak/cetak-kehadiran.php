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
$stmt_info = $pdo->prepare("SELECT b.nama_bank_soal, s.nama_mapel 
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
    // Ambil nama kelas
    $st_cls = $pdo->prepare("SELECT nama_kelas FROM cbt_classes WHERE id = ?");
    $st_cls->execute([$filter_class]);
    $nama_kelas_aktif = $st_cls->fetchColumn();

    /**
     * PERBAIKAN LOGIKA: 
     * Kita JOIN ke cbt_exam_participants agar hanya siswa yang SUDAH DI-SETTING 
     * di 'Kelola Peserta' yang muncul di daftar hadir.
     */
    $query = "SELECT s.nisn, s.nama_lengkap, k.jenjang, k.nama_kelas, s.sesi 
              FROM cbt_exam_participants p
              JOIN cbt_students s ON p.student_id = s.id
              JOIN cbt_classes k ON s.class_id = k.id
              WHERE p.exam_id = ? AND s.class_id = ?";
    
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
    <?php include '../../../includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3 shadow-sm">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Daftar Hadir & Berita Acara</h5>
                    <small class="text-muted"><?= $info_bank['nama_mapel'] ?> - <?= $info_bank['nama_bank_soal'] ?></small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="id" value="<?= esc($id_bank) ?>">

                        <div class="col-md-4">
                            <label class="small fw-bold text-uppercase">1. Pilih Ujian / Test</label>
                            <select name="exam_id" class="form-select" required onchange="this.form.submit()">
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach($exams as $e): ?>
                                    <option value="<?= esc($e['id']) ?>" <?= esc($filter_exam == $e['id'] ? 'selected' : '') ?>>
                                        <?= $e['nama_mapel_ujian'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-uppercase">2. Kelas</label>
                            <select name="class_id" class="form-select" required onchange="this.form.submit()">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>" <?= esc($filter_class == $c['id'] ? 'selected' : '') ?>>
                                        <?= $c['jenjang'] ?> - <?= $c['nama_kelas'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold text-uppercase">3. Sesi</label>
                            <select name="sesi" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= esc($filter_sesi === 'all' ? 'selected' : '') ?>>Semua Sesi</option>
                                <?php foreach ($listSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($filter_sesi == $s['id'] ? 'selected' : '') ?>><?= htmlspecialchars($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                                <i class="fas fa-eye me-2"></i> Preview
                            </button>
                            <?php if($filter_exam && $filter_class): ?>
                                <a href="cetak-administrasi.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_class) ?>&sesi=<?= esc($filter_sesi) ?>" 
                                target="_blank" class="btn btn-danger w-100 fw-bold shadow-sm">
                                    <i class="fas fa-file-pdf me-2"></i> Cetak PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if($filter_exam && $filter_class): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-muted">
                            <i class="fas fa-list me-2 text-primary"></i>Preview Peserta: <?= $nama_kelas_aktif ?>
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                            Total: <?= count($list_students) ?> Peserta Terdaftar
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" width="5%">No</th>
                                    <th width="15%">Username (Nomer Ujian)</th>
                                    <th>Nama Lengkap Peserta</th>
                                    <th width="15%">Kelas</th>
                                    <th width="10%" class="text-center">Sesi</th>
                                    <th class="text-center" width="15%">Status Adm</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_students)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-user-slash fa-3x mb-3 d-block"></i>
                                                Belum ada siswa yang di-setting untuk ujian ini di kelas tersebut.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($list_students as $i => $s): ?>
                                    <tr>
                                        <td class="text-center"><?= $i+1 ?></td>
                                        <td class="fw-bold text-primary"><?= $s['nisn'] ?></td>
                                        <td class="text-uppercase"><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                                        <td><?= $s['jenjang'] ?> - <?= $s['nama_kelas'] ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">Sesi <?= $s['sesi'] ?></span>
                                        </td>
                                        <td class="text-center small">
                                            <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Terdaftar</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded shadow-sm border">
                    <i class="fas fa-filter fa-4x text-light mb-3"></i>
                    <h5 class="text-muted">Gunakan filter di atas untuk menampilkan data peserta yang sudah di-setting.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>