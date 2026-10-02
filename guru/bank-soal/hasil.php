<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];
$id_bank    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil bank soal — harus milik guru ini
$stmt_bank = $pdo->prepare("SELECT b.*, s.nama_mapel FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ? AND b.teacher_id = ?");
$stmt_bank->execute([$id_bank, $teacher_id]);
$bank = $stmt_bank->fetch();
if (!$bank) { header("Location: index.php"); exit; }

$filter_exam  = $_GET['exam_id']  ?? '';
$filter_kelas = $_GET['class_id'] ?? '';
$filter_sesi  = $_GET['sesi']     ?? '';

$listExams = $pdo->prepare("SELECT id, nama_mapel_ujian FROM cbt_exams WHERE bank_soal_id = ? AND teacher_id = ? ORDER BY id DESC");
$listExams->execute([$id_bank, $teacher_id]);
$listExams = $listExams->fetchAll();

$classes  = $pdo->query("SELECT id, nama_kelas FROM cbt_classes ORDER BY nama_kelas ASC")->fetchAll();
$listSesi = $pdo->query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id")->fetchAll();

// Hanya query data jika ada filter aktif
$results       = [];
$filter_active = $filter_exam || $filter_kelas || $filter_sesi;

if ($filter_active) {
    $query  = "SELECT p.*, s.nama_lengkap, s.nisn, s.sesi, k.nama_kelas, e.nama_mapel_ujian, e.id as exam_id
               FROM cbt_exam_participants p
               JOIN cbt_students s ON p.student_id = s.id
               JOIN cbt_exams e ON p.exam_id = e.id
               LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
               WHERE e.bank_soal_id = ? AND e.teacher_id = ?";
    $params = [$id_bank, $teacher_id];

    if ($filter_exam)  { $query .= " AND p.exam_id = ?";  $params[] = $filter_exam; }
    if ($filter_kelas) { $query .= " AND COALESCE(p.class_id, s.class_id) = ?"; $params[] = $filter_kelas; }
    if ($filter_sesi)  { $query .= " AND s.sesi = ?";     $params[] = $filter_sesi; }

    $query .= " ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<style>
    .navbar { z-index: 1030; }
    .breadcrumb-item + .breadcrumb-item::before { content: "|"; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="mb-0 fw-bold">Rekapitulasi Hasil Test</h5>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb small mb-0">
                            <li class="breadcrumb-item text-primary"><?= htmlspecialchars($bank['nama_mapel']) ?></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($bank['nama_bank_soal']) ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= esc($id_bank) ?>">
                        <div class="col-md-4">
                            <label class="small fw-bold">Pilih Jadwal Ujian</label>
                            <select name="exam_id" class="form-select">
                                <option value="">-- Semua Ujian di Bank Soal Ini --</option>
                                <?php foreach ($listExams as $ex): ?>
                                    <option value="<?= esc($ex['id']) ?>" <?= esc($filter_exam == $ex['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($ex['nama_mapel_ujian']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Kelas</label>
                            <select name="class_id" class="form-select">
                                <option value="">-- Semua Kelas --</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?= esc($cl['id']) ?>" <?= esc($filter_kelas == $cl['id'] ? 'selected' : '') ?>><?= htmlspecialchars($cl['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Sesi</label>
                            <select name="sesi" class="form-select">
                                <option value="">-- Semua --</option>
                                <?php foreach ($listSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($filter_sesi == $s['id'] ? 'selected' : '') ?>><?= htmlspecialchars($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <a href="hasil.php?id=<?= esc($id_bank) ?>" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$filter_active): ?>
                <div class="text-center py-5 bg-white rounded-3 shadow-sm border">
                    <i class="fas fa-filter fa-4x text-secondary opacity-25 mb-3 d-block"></i>
                    <h5 class="fw-bold text-muted">Gunakan filter di atas untuk menampilkan hasil ujian</h5>
                    <p class="text-muted small mb-0">Pilih minimal satu ujian, kelas, atau sesi terlebih dahulu</p>
                </div>
            <?php else: ?>
            <!-- Tabel Hasil -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>Daftar Nilai Siswa</h6>
                        <?php if (!$filter_exam): ?>
                            <small class="text-danger">*Pilih Jadwal Ujian untuk melihat Analisis Soal &amp; Jawaban</small>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($filter_exam): ?>
                            <div class="btn-group">
                                <a href="<?= esc(BASE_URL) ?>admin/hasil/analisis-soal.php?exam_id=<?= esc($filter_exam) ?>" class="btn btn-primary btn-sm px-3">
                                    <i class="fas fa-chart-line me-2"></i> Analisis Soal
                                </a>
                                <a href="<?= esc(BASE_URL) ?>admin/hasil/analisis-jawaban.php?exam_id=<?= esc($filter_exam) ?>" class="btn btn-info btn-sm px-3 text-white">
                                    <i class="fas fa-chart-pie me-2"></i> Analisis Jawaban
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="btn-group">
                            <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-excel.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-success btn-sm px-3">
                                <i class="fas fa-file-excel me-2"></i> Excel
                            </a>
                            <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-pdf.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-danger btn-sm px-3">
                                <i class="fas fa-file-pdf me-2"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Jawaban Benar</th>
                                <th class="text-center">Nilai Obj</th>
                                <th class="text-center">Nilai Esai</th>
                                <th class="text-center">Nilai Akhir (100)</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$results): ?>
                                <tr><td colspan="9" class="text-center py-5 text-muted">Belum ada data hasil ujian.</td></tr>
                            <?php else: $n = 1; foreach ($results as $r):
                                $soal_ids_arr = !empty($r['soal_ids']) ? json_decode($r['soal_ids'], true) : null;
                                if (is_array($soal_ids_arr) && !empty($soal_ids_arr)) {
                                    $total_soal = count($soal_ids_arr);
                                } else {
                                    $stmt_q = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_questions WHERE exam_id = ?");
                                    $stmt_q->execute([$r['exam_id']]);
                                    $total_soal = (int)$stmt_q->fetchColumn();
                                }

                                $stmt_ans = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers WHERE participant_id = ? AND skor_didapat > 0");
                                $stmt_ans->execute([$r['id']]);
                                $jml_benar = (int)$stmt_ans->fetchColumn();

                                $nilai_obj_row  = (float)($r['nilai_objektif'] ?? 0);
                                $nilai_esai_row = (float)($r['nilai_esai']     ?? 0);
                                $nilai_akhir    = (isset($r['skor_akhir']) && $r['skor_akhir'] !== null)
                                    ? (float)$r['skor_akhir']
                                    : ($total_soal > 0 ? round(($jml_benar / $total_soal) * 100, 2) : 0);
                            ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $n++ ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($r['nisn']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></span></td>
                                <td class="text-center">
                                    <span class="badge <?= $r['status'] == 'finished' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= strtoupper($r['status']) ?>
                                    </span>
                                    <?php if (($r['skor_status'] ?? '') === 'pending'): ?>
                                        <span class="badge bg-warning text-dark d-block mt-1" style="font-size:0.7rem;">Esai Belum Dinilai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-dark"><?= $jml_benar ?> / <?= $total_soal ?></td>
                                <td class="text-center fw-semibold text-primary"><?= number_format($nilai_obj_row, 2) ?></td>
                                <td class="text-center fw-semibold <?= $nilai_esai_row > 0 ? 'text-success' : 'text-muted' ?>"><?= number_format($nilai_esai_row, 2) ?></td>
                                <td class="text-center">
                                    <h5 class="fw-bold mb-0 <?= $nilai_akhir >= 75 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($nilai_akhir, 2) ?>
                                    </h5>
                                </td>
                                <td class="text-center">
                                    <a href="<?= esc(BASE_URL) ?>guru/hasil/detail.php?p_id=<?= esc($r['id']) ?>&bank_id=<?= esc($id_bank) ?>" class="btn btn-info btn-sm text-white px-3 shadow-sm">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e){ e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>
