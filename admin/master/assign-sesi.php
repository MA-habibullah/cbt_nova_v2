<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") { csrf_verify(); }

// --- PROSES ASSIGN SESI ---
if (isset($_POST['proses_assign'])) {
    $student_ids    = $_POST['student_ids'] ?? [];
    $target_sesi_id = $_POST['target_sesi_id'];

    if (!empty($student_ids) && !empty($target_sesi_id)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE cbt_students SET sesi = ?, updated_at = NOW() WHERE id = ?");
            foreach ($student_ids as $student_id) {
                $stmt->execute([$target_sesi_id, $student_id]);
            }
            $pdo->commit();
            $stmtSesi = $pdo->prepare("SELECT nama_sesi FROM cbt_sesi WHERE id = ?");
            $stmtSesi->execute([$target_sesi_id]);
            $ns = $stmtSesi->fetchColumn();
            log_activity("Assign sesi: " . count($student_ids) . " siswa dipindah ke $ns", null, null, null, 'master');
            header("Location: assign-sesi.php?msg=success&from_class=" . ($_POST['from_class'] ?? ''));
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header("Location: assign-sesi.php?msg=error");
        }
        exit;
    }
}

// --- FILTER & DATA ---
$filter_class = $_GET['from_class'] ?? '';
$listSiswa = [];
if ($filter_class) {
    $stmt = $pdo->prepare("
        SELECT s.*, cs.nama_sesi
        FROM cbt_students s
        LEFT JOIN cbt_sesi cs ON s.sesi = cs.id
        WHERE s.class_id = ? AND s.is_aktif = 1
        ORDER BY s.nama_lengkap ASC
    ");
    $stmt->execute([$filter_class]);
    $listSiswa = $stmt->fetchAll();
}

$listKelas = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
$listSesi  = $pdo->query("SELECT * FROM cbt_sesi WHERE is_aktif = 1 ORDER BY jam_mulai ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>
<body>

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <button class="btn btn-light border shadow-sm" id="menu-toggle"><i class="fas fa-bars"></i></button>
            <h5 class="ms-3 mb-0 fw-bold">Assign Sesi ke Siswa</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if ($flash === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Assign sesi berhasil diproses.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan saat memproses assign sesi. Silakan coba lagi.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card card-dashboard p-4 border-0 shadow-sm">
                        <h6 class="fw-bold mb-3">1. Filter Siswa</h6>
                        <form action="" method="GET">
                            <div class="mb-3">
                                <select name="from_class" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($listKelas as $k): ?>
                                        <option value="<?= esc($k['id']) ?>" <?= esc($filter_class == $k['id'] ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($k['jenjang']) ?> - <?= htmlspecialchars($k['nama_kelas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <?php if ($filter_class): ?>
                        <hr>
                        <h6 class="fw-bold mb-3">2. Pilih Sesi Tujuan</h6>
                        <form action="" method="POST" id="formAssign">
                            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="from_class" value="<?= htmlspecialchars($filter_class) ?>">
                            <div class="mb-3">
                                <label class="small fw-bold">Pindahkan Ke Sesi:</label>
                                <select name="target_sesi_id" class="form-select border-primary" required>
                                    <option value="">-- Pilih Sesi --</option>
                                    <?php foreach ($listSesi as $s): ?>
                                        <option value="<?= esc($s['id']) ?>">
                                            <?= htmlspecialchars($s['nama_sesi']) ?> (<?= date('H:i', strtotime($s['jam_mulai'])) ?>–<?= date('H:i', strtotime($s['jam_selesai'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="proses_assign" class="btn btn-primary w-100 shadow-sm"
                                    onclick="return confirm('Terapkan sesi ke siswa yang dipilih?')">
                                <i class="fas fa-exchange-alt me-2"></i> Proses Assign Sesi
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card card-dashboard p-4 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0">Daftar Siswa</h6>
                            <?php if ($filter_class): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                    <label class="form-check-label small fw-bold" for="checkAll">Pilih Semua</label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th width="50">Pilih</th>
                                        <th>Nama Lengkap</th>
                                        <th>NISN</th>
                                        <th>Sesi Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$filter_class): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">
                                            <i class="fas fa-filter me-2"></i>Pilih kelas untuk menampilkan siswa.
                                        </td></tr>
                                    <?php elseif (empty($listSiswa)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">Tidak ada siswa aktif di kelas ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($listSiswa as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="student_ids[]" value="<?= esc($s['id']) ?>"
                                                       class="form-check-input checkSiswa" form="formAssign">
                                            </td>
                                            <td class="fw-bold"><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($s['nisn']) ?></td>
                                            <td>
                                                <?php if ($s['nama_sesi']): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                        <?= htmlspecialchars($s['nama_sesi']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border">Belum diatur</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('#checkAll').on('click', function() {
            $('.checkSiswa').prop('checked', this.checked);
        });
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#sidebar").toggleClass("show");
        });
    });
</script>
</body>
</html>
