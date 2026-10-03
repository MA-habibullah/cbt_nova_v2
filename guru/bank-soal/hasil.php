<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

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

$classes  = $pdo->query("SELECT id, jenjang, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
$listSesi = $pdo->query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id")->fetchAll();

$results       = [];
$filter_active = $filter_exam || $filter_kelas || $filter_sesi;

if ($filter_active) {
    $query  = "SELECT p.*, s.nama_lengkap, s.nisn, s.sesi, k.jenjang, k.nama_kelas, e.nama_mapel_ujian, e.id as exam_id
               FROM cbt_exam_participants p
               JOIN cbt_students s ON p.student_id = s.id
               JOIN cbt_exams e ON p.exam_id = e.id
               LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
               WHERE e.bank_soal_id = ? AND e.teacher_id = ?";
    $params = [$id_bank, $teacher_id];

    if ($filter_exam)  { $query .= " AND p.exam_id = ?";  $params[] = $filter_exam; }
    if ($filter_kelas) { $query .= " AND COALESCE(p.class_id, s.class_id) = ?"; $params[] = $filter_kelas; }
    if ($filter_sesi)  { $query .= " AND s.sesi = ?";     $params[] = $filter_sesi; }

    $query .= " ORDER BY k.jenjang ASC, k.nama_kelas ASC, s.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<style>
    .navbar { z-index: 1030; }
    .table-responsive {
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
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
    <?php include dirname(__DIR__, 2) . '/guru/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark">Rekapitulasi Hasil Test (Guru)</h5>
                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= esc($bank['kode_bank_soal']) ?></span>
                        </div>
                        <small class="text-muted"><?= esc($bank['nama_mapel']) ?> &bull; <?= esc($bank['nama_bank_soal']) ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <!-- Filter Toolbar Card -->
            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0 rounded-4 bg-white">
                <div class="card-body p-2">
                    <form method="GET" id="filterForm" class="row g-3 align-items-end">
                        <input type="hidden" name="id" value="<?= esc($id_bank) ?>">
                        <div class="col-lg-5 col-md-6">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">Pilih Jadwal Ujian</label>
                            <select name="exam_id" id="filterExam" class="form-select rounded-3 py-2 border-secondary-subtle" onchange="$('select[name=class_id]').val(''); $('select[name=sesi]').val(''); this.form.submit()">
                                <option value="">-- Semua Ujian di Bank Soal Ini --</option>
                                <?php foreach ($listExams as $ex): ?>
                                    <option value="<?= esc($ex['id']) ?>" <?= esc($filter_exam == $ex['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($ex['nama_mapel_ujian']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">Kelas Target</label>
                            <select name="class_id" id="filterClass" class="form-select rounded-3 py-2 border-secondary-subtle" onchange="this.form.submit()">
                                <option value="">-- Semua Kelas --</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?= esc($cl['id']) ?>" <?= esc($filter_kelas == $cl['id'] ? 'selected' : '') ?>>
                                        Kelas <?= esc($cl['jenjang']) ?> - <?= htmlspecialchars($cl['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">Sesi Ujian</label>
                            <select name="sesi" id="filterSesi" class="form-select rounded-3 py-2 border-secondary-subtle" onchange="this.form.submit()">
                                <option value="">-- Semua --</option>
                                <?php foreach ($listSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($filter_sesi == $s['id'] ? 'selected' : '') ?>><?= htmlspecialchars($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-12 d-flex align-items-end">
                            <a href="hasil.php?id=<?= esc($id_bank) ?>" class="btn btn-outline-secondary rounded-3 py-2 w-100 fw-semibold d-flex align-items-center justify-content-center gap-1">
                                <i class="fas fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$filter_active): ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm border p-5">
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle d-inline-flex mb-3">
                        <i class="fas fa-filter fa-3x"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Gunakan Filter di Atas</h5>
                    <p class="text-muted small mb-0">Pilih jadwal ujian atau kelas binaan Anda untuk menampilkan lembar nilai siswa.</p>
                </div>
            <?php else: ?>
            <!-- Tabel Hasil -->
            <div class="card card-dashboard p-0 shadow-sm border-0 rounded-4 overflow-hidden bg-white mb-4">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-list-check text-primary fs-5"></i>
                        <h6 class="mb-0 fw-bold text-dark">Daftar Nilai Siswa</h6>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fw-semibold ms-2">
                            Total: <?= count($results) ?> Peserta
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($filter_exam): ?>
                            <div class="btn-group shadow-sm">
                                <a href="<?= esc(BASE_URL) ?>admin/hasil/analisis-soal.php?exam_id=<?= esc($filter_exam) ?>" class="btn btn-primary rounded-start-3 btn-sm px-3 fw-semibold d-flex align-items-center gap-1">
                                    <i class="fas fa-chart-line"></i> Analisis Soal
                                </a>
                                <a href="<?= esc(BASE_URL) ?>admin/hasil/analisis-jawaban.php?exam_id=<?= esc($filter_exam) ?>" class="btn btn-info rounded-end-3 btn-sm px-3 text-white fw-semibold d-flex align-items-center gap-1">
                                    <i class="fas fa-chart-pie"></i> Analisis Jawaban
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="btn-group shadow-sm">
                            <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-excel.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-success rounded-start-3 btn-sm px-3 fw-semibold d-flex align-items-center gap-1">
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                            <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-pdf.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-danger rounded-end-3 btn-sm px-3 fw-semibold d-flex align-items-center gap-1">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="min-width: 1000px;">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold">
                            <tr>
                                <th class="ps-4" style="width: 50px;">No</th>
                                <th>Nama Lengkap & NISN</th>
                                <th>Kelas</th>
                                <th class="text-center">Status Ujian</th>
                                <th class="text-center">Jawaban Benar</th>
                                <th class="text-center">Nilai Obj</th>
                                <th class="text-center">Nilai Esai</th>
                                <th class="text-center">Nilai Akhir</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$results): ?>
                                <tr><td colspan="9" class="text-center py-5 text-muted">Belum ada data pengerjaan ujian pada filter ini.</td></tr>
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
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                                    <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= htmlspecialchars($r['nisn']) ?></span>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= !empty($r['jenjang']) ? 'Kelas ' . htmlspecialchars($r['jenjang']) . ' - ' : '' ?><?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></span></td>
                                <td class="text-center">
                                    <span class="badge <?= $r['status'] == 'finished' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis' ?> rounded-pill px-3 py-1 fw-semibold">
                                        <?= strtoupper($r['status']) ?>
                                    </span>
                                    <?php if (($r['skor_status'] ?? '') === 'pending'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis d-block mt-1" style="font-size:0.7rem;">Esai Belum Dinilai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-dark"><?= $jml_benar ?> / <?= $total_soal ?></td>
                                <td class="text-center fw-semibold text-primary"><?= number_format($nilai_obj_row, 2) ?></td>
                                <td class="text-center fw-semibold <?= $nilai_esai_row > 0 ? 'text-success' : 'text-muted' ?>"><?= number_format($nilai_esai_row, 2) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $nilai_akhir >= 75 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> font-monospace fs-6 fw-bold px-3 py-1">
                                        <?= number_format($nilai_akhir, 2) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?= esc(BASE_URL) ?>guru/hasil/detail.php?p_id=<?= esc($r['id']) ?>&bank_id=<?= esc($id_bank) ?>" class="btn btn-outline-primary rounded-3 btn-sm px-3 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-1">
                                        <i class="fas fa-eye"></i> Detail
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
