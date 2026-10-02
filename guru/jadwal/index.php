<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = $_SESSION['teacher_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }
$f_bank     = (int)($_GET['bank_soal_id'] ?? 0);

// Helper: cek status bank soal milik guru
function bankStatus($pdo, $bank_id, $teacher_id): string {
    $s = $pdo->prepare("SELECT status FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?");
    $s->execute([$bank_id, $teacher_id]);
    $r = $s->fetch();
    return $r ? $r['status'] : '';
}

// --- PROSES TAMBAH ---
if (isset($_POST['tambah_test'])) {
    $bank_id = (int)$_POST['bank_soal_id'];
    $chk = $pdo->prepare("SELECT id, subject_id, status FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?");
    $chk->execute([$bank_id, $teacher_id]);
    $bk = $chk->fetch();
    if ($bk) {
        if ($bk['status'] === 'nonaktif') {
            $back = $f_bank ? "?bank_soal_id=$f_bank&msg=locked" : "?msg=locked";
            header("Location: index.php$back"); exit;
        }
        $token = strtoupper(substr(md5(uniqid()), 0, 6));
        $pdo->prepare("INSERT INTO cbt_exams (nama_mapel_ujian, jenjang, bank_soal_id, subject_id, teacher_id, durasi_menit, mulai_pada, selesai_pada, status, token, acak_soal, acak_opsi)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)")
            ->execute([
                $_POST['nama_test'], $_POST['jenjang'], $bank_id, $bk['subject_id'], $teacher_id,
                $_POST['durasi'], $_POST['tgl_mulai'], $_POST['tgl_selesai'], $token,
                isset($_POST['acak_soal']) ? 1 : 0,
                isset($_POST['acak_opsi']) ? 1 : 0
            ]);
    }
    log_activity("Tambah jadwal ujian: " . $_POST['nama_test'] . " di bank soal ID $bank_id", null, null, null, 'ujian');
    $back = $f_bank ? "?bank_soal_id=$f_bank&msg=test_added" : "?msg=test_added";
    header("Location: index.php$back"); exit;
}

// --- PROSES EDIT ---
if (isset($_POST['edit_test'])) {
    $exam_id = (int)$_POST['exam_id'];
    // Ambil bank_soal_id milik exam ini
    $ex = $pdo->prepare("SELECT bank_soal_id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
    $ex->execute([$exam_id, $teacher_id]);
    $exRow = $ex->fetch();
    if ($exRow && bankStatus($pdo, $exRow['bank_soal_id'], $teacher_id) === 'nonaktif') {
        $back = $f_bank ? "?bank_soal_id=$f_bank&msg=locked" : "?msg=locked";
        header("Location: index.php$back"); exit;
    }
    $pdo->prepare("UPDATE cbt_exams SET nama_mapel_ujian=?, jenjang=?, durasi_menit=?, mulai_pada=?, selesai_pada=?, status=?, acak_soal=?, acak_opsi=?
                   WHERE id=? AND teacher_id=?")
        ->execute([
            $_POST['nama_test'], $_POST['jenjang'], $_POST['durasi'],
            $_POST['tgl_mulai'], $_POST['tgl_selesai'], $_POST['status'],
            isset($_POST['acak_soal']) ? 1 : 0,
            isset($_POST['acak_opsi']) ? 1 : 0,
            $exam_id, $teacher_id
        ]);
    log_activity("Update jadwal ujian ID $exam_id: " . $_POST['nama_test'], null, null, null, 'ujian');
    $back = $f_bank ? "?bank_soal_id=$f_bank&msg=test_updated" : "?msg=test_updated";
    header("Location: index.php$back"); exit;
}

// --- PROSES HAPUS ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $exam_id = (int)($_GET['exam_id'] ?? 0);
    $exRow = $pdo->prepare("SELECT bank_soal_id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
    $exRow->execute([$exam_id, $teacher_id]);
    $exData = $exRow->fetch();
    if ($exData) {
        if (bankStatus($pdo, $exData['bank_soal_id'], $teacher_id) === 'nonaktif') {
            $back = $f_bank ? "?bank_soal_id=$f_bank&msg=locked" : "?msg=locked";
            header("Location: index.php$back"); exit;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM cbt_cheat_logs WHERE exam_id = ?")->execute([$exam_id]);
            $pdo->prepare("DELETE sa FROM cbt_student_answers sa JOIN cbt_exam_participants ep ON sa.participant_id = ep.id WHERE ep.exam_id = ?")->execute([$exam_id]);
            $pdo->prepare("DELETE FROM cbt_exam_participants WHERE exam_id = ?")->execute([$exam_id]);
            $pdo->prepare("DELETE FROM cbt_exam_questions WHERE exam_id = ?")->execute([$exam_id]);
            $pdo->prepare("DELETE FROM cbt_exams WHERE id = ?")->execute([$exam_id]);
            $pdo->commit();
            log_activity("Hapus jadwal ujian ID $exam_id (cascade)", null, null, null, 'ujian');
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    $back = $f_bank ? "?bank_soal_id=$f_bank" : "";
    header("Location: index.php$back"); exit;
}

// --- DATA ---
$where  = "WHERE e.teacher_id = ?";
$params = [$teacher_id];
if ($f_bank) {
    $where .= " AND e.bank_soal_id = ?";
    $params[] = $f_bank;
}

$exams = $pdo->prepare("SELECT e.*, b.nama_bank_soal, b.status AS bank_status, s.nama_mapel,
           COUNT(DISTINCT eq.id) AS jumlah_soal,
           COUNT(DISTINCT ep.id) AS jumlah_peserta
    FROM cbt_exams e
    JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
    JOIN cbt_subjects s ON e.subject_id = s.id
    LEFT JOIN cbt_exam_questions eq ON eq.exam_id = e.id
    LEFT JOIN cbt_exam_participants ep ON ep.exam_id = e.id
    $where GROUP BY e.id ORDER BY e.created_at DESC");
$exams->execute($params);
$listExams = $exams->fetchAll();

// Bank soal milik guru (untuk modal tambah) + status
$myBanks = $pdo->prepare("SELECT b.id, b.nama_bank_soal, b.status, s.nama_mapel FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.teacher_id = ? ORDER BY b.nama_bank_soal ASC");
$myBanks->execute([$teacher_id]);
$bankList = $myBanks->fetchAll();

// Cek apakah bank yang difilter terkunci
$f_bank_locked = false;
if ($f_bank) {
    foreach ($bankList as $bk) {
        if ($bk['id'] == $f_bank) { $f_bank_locked = ($bk['status'] === 'nonaktif'); break; }
    }
}

$flash = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<style>
    .select-status { font-size: .8rem; font-weight: bold; border-radius: 5px; padding: 5px; cursor: pointer; }
    .status-aktif   { background-color: #198754; color: white; border-color: #198754; }
    .status-draft   { background-color: #6c757d; color: white; border-color: #6c757d; }
    .status-selesai { background-color: #dc3545; color: white; border-color: #dc3545; }
    .select-status:focus { box-shadow: none; color: white; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Jadwal Ujian</h5>
                    <?php if ($f_bank): ?>
                    <small class="text-muted">
                        <?php
                        $bkName = '';
                        foreach ($bankList as $bk) if ($bk['id'] == $f_bank) $bkName = $bk['nama_bank_soal'];
                        echo 'Filter: ' . htmlspecialchars($bkName);
                        ?>
                        <?php if ($f_bank_locked): ?>
                            <span class="badge bg-danger ms-1"><i class="fas fa-lock me-1"></i>Terkunci</span>
                        <?php endif; ?>
                        <a href="index.php" class="ms-2 text-danger small"><i class="fas fa-times"></i></a>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$f_bank_locked): ?>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahTest">
                <i class="fas fa-calendar-plus me-2"></i> Buat Jadwal Baru
            </button>
            <?php else: ?>
            <button class="btn btn-secondary shadow-sm" disabled>
                <i class="fas fa-lock me-2"></i> Buat Jadwal Baru
            </button>
            <?php endif; ?>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if ($f_bank_locked): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-lock fa-2x me-3"></i>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Admin telah mengunci bank soal ini. Tambah, edit, dan hapus jadwal ujian tidak dapat dilakukan.
                </div>
            </div>
            <?php endif; ?>

            <?php if ($flash === 'test_added'): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> Jadwal ujian baru berhasil dibuat!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php elseif ($flash === 'test_updated'): ?>
            <div class="alert alert-info border-0 shadow-sm mb-4 alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> Jadwal ujian berhasil diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php elseif ($flash === 'locked'): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show">
                <i class="fas fa-lock me-2"></i> Bank soal dikunci oleh admin, jadwal tidak dapat diubah.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (empty($listExams)): ?>
            <div class="text-center py-5 bg-white rounded shadow-sm border">
                <i class="fas fa-calendar-alt fa-3x text-muted opacity-50 mb-3"></i>
                <p class="text-muted">Belum ada jadwal ujian<?= $f_bank ? ' untuk bank soal ini' : '' ?>.</p>
                <?php if (!$f_bank_locked): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahTest">
                    <i class="fas fa-plus me-2"></i> Buat Jadwal Ujian
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">
            <?php foreach ($listExams as $ex):
                $ex_locked = ($ex['bank_status'] === 'nonaktif');
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-primary small fw-bold mb-1">KELAS <?= $ex['jenjang'] ?></div>
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($ex['nama_mapel_ujian']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($ex['nama_bank_soal']) ?></small>
                                <?php if ($ex_locked): ?>
                                    <div><span class="badge bg-danger mt-1"><i class="fas fa-lock me-1"></i>Bank Terkunci</span></div>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm border shadow-sm" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                    <li>
                                        <a class="dropdown-item py-2" href="test-kelola.php?exam_id=<?= esc($ex['id']) ?>&id=<?= esc($ex['bank_soal_id']) ?>">
                                            <i class="fas fa-tasks me-2 text-info"></i> Kelola Test
                                        </a>
                                    </li>
                                    <?php if (!$ex_locked): ?>
                                    <li>
                                        <a class="dropdown-item btn-edit py-2" href="javascript:void(0)"
                                           data-id="<?= esc($ex['id']) ?>"
                                           data-nama="<?= htmlspecialchars($ex['nama_mapel_ujian'], ENT_QUOTES) ?>"
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
                                        <a class="dropdown-item text-danger py-2" href="javascript:void(0)"
                                           onclick="confirmDelete(<?= esc($ex['id']) ?>, '<?= esc(addslashes($ex['nama_mapel_ujian'])) ?>')">
                                            <i class="fas fa-trash me-2"></i> Hapus Jadwal
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li>
                                        <span class="dropdown-item text-muted py-2 disabled">
                                            <i class="fas fa-lock me-2"></i> Edit / Hapus Terkunci
                                        </span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="badge bg-dark me-1">Token: <?= $ex['token'] ?></span>
                            <span class="badge <?= $ex['status']==='aktif'?'bg-success':($ex['status']==='selesai'?'bg-danger':'bg-secondary') ?>">
                                <?= strtoupper($ex['status']) ?>
                            </span>
                        </div>

                        <div class="row small text-muted g-1 mb-3">
                            <div class="col-6"><i class="far fa-clock me-1 text-primary"></i><?= $ex['durasi_menit'] ?> Menit</div>
                            <div class="col-6"><i class="fas fa-random me-1 text-primary"></i>Acak: <?= $ex['acak_soal']?'Ya':'Tidak' ?></div>
                            <div class="col-6"><i class="fas fa-file-alt me-1 text-info"></i>Soal: <strong><?= esc($ex['jumlah_soal']) ?></strong></div>
                            <div class="col-6"><i class="fas fa-users me-1 text-success"></i>Peserta: <strong><?= $ex['jumlah_peserta'] ?></strong></div>
                            <div class="col-12"><i class="far fa-calendar-alt me-1 text-primary"></i>Mulai: <?= esc(date('d M Y, H:i', strtotime($ex['mulai_pada']))) ?></div>
                            <div class="col-12"><i class="far fa-calendar-check me-1 text-primary"></i>Selesai: <?= date('d M Y, H:i', strtotime($ex['selesai_pada'])) ?></div>
                        </div>

                        <!-- Status Dropdown -->
                        <div class="mb-3">
                            <label class="small text-muted fw-bold">Ubah Status:</label>
                            <?php if (!$ex_locked): ?>
                            <select class="form-select form-select-sm select-status update-status-ajax status-<?= esc($ex['status']) ?>" data-id="<?= esc($ex['id']) ?>">
                                <option value="draft"   <?= esc($ex['status']==='draft'?'selected':'') ?>>DRAFT</option>
                                <option value="aktif"   <?= esc($ex['status']==='aktif'?'selected':'') ?>>AKTIF</option>
                                <option value="selesai" <?= esc($ex['status']==='selesai'?'selected':'') ?>>SELESAI</option>
                            </select>
                            <?php else: ?>
                            <select class="form-select form-select-sm" disabled>
                                <option><?= strtoupper($ex['status']) ?></option>
                            </select>
                            <?php endif; ?>
                        </div>

                        <a href="test-kelola.php?exam_id=<?= esc($ex['id']) ?>&id=<?= esc($ex['bank_soal_id']) ?>"
                           class="btn btn-primary w-100 btn-sm shadow-sm">
                            <i class="fas fa-users-cog me-2"></i> Atur Soal & Peserta
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambahTest" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0" id="formTambahTest">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Buat Jadwal Ujian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="tambah_test" value="1">

                <div id="alertBankLocked" class="alert alert-danger border-0 d-none">
                    <i class="fas fa-lock me-2"></i> Bank soal ini dikunci oleh admin, jadwal tidak dapat dibuat.
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Bank Soal</label>
                    <select name="bank_soal_id" id="selectBankTambah" class="form-select" required>
                        <option value="">-- Pilih Bank Soal --</option>
                        <?php foreach ($bankList as $bk): ?>
                        <option value="<?= esc($bk['id']) ?>"
                                data-locked="<?= esc($bk['status']==='nonaktif' ? '1' : '0') ?>"
                                <?= $f_bank==$bk['id']?'selected':'' ?>
                                <?= $bk['status']==='nonaktif' ? 'style="color:#aaa;"' : '' ?>>
                            <?= htmlspecialchars($bk['nama_bank_soal']) ?> (<?= htmlspecialchars($bk['nama_mapel']) ?>)
                            <?= $bk['status']==='nonaktif' ? ' — TERKUNCI' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="formFieldsTambah">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Ujian / Test</label>
                        <input type="text" name="nama_test" class="form-control" placeholder="Contoh: PTS Ganjil 2025" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenjang / Kelas</label>
                        <select name="jenjang" class="form-select" required>
                            <option value="10">Kelas 10</option>
                            <option value="11">Kelas 11</option>
                            <option value="12">Kelas 12</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Durasi (Menit)</label>
                            <input type="number" name="durasi" class="form-control" value="90" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Opsi Acak</label>
                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="acak_soal" id="ac1" checked><label class="form-check-label" for="ac1">Acak Soal</label></div>
                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="acak_opsi" id="ac2" checked><label class="form-check-label" for="ac2">Acak Jawaban</label></div>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label small fw-bold">Waktu Mulai</label>
                        <input type="datetime-local" name="tgl_mulai" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Waktu Selesai (Batas Login)</label>
                        <input type="datetime-local" name="tgl_selesai" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" id="btnSimpanTambah" class="btn btn-primary fw-bold">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEditTest" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Edit Jadwal Ujian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_test" value="1">
                <input type="hidden" name="exam_id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Ujian</label>
                    <input type="text" name="nama_test" id="edit_nama" class="form-control border-warning" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Jenjang / Kelas</label>
                    <select name="jenjang" id="edit_jenjang" class="form-select border-warning" required>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Durasi (Menit)</label>
                        <input type="number" name="durasi" id="edit_durasi" class="form-control border-warning" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" id="edit_status" class="form-select border-warning">
                            <option value="draft">DRAFT</option>
                            <option value="aktif">AKTIF</option>
                            <option value="selesai">SELESAI</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 mb-3">
                    <label class="form-label small fw-bold">Opsi Acak</label>
                    <div class="d-flex gap-3">
                        <div class="form-check small"><input class="form-check-input" type="checkbox" name="acak_soal" id="edit_acak_soal"><label class="form-check-label" for="edit_acak_soal">Acak Soal</label></div>
                        <div class="form-check small"><input class="form-check-input" type="checkbox" name="acak_opsi" id="edit_acak_opsi"><label class="form-check-label" for="edit_acak_opsi">Acak Jawaban</label></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Mulai</label>
                    <input type="datetime-local" name="tgl_mulai" id="edit_mulai" class="form-control border-warning" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Selesai</label>
                    <input type="datetime-local" name="tgl_selesai" id="edit_selesai" class="form-control border-warning" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });

// Cek lock status saat bank dipilih di modal tambah
$('#selectBankTambah').on('change', function() {
    const isLocked = $(this).find(':selected').data('locked') === 1;
    $('#alertBankLocked').toggleClass('d-none', !isLocked);
    $('#formFieldsTambah').toggleClass('d-none', isLocked);
    $('#btnSimpanTambah').prop('disabled', isLocked);
}).trigger('change');

function confirmDelete(examId, examName) {
    Swal.fire({
        title: 'Hapus Jadwal Ujian?',
        html: 'Anda akan menghapus jadwal <b>' + examName + '</b>.<br><small class="text-danger">Seluruh hasil ujian siswa di jadwal ini juga akan terhapus!</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?action=delete&exam_id=' + examId + '<?= esc($f_bank ? "&bank_soal_id=$f_bank" : "") ?>';
        }
    });
}

$(document).on('click', '.btn-edit', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_nama').val(btn.data('nama'));
    $('#edit_jenjang').val(btn.data('jenjang'));
    $('#edit_durasi').val(btn.data('durasi'));
    $('#edit_mulai').val(btn.data('mulai'));
    $('#edit_selesai').val(btn.data('selesai'));
    $('#edit_status').val(btn.data('status'));
    $('#edit_acak_soal').prop('checked', parseInt(btn.data('acaksoal')) === 1);
    $('#edit_acak_opsi').prop('checked', parseInt(btn.data('acakopsi')) === 1);
    $('#modalEditTest').modal('show');
});

$('#modalEditTest').on('hidden.bs.modal', function() { $(this).find('form').trigger('reset'); });

$(document).ready(function() {
    $('.update-status-ajax').on('change', function() {
        const sel = $(this);
        const id = sel.data('id');
        const status = sel.val();
        sel.css('opacity', '0.5');
        $.ajax({
            url: 'ajax-update-status.php',
            type: 'POST',
            data: { id: id, status: status },
            dataType: 'json',
            success: function(r) {
                sel.css('opacity', '1');
                if (r.success) {
                    sel.removeClass('status-aktif status-draft status-selesai').addClass('status-' + status);
                } else {
                    alert('Gagal: ' + r.message);
                    location.reload();
                }
            },
            error: function() { sel.css('opacity', '1'); alert('Koneksi gagal.'); }
        });
    });
});
</script>
</body>
</html>
