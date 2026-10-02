<?php
require_once '../../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo '<div class="alert alert-danger m-4">Sesi tidak valid.</div>';
    exit;
}

$exam_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = $_SESSION['student_id'];

$stmt = $pdo->prepare("
    SELECT e.*, s.nama_mapel, b.nama_bank_soal,
    (SELECT COUNT(*) FROM cbt_questions WHERE bank_soal_id = e.bank_soal_id) as total_soal
    FROM cbt_exams e
    JOIN cbt_subjects s ON e.subject_id = s.id
    JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
    WHERE e.id = ? AND e.status = 'aktif'
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    echo '<script>loadView("dashboard");</script>';
    exit;
}

$stmtCheck = $pdo->prepare("SELECT * FROM cbt_exam_participants WHERE exam_id = ? AND student_id = ?");
$stmtCheck->execute([$exam_id, $student_id]);
$participant = $stmtCheck->fetch();

if ($participant && $participant['status'] === 'finished') {
    echo '<script>loadView("dashboard");</script>';
    exit;
}

$display_total_soal = (int)$exam['total_soal'];
if (!empty($participant['soal_ids'])) {
    $decoded_sids = json_decode($participant['soal_ids'], true);
    if (is_array($decoded_sids) && !empty($decoded_sids)) {
        $display_total_soal = count($decoded_sids);
    }
} elseif (!empty($exam['jumlah_soal_limit']) && (int)$exam['jumlah_soal_limit'] > 0) {
    $display_total_soal = min((int)$exam['jumlah_soal_limit'], (int)$exam['total_soal']);
}
?>

<div class="container py-3 py-md-5 px-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark h4-mobile">Konfirmasi Data Ujian</h3>
                <p class="text-muted small">Bacalah informasi berikut dengan teliti sebelum memulai.</p>
            </div>

            <div class="card card-confirm shadow-sm p-3 p-md-4 mb-4">
                <div class="row g-3 g-md-4">
                    <div class="col-6 col-md-6">
                        <div class="mb-3">
                            <label class="info-label d-block">Mata Pelajaran</label>
                            <span class="info-value"><?= htmlspecialchars($exam['nama_mapel']) ?></span>
                        </div>
                        <div class="mb-3 mb-md-0">
                            <label class="info-label d-block">Jumlah Soal</label>
                            <span class="info-value"><?= esc($display_total_soal) ?> Butir</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-6">
                        <div class="mb-3">
                            <label class="info-label d-block">Durasi Ujian</label>
                            <span class="info-value"><?= esc($exam['durasi_menit']) ?> Menit</span>
                        </div>
                        <div class="mb-0">
                            <label class="info-label d-block">Status Pengerjaan</label>
                            <span class="info-value text-uppercase">
                                <?= $participant ? ($participant['status'] === 'working' ? 'Sedang Berjalan' : htmlspecialchars($participant['status'])) : 'Siap Mulai' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <hr class="my-3 my-md-4">

                <div class="alert alert-warning-custom p-3 rounded-3 mb-4">
                    <div class="d-flex align-items-start gap-2 gap-md-3">
                        <i class="fas fa-exclamation-triangle warning-icon flex-shrink-0 mt-1 mt-md-0"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Peringatan Penting!</h6>
                            <ul class="small mb-0 ps-3">
                                <li>Waktu akan berjalan setelah Anda menekan tombol <strong>Mulai Ujian</strong>.</li>
                                <li>Sistem akan otomatis mengunci (Device Lock) akun Anda pada perangkat ini.</li>
                                <li>Jangan mencoba menutup browser atau berpindah tab agar tidak terblokir.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form id="form-konfirmasi">
                    <input type="hidden" name="exam_id" value="<?= esc($exam_id) ?>">

                    <?php if ($exam['token'] && $exam['is_token_aktif']): ?>
                        <div class="mb-4 text-center">
                            <label class="form-label fw-bold">Masukkan Token Ujian</label>
                            <input type="text" name="token" class="form-control form-control-lg text-center fw-bold text-primary mx-auto" style="max-width: 250px; letter-spacing: 5px; border: 2px solid #4e73df;" placeholder="******" required maxlength="6">
                            <small class="text-muted d-block mt-2">Minta token kepada pengawas ujian.</small>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-pill" id="btn-submit-konfirmasi">
                            <i class="fas fa-play-circle me-2"></i> Mulai Kerjakan Sekarang
                        </button>
                        <button type="button" class="btn btn-light btn-sm text-muted" onclick="loadView('dashboard')">Kembali ke Dashboard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).off('submit.konfirmasi').on('submit.konfirmasi', '#form-konfirmasi', function(e) {
    e.preventDefault();
    var btn = $('#btn-submit-konfirmasi');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Memproses...');

    $.ajax({
        url: 'proses_mulai_ujian.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(res) {
            if (res.status === 'ok') {
                loadView('ujian', { id: res.exam_id });
            } else if (res.redirect) {
                loadView('dashboard', { msg: res.redirect });
            } else {
                btn.prop('disabled', false).html('<i class="fas fa-play-circle me-2"></i> Mulai Kerjakan Sekarang');
                Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Terjadi kesalahan.' });
            }
        },
        error: function() {
            btn.prop('disabled', false).html('<i class="fas fa-play-circle me-2"></i> Mulai Kerjakan Sekarang');
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal terhubung ke server.' });
        }
    });
});
</script>
