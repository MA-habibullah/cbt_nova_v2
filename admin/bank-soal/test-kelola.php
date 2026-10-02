<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); }

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Ambil Detail Ujian
$stmt_exam = $pdo->prepare("SELECT e.*, s.nama_mapel FROM cbt_exams e JOIN cbt_subjects s ON e.subject_id = s.id WHERE e.id = ?");
$stmt_exam->execute([$exam_id]);
$exam = $stmt_exam->fetch();
if (!$exam) { header("Location: test.php?id=$id_bank"); exit; }

// 2. PROSES UPDATE SETTING UJIAN (Termasuk Toggle Token)
if (isset($_POST['update_setting'])) {
    $is_token = isset($_POST['is_token_aktif']) ? 1 : 0;
    $pdo->prepare("UPDATE cbt_exams SET is_token_aktif = ? WHERE id = ?")->execute([$is_token, $exam_id]);
    header("Location: test-kelola.php?exam_id=$exam_id&id=$id_bank&msg=updated"); exit;
}

// 3. PROSES SIMPAN SOAL PILIHAN
if (isset($_POST['simpan_soal'])) {
    $selected_questions = $_POST['question_ids'] ?? [];
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM cbt_exam_questions WHERE exam_id = ?")->execute([$exam_id]);
        if (!empty($selected_questions)) {
            $stmt_q = $pdo->prepare("INSERT INTO cbt_exam_questions (exam_id, question_id) VALUES (?, ?)");
            foreach ($selected_questions as $q_id) { $stmt_q->execute([$exam_id, (int)$q_id]); }
        }
        $pdo->commit();
        header("Location: test-kelola.php?exam_id=$exam_id&id=$id_bank&msg=soal_success"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: test-kelola.php?exam_id=$exam_id&id=$id_bank&msg=error"); exit;
    }
}

