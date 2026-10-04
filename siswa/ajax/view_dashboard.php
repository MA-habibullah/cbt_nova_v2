<?php
require_once '../../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo '<div class="alert alert-danger m-4">Sesi tidak valid. <a href="../../auth/logout.php">Login ulang</a></div>';
    exit;
}

$student_id = (int)$_SESSION['student_id'];
session_write_close();

$stmtSiswa = $pdo->prepare("SELECT s.*, c.nama_kelas, c.jenjang
                             FROM cbt_students s
                             JOIN cbt_classes c ON s.class_id = c.id
                             WHERE s.id = ?");
$stmtSiswa->execute([$student_id]);
$siswa = $stmtSiswa->fetch();

if (!$siswa) {
    echo '<script>window.location.href = "../../auth/logout.php";</script>';
    exit;
}

$sekarang    = date('Y-m-d H:i:s');
$today_start = date('Y-m-d') . ' 00:00:00';
$today_end   = date('Y-m-d') . ' 23:59:59';
require_once '../../includes/helpers.php';

$sqlExams = "SELECT e.*, s.nama_mapel, p.id as participant_id, p.status as status_ujian
             FROM cbt_exams e
             JOIN cbt_subjects s ON e.subject_id = s.id
             JOIN cbt_exam_participants p ON p.exam_id = e.id AND p.student_id = ?
             WHERE e.status = 'aktif'
             AND e.mulai_pada BETWEEN ? AND ?
             ORDER BY e.mulai_pada ASC";

$stmtExams = $pdo->prepare($sqlExams);
$stmtExams->execute([$student_id, $today_start, $today_end]);
$exams = $stmtExams->fetchAll();

// Auto-finalize & kalkulasi skor jika siswa sebelumnya sedang mengerjakan namun waktu jadwal habis
foreach ($exams as &$e) {
    if (($e['status_ujian'] ?? '') === 'working' && $sekarang > $e['selesai_pada']) {
        if (!empty($e['participant_id'])) {
            hitung_dan_simpan_nilai_peserta($pdo, (int)$e['participant_id']);
            $e['status_ujian'] = 'finished';
        }
    }
}
unset($e);

$msg = $_GET['msg'] ?? '';
?>

<nav class="navbar navbar-siswa sticky-top mb-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 py-1">
        <span class="navbar-brand fw-bold text-primary mb-0 d-flex align-items-center">
            <i class="fas fa-graduation-cap me-2"></i> CBT ONLINE
        </span>

        <div class="d-flex align-items-center gap-2 ms-auto dashboard-nav-actions">
            <span class="me-1 d-none d-md-inline small text-muted">Hari ini: <b><?= date('d M Y') ?></b></span>
            <button type="button" onclick="internalRefresh()" class="btn btn-light btn-sm border fw-bold rounded-pill px-3 shadow-sm" aria-label="Muat Ulang">
                <i class="fas fa-sync-alt text-primary"></i><span class="d-none d-sm-inline ms-1">Muat Ulang</span>
            </button>
            <button type="button" onclick="confirmLogout()" class="btn btn-outline-danger btn-sm fw-bold rounded-pill px-3" aria-label="Keluar">
                <i class="fas fa-sign-out-alt"></i><span class="d-none d-sm-inline ms-1">Keluar</span>
            </button>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card card-profile shadow-sm p-4 text-center">
                <div class="mb-3">
                    <?php
                    $foto_file = dirname(__DIR__, 2) . '/assets/uploads/foto_siswa/' . ($siswa['foto'] ?? '');
                    if (!empty($siswa['foto']) && file_exists($foto_file)): ?>
                        <img src="../assets/uploads/foto_siswa/<?= htmlspecialchars($siswa['foto']) ?>" class="rounded-circle border border-3 border-white shadow" width="100" height="100" style="object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center shadow" style="width:100px; height:100px;">
                            <i class="fas fa-user fa-3x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mb-0"><?= strtoupper(htmlspecialchars($siswa['nama_lengkap'] ?? '')) ?></h5>
                <p class="small opacity-75 mb-2"><?= htmlspecialchars($siswa['nisn'] ?? '') ?> | Kelas <?= htmlspecialchars($siswa['nama_kelas'] ?? '') ?></p>
                <div class="mt-2 py-1 px-3 bg-white bg-opacity-25 rounded-pill d-inline-block">
                    <small class="fw-bold"><i class="fas fa-clock me-1"></i> SESI <?= $siswa['sesi'] ?? '1' ?></small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4 p-3">
                <h6 class="fw-bold text-dark border-bottom pb-2"><i class="fas fa-info-circle me-2 text-primary"></i> Tata Tertib</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-1">Pastikan koneksi internet stabil.</li>
                    <li class="mb-1 text-danger fw-bold">Dilarang keluar dari halaman ujian (Auto-Block).</li>
                    <li class="mb-1 text-primary fw-semibold"><i class="fas fa-hand-point-right me-1"></i> Di HP: Cukup geser layar ke kiri/kanan untuk pindah soal.</li>
                    <li class="mb-1">Gunakan NISN dan Password yang valid.</li>
                    <li>Selesaikan ujian tepat waktu sesuai durasi.</li>
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-edit me-2 text-primary"></i> Ujian Tersedia</h5>
                <span class="badge bg-primary rounded-pill"><?= count($exams) ?> Mata Pelajaran</span>
            </div>

            <?php if (empty($exams)): ?>
                <div class="card border-0 shadow-sm p-5 text-center">
                    <i class="fas fa-calendar-times fa-4x text-muted opacity-25 mb-3"></i>
                    <h6 class="text-muted">Tidak ada jadwal ujian aktif untuk Anda saat ini.</h6>
                    <p class="small text-muted">Silakan hubungi proktor jika jadwal tidak muncul.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($exams as $e): ?>
                        <?php $status_ujian = $e['status_ujian'] ?? 'ready'; ?>
                        <div class="col-12">
                            <div class="card exam-card shadow-sm p-3">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($e['nama_mapel']) ?></h6>
                                        <div class="d-flex flex-wrap gap-3 small text-muted">
                                            <span><i class="far fa-clock me-1 text-primary"></i> <?= $e['durasi_menit'] ?> Menit</span>
                                            <span><i class="far fa-calendar-alt me-1 text-primary"></i> <?= esc(date('d M Y H:i', strtotime($e['mulai_pada']))) ?></span>
                                            <span><i class="fas fa-stopwatch me-1 text-primary"></i> Selesai: <?= date('d M Y H:i', strtotime($e['selesai_pada'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                        <?php if ($status_ujian == 'finished'): ?>
                                            <span class="badge bg-success-subtle text-success badge-status">
                                                <i class="fas fa-check-circle me-1"></i> Terkirim
                                            </span>
                                        <?php elseif ($status_ujian == 'blocked'): ?>
                                            <span class="badge bg-danger-subtle text-danger badge-status">
                                                <i class="fas fa-lock me-1"></i> Terkunci
                                            </span>
                                        <?php elseif ($sekarang < $e['mulai_pada']): ?>
                                            <button class="btn btn-secondary btn-sm rounded-pill px-4 fw-bold" disabled>
                                                <i class="fas fa-clock me-1"></i> Belum Waktunya
                                            </button>
                                        <?php elseif ($sekarang > $e['selesai_pada']): ?>
                                            <button class="btn btn-danger btn-sm rounded-pill px-4 fw-bold" disabled>
                                                <i class="fas fa-times-circle me-1"></i> Waktu Habis
                                            </button>
                                        <?php else: ?>
                                            <a href="#" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm fw-bold btn-mulai"
                                               data-exam-id="<?= esc($e['id']) ?>">
                                                <?= ($status_ujian == 'working') ? 'Lanjutkan' : 'Mulai Ujian' ?> <i class="fas fa-chevron-right ms-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
<?php if ($msg === 'ujian_selesai'): ?>
Swal.fire({ icon: 'success', title: 'Ujian Selesai!', text: 'Jawaban Anda telah berhasil dikirim.', timer: 3000, showConfirmButton: false });
<?php elseif ($msg === 'sesi_berakhir'): ?>
Swal.fire({ icon: 'warning', title: 'Sesi Berakhir', text: 'Waktu ujian telah habis atau sesi Anda tidak valid.', confirmButtonColor: '#4e73df' });
<?php elseif ($msg === 'sudah_selesai'): ?>
Swal.fire({ icon: 'info', title: 'Ujian Sudah Selesai', text: 'Anda sudah mengerjakan ujian ini sebelumnya.', timer: 2500, showConfirmButton: false });
<?php elseif ($msg === 'akun_diblokir'): ?>
Swal.fire({ icon: 'error', title: 'Akun Diblokir', text: 'Akun Anda diblokir karena pelanggaran. Hubungi pengawas.', confirmButtonColor: '#4e73df' });
<?php elseif ($msg === 'device_locked'): ?>
Swal.fire({ icon: 'error', title: 'Perangkat Terkunci', text: 'Akun Anda sudah terkunci di perangkat lain. Hubungi pengawas jika ini kesalahan.', confirmButtonColor: '#4e73df' });
<?php endif; ?>

// Bersihkan parameter msg dari URL browser agar tidak muncul lagi saat refresh
if (window.history.replaceState && window.location.search.includes('msg=')) {
    window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]msg=[^&]+/, '').replace(/^&/, '?'));
}

$(document).off('click.dashboard').on('click.dashboard', '.btn-mulai', function(e) {
    e.preventDefault();
    loadView('konfirmasi', { id: $(this).data('exam-id') });
});

function confirmLogout() {
    Swal.fire({
        title: 'Yakin ingin keluar?',
        text: "Pastikan Anda tidak sedang dalam pengerjaan ujian.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = "../auth/logout.php";
    });
}
</script>
