<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];


$filter_exam  = $_GET['exam_id']  ?? ($_GET['jadwal_id'] ?? '');
$filter_kelas = $_GET['class_id'] ?? '';
$filter_sesi  = $_GET['sesi']     ?? '';

// Dropdown: semua exam milik guru
$listExams = query(
    "SELECT e.id, e.nama_mapel_ujian, b.id as bank_soal_id, b.nama_bank_soal FROM cbt_exams e
     JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
     WHERE e.teacher_id = ? ORDER BY e.id DESC",
    [$teacher_id]
)->fetchAll();

if ($filter_exam) {
    // Only show classes that have participants in the selected exam (menggunakan snapshot kelas)
    $stmt_cls = $pdo->prepare(
        "SELECT DISTINCT k.id, k.jenjang, k.nama_kelas
         FROM cbt_exam_participants p
         JOIN cbt_students s ON p.student_id = s.id
         JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
         WHERE p.exam_id = ?
         ORDER BY k.jenjang ASC, k.nama_kelas ASC"
    );
    $stmt_cls->execute([$filter_exam]);
    $classes = $stmt_cls->fetchAll();

    $stmt_sesi = $pdo->prepare(
        "SELECT DISTINCT cs.id, cs.nama_sesi
         FROM cbt_exam_participants p
         JOIN cbt_students s ON p.student_id = s.id
         JOIN cbt_sesi cs ON s.sesi = cs.id
         WHERE p.exam_id = ? AND cs.is_aktif = 1
         ORDER BY cs.id"
    );
    $stmt_sesi->execute([$filter_exam]);
    $listSesi = $stmt_sesi->fetchAll();
} else {
    $classes  = query("SELECT id, jenjang, nama_kelas FROM cbt_classes ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
    $listSesi = query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id")->fetchAll();
}

// Derive bank_soal_id dari exam yang dipilih (untuk export PDF)
$filter_bank_id = 0;
if ($filter_exam) {
    foreach ($listExams as $ex) {
        if ($ex['id'] == $filter_exam) { $filter_bank_id = $ex['bank_soal_id']; break; }
    }
}

// Hanya query data jika ada filter aktif
$results = [];
$filter_active = $filter_exam || $filter_kelas || $filter_sesi;

if ($filter_active) {
    // Auto-Finalize Peserta yang Waktu/Jadwal Ujiannya Telah Habis
    auto_finalize_expired_participants($pdo, (int)$filter_exam, (int)$filter_bank_id);

    $sql    = "SELECT p.*, s.nama_lengkap, s.nisn, s.sesi, k.nama_kelas, k.jenjang, e.nama_mapel_ujian, e.id as exam_id, e.jumlah_soal_limit
               FROM cbt_exam_participants p
               JOIN cbt_students s ON p.student_id = s.id
               JOIN cbt_exams e ON p.exam_id = e.id
               LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
               WHERE e.teacher_id = ?";
    $params = [$teacher_id];

    if ($filter_exam)  { $sql .= " AND p.exam_id = ?";   $params[] = $filter_exam; }
    if ($filter_kelas) { $sql .= " AND COALESCE(p.class_id, s.class_id) = ?";  $params[] = $filter_kelas; }
    if ($filter_sesi)  { $sql .= " AND s.sesi = ?";      $params[] = $filter_sesi; }

    $sql .= " ORDER BY k.jenjang ASC, k.nama_kelas ASC, s.nama_lengkap ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<style>
    .navbar { z-index: 1030; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border shadow-sm me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold text-primary">Hasil Test</h5>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" id="filterForm" class="row g-3">
                        <div class="col-md-5">
                            <label class="small fw-bold">Pilih Jadwal Ujian</label>
                            <select name="exam_id" id="filterExam" class="form-select" onchange="$('#filterClass').val(''); $('#filterSesi').val(''); this.form.submit()">
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach ($listExams as $ex): ?>
                                    <option value="<?= esc($ex['id']) ?>" <?= esc($filter_exam == $ex['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($ex['nama_mapel_ujian']) ?>
                                        (<?= htmlspecialchars($ex['nama_bank_soal']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Kelas</label>
                            <select name="class_id" id="filterClass" class="form-select" onchange="this.form.submit()" <?= !$filter_exam ? 'disabled' : '' ?>>
                                <option value=""><?= esc(!$filter_exam ? '-- Pilih jadwal dulu --' : '-- Semua Kelas --') ?></option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?= esc($cl['id']) ?>" <?= esc($filter_kelas == $cl['id'] ? 'selected' : '') ?>>
                                        Kelas <?= esc($cl['jenjang']) ?> - <?= htmlspecialchars($cl['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Sesi</label>
                            <select name="sesi" id="filterSesi" class="form-select" onchange="this.form.submit()" <?= !$filter_exam ? 'disabled' : '' ?>>
                                <option value=""><?= esc(!$filter_exam ? '-- Pilih jadwal dulu --' : '-- Semua --') ?></option>
                                <?php foreach ($listSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($filter_sesi == $s['id'] ? 'selected' : '') ?>><?= htmlspecialchars($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="index.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$filter_active): ?>
                <!-- State awal: belum ada filter -->
                <div class="text-center py-5 bg-white rounded-3 shadow-sm border">
                    <i class="fas fa-filter fa-4x text-secondary opacity-25 mb-3 d-block"></i>
                    <h5 class="fw-bold text-muted">Gunakan filter di atas untuk menampilkan hasil ujian</h5>
                    <p class="text-muted small mb-0">Pilih minimal satu ujian, kelas, atau sesi terlebih dahulu</p>
                </div>
            <?php else: ?>
            <!-- Info Banner Nilai Otomatis -->
            <div class="alert alert-info border-0 shadow-sm rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-3">
                <i class="fas fa-info-circle text-info fs-4 flex-shrink-0"></i>
                <div class="small">
                    <span class="fw-bold">Nilai Otomatis Tersimpan:</span> Nilai peserta ujian otomatis terhitung secara instan saat siswa menekan tombol <em>Selesai Ujian</em>. Anda bisa <strong>langsung mengunduh Excel atau PDF</strong> tanpa perlu menekan tombol <em>Hitung Ulang Nilai</em>.
                    <br><span class="text-muted"><i class="fas fa-lightbulb text-warning me-1"></i>Tombol <strong>Hitung Ulang Nilai</strong> hanya digunakan jika ada revisi kunci jawaban di bank soal, perubahan bobot nilai, atau setelah selesai mengoreksi soal esai.</span>
                </div>
            </div>

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
                        <button type="button" class="btn btn-warning btn-sm px-3 fw-bold shadow-sm" id="btnRecalculateBatch" data-exam-id="<?= esc($filter_exam) ?>" data-bank-id="<?= esc($filter_bank_id) ?>" title="Gunakan hanya jika ada revisi kunci jawaban di bank soal atau perubahan bobot">
                            <i class="fas fa-sync-alt me-1"></i> Hitung Ulang Nilai
                        </button>
                        <?php endif; ?>
                            <div class="btn-group">
                                <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-excel.php?exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-success btn-sm px-3">
                                    <i class="fas fa-file-excel me-2"></i> Excel
                                </a>
                                <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-pdf.php?exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-danger btn-sm px-3">
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
                                    <th>Ujian</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Status Koreksi</th>
                                    <th class="text-center">Terjawab Benar</th>
                                    <th class="text-center">Nilai Obj</th>
                                    <th class="text-center">Nilai Esai</th>
                                    <th class="text-center">Nilai Akhir (100)</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$results): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted">Tidak ada data untuk filter ini.</td></tr>
                                <?php else: $n = 1; $exam_bobot_cache = []; foreach ($results as $r):
                                    // Total soal & jumlah benar untuk display
                                    $soal_ids_arr = !empty($r['soal_ids']) ? json_decode($r['soal_ids'], true) : null;
                                    if (is_array($soal_ids_arr) && !empty($soal_ids_arr)) {
                                        $total_soal = count($soal_ids_arr);
                                    } elseif (!empty($r['jumlah_soal_limit']) && (int)$r['jumlah_soal_limit'] > 0) {
                                        $total_soal = (int)$r['jumlah_soal_limit'];
                                    } else {
                                        if (!isset($exam_q_count_cache[$r['exam_id']])) {
                                            $stmt_q = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_questions WHERE exam_id = ?");
                                            $stmt_q->execute([$r['exam_id']]);
                                            $exam_q_count_cache[$r['exam_id']] = (int)$stmt_q->fetchColumn();
                                        }
                                        $total_soal = $exam_q_count_cache[$r['exam_id']];
                                    }

                                    $stmt_ans = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers WHERE participant_id = ? AND skor_didapat > 0");
                                    $stmt_ans->execute([$r['id']]);
                                    $jml_benar = (int)$stmt_ans->fetchColumn();

                                    // Ambil nilai dari kolom DB (dihitung engine scoring)
                                    $nilai_obj_row  = (float)($r['nilai_objektif'] ?? 0);
                                    $nilai_esai_row = (float)($r['nilai_esai']     ?? 0);

                                    // Cache bobot per exam_id untuk informasi komposisi soal & formula 50:50
                                    if (!isset($exam_bobot_cache[$r['exam_id']])) {
                                        $stmtEB = $pdo->prepare("
                                            SELECT
                                                SUM(CASE WHEN q.tipe != 'essay' THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
                                                SUM(CASE WHEN q.tipe  = 'essay' THEN q.bobot_skor ELSE 0 END) AS bobot_essay
                                            FROM cbt_exam_questions eq
                                            JOIN cbt_questions q ON eq.question_id = q.id
                                            WHERE eq.exam_id = ?
                                        ");
                                        $stmtEB->execute([$r['exam_id']]);
                                        $exam_bobot_cache[$r['exam_id']] = $stmtEB->fetch() ?: ['bobot_obj' => 0, 'bobot_essay' => 0];
                                    }
                                    $eb = $exam_bobot_cache[$r['exam_id']];
                                    $has_obj_r  = (float)($eb['bobot_obj']   ?? 0) > 0;
                                    $has_esai_r = (float)($eb['bobot_essay'] ?? 0) > 0;

                                    // Prioritaskan skor_akhir dari database
                                    if (isset($r['skor_akhir']) && $r['skor_akhir'] !== null) {
                                        $nilai_akhir = (float)$r['skor_akhir'];
                                    } else {
                                        if ($has_obj_r && $has_esai_r) {
                                            $nilai_akhir = round(($nilai_obj_row * 0.5) + ($nilai_esai_row * 0.5), 2);
                                        } elseif ($has_obj_r) {
                                            $nilai_akhir = $nilai_obj_row;
                                        } elseif ($has_esai_r) {
                                            $nilai_akhir = $nilai_esai_row;
                                        } else {
                                            $nilai_akhir = 0.0;
                                        }
                                    }

                                    $status_p = $r['status'] ?? 'ready';
                                ?>
                                <tr>
                                    <td class="ps-4 text-muted"><?= $n++ ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($r['nisn']) ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-secondary"><?= !empty($r['jenjang']) ? 'Kelas ' . htmlspecialchars($r['jenjang']) . ' - ' : '' ?><?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></span></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($r['nama_mapel_ujian']) ?></small></td>
                                    <td class="text-center">
                                        <?php if ($status_p === 'finished'): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="fas fa-check-circle me-1"></i>SELESAI</span>
                                        <?php elseif ($status_p === 'working'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-1"><i class="fas fa-spinner fa-spin me-1"></i>MENGERJAKAN</span>
                                        <?php elseif ($status_p === 'blocked'): ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1"><i class="fas fa-lock me-1"></i>TERKUNCI</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1"><i class="fas fa-user-clock me-1"></i>BELUM UJIAN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status_p === 'ready'): ?>
                                            <span class="text-muted">-</span>
                                        <?php elseif (($r['skor_status'] ?? 'final') === 'pending'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Belum Final</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Final</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold text-dark">
                                        <?php if ($status_p === 'ready'): ?>
                                            <span class="text-muted">-</span>
                                        <?php else: ?>
                                            <?= $jml_benar ?> / <?= $total_soal ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status_p === 'ready' || !$has_obj_r): ?>
                                            <span class="text-muted">-</span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= number_format($nilai_obj_row, 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status_p === 'ready' || !$has_esai_r): ?>
                                            <span class="text-muted">-</span>
                                        <?php elseif (($r['skor_status'] ?? 'final') === 'pending' && $nilai_esai_row == 0): ?>
                                            <span class="text-warning fw-bold">0.00 <small>*</small></span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= number_format($nilai_esai_row, 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status_p === 'ready'): ?>
                                            <span class="badge bg-secondary-subtle text-secondary py-2 px-3 fw-semibold">Belum Mengerjakan</span>
                                        <?php elseif ($status_p === 'working'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis py-2 px-3 fw-semibold"><i class="fas fa-clock me-1"></i>Sedang Mengerjakan</span>
                                        <?php else: ?>
                                            <h5 class="fw-bold mb-0 <?= $nilai_akhir >= 75 ? 'text-success' : 'text-danger' ?>">
                                                <?= number_format($nilai_akhir, 2) ?>
                                            </h5>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?p_id=<?= esc($r['id']) ?>" class="btn btn-info text-white px-2 shadow-sm" title="Lihat Detail">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                            <?php if ($status_p === 'finished'): ?>
                                            <button type="button" class="btn btn-outline-warning btn-recalc-single px-2 shadow-sm" data-id="<?= esc($r['id']) ?>" data-name="<?= esc($r['nama_lengkap']) ?>" title="Hitung Ulang Nilai Siswa Ini">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $("#menu-toggle").click(function(e){ e.preventDefault(); $("#wrapper").toggleClass("toggled"); });

    // Hitung Ulang Massal
    $('#btnRecalculateBatch').on('click', function() {
        const examId = $(this).data('exam-id');
        const bankId = $(this).data('bank-id');
        if (!examId && !bankId) return;

        Swal.fire({
            title: '<span class="text-primary fw-bold fs-4"><i class="fas fa-info-circle me-2"></i>Informasi Hitung Ulang Nilai</span>',
            html: `
                <div class="text-start small text-secondary mt-2">
                    <div class="alert alert-warning border-0 py-2 px-3 mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-lightbulb text-warning fs-5 flex-shrink-0"></i>
                        <div><strong>Kapan fitur ini digunakan?</strong> Gunakan jika Anda baru saja <u>mengubah kunci jawaban</u> di bank soal, <u>mengubah bobot nilai</u>, atau <u>selesai mengoreksi soal esai</u>.</div>
                    </div>
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-cog me-1 text-primary"></i>Apa yang akan dilakukan sistem?</h6>
                    <ul class="ps-3 mb-3">
                        <li class="mb-1">Sistem membaca kunci jawaban dan bobot soal terkini dari database.</li>
                        <li class="mb-1">Mencocokkan ulang seluruh jawaban siswa yang sudah menyelesaikan ujian (<span class="badge bg-success-subtle text-success">SELESAI</span>).</li>
                        <li class="mb-1">Menghitung ulang persentase nilai objektif, esai, dan skor akhir secara otomatis.</li>
                    </ul>
                    <div class="bg-light p-2 rounded border small mb-3 text-dark">
                        <i class="fas fa-shield-alt text-success me-1"></i><strong>Proteksi Siswa Aktif:</strong> Siswa yang berstatus <span class="badge bg-secondary-subtle text-secondary">BELUM UJIAN</span> atau <span class="badge bg-warning-subtle text-warning-emphasis">MENGERJAKAN</span> <u>tidak akan terganggu</u> dan tidak akan dikunci.
                    </div>
                    <p class="mb-0 text-center text-muted fst-italic">Apakah Anda yakin ingin melanjutkan proses hitung ulang nilai?</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-sync-alt me-1"></i> Ya, Lanjutkan Hitung Ulang',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-4 shadow'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Penilaian...',
                    text: 'Sedang menghitung ulang nilai seluruh siswa yang sudah selesai.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: '<?= esc(BASE_URL) ?>guru/hasil/ajax/recalculate.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { exam_id: examId, bank_soal_id: bankId, include_working: false },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: res.message,
                                confirmButtonColor: '#3b82f6'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message || 'Terjadi kesalahan saat menghitung ulang.', 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Gagal terhubung ke server: ' + xhr.statusText, 'error');
                    }
                });
            }
        });
    });

    // Hitung Ulang Individual (Per-Siswa)
    $('.btn-recalc-single').on('click', function() {
        const pId = $(this).data('id');
        const name = $(this).data('name');
        if (!pId) return;

        Swal.fire({
            title: '<span class="text-primary fw-bold fs-5"><i class="fas fa-user-edit me-2"></i>Hitung Ulang Nilai Siswa</span>',
            html: `
                <div class="text-start small text-secondary mt-2">
                    <p class="mb-2">Hitung ulang penilaian untuk siswa: <strong class="text-dark">${name}</strong>?</p>
                    <div class="alert alert-info border-0 py-2 px-3 mb-2 small">
                        <i class="fas fa-info-circle me-1"></i>Sistem akan mencocokkan ulang jawaban siswa ini dengan kunci jawaban dan bobot soal terbaru saat ini.
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-sync-alt me-1"></i> Ya, Hitung Ulang',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-4 shadow'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Penilaian...',
                    text: 'Sedang menghitung ulang nilai siswa...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: '<?= esc(BASE_URL) ?>guru/hasil/ajax/recalculate.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { participant_id: pId },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: 'Nilai akhir baru: ' + res.data.nilai_akhir,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>
