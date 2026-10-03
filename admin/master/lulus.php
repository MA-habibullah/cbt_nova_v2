<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$page_title = 'Kelulusan Siswa';

// Ambil semua kelas aktif untuk filter dropdown
$listKelas = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif=1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();

// Ambil distinct jenjang untuk filter
$listJenjang = $pdo->query("SELECT DISTINCT jenjang FROM cbt_classes WHERE is_aktif=1 ORDER BY jenjang ASC")->fetchAll(PDO::FETCH_COLUMN);

// Cek apakah kelas LULUS (jenjang 12) sudah ada
$stmtLulus = $pdo->prepare("SELECT id FROM cbt_classes WHERE nama_kelas='LULUS' AND jenjang='12' LIMIT 1");
$stmtLulus->execute();
$kelas_lulus = $stmtLulus->fetch();

// Ambil distinct sesi dari cbt_students
$listSesi = $pdo->query("SELECT DISTINCT sesi FROM cbt_students ORDER BY sesi ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- PROSES KELULUSAN ---
if (isset($_POST['proses_lulus'])) {
    csrf_verify();

    $student_ids = $_POST['student_ids'] ?? [];

    if (!$kelas_lulus) {
        header("Location: lulus.php?msg=no_kelas_lulus");
        exit;
    }
    if (empty($student_ids)) {
        header("Location: lulus.php?msg=empty_selection");
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmtUpdate = $pdo->prepare("UPDATE cbt_students SET is_aktif=0, class_id=?, updated_at=NOW() WHERE id=?");
        foreach ($student_ids as $sid) {
            $stmtUpdate->execute([$kelas_lulus['id'], (int)$sid]);
        }
        $pdo->commit();
        log_activity("Kelulusan siswa: " . count($student_ids) . " siswa diluluskan ke kelas LULUS (ID " . $kelas_lulus['id'] . ")", null, null, null, 'master');
        header("Location: lulus.php?msg=success");
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: lulus.php?msg=error");
    }
    exit;
}

// --- FILTER PARAMS ---
$f_jenjang = $_GET['f_jenjang'] ?? '12';
$f_kelas   = $_GET['f_kelas']   ?? '';
$f_sesi    = $_GET['f_sesi']    ?? '';
$search    = trim($_GET['search'] ?? '');

// --- QUERY SISWA AKTIF (bukan kelas LULUS) ---
$query_str = "SELECT s.*, k.nama_kelas, k.jenjang
              FROM cbt_students s
              JOIN cbt_classes k ON s.class_id = k.id
              WHERE s.is_aktif = 1
              AND k.nama_kelas != 'LULUS'";
$params = [];

if ($f_jenjang) {
    $query_str .= " AND k.jenjang = ?";
    $params[] = $f_jenjang;
}
if ($f_kelas) {
    $query_str .= " AND s.class_id = ?";
    $params[] = $f_kelas;
}
if ($f_sesi) {
    $query_str .= " AND s.sesi = ?";
    $params[] = $f_sesi;
}
if ($search) {
    $s_esc = like_escape($search);
    $query_str .= " AND (s.nama_lengkap LIKE ? OR s.nisn LIKE ?)";
    $params[] = "%$s_esc%";
    $params[] = "%$s_esc%";
}

$query_str .= " ORDER BY k.jenjang ASC, k.nama_kelas ASC, s.nama_lengkap ASC";

$stmtSiswa = $pdo->prepare($query_str);
$stmtSiswa->execute($params);
$listSiswa = $stmtSiswa->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Kelulusan Siswa</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">

            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if ($flash === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Siswa berhasil diluluskan.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan, silakan coba lagi.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'no_kelas_lulus'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> Kelas LULUS (jenjang 12) belum ada.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'empty_selection'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> Pilih minimal satu siswa terlebih dahulu.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$kelas_lulus): ?>
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-start" role="alert">
                    <i class="fas fa-exclamation-triangle me-3 fa-lg mt-1"></i>
                    <div>
                        Kelas <strong>LULUS</strong> dengan jenjang <strong>12</strong> belum ditemukan.
                        Silakan buat kelas tersebut terlebih dahulu di menu <a href="kelas.php">Manajemen Kelas</a>.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action & Filter Toolbar -->
            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0">
                <form method="GET" action="lulus.php" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / NISN..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="f_jenjang" class="form-select form-select-sm" id="filterJenjang">
                            <option value="">-- Semua Jenjang --</option>
                            <?php foreach ($listJenjang as $j): ?>
                                <option value="<?= htmlspecialchars($j) ?>" <?= esc($f_jenjang === (string)$j ? 'selected' : '') ?>>
                                    Jenjang <?= htmlspecialchars($j) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="f_kelas" class="form-select form-select-sm">
                            <option value="">-- Semua Kelas --</option>
                            <?php foreach ($listKelas as $k): ?>
                                <?php if ($k['nama_kelas'] === 'LULUS') continue; ?>
                                <?php if ($f_jenjang && $k['jenjang'] !== $f_jenjang) continue; ?>
                                <option value="<?= esc($k['id']) ?>" <?= esc($f_kelas == $k['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($k['jenjang'] . ' - ' . $k['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="f_sesi" class="form-select form-select-sm">
                            <option value="">-- Semua Sesi --</option>
                            <?php foreach ($listSesi as $sesi): ?>
                                <option value="<?= htmlspecialchars($sesi) ?>" <?= esc($f_sesi === (string)$sesi ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($sesi) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill shadow-sm">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="lulus.php" class="btn btn-outline-secondary btn-sm flex-fill">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Data Table Container -->
            <div class="card card-dashboard p-0 shadow-sm border-0 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-graduation-cap text-primary"></i> Daftar Siswa untuk Kelulusan
                    </h6>
                    <span class="badge bg-primary-subtle text-primary font-monospace rounded-pill px-3">
                        Total: <?= number_format(count($listSiswa)) ?> Siswa
                    </span>
                </div>

                <form method="POST" action="lulus.php" id="formLulus">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small text-uppercase fw-semibold">
                                <tr>
                                    <th width="45" class="ps-4 text-center">
                                        <input class="form-check-input" type="checkbox" id="checkAll" title="Pilih Semua">
                                    </th>
                                    <th width="50" class="text-center">No</th>
                                    <th width="60">Foto</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>Kelas</th>
                                    <th>Jenjang</th>
                                    <th class="text-center pe-4">Sesi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($listSiswa)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x d-block mb-2 text-secondary"></i>
                                            Tidak ada siswa yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($listSiswa as $no => $s): ?>
                                    <tr>
                                        <td class="ps-4 text-center">
                                            <input type="checkbox" name="student_ids[]" value="<?= esc($s['id']) ?>"
                                                   class="form-check-input checkSiswa" form="formLulus">
                                        </td>
                                        <td class="text-center text-muted small"><?= $no + 1 ?></td>
                                        <td>
                                            <?php if (!empty($s['foto'])): ?>
                                                <img src="<?= esc(BASE_URL) ?>assets/uploads/foto_siswa/<?= htmlspecialchars($s['foto']) ?>"
                                                     class="rounded-circle border shadow-sm"
                                                     style="width:36px;height:36px;object-fit:cover;"
                                                     alt="Foto"
                                                     onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='inline-flex'">
                                                <span class="d-none align-items-center justify-content-center rounded-circle bg-light border text-secondary"
                                                      style="width:36px;height:36px;">
                                                    <i class="fas fa-user"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border text-secondary"
                                                      style="width:36px;height:36px;">
                                                    <i class="fas fa-user"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary font-monospace px-2 py-1"><?= htmlspecialchars($s['nisn']) ?></span></td>
                                        <td><span class="fw-semibold"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                                        <td><span class="badge bg-info-subtle text-info-emphasis font-monospace px-3">Tingkat <?= htmlspecialchars($s['jenjang']) ?></span></td>
                                        <td class="text-center pe-4">
                                            <span class="badge bg-light text-dark font-monospace border px-2 py-1"><?= htmlspecialchars($s['sesi'] ?? '-') ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Action Bar -->
                    <div class="card-footer bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3 border-top">
                        <div class="text-muted small">
                            <span id="selectedCountText" class="fw-semibold text-primary">0</span> siswa dipilih untuk kelulusan
                        </div>
                        <button type="button" id="btnLulus" class="btn btn-danger px-4 shadow-sm"
                                <?= !$kelas_lulus ? 'disabled' : '' ?>>
                            <i class="fas fa-graduation-cap me-2"></i> Luluskan Siswa Terpilih
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Toggle sidebar
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#sidebar").toggleClass("show");
    });



    function updateSelectionCount() {
        var checked = $('.checkSiswa:checked').length;
        var total = $('.checkSiswa').length;
        $('#selectedCountText').text(checked);
        $('#checkAll').prop('checked', total > 0 && checked === total);
    }

    // Pilih Semua
    $('#checkAll').on('click', function() {
        $('.checkSiswa').prop('checked', this.checked);
        updateSelectionCount();
    });

    // Update state checkAll saat individual checkbox berubah
    $(document).on('change', '.checkSiswa', function() {
        updateSelectionCount();
    });

    // Submit dengan konfirmasi SweetAlert2
    $('#btnLulus').on('click', function() {
        var selected = $('.checkSiswa:checked').length;
        if (selected === 0) {
            Swal.fire('Peringatan', 'Pilih minimal satu siswa terlebih dahulu.', 'warning');
            return;
        }
        Swal.fire({
            title: 'Luluskan Siswa?',
            text: selected + ' siswa akan ditandai Tidak Aktif dan dipindahkan ke kelas LULUS.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Luluskan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#formLulus').append('<input type="hidden" name="proses_lulus" value="1">').submit();
            }
        });
    });
});
</script>

</body>
</html>