// 4. PROSES SIMPAN PESERTA
if (isset($_POST['simpan_peserta'])) {
    $students = $_POST['student_ids'] ?? [];
    $new_ids  = array_values(array_unique(array_map('intval', $students)));
    $pdo->beginTransaction();
    try {
        // Ambil peserta yang sudah ada
        $stmtEx = $pdo->prepare("SELECT student_id FROM cbt_exam_participants WHERE exam_id = ?");
        $stmtEx->execute([$exam_id]);
        $existing_ids = array_map('intval', $stmtEx->fetchAll(PDO::FETCH_COLUMN));

        // Hapus hanya siswa yang dihapus dari daftar DAN belum mulai (status=ready, soal_ids NULL)
        // Siswa yang sudah mulai/selesai ujian TIDAK dihapus agar soal_ids dan progress-nya terjaga
        $to_remove = array_diff($existing_ids, $new_ids);
        if (!empty($to_remove)) {
            $ph = implode(',', array_fill(0, count($to_remove), '?'));
            $pdo->prepare("DELETE FROM cbt_exam_participants
                WHERE exam_id = ? AND student_id IN ($ph)
                AND status = 'ready' AND soal_ids IS NULL")
                ->execute(array_merge([$exam_id], array_values($to_remove)));
        }

        // Insert hanya siswa baru yang belum ada di daftar peserta
        $to_add = array_diff($new_ids, $existing_ids);
        if (!empty($to_add)) {
            $stmt_ins = $pdo->prepare("INSERT INTO cbt_exam_participants (exam_id, student_id, status) VALUES (?, ?, 'ready')");
            foreach ($to_add as $s_id) { $stmt_ins->execute([$exam_id, $s_id]); }
        }

        $pdo->commit();
        header("Location: test-kelola.php?exam_id=$exam_id&id=$id_bank&msg=peserta_success"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: test-kelola.php?exam_id=$exam_id&id=$id_bank&msg=error"); exit;
    }
}
// Ambil data kelas untuk filter dropdown
$listKelas = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();

// Ambil data jenjang unik untuk filter dropdown
$listJenjang = $pdo->query("SELECT DISTINCT jenjang FROM cbt_classes ORDER BY jenjang ASC")->fetchAll(PDO::FETCH_COLUMN);

// List Agama (Bisa statis jika tidak ada tabelnya)
$listAgama = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'];

// List Sesi
$listSesi = [1, 2, 3];

// 5. AMBIL DATA TAMPILAN
$questions = $pdo->prepare("SELECT * FROM cbt_questions WHERE bank_soal_id = ?");
$questions->execute([$id_bank]);
$allQuestions = $questions->fetchAll();

$stmt_sq = $pdo->prepare("SELECT question_id FROM cbt_exam_questions WHERE exam_id = ?");
$stmt_sq->execute([$exam_id]);
$currentQuestions = $stmt_sq->fetchAll(PDO::FETCH_COLUMN);

// Stats soal yang sudah di-assign ke ujian ini (untuk panel distribusi)
$assignedStatsStmt = $pdo->prepare(
    "SELECT q.tipe, q.tingkat_kesulitan, COUNT(*) AS jumlah
     FROM cbt_exam_questions eq
     JOIN cbt_questions q ON eq.question_id = q.id
     WHERE eq.exam_id = ?
     GROUP BY q.tipe, q.tingkat_kesulitan"
);
$assignedStatsStmt->execute([$exam_id]);
$totalPerTipe      = [];
$totalPerKesulitan = [];
$totalSoalBank     = 0;
foreach ($assignedStatsStmt->fetchAll() as $row) {
    $totalPerTipe[$row['tipe']]                   = ($totalPerTipe[$row['tipe']] ?? 0) + (int)$row['jumlah'];
    $totalPerKesulitan[$row['tingkat_kesulitan']] = ($totalPerKesulitan[$row['tingkat_kesulitan']] ?? 0) + (int)$row['jumlah'];
    $totalSoalBank += (int)$row['jumlah'];
}

// Ringkasan distribusi soal yang sudah dipilih
$distSummaryStmt = $pdo->prepare(
    "SELECT q.tipe, q.tingkat_kesulitan, COUNT(*) AS jumlah
     FROM cbt_exam_questions eq
     JOIN cbt_questions q ON eq.question_id = q.id
     WHERE eq.exam_id = ?
     GROUP BY q.tipe, q.tingkat_kesulitan"
);
$distSummaryStmt->execute([$exam_id]);
$distByTipe      = [];
$distByKesulitan = [];
foreach ($distSummaryStmt->fetchAll() as $row) {
    $distByTipe[$row['tipe']]                       = ($distByTipe[$row['tipe']] ?? 0) + (int)$row['jumlah'];
    $distByKesulitan[$row['tingkat_kesulitan']]     = ($distByKesulitan[$row['tingkat_kesulitan']] ?? 0) + (int)$row['jumlah'];
}
$tipeLabels = [
    'pg' => 'Pilihan Ganda', 'pg_kompleks' => 'Pilihan Ganda Kompleks', 'isian' => 'Isian Singkat',
    'benar_salah' => 'Benar/Salah', 'menjodohkan' => 'Menjodohkan', 'essay' => 'Essay'
];

$listStudents = $pdo->query("SELECT s.*, k.nama_kelas, k.jenjang FROM cbt_students s LEFT JOIN cbt_classes k ON s.class_id = k.id WHERE s.is_aktif = 1 ORDER BY k.jenjang ASC, k.nama_kelas ASC, s.nama_lengkap ASC")->fetchAll();

$stmt_p = $pdo->prepare("SELECT student_id, status, soal_ids FROM cbt_exam_participants WHERE exam_id = ?");
$stmt_p->execute([$exam_id]);
$participants_raw    = $stmt_p->fetchAll();
$currentParticipants = array_column($participants_raw, 'student_id');
// Map student_id → participant info (untuk status badge & lock visual)
$participantMap      = array_column($participants_raw, null, 'student_id');
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center w-100 justify-content-between">
                <div class="d-flex align-items-center">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/test.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3"><i class="fas fa-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0 fw-bold">Kelola Test: <?= $exam['nama_mapel_ujian'] ?></h5>
                        <small class="text-muted">Mata Pelajaran: <?= $exam['nama_mapel'] ?></small>
                    </div>
                </div>
                <form action="" method="POST" class="d-flex align-items-center bg-light p-2 rounded border">
                    <div class="form-check form-switch mb-0 me-3">
                        <input class="form-check-input" type="checkbox" name="is_token_aktif" id="tokenSwitch" <?= $exam['is_token_aktif'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold small" for="tokenSwitch">
                            Token: <?= $exam['is_token_aktif'] ? '<span class="text-primary">'.$exam['token'].'</span>' : '<span class="text-danger">NONAKTIF</span>' ?>
                        </label>
                    </div>
                    <button type="submit" name="update_setting" class="btn btn-sm btn-dark px-3">Update</button>
                </form>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if($flash === 'soal_success'): ?>
                <div class="alert alert-primary border-0 shadow-sm mb-4 alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> Daftar pertanyaan berhasil diperbarui!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($flash === 'peserta_success'): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> Daftar peserta berhasil disimpan!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($flash === 'updated'): ?>
                <div class="alert alert-info border-0 shadow-sm mb-4 alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> Pengaturan token berhasil diperbarui!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($flash === 'error'): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show">
                    <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan, perubahan tidak disimpan.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-3 g-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-primary border-4">
                        <small class="text-muted fw-bold">SOAL TERPILIH</small>
                        <h3 class="fw-bold mb-0"><?= count($currentQuestions) ?> <small class="fs-6 fw-normal">dari <?= count($allQuestions) ?></small></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-success border-4">
                        <small class="text-muted fw-bold">PESERTA TERDAFTAR</small>
                        <h3 class="fw-bold mb-0 text-success"><?= count($currentParticipants) ?> <small class="fs-6 fw-normal text-muted">Siswa</small></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-warning border-4">
                        <small class="text-muted fw-bold">STATUS UJIAN</small>
                        <h3 class="fw-bold mb-0"><?= strtoupper($exam['status']) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Ringkasan Komposisi Pool Soal -->
            <?php if (!empty($distByTipe)): ?>
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body py-2 px-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <small class="text-muted fw-bold me-1">POOL SOAL:</small>
                        <?php foreach ($distByTipe as $tipe => $jml): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2">
                            <?= $tipeLabels[$tipe] ?? strtoupper($tipe) ?>: <?= $jml ?>
                        </span>
                        <?php endforeach; ?>
                        <span class="text-muted mx-1">|</span>
                        <?php
                        $levelColors = ['mudah' => 'success', 'sedang' => 'warning', 'sulit' => 'danger'];
                        foreach ($distByKesulitan as $level => $jml): ?>
                        <span class="badge bg-<?= $levelColors[$level] ?? 'secondary' ?> bg-opacity-10 text-<?= $levelColors[$level] ?? 'secondary' ?> border border-<?= $levelColors[$level] ?? 'secondary' ?> px-2">
                            <?= ucfirst($level) ?>: <?= $jml ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Setting Distribusi Aktif ke Siswa -->
            <?php
            $dist_tipe_set = $exam['distribusi_tipe']     ? json_decode($exam['distribusi_tipe'], true)     : null;
            $dist_kes_set  = $exam['distribusi_kesulitan'] ? json_decode($exam['distribusi_kesulitan'], true) : null;
            $limit_set     = (int)($exam['jumlah_soal_limit'] ?? 0);
            $has_dist      = $limit_set > 0;
            ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-<?= $has_dist ? 'info' : 'secondary' ?> border-3">
                <div class="card-body py-2 px-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <small class="text-<?= $has_dist ? 'info' : 'secondary' ?> fw-bold me-1">
                            <i class="fas fa-sliders-h me-1"></i>DISTRIBUSI KE SISWA:
                        </small>
                        <?php if (!$has_dist): ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2">
                                <i class="fas fa-layer-group me-1"></i>Semua soal ditampilkan
                            </span>
                            <span class="text-muted small ms-1">— distribusi belum di-set, seluruh soal yang di-assign tampil ke semua siswa</span>
                        <?php elseif ($dist_tipe_set): ?>
                            <?php foreach ($dist_tipe_set as $tipe => $jml): ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info px-2">
                                <?= $tipeLabels[$tipe] ?? strtoupper($tipe) ?>: <?= $jml ?>
                            </span>
                            <?php endforeach; ?>
                            <span class="text-muted small ms-1">— tiap siswa mendapat <strong><?= $limit_set ?></strong> soal</span>
                        <?php elseif ($dist_kes_set): ?>
                            <?php foreach ($dist_kes_set as $level => $jml): ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info px-2">
                                <?= ucfirst($level) ?>: <?= $jml ?>
                            </span>
                            <?php endforeach; ?>
                            <span class="text-muted small ms-1">— tiap siswa mendapat <strong><?= $limit_set ?></strong> soal</span>
                        <?php else: ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info px-2">Acak <?= $limit_set ?> soal</span>
                            <span class="text-muted small ms-1">— tiap siswa mendapat <strong><?= $limit_set ?></strong> soal secara acak</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm" id="testTab" role="tablist">
                <li class="nav-item flex-fill">
                    <button class="nav-link active w-100 fw-bold" data-bs-toggle="tab" data-bs-target="#tabSoal">
                        <i class="fas fa-tasks me-2"></i> 1. Pilih Pertanyaan
                    </button>
                </li>
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100 fw-bold" data-bs-toggle="tab" data-bs-target="#tabPeserta">
                        <i class="fas fa-user-check me-2"></i> 2. Pilih Peserta
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabSoal">
                    <form action="" method="POST" class="card border-0 shadow-sm overflow-hidden">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <input type="text" id="searchSoal" class="form-control form-control-sm border-primary" style="max-width:220px;" placeholder="Cari konten soal...">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-info px-3 shadow-sm fw-bold"
                                        data-bs-toggle="modal" data-bs-target="#modalDistribusi">
                                    <i class="fas fa-sliders-h me-2"></i> Atur Distribusi
                                </button>
                                <button type="submit" name="simpan_soal" class="btn btn-primary px-4 shadow-sm fw-bold">
                                    <i class="fas fa-save me-2"></i> Simpan Pilihan Soal
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 500px;">
                            <table class="table table-hover align-middle mb-0" id="tableSoal">
                                <thead class="table-light sticky-top shadow-sm">
                                    <tr>
                                        <th width="60" class="text-center">
                                            <input type="checkbox" class="form-check-input border-primary" id="checkAllSoal">
                                        </th>
                                        <th>Konten Pertanyaan</th>
                                        <th width="150">Tipe</th>
                                        <th width="100" class="text-center">Bobot</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($allQuestions as $q): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="question_ids[]" value="<?= esc($q['id']) ?>" 
                                                   class="form-check-input checkSoal border-primary" 
                                                   <?= in_array($q['id'], $currentQuestions) ? 'checked' : '' ?>>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 550px;">
                                                <?= strip_tags($q['konten_soal']) ?>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border px-3"><?= strtoupper($q['tipe']) ?></span></td>
                                        <td class="text-center fw-bold text-primary"><?= $q['bobot_skor'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="tabPeserta">
                    <form action="" method="POST" class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-4 border-0">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="small fw-bold text-success mb-1">Filter Kelas</label>
                                    <select id="filterKelas" class="form-select form-select-sm border-success">
                                        <option value="">Semua Kelas</option>
                                        <?php foreach($listKelas as $k): ?>
                                            <option value="<?= esc(strtolower($k['jenjang'] . ' - ' . $k['nama_kelas'])) ?>">
                                                <?= $k['jenjang'] ?> - <?= $k['nama_kelas'] ?>
                                            </option>                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-success mb-1">Filter Jenjang</label>
                                    <select id="filterJenjang" class="form-select form-select-sm border-success">
                                        <option value="">Semua Jenjang</option>
                                        <?php foreach($listJenjang as $j): ?>
                                            <option value="<?= esc(strtolower($j)) ?>"><?= esc($j) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="small fw-bold text-success mb-1">Filter Agama</label>
                                    <select id="filterAgama" class="form-select form-select-sm border-success">
                                        <option value="">Semua Agama</option>
                                        <?php foreach($listAgama as $a): ?>
                                            <option value="<?= esc(strtolower($a)) ?>"><?= esc($a) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-success mb-1">Filter Sesi</label>
                                    <select id="filterSesi" class="form-select form-select-sm border-success">
                                        <option value="">Semua Sesi</option>
                                        <?php foreach($listSesi as $s): ?>
                                            <option value="sesi <?= esc($s) ?>">Sesi <?= esc($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-success mb-1">Pencarian Nama</label>
                                    <input type="text" id="searchSiswa" class="form-control form-control-sm border-success" placeholder="Cari Nama...">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="simpan_peserta" class="btn btn-success btn-sm w-100 fw-bold py-2 shadow-sm">
                                        <i class="fas fa-save me-1"></i> Simpan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 500px;">
                            <table class="table table-hover align-middle mb-0" id="tableSiswa">
                                <thead class="table-light sticky-top shadow-sm">
                                    <tr>
                                        <th width="60" class="text-center">
                                            <input type="checkbox" class="form-check-input border-success" id="checkAllSiswa">
                                        </th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Agama</th>
                                        <th class="text-center">Sesi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($listStudents as $s):
                                        $pInfo    = $participantMap[$s['id']] ?? null;
                                        $isLocked = $pInfo && ($pInfo['status'] !== 'ready' || $pInfo['soal_ids'] !== null);
                                        $badge    = null;
                                        if ($pInfo) {
                                            if ($pInfo['status'] === 'finished')         $badge = ['Selesai',       'success'];
                                            elseif ($pInfo['status'] === 'blocked')      $badge = ['Diblokir',      'danger'];
                                            elseif ($pInfo['status'] === 'working')      $badge = ['Sedang Ujian',  'warning'];
                                            elseif ($pInfo['soal_ids'] !== null)         $badge = ['Soal Tersimpan','info'];
                                        }
                                    ?>
                                    <tr class="student-row<?= $isLocked ? ' table-secondary' : '' ?>"<?= $isLocked ? ' style="opacity:0.65"' : '' ?>>
                                        <td class="text-center">
                                            <?php if ($isLocked): ?>
                                            <i class="fas fa-lock text-muted small" title="Tidak dapat dihapus: siswa sudah memulai atau memiliki soal tersimpan"></i>
                                            <input type="hidden" name="student_ids[]" value="<?= esc($s['id']) ?>">
                                            <?php else: ?>
                                            <input type="checkbox" name="student_ids[]" value="<?= esc($s['id']) ?>"
                                                class="form-check-input checkSiswa border-success"
                                                <?= in_array($s['id'], $currentParticipants) ? 'checked' : '' ?>>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= $s['nama_lengkap'] ?></div>
                                            <small class="text-muted"><?= $s['nisn'] ?></small>
                                            <?php if ($badge): ?>
                                            <span class="badge bg-<?= $badge[1] ?><?= $badge[1] === 'warning' ? ' text-dark' : '' ?> ms-1" style="font-size:0.65rem"><?= $badge[0] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-kelas text-lowercase" data-jenjang="<?= esc(strtolower($s['jenjang'])) ?>"><?= esc(isset($s['jenjang']) ? $s['jenjang'] . ' - ' . $s['nama_kelas'] : ($s['nama_kelas'] ?? 'N/A')) ?></td>
                                        <td class="col-agama text-lowercase"><?= $s['agama'] ?? '-' ?></td>
                                        <td class="text-center col-sesi text-lowercase">Sesi <?= $s['sesi'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Distribusi Otomatis -->
<div class="modal fade" id="modalDistribusi" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-sliders-h me-2"></i>Atur Distribusi Soal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0 py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Distribusi menentukan <strong>berapa soal per kategori yang ditampilkan ke tiap siswa</strong>.
                    Soal yang sudah di-assign ke ujian (<strong><?= $totalSoalBank ?></strong> soal) tetap tidak berubah.
                </div>

                <!-- Langkah 1: Total Soal -->
                <div class="mb-4 p-3 rounded border bg-light">
                    <label class="form-label fw-bold small text-muted mb-2">LANGKAH 1 — TOTAL SOAL</label>
                    <div class="d-flex align-items-center gap-3">
                        <input type="number" id="inputTotal" class="form-control fw-bold" style="max-width:130px;font-size:1.1rem;"
                               min="1" max="<?= esc($totalSoalBank) ?>" value="<?= esc($limit_set ?: min(40, $totalSoalBank)) ?>">
                        <span class="text-muted small">dari <strong><?= $totalSoalBank ?></strong> soal yang dipilih</span>
                    </div>
                </div>

                <!-- Langkah 2: Cara Distribusi -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted mb-2">LANGKAH 2 — CARA DISTRIBUSI</label>
                    <div class="d-flex gap-4 mb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="distribusiMode" id="modeTotal" value="total" <?= esc((!$dist_tipe_set && !$dist_kes_set) ? 'checked' : '') ?>>
                            <label class="form-check-label fw-bold" for="modeTotal">Acak Saja</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="distribusiMode" id="modeTipe" value="tipe" <?= esc($dist_tipe_set ? 'checked' : '') ?>>
                            <label class="form-check-label fw-bold" for="modeTipe">Per Jenis Soal</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="distribusiMode" id="modeKesulitan" value="kesulitan" <?= esc($dist_kes_set ? 'checked' : '') ?>>
                            <label class="form-check-label fw-bold" for="modeKesulitan">Per Tingkat Kesulitan</label>
                        </div>
                    </div>
                    <small id="hintMode" class="text-muted">Soal dipilih secara acak dari seluruh bank.</small>
                </div>

                <div id="panelTipe" class="d-none">
                    <p class="small text-muted mb-2">
                        Isi jumlah soal per jenis. Tingkat kesulitan akan dibagi <strong>rata otomatis</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Jenis Soal</th>
                                    <th class="text-center" width="120">Jumlah</th>
                                    <th class="text-center" width="110">Tersedia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $tipeRows = [
                                    'pg'           => 'Pilihan Ganda',
                                    'pg_kompleks'  => 'Pilihan Ganda Kompleks',
                                    'isian'        => 'Isian Singkat',
                                    'benar_salah'  => 'Benar / Salah',
                                    'menjodohkan'  => 'Menjodohkan',
                                    'essay'        => 'Essay',
                                ];
                                foreach ($tipeRows as $val => $label):
                                    $avail = $totalPerTipe[$val] ?? 0;
                                ?>
                                <tr>
                                    <td><?= $label ?></td>
                                    <td class="text-center">
                                        <input type="number" class="form-control form-control-sm text-center input-tipe"
                                               name="tipe[<?= $val ?>]" min="0" max="<?= $avail ?>"
                                               value="<?= esc($dist_tipe_set[$val] ?? 0) ?>" data-max="<?= esc($avail) ?>" <?= esc($avail === 0 ? 'disabled' : '') ?>>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($avail > 0): ?>
                                        <span class="badge bg-secondary"><?= $avail ?></span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">
                                        <span id="counterTipe" class="fw-bold text-warning">0</span>
                                        <span class="fw-normal text-muted"> / <span id="targetTipe"><?= min(40, $totalSoalBank) ?></span></span>
                                    </td>
                                    <td class="text-center text-muted"><?= $totalSoalBank ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small class="text-info"><i class="fas fa-info-circle me-1"></i>Soal dibagi rata ke Mudah / Sedang / Sulit. Total terdistribusi harus sama dengan langkah 1.</small>
                </div>

                <div id="panelKesulitan" class="d-none">
                    <p class="small text-muted mb-2">
                        Isi jumlah soal per tingkat. Jenis soal akan dibagi <strong>rata otomatis</strong> dari yang tersedia.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Tingkat Kesulitan</th>
                                    <th class="text-center" width="120">Jumlah</th>
                                    <th class="text-center" width="110">Tersedia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $levelRows = [
                                    'mudah'  => ['label' => 'Mudah',  'color' => 'success'],
                                    'sedang' => ['label' => 'Sedang', 'color' => 'warning'],
                                    'sulit'  => ['label' => 'Sulit',  'color' => 'danger'],
                                ];
                                foreach ($levelRows as $val => $cfg):
                                    $avail = $totalPerKesulitan[$val] ?? 0;
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $cfg['color'] ?>"><?= $cfg['label'] ?></span></td>
                                    <td class="text-center">
                                        <input type="number" class="form-control form-control-sm text-center input-kesulitan"
                                               name="kesulitan[<?= $val ?>]" min="0" max="<?= $avail ?>"
                                               value="<?= esc($dist_kes_set[$val] ?? 0) ?>" data-max="<?= esc($avail) ?>" <?= esc($avail === 0 ? 'disabled' : '') ?>>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($avail > 0): ?>
                                        <span class="badge bg-<?= $cfg['color'] ?>"><?= $avail ?></span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center">
                                        <span id="counterKesulitan" class="fw-bold text-warning">0</span>
                                        <span class="fw-normal text-muted"> / <span id="targetKesulitan"><?= min(40, $totalSoalBank) ?></span></span>
                                    </td>
                                    <td class="text-center text-muted"><?= $totalSoalBank ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small class="text-info"><i class="fas fa-info-circle me-1"></i>Jenis soal dibagi rata dari yang tersedia. Total terdistribusi harus sama dengan langkah 1.</small>
                </div>

                <div id="distribusiError" class="alert alert-danger border-0 d-none mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btnTerapkan" class="btn btn-info text-white fw-bold px-4">
                    <i class="fas fa-save me-2"></i> Simpan Distribusi
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // --- 1. Pencarian Real-time Soal ---
        $("#searchSoal").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#tableSoal tbody tr").each(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // --- 2. Check All Soal ---
        $("#checkAllSoal").click(function(){
            $(".checkSoal:visible").prop('checked', $(this).prop('checked'));
        });
    });
</script>
<script>
$(document).ready(function() {
    // Fungsi Utama Filter
    function filterSiswa() {
        var kelas   = $("#filterKelas").val().toLowerCase();
        var jenjang = $("#filterJenjang").val().toLowerCase(); // Ambil nilai jenjang
        var agama   = $("#filterAgama").val().toLowerCase();
        var sesi    = $("#filterSesi").val().toLowerCase();
        var nama    = $("#searchSiswa").val().toLowerCase();

        $("#tableSiswa tbody tr").each(function() {
            var $row = $(this);
            // Konversi ke String untuk mencegah error .indexOf pada angka
            var valJenjang   = String($row.find(".col-kelas").data('jenjang') || "").toLowerCase();
            var txtKelas     = $row.find(".col-kelas").text().toLowerCase();
            var txtAgama     = $row.find(".col-agama").text().toLowerCase();
            var txtSesi      = $row.find(".col-sesi").text().toLowerCase();
            var txtNama      = $row.find("td:nth-child(2)").text().toLowerCase();

            var matchKelas   = txtKelas.indexOf(kelas) > -1;
            var matchJenjang = valJenjang.indexOf(jenjang) > -1;
            var matchAgama   = txtAgama.indexOf(agama) > -1;
            var matchSesi    = txtSesi.indexOf(sesi) > -1;
            var matchNama    = txtNama.indexOf(nama) > -1;

            $row.toggle(matchKelas && matchJenjang && matchAgama && matchSesi && matchNama);
        });
    }

    $("#filterKelas, #filterJenjang, #filterAgama, #filterSesi, #searchSiswa").on("change keyup", function() {
        filterSiswa();
    });

    // Check All Siswa
    $("#checkAllSiswa").click(function(){
        $(".checkSiswa:visible").prop('checked', $(this).prop('checked'));
    });

    // ── Distribusi Otomatis ──

    const hintMap = {
        total:     'Soal dipilih secara acak dari seluruh bank.',
        tipe:      'Soal dibagi rata ke Mudah / Sedang / Sulit. Total terdistribusi harus sama dengan langkah 1.',
        kesulitan: 'Jenis soal dibagi rata dari yang tersedia. Total terdistribusi harus sama dengan langkah 1.'
    };
    const tipeLabel = {
        pg: 'Pilihan Ganda', pg_kompleks: 'Pilihan Ganda Kompleks', isian: 'Isian Singkat',
        benar_salah: 'Benar/Salah', menjodohkan: 'Menjodohkan', essay: 'Essay'
    };
    const kesulitanLabel = { mudah: 'Mudah', sedang: 'Sedang', sulit: 'Sulit' };

    function updateDistribusiCounter() {
        const target = parseInt($('#inputTotal').val()) || 0;
        $('#targetTipe, #targetKesulitan').text(target);

        function applyColor($el, val, tgt) {
            $el.removeClass('text-warning text-success text-danger');
            if (val === tgt && tgt > 0)  $el.addClass('text-success');
            else if (val > tgt)          $el.addClass('text-danger');
            else                         $el.addClass('text-warning');
        }

        let sumTipe = 0;
        $('.input-tipe').each(function() { sumTipe += parseInt($(this).val()) || 0; });
        applyColor($('#counterTipe').text(sumTipe), sumTipe, target);

        let sumKes = 0;
        $('.input-kesulitan').each(function() { sumKes += parseInt($(this).val()) || 0; });
        applyColor($('#counterKesulitan').text(sumKes), sumKes, target);
    }

    $('input[name="distribusiMode"]').on('change', function() {
        const mode = $(this).val();
        $('#panelTipe, #panelKesulitan').addClass('d-none');
        if (mode === 'tipe')       $('#panelTipe').removeClass('d-none');
        if (mode === 'kesulitan')  $('#panelKesulitan').removeClass('d-none');
        $('#hintMode').text(hintMap[mode] || '');
        $('#distribusiError').addClass('d-none');
        $('.input-tipe, .input-kesulitan, #inputTotal').removeClass('is-invalid');
        updateDistribusiCounter();
    });

    $('#inputTotal').on('input', updateDistribusiCounter);
    $(document).on('input', '.input-tipe, .input-kesulitan', updateDistribusiCounter);

    $('#btnTerapkan').on('click', function() {
        const mode    = $('input[name="distribusiMode"]:checked').val();
        const data    = { exam_id: <?= $exam_id ?>, mode: mode };
        const total   = parseInt($('#inputTotal').val()) || 0;
        const maxBank = <?= $totalSoalBank ?>;
        let errors    = [];

        $('.input-tipe, .input-kesulitan, #inputTotal').removeClass('is-invalid');

        if (total <= 0) {
            $('#inputTotal').addClass('is-invalid');
            errors.push('Jumlah total soal harus lebih dari 0.');
        } else if (total > maxBank) {
            $('#inputTotal').addClass('is-invalid');
            errors.push('Jumlah melebihi stok bank (' + maxBank + ' tersedia).');
        }

        if (errors.length === 0) {
            if (mode === 'total') {
                data.jumlah = total;
            } else {
                const selector = mode === 'tipe' ? '.input-tipe' : '.input-kesulitan';
                const prefix   = mode === 'tipe' ? 'tipe[' : 'kesulitan[';
                data['distribusi'] = {};
                let sum = 0, hasExceeded = false;

                $(selector).each(function() {
                    const v   = parseInt($(this).val()) || 0;
                    const max = parseInt($(this).data('max')) || 0;
                    const key = $(this).attr('name').replace(prefix, '').replace(']', '');
                    if (v > max) {
                        $(this).addClass('is-invalid');
                        const label = mode === 'tipe' ? (tipeLabel[key] || key) : (kesulitanLabel[key] || key);
                        errors.push(label + ': minta ' + v + ', tersedia ' + max + '.');
                        hasExceeded = true;
                    } else if (v > 0) {
                        data['distribusi'][key] = v;
                        sum += v;
                    }
                });

                if (!hasExceeded) {
                    if (sum === 0) {
                        errors.push('Isi minimal satu ' + (mode === 'tipe' ? 'jenis soal' : 'tingkat kesulitan') + '.');
                    } else if (sum !== total) {
                        errors.push('Total terdistribusi (' + sum + ') harus sama dengan jumlah total soal (' + total + ').');
                    }
                }
            }
        }

        if (errors.length > 0) {
            $('#distribusiError').html(errors.join('<br>')).removeClass('d-none');
            return;
        }

        $('#distribusiError').addClass('d-none');
        $('#btnTerapkan').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Memproses...');

        $.ajax({
            url: 'ajax-distribusi-soal.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(r) {
                if (r.success) {
                    $('#modalDistribusi').modal('hide');
                    Swal.fire({
                        icon: 'success', title: 'Berhasil!', text: r.message,
                        timer: 2000, showConfirmButton: false
                    }).then(function() { location.reload(); });
                } else {
                    $('#distribusiError').text(r.message).removeClass('d-none');
                    $('#btnTerapkan').prop('disabled', false).html('<i class="fas fa-save me-2"></i> Simpan Distribusi');
                }
            },
            error: function() {
                $('#distribusiError').text('Koneksi gagal. Coba lagi.').removeClass('d-none');
                $('#btnTerapkan').prop('disabled', false).html('<i class="fas fa-magic me-2"></i> Terapkan');
            }
        });
    });

    function ucFirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    $('#modalDistribusi').on('shown.bs.modal', function() {
        $('input[name="distribusiMode"]:checked').trigger('change');
    });

    $('#modalDistribusi').on('hidden.bs.modal', function() {
        $('#distribusiError').addClass('d-none');
        $('.input-tipe, .input-kesulitan, #inputTotal').removeClass('is-invalid');
        $('input[name="distribusiMode"][value="total"]').prop('checked', true).trigger('change');
        $('.input-tipe, .input-kesulitan').val(0);
    });
});
</script>
</body>
</html>