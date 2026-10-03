<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

// Helper: cek status bank soal milik guru
function bankStatus($pdo, $bank_id, $teacher_id): string {
    $s = $pdo->prepare("SELECT status FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?");
    $s->execute([$bank_id, $teacher_id]);
    $r = $s->fetch();
    return $r ? $r['status'] : '';
}

// --- 1. PROSES FORM (TAMBAH JADWAL, EDIT, HAPUS, REFRESH TOKEN, TOGGLE TOKEN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    // Tambah Jadwal Baru
    if ($act === 'tambah_jadwal') {
        $bank_id    = (int)($_POST['bank_soal_id'] ?? 0);
        $nama_test  = trim($_POST['nama_test'] ?? '');
        $jenjang    = trim($_POST['jenjang'] ?? '10');
        $durasi     = max(5, (int)($_POST['durasi'] ?? 60));
        $mulai      = trim($_POST['tgl_mulai'] ?? '');
        $selesai    = trim($_POST['tgl_selesai'] ?? '');
        $status     = in_array($_POST['status'] ?? '', ['draft', 'aktif', 'selesai']) ? $_POST['status'] : 'draft';
        $acak_soal  = isset($_POST['acak_soal']) ? 1 : 0;
        $acak_opsi  = isset($_POST['acak_opsi']) ? 1 : 0;
        $tampil_nilai = isset($_POST['tampilkan_nilai']) ? 1 : 0;

        if (strtotime($selesai) <= strtotime($mulai)) {
            header("Location: index.php?msg=date_invalid");
            exit;
        }

        $chk = $pdo->prepare("SELECT id, subject_id, status FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?");
        $chk->execute([$bank_id, $teacher_id]);
        $bk = $chk->fetch();

        if ($bk) {
            if ($bk['status'] === 'nonaktif') {
                header("Location: index.php?msg=locked");
                exit;
            }

            $token = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $stmt = $pdo->prepare("INSERT INTO cbt_exams 
                (nama_mapel_ujian, jenjang, bank_soal_id, subject_id, teacher_id, durasi_menit, mulai_pada, selesai_pada, status, token, is_token_aktif, acak_soal, acak_opsi, tampilkan_nilai, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())");
            $stmt->execute([
                $nama_test, $jenjang, $bank_id, $bk['subject_id'], $teacher_id,
                $durasi, $mulai, $selesai, $status, $token, $acak_soal, $acak_opsi, $tampil_nilai
            ]);
            $new_exam_id = (int)$pdo->lastInsertId();

            // Salin butir soal ke exam_questions
            $pdo->prepare("INSERT INTO cbt_exam_questions (exam_id, question_id) 
                           SELECT ?, id FROM cbt_questions WHERE bank_soal_id = ?")
                ->execute([$new_exam_id, $bank_id]);

            log_activity("Guru tambah jadwal ujian: $nama_test (ID: $new_exam_id)", $teacher_id, 'guru', null, 'ujian');
            header("Location: index.php?msg=test_added");
            exit;
        }
        header("Location: index.php?msg=invalid");
        exit;
    }

    // Edit Jadwal Ujian
    if ($act === 'edit_jadwal') {
        $exam_id    = (int)($_POST['exam_id'] ?? 0);
        $nama_test  = trim($_POST['nama_test'] ?? '');
        $jenjang    = trim($_POST['jenjang'] ?? '10');
        $durasi     = max(5, (int)($_POST['durasi'] ?? 60));
        $mulai      = trim($_POST['tgl_mulai'] ?? '');
        $selesai    = trim($_POST['tgl_selesai'] ?? '');
        $status     = in_array($_POST['status'] ?? '', ['draft', 'aktif', 'selesai']) ? $_POST['status'] : 'draft';
        $acak_soal  = isset($_POST['acak_soal']) ? 1 : 0;
        $acak_opsi  = isset($_POST['acak_opsi']) ? 1 : 0;
        $tampil_nilai = isset($_POST['tampilkan_nilai']) ? 1 : 0;

        if (strtotime($selesai) <= strtotime($mulai)) {
            header("Location: index.php?msg=date_invalid");
            exit;
        }

        $ex = $pdo->prepare("SELECT bank_soal_id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
        $ex->execute([$exam_id, $teacher_id]);
        $exRow = $ex->fetch();

        if ($exRow) {
            if (bankStatus($pdo, $exRow['bank_soal_id'], $teacher_id) === 'nonaktif') {
                header("Location: index.php?msg=locked");
                exit;
            }

            $pdo->prepare("UPDATE cbt_exams SET nama_mapel_ujian=?, jenjang=?, durasi_menit=?, mulai_pada=?, selesai_pada=?, status=?, acak_soal=?, acak_opsi=?, tampilkan_nilai=?, updated_at=NOW()
                           WHERE id=? AND teacher_id=?")
                ->execute([
                    $nama_test, $jenjang, $durasi, $mulai, $selesai, $status,
                    $acak_soal, $acak_opsi, $tampil_nilai, $exam_id, $teacher_id
                ]);

            log_activity("Guru update jadwal ujian ID $exam_id: $nama_test", $teacher_id, 'guru', null, 'ujian');
            header("Location: index.php?msg=test_updated");
            exit;
        }
        header("Location: index.php?msg=invalid");
        exit;
    }

    // Refresh Token Ujian
    if ($act === 'refresh_token') {
        $exam_id   = (int)($_POST['exam_id'] ?? 0);
        $new_token = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $chk = $pdo->prepare("SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
        $chk->execute([$exam_id, $teacher_id]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE cbt_exams SET token = ?, is_token_aktif = 1, updated_at = NOW() WHERE id = ? AND teacher_id = ?")
                ->execute([$new_token, $exam_id, $teacher_id]);
            log_activity("Guru generate token baru ujian ID $exam_id: $new_token", $teacher_id, 'guru', null, 'ujian');
            header("Location: index.php?msg=token_updated");
            exit;
        }
    }

    // Toggle Token
    if ($act === 'toggle_token') {
        $exam_id   = (int)($_POST['exam_id'] ?? 0);
        $curr_stat = (int)($_POST['current_token_status'] ?? 0);
        $new_stat  = ($curr_stat === 1) ? 0 : 1;

        $chk = $pdo->prepare("SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
        $chk->execute([$exam_id, $teacher_id]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE cbt_exams SET is_token_aktif = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?")
                ->execute([$new_stat, $exam_id, $teacher_id]);
            log_activity("Guru ubah status rilis token ujian ID $exam_id menjadi $new_stat", $teacher_id, 'guru', null, 'ujian');
            header("Location: index.php?msg=token_status_changed");
            exit;
        }
    }

    // Hapus Jadwal Ujian
    if ($act === 'hapus_jadwal') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $exRow = $pdo->prepare("SELECT bank_soal_id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
        $exRow->execute([$exam_id, $teacher_id]);
        $exData = $exRow->fetch();

        if ($exData) {
            if (bankStatus($pdo, $exData['bank_soal_id'], $teacher_id) === 'nonaktif') {
                header("Location: index.php?msg=locked");
                exit;
            }
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM cbt_cheat_logs WHERE exam_id = ?")->execute([$exam_id]);
                $pdo->prepare("DELETE sa FROM cbt_student_answers sa JOIN cbt_exam_participants ep ON sa.participant_id = ep.id WHERE ep.exam_id = ?")->execute([$exam_id]);
                $pdo->prepare("DELETE FROM cbt_exam_participants WHERE exam_id = ?")->execute([$exam_id]);
                $pdo->prepare("DELETE FROM cbt_exam_questions WHERE exam_id = ?")->execute([$exam_id]);
                $pdo->prepare("DELETE FROM cbt_exams WHERE id = ? AND teacher_id = ?")->execute([$exam_id, $teacher_id]);
                $pdo->commit();
                log_activity("Guru hapus jadwal ujian ID $exam_id", $teacher_id, 'guru', null, 'ujian');
                header("Location: index.php?msg=test_deleted");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                header("Location: index.php?msg=error");
                exit;
            }
        }
    }
}

// --- 2. PARAMETER FILTER & PAGINATION ---
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = max(10, min(500, (int)($_GET['limit'] ?? 50)));
$search         = trim($_GET['q'] ?? '');
$filter_jenjang = trim($_GET['jenjang'] ?? 'all');
$filter_status  = trim($_GET['status'] ?? 'all');
$f_bank         = (int)($_GET['bank_soal_id'] ?? 0);
$tgl_mulai      = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai    = trim($_GET['tgl_selesai'] ?? '');

$where  = "WHERE e.teacher_id = ?";
$params = [$teacher_id];

if ($filter_jenjang !== 'all' && in_array($filter_jenjang, ['10', '11', '12'])) {
    $where .= " AND e.jenjang = ?";
    $params[] = $filter_jenjang;
}

if ($f_bank > 0) {
    $where .= " AND e.bank_soal_id = ?";
    $params[] = $f_bank;
}

if ($filter_status !== 'all') {
    if ($filter_status === 'live') {
        $where .= " AND e.status = 'aktif' AND NOW() BETWEEN e.mulai_pada AND e.selesai_pada";
    } elseif ($filter_status === 'upcoming') {
        $where .= " AND (e.status = 'draft' OR (e.status = 'aktif' AND e.mulai_pada > NOW()))";
    } elseif ($filter_status === 'completed') {
        $where .= " AND (e.status = 'selesai' OR (e.status = 'aktif' AND e.selesai_pada < NOW()))";
    } elseif (in_array($filter_status, ['draft', 'aktif', 'selesai'])) {
        $where .= " AND e.status = ?";
        $params[] = $filter_status;
    }
}

if ($search !== '') {
    $where .= " AND (e.nama_mapel_ujian LIKE ? OR s.nama_mapel LIKE ? OR b.nama_bank_soal LIKE ? OR e.token LIKE ?)";
    $sw = "%" . like_escape($search) . "%";
    array_push($params, $sw, $sw, $sw, $sw);
}

if ($tgl_mulai !== '' && $tgl_selesai !== '') {
    $where .= " AND DATE(e.mulai_pada) BETWEEN ? AND ?";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;
} elseif ($tgl_mulai !== '') {
    $where .= " AND DATE(e.mulai_pada) >= ?";
    $params[] = $tgl_mulai;
} elseif ($tgl_selesai !== '') {
    $where .= " AND DATE(e.mulai_pada) <= ?";
    $params[] = $tgl_selesai;
}

$base_from = "FROM cbt_exams e
              JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
              JOIN cbt_subjects s ON e.subject_id = s.id";

// Hitung total data
$countQuery = "SELECT COUNT(DISTINCT e.id) $base_from $where";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// Ambil Data Jadwal Ujian
$dataQuery = "SELECT e.*, b.nama_bank_soal, b.kode_bank_soal, b.status AS bank_status, s.nama_mapel, s.kode_mapel,
                     COUNT(DISTINCT eq.id) AS jumlah_soal,
                     COUNT(DISTINCT ep.id) AS jumlah_peserta,
                     COUNT(DISTINCT CASE WHEN ep.status = 'finished' THEN ep.id END) AS peserta_selesai,
                     COUNT(DISTINCT CASE WHEN ep.status = 'working' THEN ep.id END) AS peserta_mengerjakan
              $base_from
              LEFT JOIN cbt_exam_questions eq ON eq.exam_id = e.id
              LEFT JOIN cbt_exam_participants ep ON ep.exam_id = e.id
              $where
              GROUP BY e.id
              ORDER BY e.mulai_pada DESC, e.id DESC
              LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$limit, $offset]);
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($dataParams);
$listExams = $stmt->fetchAll();

// --- 3. OVERVIEW METRIC CARDS (GURU SCOPE) ---
$st_tj = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ?");
$st_tj->execute([$teacher_id]);
$metric_total_jadwal = (int)$st_tj->fetchColumn();

$st_ln = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ? AND status = 'aktif' AND NOW() BETWEEN mulai_pada AND selesai_pada");
$st_ln->execute([$teacher_id]);
$metric_live_now     = (int)$st_ln->fetchColumn();

$st_up = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ? AND (status = 'draft' OR (status = 'aktif' AND mulai_pada > NOW()))");
$st_up->execute([$teacher_id]);
$metric_upcoming     = (int)$st_up->fetchColumn();

$st_cp = $pdo->prepare("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ? AND (status = 'selesai' OR (status = 'aktif' AND selesai_pada < NOW()))");
$st_cp->execute([$teacher_id]);
$metric_completed    = (int)$st_cp->fetchColumn();

// Bank Soal Milik Guru
$myBanks = $pdo->prepare("SELECT b.id, b.nama_bank_soal, b.kode_bank_soal, b.status, b.jenjang, s.nama_mapel, s.kode_mapel 
                          FROM cbt_bank_soal b 
                          JOIN cbt_subjects s ON b.subject_id = s.id 
                          WHERE b.teacher_id = ? 
                          ORDER BY b.nama_bank_soal ASC");
$myBanks->execute([$teacher_id]);
$bankList = $myBanks->fetchAll();

$has_filter = $search !== '' || $filter_jenjang !== 'all' || $filter_status !== 'all' || $f_bank > 0 || $tgl_mulai !== '' || $tgl_selesai !== '' || $limit !== 50;

function gJadwalQuery(array $extra = []): string {
    $p = array_filter(array_merge([
        'q'            => $GLOBALS['search'],
        'status'       => $GLOBALS['filter_status'],
        'jenjang'      => $GLOBALS['filter_jenjang'],
        'bank_soal_id' => $GLOBALS['f_bank'] ?: '',
        'tgl_mulai'    => $GLOBALS['tgl_mulai'],
        'tgl_selesai'  => $GLOBALS['tgl_selesai'],
        'limit'        => $GLOBALS['limit'],
    ], $extra), fn($v) => $v !== '' && $v !== null && $v !== 'all');
    return http_build_query($p);
}
?>

<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>

<style>
/* Status Dropdown Styling */
.select-status { font-size: 0.78rem; font-weight: 700; border-radius: 6px; padding: 4px 8px; cursor: pointer; transition: all 0.2s; }
.status-aktif { background-color: #198754; color: white; border-color: #198754; }
.status-draft { background-color: #6c757d; color: white; border-color: #6c757d; }
.status-selesai { background-color: #dc3545; color: white; border-color: #dc3545; }
.select-status:focus { box-shadow: none; color: white; }

/* Pulsing Live Badge Animation */
@keyframes pulse-live {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
}
.live-badge-pulse {
    animation: pulse-live 1.8s infinite;
}

/* Custom Horizontal Scrollbar */
.table-responsive {
    overflow-x: auto;
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
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm border-bottom">
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <div class="ms-3 d-flex align-items-center">
                <i class="fas fa-calendar-alt text-primary fs-5 me-2"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Jadwal Ujian Saya</h5>
                    <small class="text-muted">Kelola sesi pelaksanaan tes, token peserta, dan monitoring ujian</small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 py-3">
            
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= esc(BASE_URL) ?>guru/index.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Jadwal Ujian</li>
                </ol>
            </nav>

            <!-- Flash Alerts -->
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if (!empty($flash)): ?>
            <?php
                $alertClass = in_array($flash, ['test_added','test_updated','test_deleted','token_updated','token_status_changed']) ? 'success' : 'danger';
                $alertIcon  = $alertClass === 'success' ? 'check-circle' : 'exclamation-triangle';
                $alertMsg   = match($flash) {
                    'test_added'           => 'Jadwal pelaksanaan ujian baru berhasil dibuat.',
                    'test_updated'         => 'Data jadwal ujian berhasil diperbarui.',
                    'test_deleted'         => 'Jadwal ujian berhasil dihapus.',
                    'token_updated'        => 'Token ujian baru berhasil digenerate.',
                    'token_status_changed' => 'Status rilis token ujian berhasil diubah.',
                    'locked'               => 'Bank soal terkait terkunci oleh admin. Jadwal tidak dapat dimodifikasi.',
                    'date_invalid'         => 'Rentang waktu tidak valid: Waktu Selesai harus lebih besar dari Waktu Mulai.',
                    'invalid'              => 'Data form tidak valid atau Anda tidak memiliki hak akses.',
                    default                => 'Terjadi kesalahan sistem. Silakan coba kembali.',
                };
            ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fas fa-<?= $alertIcon ?> me-2"></i>
                <strong><?= $alertClass === 'success' ? 'Berhasil!' : 'Perhatian!' ?></strong> <?= $alertMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Metric Cards (Overview Statistik Guru) -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">TOTAL JADWAL SAYA</span>
                                <h3 class="fw-bold mb-0 mt-1 text-dark"><?= number_format($metric_total_jadwal, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-calendar me-1 text-primary"></i>Seluruh sesi yang Anda buat</small>
                            </div>
                            <div class="rounded-3 p-3 bg-primary-subtle text-primary">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">SEDANG BERLANGSUNG</span>
                                <h3 class="fw-bold mb-0 mt-1 text-success"><?= number_format($metric_live_now, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-circle text-success me-1"></i>Siswa aktif mengerjakan</small>
                            </div>
                            <div class="rounded-3 p-3 bg-success-subtle text-success">
                                <i class="fas fa-play-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">MENDATANG / DRAFT</span>
                                <h3 class="fw-bold mb-0 mt-1 text-info"><?= number_format($metric_upcoming, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-clock me-1 text-info"></i>Sesi terjadwal</small>
                            </div>
                            <div class="rounded-3 p-3 bg-info-subtle text-info">
                                <i class="fas fa-hourglass-half fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">SELESAI / DITUTUP</span>
                                <h3 class="fw-bold mb-0 mt-1 text-secondary"><?= number_format($metric_completed, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-check-double me-1 text-secondary"></i>Siap olah nilai</small>
                            </div>
                            <div class="rounded-3 p-3 bg-secondary-subtle text-secondary">
                                <i class="fas fa-archive fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-list-alt text-primary me-2"></i>Daftar Sesi Jadwal</h6>
                        <span class="badge bg-light text-secondary border px-2 py-1"><?= number_format($totalRows, 0, ',', '.') ?> jadwal ditemukan</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?= esc(BASE_URL) ?>guru/monitoring/index.php" class="btn btn-sm btn-outline-primary shadow-sm fw-semibold">
                            <i class="fas fa-desktop me-1"></i> Monitoring Proktor
                        </a>
                        <button class="btn btn-sm btn-primary shadow-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalTambahTest">
                            <i class="fas fa-plus-circle me-1"></i> Buat Jadwal Baru
                        </button>
                    </div>
                </div>
            </div>

            <!-- Unified Filter Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end" id="filterForm">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-search me-1"></i>Pencarian Jadwal</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Nama Ujian, Mapel, Token..." value="<?= esc($search) ?>">
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-toggle-on me-1"></i>Status Pelaksanaan</label>
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= ($filter_status === 'all' ? 'selected' : '') ?>>Semua Status</option>
                                <option value="live" <?= ($filter_status === 'live' ? 'selected' : '') ?>>Sedang Berlangsung (Live)</option>
                                <option value="upcoming" <?= ($filter_status === 'upcoming' ? 'selected' : '') ?>>Mendatang / Draft</option>
                                <option value="completed" <?= ($filter_status === 'completed' ? 'selected' : '') ?>>Selesai</option>
                                <option value="draft" <?= ($filter_status === 'draft' ? 'selected' : '') ?>>Status: DRAFT</option>
                                <option value="aktif" <?= ($filter_status === 'aktif' ? 'selected' : '') ?>>Status: AKTIF</option>
                                <option value="selesai" <?= ($filter_status === 'selesai' ? 'selected' : '') ?>>Status: SELESAI</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-layer-group me-1"></i>Tingkat Jenjang</label>
                            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= ($filter_jenjang === 'all' ? 'selected' : '') ?>>Semua Jenjang</option>
                                <option value="10" <?= ($filter_jenjang === '10' ? 'selected' : '') ?>>Kelas 10 (Fase E)</option>
                                <option value="11" <?= ($filter_jenjang === '11' ? 'selected' : '') ?>>Kelas 11 (Fase F)</option>
                                <option value="12" <?= ($filter_jenjang === '12' ? 'selected' : '') ?>>Kelas 12 (Fase F+)</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-calendar-day me-1"></i>Dari Tanggal</label>
                            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= esc($tgl_mulai) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-calendar-check me-1"></i>Sampai Tanggal</label>
                            <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= esc($tgl_selesai) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-lg-1 col-md-2 col-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-list me-1"></i>Baris</label>
                            <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="25"  <?= ($limit === 25  ? 'selected' : '') ?>>25</option>
                                <option value="50"  <?= ($limit === 50  ? 'selected' : '') ?>>50</option>
                                <option value="100" <?= ($limit === 100 ? 'selected' : '') ?>>100</option>
                                <option value="200" <?= ($limit === 200 ? 'selected' : '') ?>>200</option>
                            </select>
                        </div>

                        <?php if ($has_filter): ?>
                        <div class="col-12 text-end mt-1">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary px-3"><i class="fas fa-redo me-1"></i> Reset Semua Filter</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0" style="min-width: 950px;">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                            <tr>
                                <th class="text-center py-3" width="50">#</th>
                                <th class="py-3">Identitas Ujian</th>
                                <th class="py-3 d-none d-sm-table-cell">Jenjang & Bank Soal</th>
                                <th class="py-3 text-center">Jadwal & Waktu</th>
                                <th class="py-3 text-center d-none d-lg-table-cell">Soal & Peserta</th>
                                <th class="py-3 text-center" width="130">Token Ujian</th>
                                <th class="py-3 text-center" width="130">Status</th>
                                <th class="text-center py-3" width="180">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listExams)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <div class="py-4">
                                            <i class="fas fa-calendar-times fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                                            <h6 class="fw-bold text-secondary mb-1">Belum Ada Jadwal Ujian</h6>
                                            <p class="small text-muted mb-3">Mulai jadwalkan sesi ujian dari bank soal yang Anda miliki.</p>
                                            <button class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahTest">
                                                <i class="fas fa-plus-circle me-1"></i> Buat Jadwal Baru
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($listExams as $i => $e): ?>
                                <?php 
                                    $now_ts   = time();
                                    $start_ts = strtotime($e['mulai_pada']);
                                    $end_ts   = strtotime($e['selesai_pada']);
                                    $is_live  = ($e['status'] === 'aktif' && $now_ts >= $start_ts && $now_ts <= $end_ts);
                                    $is_bank_locked = ($e['bank_status'] === 'nonaktif');
                                ?>
                                <tr class="<?= $is_live ? 'table-success bg-opacity-25' : '' ?>">
                                    <td class="text-center fw-bold text-secondary small"><?= $offset + $i + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($is_live): ?>
                                                <span class="badge bg-success text-white px-2 py-1 rounded-pill live-badge-pulse" style="font-size:0.68rem;">
                                                    <i class="fas fa-broadcast-tower me-1"></i>LIVE
                                                </span>
                                            <?php endif; ?>
                                            <div class="fw-bold text-dark fs-6"><?= esc($e['nama_mapel_ujian']) ?></div>
                                        </div>
                                        <div class="small text-dark fw-semibold mt-1">
                                            <i class="fas fa-book-open text-primary me-1"></i><?= esc($e['kode_mapel']) ?> - <?= esc($e['nama_mapel']) ?>
                                        </div>
                                        <!-- Sub-label untuk Tampilan Mobile (< 768px) -->
                                        <div class="d-md-none mt-1 small">
                                            <span class="badge badge-soft-info me-1">Kelas <?= esc($e['jenjang']) ?></span>
                                            <span class="badge badge-soft-secondary me-1"><i class="far fa-clock text-primary me-1"></i><?= (int)$e['durasi_menit'] ?>m</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle me-1"><i class="fas fa-check-double me-1"></i><?= (int)$e['peserta_selesai'] ?>/<?= (int)$e['jumlah_peserta'] ?> Selesai</span>
                                        </div>
                                    </td>
                                    <td class="d-none d-sm-table-cell">
                                        <div class="mb-1">
                                            <span class="badge badge-soft-primary px-2.5 py-1 rounded-2 fw-bold">
                                                <i class="fas fa-graduation-cap me-1"></i>Kelas <?= esc($e['jenjang']) ?>
                                            </span>
                                        </div>
                                        <span class="badge badge-soft-secondary border font-monospace px-2 py-1" style="font-size:0.75rem;" title="<?= esc($e['nama_bank_soal']) ?>">
                                            <i class="fas fa-database me-1 text-primary"></i><?= esc($e['kode_bank_soal'] ?: $e['nama_bank_soal']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold text-dark small"><?= date('d M Y', $start_ts) ?></div>
                                        <div class="small fw-semibold text-secondary"><?= date('H:i', $start_ts) ?> &ndash; <?= date('H:i', $end_ts) ?> WIB</div>
                                        <div class="mt-1">
                                            <span class="badge badge-soft-secondary px-2 py-1" style="font-size:0.72rem;">
                                                <i class="far fa-clock text-primary me-1"></i><?= (int)$e['durasi_menit'] ?> Menit
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center d-none d-lg-table-cell">
                                        <div class="mb-1">
                                            <span class="badge badge-soft-info px-2 py-1">
                                                <i class="fas fa-tasks me-1"></i><?= (int)$e['jumlah_soal'] ?> Soal
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <span class="badge badge-soft-primary px-2 py-1" style="font-size:0.75rem;" title="Total Siswa Terdaftar">
                                                <i class="fas fa-user-graduate me-1"></i><?= (int)$e['jumlah_peserta'] ?> Peserta
                                            </span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 fw-bold" style="font-size:0.72rem;" title="Siswa yang telah menyelesaikan ujian">
                                                <i class="fas fa-check-double me-1"></i><?= (int)$e['peserta_selesai'] ?> Selesai
                                            </span>
                                            <?php if ((int)($e['peserta_mengerjakan'] ?? 0) > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-0.5" style="font-size:0.68rem;" title="Siswa sedang aktif mengerjakan ujian">
                                                    <i class="fas fa-spinner fa-spin me-1"></i><?= (int)$e['peserta_mengerjakan'] ?> Mengerjakan
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$e['is_token_aktif'] === 1 && !empty($e['token'])): ?>
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <code class="fw-bold fs-6 text-primary bg-white px-2 py-1 border border-primary-subtle rounded shadow-sm font-monospace"><?= esc($e['token']) ?></code>
                                                <button type="button" class="btn btn-sm btn-light border btn-copy-token p-1 px-2 shadow-none" 
                                                        data-token="<?= esc($e['token']) ?>" title="Salin Token">
                                                    <i class="far fa-copy text-secondary"></i>
                                                </button>
                                            </div>
                                            <div class="mt-1">
                                                <span class="badge badge-soft-success px-2 py-0.5" style="font-size:0.68rem;">
                                                    <i class="fas fa-check-circle me-1"></i>Rilis Aktif
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary px-2 py-1">
                                                <i class="fas fa-lock me-1 text-muted"></i>Nonaktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($e['status'] === 'aktif'): ?>
                                            <span class="badge badge-soft-success px-2 py-1 rounded-pill">
                                                <i class="fas fa-check-circle me-1"></i>Aktif
                                            </span>
                                        <?php elseif ($e['status'] === 'draft'): ?>
                                            <span class="badge badge-soft-secondary px-2 py-1 rounded-pill">
                                                <i class="fas fa-file-alt me-1"></i>Draft
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary px-2 py-1 rounded-pill">
                                                <i class="fas fa-check-double me-1"></i>Selesai
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1.5 flex-nowrap">
                                            <a href="<?= esc(BASE_URL) ?>guru/bank-soal/test-kelola.php?exam_id=<?= (int)$e['id'] ?>&id=<?= (int)$e['bank_soal_id'] ?>" 
                                               class="btn btn-sm btn-info text-white rounded-3 fw-semibold px-2.5 py-1.5" 
                                               title="Atur Konfigurasi Soal & Peserta Siswa (Sesi)">
                                                <i class="fas fa-users-cog me-1"></i> Kelola
                                            </a>
                                            <a href="<?= esc(BASE_URL) ?>guru/monitoring/index.php" 
                                               class="btn btn-sm btn-primary rounded-3 fw-semibold px-2.5 py-1.5" 
                                               title="Monitoring Peserta Ujian">
                                                <i class="fas fa-desktop me-1"></i> Proktor
                                            </a>
                                            <a href="<?= esc(BASE_URL) ?>guru/hasil/index.php?exam_id=<?= (int)$e['id'] ?>" 
                                               class="btn btn-sm btn-outline-success rounded-3 fw-semibold px-2.5 py-1.5" 
                                               title="Lihat Rekap Hasil & Unduh Nilai">
                                                <i class="fas fa-chart-bar me-1"></i> Hasil
                                            </a>
                                            
                                            <?php if (!$is_bank_locked): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-action-more" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Opsi Lainnya">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                    <li>
                                                        <a href="<?= esc(BASE_URL) ?>guru/bank-soal/test-kelola.php?exam_id=<?= (int)$e['id'] ?>&id=<?= (int)$e['bank_soal_id'] ?>" class="dropdown-item fw-semibold text-primary">
                                                            <i class="fas fa-users-cog me-2 text-primary"></i> Atur Soal &amp; Siswa (Sesi)
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a href="<?= esc(BASE_URL) ?>guru/hasil/index.php?exam_id=<?= (int)$e['id'] ?>" class="dropdown-item fw-semibold text-success">
                                                            <i class="fas fa-poll me-2"></i> Rekap Nilai Siswa
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-excel.php?exam_id=<?= (int)$e['id'] ?>" class="dropdown-item">
                                                            <i class="fas fa-file-excel text-success me-2"></i> Unduh Rekap (Excel)
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="<?= esc(BASE_URL) ?>guru/hasil/cetak/export-pdf.php?exam_id=<?= (int)$e['id'] ?>" class="dropdown-item">
                                                            <i class="fas fa-file-pdf text-danger me-2"></i> Unduh Rekap (PDF)
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item btn-edit-test"
                                                                data-id="<?= (int)$e['id'] ?>"
                                                                data-nama="<?= esc($e['nama_mapel_ujian']) ?>"
                                                                data-jenjang="<?= esc($e['jenjang']) ?>"
                                                                data-durasi="<?= (int)$e['durasi_menit'] ?>"
                                                                data-mulai="<?= esc($e['mulai_pada']) ?>"
                                                                data-selesai="<?= esc($e['selesai_pada']) ?>"
                                                                data-status="<?= esc($e['status']) ?>"
                                                                data-acaksoal="<?= (int)$e['acak_soal'] ?>"
                                                                data-acakopsi="<?= (int)$e['acak_opsi'] ?>"
                                                                data-tampilnilai="<?= (int)$e['tampilkan_nilai'] ?>"
                                                                data-bs-toggle="modal" data-bs-target="#modalEditTest">
                                                            <i class="fas fa-edit text-primary me-2"></i> Edit Jadwal
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <form action="" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                            <input type="hidden" name="action" value="refresh_token">
                                                            <input type="hidden" name="exam_id" value="<?= (int)$e['id'] ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="fas fa-sync-alt text-warning me-2"></i> Generate Token Baru
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form action="" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                            <input type="hidden" name="action" value="toggle_token">
                                                            <input type="hidden" name="exam_id" value="<?= (int)$e['id'] ?>">
                                                            <input type="hidden" name="current_token_status" value="<?= (int)$e['is_token_aktif'] ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="fas <?= (int)$e['is_token_aktif'] === 1 ? 'fa-ban text-danger' : 'fa-check text-success' ?> me-2"></i>
                                                                <?= (int)$e['is_token_aktif'] === 1 ? 'Kunci Token' : 'Aktifkan Token' ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a href="javascript:void(0)" class="dropdown-item text-danger btn-hapus-test"
                                                           data-id="<?= (int)$e['id'] ?>"
                                                           data-nama="<?= esc($e['nama_mapel_ujian']) ?>"
                                                           data-peserta="<?= (int)$e['jumlah_peserta'] ?>">
                                                            <i class="fas fa-trash-alt me-2"></i> Hapus Jadwal
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-muted border px-2 py-1"><i class="fas fa-lock"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1 || $totalRows > 0): ?>
                <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top">
                    <div class="small text-muted">
                        Menampilkan <strong><?= $totalRows > 0 ? ($offset + 1) : 0 ?></strong> &ndash; <strong><?= min($offset + $limit, $totalRows) ?></strong> dari total <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> jadwal ujian
                        <?php if ($has_filter): ?><span class="badge bg-primary-subtle text-primary ms-1">Terfilter</span><?php endif; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navigasi Halaman">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(gJadwalQuery(['page' => 1])) ?>" title="Halaman Pertama"><i class="fas fa-angle-double-left"></i></a>
                            </li>
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(gJadwalQuery(['page' => $page - 1])) ?>" title="Sebelumnya"><i class="fas fa-angle-left"></i></a>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage   = min($totalPages, $page + 2);
                            if ($startPage > 1) {
                                echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                            }
                            for ($p = $startPage; $p <= $endPage; $p++):
                            ?>
                                <li class="page-item <?= ($page === $p) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= esc(gJadwalQuery(['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($endPage < $totalPages): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>

                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(gJadwalQuery(['page' => $page + 1])) ?>" title="Berikutnya"><i class="fas fa-angle-right"></i></a>
                            </li>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= esc(gJadwalQuery(['page' => $totalPages])) ?>" title="Halaman Terakhir"><i class="fas fa-angle-double-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH JADWAL (GURU) -->
<div class="modal fade" id="modalTambahTest" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content border-0 shadow" id="formTambahTest">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="tambah_jadwal">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Buat Jadwal Pelaksanaan Ujian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Pilih Paket Bank Soal Saya <span class="text-danger">*</span></label>
                        <select name="bank_soal_id" id="tambah-g-bank" class="form-select" required>
                            <option value="">-- Pilih Bank Soal --</option>
                            <?php foreach($bankList as $bk): ?>
                                <?php if ($bk['status'] === 'aktif'): ?>
                                    <option value="<?= (int)$bk['id'] ?>" 
                                            data-nama="<?= esc($bk['nama_bank_soal']) ?>" 
                                            data-jenjang="<?= esc($bk['jenjang'] ?? '10') ?>">
                                        <?= esc($bk['kode_mapel']) ?> - <?= esc($bk['nama_bank_soal']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Tingkat Jenjang <span class="text-danger">*</span></label>
                        <select name="jenjang" id="tambah-g-jenjang" class="form-select" required>
                            <option value="10">Kelas 10 (Fase E)</option>
                            <option value="11">Kelas 11 (Fase F)</option>
                            <option value="12">Kelas 12 (Fase F+)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small">Nama Sesi Ujian <span class="text-danger">*</span></label>
                        <input type="text" name="nama_test" id="tambah-g-nama" class="form-control" placeholder="Contoh: Penilaian Harian 1 - Matematika X" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Waktu Mulai <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="tgl_mulai" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Waktu Selesai <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="tgl_selesai" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime('+2 hours')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Durasi (Menit) <span class="text-danger">*</span></label>
                        <input type="number" name="durasi" class="form-control" value="60" min="5" max="360" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Status Awal</label>
                        <select name="status" class="form-select">
                            <option value="draft">DRAFT (Tertutup)</option>
                            <option value="aktif" selected>AKTIF (Siap Uji)</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex flex-wrap align-items-center gap-4 pt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="acak_soal" id="g_acak_soal" checked>
                            <label class="form-check-label small fw-semibold" for="g_acak_soal">Acak Soal</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="acak_opsi" id="g_acak_opsi" checked>
                            <label class="form-check-label small fw-semibold" for="g_acak_opsi">Acak Opsi</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="tampilkan_nilai" id="g_tampil_nilai">
                            <label class="form-check-label small fw-semibold" for="g_tampil_nilai">Tampilkan Nilai</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnSubmitGTest">
                    <i class="fas fa-save me-1"></i> Simpan Jadwal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT JADWAL (GURU) -->
<div class="modal fade" id="modalEditTest" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="" method="POST" class="modal-content border-0 shadow" id="formEditTest">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_jadwal">
            <input type="hidden" name="exam_id" id="edit-g-id">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Jadwal Ujian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Nama Sesi Ujian <span class="text-danger">*</span></label>
                        <input type="text" name="nama_test" id="edit-g-nama" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Tingkat Jenjang <span class="text-danger">*</span></label>
                        <select name="jenjang" id="edit-g-jenjang" class="form-select" required>
                            <option value="10">Kelas 10 (Fase E)</option>
                            <option value="11">Kelas 11 (Fase F)</option>
                            <option value="12">Kelas 12 (Fase F+)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Waktu Mulai <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="tgl_mulai" id="edit-g-mulai" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Waktu Selesai <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="tgl_selesai" id="edit-g-selesai" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Durasi (Menit) <span class="text-danger">*</span></label>
                        <input type="number" name="durasi" id="edit-g-durasi" class="form-control" min="5" max="360" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Status Pelaksanaan</label>
                        <select name="status" id="edit-g-status" class="form-select">
                            <option value="draft">DRAFT</option>
                            <option value="aktif">AKTIF</option>
                            <option value="selesai">SELESAI</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex flex-wrap align-items-center gap-4 pt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="acak_soal" id="edit_g_acak_soal">
                            <label class="form-check-label small fw-semibold" for="edit_g_acak_soal">Acak Soal</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="acak_opsi" id="edit_g_acak_opsi">
                            <label class="form-check-label small fw-semibold" for="edit_g_acak_opsi">Acak Opsi</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="tampilkan_nilai" id="edit_g_tampil_nilai">
                            <label class="form-check-label small fw-semibold" for="edit_g_tampil_nilai">Tampilkan Nilai</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnSubmitEditGTest">
                    <i class="fas fa-save me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $("#menu-toggle").click(function(e) { 
        e.preventDefault(); 
        $("#wrapper").toggleClass("toggled"); 
    });

    // Auto-fill nama ujian & jenjang dari bank soal
    $('#tambah-g-bank').on('change', function() {
        var selected = $(this).find('option:selected');
        var namaBank = selected.data('nama');
        var jenjang = selected.data('jenjang');
        if (namaBank && $('#tambah-g-nama').val() === '') {
            $('#tambah-g-nama').val(namaBank);
        }
        if (jenjang) {
            $('#tambah-g-jenjang').val(jenjang);
        }
    });

    // Populate edit test modal
    $('.btn-edit-test').on('click', function() {
        $('#edit-g-id').val($(this).data('id'));
        $('#edit-g-nama').val($(this).data('nama'));
        $('#edit-g-jenjang').val($(this).data('jenjang'));
        $('#edit-g-durasi').val($(this).data('durasi'));
        $('#edit-g-status').val($(this).data('status'));
        
        var mulai = $(this).data('mulai');
        var selesai = $(this).data('selesai');
        if (mulai) $('#edit-g-mulai').val(mulai.replace(' ', 'T').substring(0, 16));
        if (selesai) $('#edit-g-selesai').val(selesai.replace(' ', 'T').substring(0, 16));

        $('#edit_g_acak_soal').prop('checked', $(this).data('acaksoal') == 1);
        $('#edit_g_acak_opsi').prop('checked', $(this).data('acakopsi') == 1);
        $('#edit_g_tampil_nilai').prop('checked', $(this).data('tampilnilai') == 1);
    });

    // Salin token ke clipboard
    $('.btn-copy-token').on('click', function() {
        var token = $(this).data('token');
        navigator.clipboard.writeText(token).then(function() {
            Swal.fire({
                icon: 'success',
                title: 'Token Disalin!',
                text: 'Token ' + token + ' berhasil disalin ke clipboard.',
                timer: 1200,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        });
    });

    // Hapus Jadwal Konfirmasi SweetAlert2
    $('.btn-hapus-test').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var peserta = parseInt($(this).data('peserta') || 0);

        var warn = '';
        if (peserta > 0) {
            warn = '<div class="alert alert-danger text-start small mt-2 mb-0"><i class="fas fa-exclamation-triangle me-1"></i> <strong>Peringatan:</strong> Jadwal ini memiliki <b>' + peserta + ' riwayat ujian siswa</b>. Seluruh data nilai dan jawaban pada sesi ini akan terhapus!</div>';
        } else {
            warn = '<small class="text-muted d-block mt-2">Jadwal ini akan dihapus dari sistem.</small>';
        }

        Swal.fire({
            title: 'Hapus Jadwal Ujian?',
            html: 'Apakah Anda yakin ingin menghapus jadwal <b>' + $('<div>').text(nama).html() + '</b>?' + warn,
            icon: peserta > 0 ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Ya, Hapus Sekarang',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-danger fw-bold px-3',
                cancelButton: 'btn btn-secondary px-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'index.php';

                var inputCsrf = document.createElement('input');
                inputCsrf.type = 'hidden';
                inputCsrf.name = 'csrf_token';
                inputCsrf.value = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
                form.appendChild(inputCsrf);

                var inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'hapus_jadwal';
                form.appendChild(inputAction);

                var inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'exam_id';
                inputId.value = id;
                form.appendChild(inputId);

                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Double-submit protection
    $('#formTambahTest').on('submit', function() {
        $('#btnSubmitGTest').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan Jadwal...');
    });
    $('#formEditTest').on('submit', function() {
        $('#btnSubmitEditGTest').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan Perubahan...');
    });
});
</script>
</body>
</html>
