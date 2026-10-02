<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") { csrf_verify(); }

// Ambil Tahun Ajaran yang sedang Aktif
$taAktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();

if (!$taAktif) {
    die("Peringatan: Tidak ada Tahun Ajaran yang aktif. Silakan aktifkan satu tahun ajaran terlebih dahulu.");
}

// --- PROSES KENAIKAN / PINDAH KELAS ---
if (isset($_POST['proses_pindah'])) {
    $student_ids     = $_POST['student_ids'] ?? [];
    $target_class_id = $_POST['target_class_id'];
    $tahun_ajaran_id = $taAktif['id'];

    if (!empty($student_ids) && !empty($target_class_id)) {
        try {
            $pdo->beginTransaction();
            $stmtHist   = $pdo->prepare("INSERT INTO cbt_riwayat_kelas (student_id, class_id, tahun_ajaran_id, created_at) VALUES (?, ?, ?, NOW())");
            $stmtUpdate = $pdo->prepare("UPDATE cbt_students SET class_id = ?, tahun_ajaran_id = ?, updated_at = NOW() WHERE id = ?");
            foreach ($student_ids as $student_id) {
                $stmtHist->execute([$student_id, $target_class_id, $tahun_ajaran_id]);
                $stmtUpdate->execute([$target_class_id, $tahun_ajaran_id, $student_id]);
            }
            $pdo->commit();
            log_activity("Kenaikan kelas: " . count($student_ids) . " siswa dipindah ke kelas ID $target_class_id", null, null, null, 'master');
            header("Location: kenaikan-kelas.php?msg=success");
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header("Location: kenaikan-kelas.php?msg=error");
        }
        exit;
    }
}

// --- FILTER & DATA ---
$filter_class = $_GET['from_class'] ?? '';
$listSiswa = [];
if ($filter_class) {
    $stmtSiswa = $pdo->prepare("SELECT * FROM cbt_students WHERE class_id = ? ORDER BY nama_lengkap ASC");
    $stmtSiswa->execute([$filter_class]);
    $listSiswa = $stmtSiswa->fetchAll();
}

// Ambil semua kelas untuk dropdown
$listKelas = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
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
            <h5 class="ms-3 mb-0 fw-bold">Kenaikan / Pindah Kelas</h5>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php $flash = $_GET['msg'] ?? ''; ?>
            <?php if ($flash === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Kenaikan kelas berhasil diproses.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($flash === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-times-circle me-2"></i> Terjadi kesalahan saat memproses kenaikan kelas. Silakan coba lagi.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
                <i class="fas fa-info-circle me-3 fa-lg"></i>
                <div>
                    Tahun Ajaran Aktif: <strong><?= $taAktif['tahun'] ?> (<?= ucfirst($taAktif['semester']) ?>)</strong>.
                    Semua riwayat akan dicatat pada periode ini.
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card card-dashboard p-4 border-0 shadow-sm">
                        <h6 class="fw-bold mb-3">1. Pilih Asal Kelas</h6>
                        <form action="" method="GET">
                            <div class="mb-3">
                                <select name="from_class" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Pilih Kelas Asal --</option>
                                    <?php foreach ($listKelas as $k): ?>
                                        <option value="<?= esc($k['id']) ?>" <?= esc($filter_class == $k['id'] ? 'selected' : '') ?>>
                                            Kelas <?= $k['jenjang'] ?> - <?= $k['nama_kelas'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <?php if ($filter_class): ?>
                        <hr>
                        <h6 class="fw-bold mb-3">2. Tujuan Kenaikan</h6>
                        <form action="" method="POST" id="formKenaikan">
                            <div class="mb-3">
                                <label class="small fw-bold">Pindahkan Ke Kelas:</label>
                                <select name="target_class_id" class="form-select border-primary" required>
                                    <option value="">-- Pilih Kelas Tujuan --</option>
                                    <?php foreach ($listKelas as $k): ?>
                                        <option value="<?= esc($k['id']) ?>">
                                            Kelas <?= $k['jenjang'] ?> - <?= $k['nama_kelas'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="proses_pindah" class="btn btn-primary w-100 shadow-sm" onclick="return confirm('Proses kenaikan kelas untuk siswa yang dipilih?')">
                                <i class="fas fa-exchange-alt me-2"></i> Proses Kenaikan
                            </button>
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

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th width="50">Pilih</th>
                                        <th>Nama Lengkap</th>
                                        <th>NISN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$filter_class): ?>
                                        <tr><td colspan="3" class="text-center text-muted">Silakan pilih kelas asal terlebih dahulu.</td></tr>
                                    <?php elseif (empty($listSiswa)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">Tidak ada siswa di kelas ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($listSiswa as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="student_ids[]" value="<?= esc($s['id']) ?>" class="form-check-input checkSiswa" form="formKenaikan">
                                            </td>
                                            <td class="fw-bold"><?= $s['nama_lengkap'] ?></td>
                                            <td><?= $s['nisn'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        </form> </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Toggle Pilih Semua
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