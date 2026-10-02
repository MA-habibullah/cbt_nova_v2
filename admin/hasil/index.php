<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

log_activity('Akses halaman Hasil Ujian', null, null, null, 'akses');

// 1. Tangkap Parameter Filter & ID
$filter_exam  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : (isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0);
$id_bank      = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0);
$filter_kelas = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$filter_sesi  = isset($_GET['sesi']) ? $_GET['sesi'] : '';

// Jika id_bank tidak dikirim tapi exam_id ada, lookup bank_soal_id secara otomatis
if ($id_bank <= 0 && $filter_exam > 0) {
    $stmtEx = $pdo->prepare("SELECT bank_soal_id FROM cbt_exams WHERE id = ?");
    $stmtEx->execute([$filter_exam]);
    $id_bank = (int)$stmtEx->fetchColumn();
}

$filter_active = ($filter_exam > 0);

// 2. Ambil Info Bank Soal
$stmt_bank = $pdo->prepare("SELECT b.*, COALESCE(s.nama_mapel, '-') AS nama_mapel FROM cbt_bank_soal b LEFT JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ?");
$stmt_bank->execute([$id_bank]);
$bank = $stmt_bank->fetch();

if (!$bank) {
    header("Location: ../bank-soal/index.php");
    exit;
}

// 4. Ambil Data Master Dropdown
$exams = $pdo->prepare("SELECT id, nama_mapel_ujian FROM cbt_exams WHERE bank_soal_id = ? ORDER BY id DESC");
$exams->execute([$id_bank]);
$listExams = $exams->fetchAll();

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

    // Only show sesi values that exist in that exam's participants
    $stmt_sesi = $pdo->prepare(
        "SELECT DISTINCT cs.id, cs.nama_sesi
         FROM cbt_exam_participants p
         JOIN cbt_students s ON p.student_id = s.id
         JOIN cbt_sesi cs ON s.sesi = cs.id
         WHERE p.exam_id = ?
         ORDER BY cs.id"
    );
    $stmt_sesi->execute([$filter_exam]);
    $listSesi = $stmt_sesi->fetchAll();
} else {
    $classes  = $pdo->query("SELECT id, jenjang, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
    $listSesi = $pdo->query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id")->fetchAll();
}

// 5. Query Utama (Mendapatkan ID Peserta)
$query = "SELECT p.*, s.nama_lengkap, s.nisn, s.sesi, k.jenjang, k.nama_kelas, e.nama_mapel_ujian 
          FROM cbt_exam_participants p
          JOIN cbt_students s ON p.student_id = s.id
          JOIN cbt_exams e ON p.exam_id = e.id
          LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
          WHERE e.bank_soal_id = ?";

$params = [$id_bank];
if ($filter_exam) { $query .= " AND p.exam_id = ?"; $params[] = $filter_exam; }
if ($filter_kelas) { $query .= " AND COALESCE(p.class_id, s.class_id) = ?"; $params[] = $filter_kelas; }
if ($filter_sesi) { $query .= " AND s.sesi = ?"; $params[] = $filter_sesi; }

$query .= " ORDER BY k.jenjang ASC, k.nama_kelas ASC, s.nama_lengkap ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();
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
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="mb-0 fw-bold">Rekapitulasi Hasil Test</h5>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb small mb-0">
                            <li class="breadcrumb-item text-primary"><?= $bank['nama_mapel'] ?></li>
                            <li class="breadcrumb-item active"><?= $bank['nama_bank_soal'] ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" id="filterForm" class="row g-3">
                        <input type="hidden" name="id" value="<?= esc($id_bank) ?>">
                        <div class="col-md-5">
                            <label class="small fw-bold">Pilih Jadwal Ujian</label>
                            <select name="exam_id" id="filterExam" class="form-select" onchange="$('select[name=class_id]').val(''); $('select[name=sesi]').val(''); this.form.submit()">
                                <option value="">-- Semua Ujian di Bank Soal Ini --</option>
                                <?php foreach($listExams as $ex): ?>
                                    <option value="<?= esc($ex['id']) ?>" <?= esc($filter_exam == $ex['id'] ? 'selected' : '') ?>><?= esc($ex['nama_mapel_ujian']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Kelas</label>
                            <select name="class_id" id="filterClass" class="form-select" onchange="this.form.submit()" <?= esc(!$filter_exam ? 'disabled' : '') ?>>
                                <option value=""><?= esc(!$filter_exam ? '-- Pilih jadwal dulu --' : '-- Semua Kelas --') ?></option>
                                <?php foreach($classes as $cl): ?>
                                    <option value="<?= esc($cl['id']) ?>" <?= esc($filter_kelas == $cl['id'] ? 'selected' : '') ?>>Kelas <?= esc($cl['jenjang']) ?> - <?= esc($cl['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Sesi</label>
                            <select name="sesi" id="filterSesi" class="form-select" onchange="this.form.submit()" <?= esc(!$filter_exam ? 'disabled' : '') ?>>
                                <option value=""><?= esc(!$filter_exam ? '-- Pilih jadwal dulu --' : '-- Semua --') ?></option>
                                <?php foreach ($listSesi as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= esc($filter_sesi == $s['id'] ? 'selected' : '') ?>><?= htmlspecialchars($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="index.php?id=<?= esc($id_bank) ?>" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($filter_active): ?>
            <!-- Info Banner Nilai Otomatis -->
            <div class="alert alert-info border-0 shadow-sm rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-3">
                <i class="fas fa-info-circle text-info fs-4 flex-shrink-0"></i>
                <div class="small">
                    <span class="fw-bold">Nilai Otomatis Tersimpan:</span> Nilai peserta ujian otomatis terhitung secara instan saat siswa menekan tombol <em>Selesai Ujian</em>. Anda bisa <strong>langsung mengunduh Excel atau PDF</strong> tanpa perlu menekan tombol <em>Hitung Ulang Nilai</em>.
                    <br><span class="text-muted"><i class="fas fa-lightbulb text-warning me-1"></i>Tombol <strong>Hitung Ulang Nilai</strong> hanya digunakan jika ada revisi kunci jawaban di bank soal, perubahan bobot nilai, atau setelah selesai mengoreksi soal esai.</span>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>Daftar Nilai Siswa</h6>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($filter_exam || $id_bank): ?>
                        <button type="button" class="btn btn-warning btn-sm px-3 fw-bold shadow-sm" id="btnRecalculateBatch" data-exam-id="<?= esc($filter_exam) ?>" data-bank-id="<?= esc($id_bank) ?>" title="Gunakan hanya jika ada revisi kunci jawaban di bank soal atau perubahan bobot">
                            <i class="fas fa-sync-alt me-1"></i> Hitung Ulang Nilai
                        </button>
                        <?php endif; ?>
                        <div class="btn-group">
                            <a href="<?= esc(BASE_URL) ?>admin/hasil/cetak/export-excel.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-success btn-sm px-3">
                                <i class="fas fa-file-excel me-2"></i> Excel
                            </a>
                            <a href="<?= esc(BASE_URL) ?>admin/hasil/cetak/export-pdf.php?id=<?= esc($id_bank) ?>&exam_id=<?= esc($filter_exam) ?>&class_id=<?= esc($filter_kelas) ?>&sesi=<?= esc($filter_sesi) ?>" class="btn btn-danger btn-sm px-3">
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
                                <th class="text-center">Status Koreksi</th>
                                <th class="text-center">Terjawab Benar</th>
                                <th class="text-center">Nilai Obj</th>
                                <th class="text-center">Nilai Esai</th>
                                <th class="text-center">Nilai Akhir (100)</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $n=1; $exam_bobot_cache = []; foreach($results as $r):
                                // --- LOGIKA KALKULASI NILAI ---

                                // 1. Hitung total soal (untuk display)
                                $soal_ids_arr = !empty($r['soal_ids']) ? json_decode($r['soal_ids'], true) : null;
                                if (is_array($soal_ids_arr) && !empty($soal_ids_arr)) {
                                    $total_soal = count($soal_ids_arr);
                                } else {
                                    $stmt_q = $pdo->prepare("SELECT COUNT(*) FROM cbt_exam_questions WHERE exam_id = ?");
                                    $stmt_q->execute([$r['exam_id']]);
                                    $total_soal = (int)$stmt_q->fetchColumn();
                                }

                                // 2. Hitung jumlah soal benar (untuk display)
                                $stmt_ans = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers WHERE participant_id = ? AND skor_didapat > 0");
                                $stmt_ans->execute([$r['id']]);
                                $jml_benar = (int)$stmt_ans->fetchColumn();

                                // 3. Ambil nilai dari kolom DB (dihitung engine scoring)
                                $nilai_obj_row  = (float)($r['nilai_objektif'] ?? 0);
                                $nilai_esai_row = (float)($r['nilai_esai']     ?? 0);

                                // Cache bobot per exam_id untuk komposisi soal & formula 50:50
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

                                // 4. Prioritaskan skor_akhir dari database
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

                            ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $n++ ?></td>
                                <td>
                                    <div class="fw-bold"><?= $r['nama_lengkap'] ?></div>
                                    <small class="text-muted"><?= $r['nisn'] ?></small>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= !empty($r['jenjang']) ? 'Kelas ' . htmlspecialchars($r['jenjang']) . ' - ' : '' ?><?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></span></td>
                                <td class="text-center">
                                    <span class="badge <?= $r['status'] == 'finished' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= strtoupper($r['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (($r['skor_status'] ?? 'final') === 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Belum Final</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Final</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-dark">
                                    <?= $jml_benar ?> / <?= $total_soal ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!$has_obj_r): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <span class="fw-bold"><?= number_format($nilai_obj_row, 2) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!$has_esai_r): ?>
                                        <span class="text-muted">-</span>
                                    <?php elseif (($r['skor_status'] ?? 'final') === 'pending' && $nilai_esai_row == 0): ?>
                                        <span class="text-warning fw-bold">0.00 <small>*</small></span>
                                    <?php else: ?>
                                        <span class="fw-bold"><?= number_format($nilai_esai_row, 2) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <h5 class="fw-bold mb-0 <?= $nilai_akhir >= 75 ? 'text-success' : 'text-danger' ?>">
                                        <?= $nilai_akhir ?>
                                    </h5>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= esc(BASE_URL) ?>admin/hasil/detail.php?p_id=<?= esc($r['id']) ?>&id=<?= esc($id_bank) ?>" class="btn btn-info text-white px-2 shadow-sm" title="Lihat Detail">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        <button type="button" class="btn btn-outline-warning btn-recalc-single px-2 shadow-sm" data-id="<?= esc($r['id']) ?>" data-name="<?= esc($r['nama_lengkap']) ?>" title="Hitung Ulang Nilai Siswa Ini">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-5 bg-white rounded-3 shadow-sm border">
                <i class="fas fa-filter fa-4x text-secondary opacity-25 mb-3 d-block"></i>
                <h5 class="fw-bold text-muted">Pilih jadwal ujian untuk menampilkan rekapitulasi nilai</h5>
                <p class="text-muted small mb-0">Gunakan filter "Pilih Jadwal Ujian" di atas</p>
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
    // Hitung Ulang Massal (Semua Siswa di Jadwal / Bank Soal Ini)
    $('#btnRecalculateBatch').on('click', function() {
        const examId = $(this).data('exam-id');
        const bankId = $(this).data('bank-id');
        if (!examId && !bankId) return;

        Swal.fire({
            title: 'Hitung Ulang Semua Nilai?',
            text: 'Sistem akan mengoreksi dan menghitung ulang nilai seluruh siswa sesuai kunci jawaban & bobot saat ini.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-sync-alt me-1"></i> Ya, Hitung Ulang',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Penilaian...',
                    text: 'Sedang menghitung ulang nilai seluruh siswa.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: '<?= esc(BASE_URL) ?>admin/hasil/ajax/recalculate.php',
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
            title: 'Hitung Ulang Siswa Ini?',
            text: 'Hitung ulang nilai untuk ' + name + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hitung',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: '<?= esc(BASE_URL) ?>admin/hasil/ajax/recalculate.php',
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