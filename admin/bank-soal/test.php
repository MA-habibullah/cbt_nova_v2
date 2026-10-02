<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }

$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info bank soal & Mapel
$stmt_bank = $pdo->prepare("SELECT b.*, s.nama_mapel, s.id as subject_id FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ?");
$stmt_bank->execute([$id_bank]);
$bank = $stmt_bank->fetch();

if (!$bank) { header("Location: index.php"); exit; }

// --- 1. PROSES TAMBAH TEST ---
if (isset($_POST['tambah_test'])) {
    // Validasi Tanggal: Selesai tidak boleh <= Mulai
    if (strtotime($_POST['tgl_selesai']) <= strtotime($_POST['tgl_mulai'])) {
        header("Location: test.php?id=$id_bank&msg=date_invalid");
        exit;
    }

    $token = strtoupper(substr(md5(uniqid()), 0, 6)); 
    $stmt = $pdo->prepare("INSERT INTO cbt_exams 
        (nama_mapel_ujian, jenjang, bank_soal_id, subject_id, teacher_id, durasi_menit, mulai_pada, selesai_pada, status, token, is_token_aktif, acak_soal, acak_opsi) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $_POST['nama_test'],
        $_POST['jenjang'], // Input jenjang baru
        $id_bank,
        $bank['subject_id'],
        $bank['teacher_id'], 
        $_POST['durasi'],
        $_POST['tgl_mulai'],
        $_POST['tgl_selesai'],
        $_POST['status'],
        $token,
        0, // is_token_aktif: Default non-aktif
        isset($_POST['acak_soal']) ? 1 : 0,
        isset($_POST['acak_opsi']) ? 1 : 0
    ]);
    log_activity("Tambah ujian: " . $_POST['nama_test'] . " di bank soal ID $id_bank", null, null, null, 'ujian');
    header("Location: test.php?id=$id_bank&msg=test_added"); exit;
}

