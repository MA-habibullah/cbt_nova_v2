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

// --- 3. PROSES SALIN / DUPLIKASI TEST (UJIAN SUSULAN / SESI BARU) ---
if (isset($_POST['salin_test'])) {
    $source_exam_id = (int)($_POST['source_exam_id'] ?? 0);
    $mode_salin     = $_POST['mode_salin'] ?? 'susulan'; // 'susulan', 'semua_siswa', 'hanya_soal'
    
    // Validasi Tanggal: Selesai tidak boleh <= Mulai
    if (strtotime($_POST['tgl_selesai']) <= strtotime($_POST['tgl_mulai'])) {
        header("Location: test.php?id=$id_bank&msg=date_invalid");
        exit;
    }

    // Ambil info ujian sumber untuk fallback data
    $stmt_src = $pdo->prepare("SELECT * FROM cbt_exams WHERE id = ? AND bank_soal_id = ?");
    $stmt_src->execute([$source_exam_id, $id_bank]);
    $src_exam = $stmt_src->fetch();

    if (!$src_exam) {
        header("Location: test.php?id=$id_bank&msg=error");
        exit;
    }

    $token = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    $tampilkan_nilai = isset($_POST['tampilkan_nilai']) ? 1 : ($src_exam['tampilkan_nilai'] ?? 0);

    $pdo->beginTransaction();
    try {
        // 1. Insert header ujian baru
        $stmt = $pdo->prepare("INSERT INTO cbt_exams 
            (nama_mapel_ujian, jenjang, bank_soal_id, subject_id, teacher_id, durasi_menit, mulai_pada, selesai_pada, status, token, is_token_aktif, acak_soal, acak_opsi, tampilkan_nilai, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())");
        
        $stmt->execute([
            trim($_POST['nama_test']),
            $_POST['jenjang'] ?? $src_exam['jenjang'],
            $id_bank,
            $bank['subject_id'],
            $bank['teacher_id'], 
            max(5, (int)($_POST['durasi'] ?? $src_exam['durasi_menit'])),
            $_POST['tgl_mulai'],
            $_POST['tgl_selesai'],
            $_POST['status'] ?? 'draft',
            $token,
            isset($_POST['acak_soal']) ? 1 : 0,
            isset($_POST['acak_opsi']) ? 1 : 0,
            $tampilkan_nilai
        ]);
        $new_exam_id = (int)$pdo->lastInsertId();

        // 2. Salin komposisi butir soal terpilih dari ujian sumber
        $stmt_copy_q = $pdo->prepare("
            INSERT INTO cbt_exam_questions (exam_id, question_id)
            SELECT ?, question_id 
            FROM cbt_exam_questions 
            WHERE exam_id = ?
        ");
        $stmt_copy_q->execute([$new_exam_id, $source_exam_id]);
        $count_q = $stmt_copy_q->rowCount();

        // Jika ujian sumber belum memilih soal di cbt_exam_questions, salin semua dari bank soal
        if ($count_q === 0) {
            $stmt_all_q = $pdo->prepare("
                INSERT INTO cbt_exam_questions (exam_id, question_id)
                SELECT ?, id FROM cbt_questions WHERE bank_soal_id = ?
            ");
            $stmt_all_q->execute([$new_exam_id, $id_bank]);
            $count_q = $stmt_all_q->rowCount();
        }

        // 3. Salin peserta ujian sesuai mode
        $count_p = 0;
        if ($mode_salin === 'susulan') {
            // Khusus siswa yang belum selesai (status != 'finished' atau NULL)
            $stmt_copy_p = $pdo->prepare("
                INSERT INTO cbt_exam_participants (exam_id, student_id, class_id, status)
                SELECT ?, student_id, class_id, 'ready'
                FROM cbt_exam_participants
                WHERE exam_id = ? AND (status != 'finished' OR status IS NULL)
            ");
            $stmt_copy_p->execute([$new_exam_id, $source_exam_id]);
            $count_p = $stmt_copy_p->rowCount();
        } elseif ($mode_salin === 'semua_siswa') {
            // Salin seluruh siswa terdaftar (status reset ke 'ready')
            $stmt_copy_p = $pdo->prepare("
                INSERT INTO cbt_exam_participants (exam_id, student_id, class_id, status)
                SELECT ?, student_id, class_id, 'ready'
                FROM cbt_exam_participants
                WHERE exam_id = ?
            ");
            $stmt_copy_p->execute([$new_exam_id, $source_exam_id]);
            $count_p = $stmt_copy_p->rowCount();
        }
        // mode 'hanya_soal' tidak menyalin peserta (count_p = 0)

        $pdo->commit();

        log_activity("Duplikasi ujian (Mode: $mode_salin) ID $source_exam_id -> ID $new_exam_id: " . $_POST['nama_test'], null, null, null, 'ujian');
        header("Location: " . BASE_URL . "admin/bank-soal/test-kelola.php?exam_id=$new_exam_id&id=$id_bank&msg=test_cloned&mode=$mode_salin&q=$count_q&p=$count_p");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: test.php?id=$id_bank&msg=error");
        exit;
    }
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
            <?php elseif($flash === 'test_cloned'): ?>
                <div class="alert alert-success border-0 shadow-sm mb-3 alert-dismissible fade show">
                    <i class="fas fa-check-double me-2"></i> <strong>Berhasil Menduplikasi Ujian!</strong> Jadwal ujian baru telah dibuat lengkap dengan soal dan peserta.
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
                                            <a class="dropdown-item btn-salin py-2" href="javascript:void(0)"
                                                data-id="<?= esc($ex['id']) ?>"
                                                data-nama="<?= esc($ex['nama_mapel_ujian']) ?>"
                                                data-jenjang="<?= esc($ex['jenjang']) ?>"
                                                data-durasi="<?= esc($ex['durasi_menit']) ?>"
                                                data-status="<?= esc($ex['status']) ?>"
                                                data-acaksoal="<?= esc($ex['acak_soal']) ?>"
                                                data-acakopsi="<?= esc($ex['acak_opsi']) ?>">
                                                <i class="fas fa-copy me-2 text-success"></i> Salin / Buat Susulan
                                            </a>
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
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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

<div class="modal fade" id="modalSalinTest" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-copy me-2"></i>Salin Jadwal / Ujian Susulan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="salin_test" value="1">
                <input type="hidden" name="source_exam_id" id="salin_source_id">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Mode Duplikasi</label>
                    <select name="mode_salin" id="salin_mode" class="form-select border-success fw-semibold">
                        <option value="susulan" selected>🎯 Ujian Susulan (Khusus Siswa Belum Selesai / Absen)</option>
                        <option value="semua_siswa">👥 Duplikasi Penuh (Semua Siswa & Soal Terpilih)</option>
                        <option value="hanya_soal">📋 Duplikasi Template (Hanya Soal & Pengaturan)</option>
                    </select>
                    <div class="form-text small" id="salin_mode_help">
                        Otomatis menyalin semua soal terpilih dan hanya menyertakan siswa yang belum berstatus 'Selesai'.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Ujian Baru</label>
                    <input type="text" name="nama_test" id="salin_nama" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Jenjang / Kelas</label>
                    <select name="jenjang" id="salin_jenjang" class="form-select" required>
                        <option value="10">Kelas 10</option>
                        <option value="11">Kelas 11</option>
                        <option value="12">Kelas 12</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Durasi (Menit)</label>
                        <input type="number" name="durasi" id="salin_durasi" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Status Awal</label>
                        <select name="status" id="salin_status" class="form-select">
                            <option value="draft" selected>DRAFT</option>
                            <option value="aktif">AKTIF</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Opsi Acak</label>
                    <div class="d-flex gap-3">
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="acak_soal" id="salin_acak_soal" checked>
                            <label class="form-check-label" for="salin_acak_soal">Acak Soal</label>
                        </div>
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="acak_opsi" id="salin_acak_opsi" checked>
                            <label class="form-check-label" for="salin_acak_opsi">Acak Jawaban</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Mulai Baru</label>
                    <input type="datetime-local" name="tgl_mulai" id="salin_mulai" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Waktu Selesai Baru (Batas Login)</label>
                    <input type="datetime-local" name="tgl_selesai" id="salin_selesai" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-copy me-1"></i> Buat Ujian Duplikasi</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(examId, examName) {
    const safeExamName = examName.replace(/['"]/g, '\\$&'); 

    Swal.fire({
        title: 'Hapus Jadwal Ujian?',
        html: "Anda akan menghapus jadwal <b>" + safeExamName + "</b>.<br><small class='text-danger'>Peringatan: Seluruh hasil ujian siswa di jadwal ini juga akan ikut terhapus!</small>",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Tetap!',
        cancelButtonText: 'Batal',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            window.location.href = "controllers/bank-soal-test-delete.php?exam_id=" + examId + "&id=<?= esc((isset($id_bank) ? $id_bank : 0)) ?>";
        }
    });
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

    // Helper format datetime-local
    function formatDateTimeLocal(d) {
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    // Modal Salin / Susulan Ujian
    let originalExamName = '';
    $(document).on('click', '.btn-salin', function() {
        const btn = $(this);
        const id = btn.data('id');
        originalExamName = btn.data('nama') || '';
        const jenjang = btn.data('jenjang');
        const durasi = parseInt(btn.data('durasi')) || 90;
        const acaksoal = btn.data('acaksoal');
        const acakopsi = btn.data('acakopsi');

        $('#salin_source_id').val(id);
        $('#salin_jenjang').val(jenjang);
        $('#salin_durasi').val(durasi);
        $('#salin_acak_soal').prop('checked', parseInt(acaksoal) === 1);
        $('#salin_acak_opsi').prop('checked', parseInt(acakopsi) === 1);
        $('#salin_mode').val('susulan');

        // Update default nama & help text
        updateSalinTitleAndHelp();

        // Default waktu: Mulai sekarang, selesai = sekarang + durasi + 60 menit
        const now = new Date();
        const start = new Date(now.getTime() + 10 * 60000); // 10 menit lagi
        const end = new Date(start.getTime() + (durasi + 60) * 60000);

        $('#salin_mulai').val(formatDateTimeLocal(start));
        $('#salin_selesai').val(formatDateTimeLocal(end));

        $('#modalSalinTest').modal('show');
    });

    $('#salin_mode').on('change', function() {
        updateSalinTitleAndHelp();
    });

    function updateSalinTitleAndHelp() {
        const mode = $('#salin_mode').val();
        let prefix = '[Susulan] ';
        let help = 'Otomatis menyalin semua butir soal terpilih dan HANYA menyertakan siswa yang belum berstatus \'Selesai\' (absen/gagal sebelumnya).';
        
        if (mode === 'semua_siswa') {
            prefix = '[Salinan] ';
            help = 'Menyalin semua butir soal terpilih dan SELURUH siswa dengan status di-reset ke \'Ready\' (untuk sesi atau kelas baru).';
        } else if (mode === 'hanya_soal') {
            prefix = '[Template] ';
            help = 'Menyalin komposisi butir soal terpilih dan pengaturan waktu/acak saja (tanpa mendaftarkan siswa).';
        }

        // Ganti prefix jika nama belum diubah manual yang rumit
        let cleanName = originalExamName.replace(/^\[(Susulan|Salinan|Template)\]\s*/i, '');
        $('#salin_nama').val(prefix + cleanName);
        $('#salin_mode_help').text(help);
    }

    // Modal Edit Test
    $(document).on('click', '.btn-edit', function() {
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

        $('#edit_id').val(id);
        $('#edit_nama').val(nama);
        $('#edit-jenjang').val(jenjang);
        $('#edit_durasi').val(durasi);
        $('#edit_mulai').val(mulai.replace(' ', 'T'));
        $('#edit_selesai').val(selesai.replace(' ', 'T'));
        $('#edit_status').val(status);
        
        $('#edit_acak_soal').prop('checked', parseInt(acaksoal) === 1);
        $('#edit_acak_opsi').prop('checked', parseInt(acakopsi) === 1);

        $('#modalEditTest').modal('show');
    });

    $('#modalEditTest, #modalSalinTest').on('hidden.bs.modal', function () {
        $(this).find('form').trigger('reset');
    });
});
</script>

</body>
</html>