// --- 2. PROSES EDIT TEST ---
if (isset($_POST['edit_test'])) {
    // Validasi Tanggal: Selesai tidak boleh <= Mulai
    if (strtotime($_POST['tgl_selesai']) <= strtotime($_POST['tgl_mulai'])) {
        header("Location: test.php?id=$id_bank&msg=date_invalid");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE cbt_exams SET 
        nama_mapel_ujian = ?, 
        jenjang = ?, 
        durasi_menit = ?, 
        mulai_pada = ?, 
        selesai_pada = ?, 
        status = ?,
        acak_soal = ?, 
        acak_opsi = ? 
        WHERE id = ?");
    
    $stmt->execute([
        $_POST['nama_test'],
        $_POST['jenjang'], // Update jenjang baru
        $_POST['durasi'],
        $_POST['tgl_mulai'],
        $_POST['tgl_selesai'],
        $_POST['status'],
        isset($_POST['acak_soal']) ? 1 : 0,
        isset($_POST['acak_opsi']) ? 1 : 0,
        $_POST['exam_id']
    ]);
    log_activity("Update ujian ID " . $_POST['exam_id'] . ": " . $_POST['nama_test'], null, null, null, 'ujian');
    header("Location: " . BASE_URL . "admin/bank-soal/test.php?id=$id_bank&msg=test_updated"); exit;
}

// Ambil list Ujian
$exams = $pdo->prepare("SELECT * FROM cbt_exams WHERE bank_soal_id = ? ORDER BY id DESC");
$exams->execute([$id_bank]);
$listExams = $exams->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                <h5 class="mb-0 fw-bold">Jadwal Ujian: <?= $bank['nama_mapel'] ?></h5>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if($flash === 'test_added'): ?>
                <div class="alert alert-success border-0 shadow-sm mb-3 alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> Jadwal ujian baru berhasil dibuat!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($flash === 'test_updated'): ?>
                <div class="alert alert-info border-0 shadow-sm mb-3 alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> Jadwal ujian berhasil diperbarui!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($flash === 'date_invalid'): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-3 alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i> <strong>Gagal:</strong> Tanggal selesai tidak boleh lebih dulu atau sama dengan tanggal mulai!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <button class="btn btn-primary shadow-sm mb-4" data-bs-toggle="modal" data-bs-target="#modalTambahTest">
                <i class="fas fa-calendar-plus me-2"></i> Buat Jadwal Ujian Baru
            </button>

            <div class="row">
                <?php foreach($listExams as $ex): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="text-primary small fw-bold mb-1">KELAS <?= $ex['jenjang'] ?></div>
                                    <h5 class="fw-bold mb-2"><?= $ex['nama_mapel_ujian'] ?></h5>
                                    
                                    <div class="d-flex gap-1 flex-wrap">
                                        <span class="badge bg-dark">Token: <?= $ex['token'] ?></span>
                                        <?php 
                                            $status_badge = 'bg-secondary';
                                            if($ex['status'] == 'aktif') $status_badge = 'bg-success';
                                            if($ex['status'] == 'selesai') $status_badge = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $status_badge ?>"><?= strtoupper($ex['status']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm border shadow-sm" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li><a class="dropdown-item py-2" href="<?= esc(BASE_URL) ?>admin/bank-soal/test-kelola.php?exam_id=<?= esc($ex['id']) ?>&id=<?= esc($id_bank) ?>">
                                            <i class="fas fa-tasks me-2 text-info"></i> Kelola Test</a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item btn-edit py-2" href="javascript:void(0)" 
                                                data-id="<?= esc($ex['id']) ?>"
                                                data-nama="<?= esc($ex['nama_mapel_ujian']) ?>"
                                                data-jenjang="<?= esc($ex['jenjang']) ?>" 
                                                data-durasi="<?= esc($ex['durasi_menit']) ?>"
                                                data-mulai="<?= esc(date('Y-m-d\TH:i', strtotime($ex['mulai_pada']))) ?>"
                                                data-selesai="<?= esc(date('Y-m-d\TH:i', strtotime($ex['selesai_pada']))) ?>"
                                                data-status="<?= esc($ex['status']) ?>"
                                                data-acaksoal="<?= esc($ex['acak_soal']) ?>"
                                                data-acakopsi="<?= esc($ex['acak_opsi']) ?>">
                                                <i class="fas fa-edit me-2 text-primary"></i> Edit Jadwal
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger py-2" href="javascript:void(0)" onclick="confirmDelete(<?= esc($ex['id']) ?>, '<?= esc(addslashes($ex['nama_mapel_ujian'])) ?>')">
                                                <i class="fas fa-trash me-2"></i> Hapus Jadwal
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="row small text-muted g-2">
                                <div class="col-6"><i class="far fa-clock me-2 text-primary"></i><?= $ex['durasi_menit'] ?> Menit</div>
                                <div class="col-6"><i class="fas fa-random me-2 text-primary"></i>Acak: <?= $ex['acak_soal'] ? 'Ya' : 'Tidak' ?></div>
                                <div class="col-12"><i class="far fa-calendar-alt me-2 text-primary"></i>Mulai: <?= esc(date('d M Y, H:i', strtotime($ex['mulai_pada']))) ?> WIB</div>
                                <div class="col-12"><i class="far fa-calendar-check me-2 text-primary"></i>Selesai: <?= date('d M Y, H:i', strtotime($ex['selesai_pada'])) ?> WIB</div>
                            </div>

                            <hr class="my-3">
                            
                            <div class="row g-2">
                                <div class="col-12">
                                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/test-kelola.php?exam_id=<?= esc($ex['id']) ?>&id=<?= esc($id_bank) ?>" class="btn btn-primary w-100 btn-sm shadow-sm">
                                        <i class="fas fa-users-cog me-2"></i> Atur Soal & Peserta
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditTest" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Jadwal Ujian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_test" value="1">
                <input type="hidden" name="exam_id" id="edit_id">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Ujian / Test</label>
                    <input type="text" name="nama_test" id="edit_nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Jenjang / Kelas</label>
                    <select name="jenjang" id="edit-jenjang" class="form-select" required>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Durasi (Menit)</label>
                        <input type="number" name="durasi" id="edit_durasi" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="draft">DRAFT</option>
                            <option value="aktif">AKTIF</option>
                            <option value="selesai">SELESAI</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Opsi Acak</label>
                    <div class="d-flex gap-3">
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="acak_soal" id="edit_acak_soal">
                            <label class="form-check-label" for="edit_acak_soal">Acak Soal</label>
                        </div>
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="acak_opsi" id="edit_acak_opsi">
                            <label class="form-check-label" for="edit_acak_opsi">Acak Jawaban</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Mulai</label>
                    <input type="datetime-local" name="tgl_mulai" id="edit_mulai" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Selesai</label>
                    <input type="datetime-local" name="tgl_selesai" id="edit_selesai" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary text-white">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTambahTest" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Buat Jadwal Ujian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="tambah_test" value="1">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Ujian / Test</label>
                    <input type="text" name="nama_test" class="form-control" placeholder="Contoh: PTS Ganjil 2024" required>
                </div>
                <div class="mb-3">
                    <label>Jenjang / Kelas</label>
                    <select name="jenjang" class="form-select" required>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Durasi (Menit)</label>
                        <input type="number" name="durasi" class="form-control" value="90" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" selected>DRAFT</option>
                            <option value="aktif">AKTIF</option>
                            <option value="selesai">SELESAI</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Opsi Acak</label>
                    <div class="d-flex gap-3">
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="acak_soal" checked id="ac1">
                            <label class="form-check-label" for="ac1">Acak Soal</label>
                        </div>
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="acak_opsi" checked id="ac2">
                            <label class="form-check-label" for="ac2">Acak Jawaban</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Mulai</label>
                    <input type="datetime-local" name="tgl_mulai" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Selesai (Batas Login)</label>
                    <input type="datetime-local" name="tgl_selesai" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan Jadwal</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(examId, examName) {
    // Menghindari error jika examName mengandung karakter khusus
    const safeExamName = examName.replace(/['"]/g, '\\$&'); 

    Swal.fire({
        title: 'Hapus Jadwal Ujian?',
        html: "Anda akan menghapus jadwal <b>" + examName + "</b>.<br><small class='text-danger'>Peringatan: Seluruh hasil ujian siswa di jadwal ini juga akan ikut terhapus!</small>",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Tetap!',
        cancelButtonText: 'Batal',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan loading saat proses
            Swal.showLoading();
            // Arahkan ke file proses hapus menggunakan exam_id yang konsisten
            window.location.href = "controllers/bank-soal-test-delete.php?exam_id=" + examId + "&id=<?= esc((isset($id_bank) ? $id_bank : 0)) ?>";
        }
    })
}
</script>

<script>
$(document).ready(function() {
    // Validasi Client-side sebelum form dikirim
    $('form').on('submit', function(e) {
        const form = $(this);
        const tglMulai = form.find('input[name="tgl_mulai"]').val();
        const tglSelesai = form.find('input[name="tgl_selesai"]').val();

        if (tglMulai && tglSelesai) {
            const dMulai = new Date(tglMulai);
            const dSelesai = new Date(tglSelesai);

            if (dSelesai <= dMulai) {
                e.preventDefault();
                Swal.fire('Kesalahan Input', 'Waktu selesai (batas login) harus setelah waktu mulai!', 'error');
            }
        }
    });

    // Menggunakan Event Delegation agar tombol tetap bekerja meski data di-refresh
    $(document).on('click', '.btn-edit', function() {
        // Ambil data dari atribut tombol
        const btn = $(this);
        const id = btn.data('id');
        const nama = btn.data('nama');
        const jenjang = btn.data('jenjang');
        const durasi = btn.data('durasi');
        const mulai = btn.data('mulai');
        const selesai = btn.data('selesai');
        const status = btn.data('status');
        const acaksoal = btn.data('acaksoal');
        const acakopsi = btn.data('acakopsi');

        // Masukkan ke dalam form modal
        $('#edit_id').val(id);
        $('#edit_nama').val(nama);
        $('#edit-jenjang').val(jenjang);
        $('#edit_durasi').val(durasi);
        $('#edit_mulai').val(mulai.replace(' ', 'T'));
        $('#edit_selesai').val(selesai.replace(' ', 'T'));
        $('#edit_status').val(status);
        
        // Set checkbox dengan eksplisit (true/false)
        $('#edit_acak_soal').prop('checked', parseInt(acaksoal) === 1);
        $('#edit_acak_opsi').prop('checked', parseInt(acakopsi) === 1);

        // Tampilkan modal
        $('#modalEditTest').modal('show');
    });

    // Opsional: Bersihkan form saat modal ditutup agar tidak terjadi shadow data
    $('#modalEditTest').on('hidden.bs.modal', function () {
        $(this).find('form').trigger('reset');
    });
});
</script>

</body>
</html>