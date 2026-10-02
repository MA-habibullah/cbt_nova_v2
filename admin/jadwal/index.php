<?php
session_start();
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// 1. TANGKAP FILTER DARI URL
$filter_jenjang = isset($_GET['jenjang']) ? $_GET['jenjang'] : 'all';
$tgl_mulai      = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : '';
$tgl_selesai    = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : '';

// 2. BANGUN QUERY DINAMIS
$query = "SELECT e.*, b.nama_bank_soal, s.nama_mapel, t.nama_lengkap as nama_guru,
                 COUNT(DISTINCT eq.id) AS jumlah_soal,
                 COUNT(DISTINCT ep.id) AS jumlah_peserta
          FROM cbt_exams e
          JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
          JOIN cbt_subjects s ON e.subject_id = s.id
          JOIN cbt_teachers t ON e.teacher_id = t.id
          LEFT JOIN cbt_exam_questions eq ON eq.exam_id = e.id
          LEFT JOIN cbt_exam_participants ep ON ep.exam_id = e.id
          WHERE 1=1";

$params = [];

// FILTER JENJANG
if ($filter_jenjang !== 'all') {
    $query .= " AND e.jenjang = ?";
    $params[] = $filter_jenjang;
}

// FILTER RENTANG TANGGAL
if ($tgl_mulai != '' && $tgl_selesai != '') {
    $query .= " AND DATE(e.mulai_pada) BETWEEN ? AND ?";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;
}

$query .= " GROUP BY e.id ORDER BY e.mulai_pada ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$exams = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
    <?php include '../../includes/header.php'; ?>

    <style>
        /* Style untuk Dropdown Status Dinamis */
        .select-status { font-size: 0.8rem; font-weight: bold; border-radius: 5px; padding: 5px; cursor: pointer; transition: all 0.3s; }
        .status-aktif { background-color: #198754; color: white; border-color: #198754; }
        .status-draft { background-color: #6c757d; color: white; border-color: #6c757d; }
        .status-selesai { background-color: #dc3545; color: white; border-color: #dc3545; }
        .select-status:focus { box-shadow: none; color: white; }
    </style>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center w-100">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold text-primary">Manajemen Jadwal Ujian</h5>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">FILTER JENJANG</label>
                            <select name="jenjang" class="form-select">
                                <option value="all" <?= esc($filter_jenjang == 'all' ? 'selected' : '') ?>>Semua Jenjang</option>
                                <option value="10" <?= esc($filter_jenjang == '10' ? 'selected' : '') ?>>Kelas 10</option>
                                <option value="11" <?= esc($filter_jenjang == '11' ? 'selected' : '') ?>>Kelas 11</option>
                                <option value="12" <?= esc($filter_jenjang == '12' ? 'selected' : '') ?>>Kelas 12</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">DARI TANGGAL</label>
                            <input type="date" name="tgl_mulai" class="form-control" value="<?= esc($tgl_mulai) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">SAMPAI TANGGAL</label>
                            <input type="date" name="tgl_selesai" class="form-control" value="<?= esc($tgl_selesai) ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="fas fa-filter me-2"></i> Terapkan
                            </button>
                            <a href="index.php" class="btn btn-light border w-100 fw-bold">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-muted"><i class="fas fa-calendar-alt me-2"></i>Daftar Jadwal Pelaksanaan</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-center">
                                <th width="50">No</th>
                                <th class="text-start">Ujian / Mapel</th>
                                <th width="100">Jenjang</th>
                                <th width="150">Waktu Mulai</th>
                                <th width="100">Durasi</th>
                                <th width="100">Soal</th>
                                <th width="120">Peserta</th>
                                <th width="120">Token</th>
                                <th width="150">Status (Klik Ubah)</th>
                                <th width="100">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($exams)): ?>
                                <tr><td colspan="10" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach($exams as $i => $e): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $i+1 ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($e['nama_mapel_ujian']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($e['nama_mapel']) ?> | <?= htmlspecialchars($e['nama_guru']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3">
                                            Kelas <?= $e['jenjang'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="small fw-bold"><?= date('d/m/Y', strtotime($e['mulai_pada'])) ?></div>
                                        <div class="small text-muted"><?= date('H:i', strtotime($e['mulai_pada'])) ?> WIB</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= $e['durasi_menit'] ?> Menit</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $e['jumlah_soal'] ?> Soal</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= $e['jumlah_peserta'] ?> Siswa</span>
                                    </td>
                                    <td class="text-center">
                                        <code class="fw-bold fs-6"><?php if ($e['is_token_aktif'] == 1) {
                                            echo htmlspecialchars($e['token']);
                                        } else {
                                            echo '<span class="text-muted">Nonaktif</span>';
                                        }
                                         ?></code>
                                    </td>
                                    <td class="text-center">
                                        <select class="form-select form-select-sm select-status update-status-ajax 
                                            <?= 'status-'.$e['status'] ?>" 
                                            data-id="<?= esc($e['id']) ?>">
                                            <option value="draft" <?= esc($e['status'] == 'draft' ? 'selected' : '') ?>>DRAFT</option>
                                            <option value="aktif" <?= esc($e['status'] == 'aktif' ? 'selected' : '') ?>>AKTIF</option>
                                            <option value="selesai" <?= esc($e['status'] == 'selesai' ? 'selected' : '') ?>>SELESAI</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= esc(BASE_URL) ?>admin/bank-soal/test.php?id=<?= esc($e['bank_soal_id']) ?>" 
                                           class="btn btn-sm btn-info text-white shadow-sm" 
                                           title="Kelola Soal">
                                            <i class="fas fa-file-alt me-1"></i> Kelola Test
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <footer class="bg-white text-center py-3 border-top mt-auto no-print">
            <small class="text-muted">CBT Native &copy; 2026 - Scheduling System</small>
        </footer>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { 
        e.preventDefault(); 
        $("#wrapper").toggleClass("toggled"); 
    });

    $(document).ready(function() {
        // Fungsi AJAX Update Status
        $('.update-status-ajax').on('change', function() {
            const examId = $(this).data('id');
            const newStatus = $(this).val();
            const selectElement = $(this);

            // Beri efek loading ringan
            selectElement.css('opacity', '0.5');

            $.ajax({
                url: 'ajax-update-status.php',
                type: 'POST',
                data: { id: examId, status: newStatus },
                dataType: 'json',
                success: function(response) {
                    selectElement.css('opacity', '1');
                    if (response.success) {
                        // Ganti warna class sesuai status baru secara real-time
                        selectElement.removeClass('status-aktif status-draft status-selesai')
                                     .addClass('status-' + newStatus);
                        
                        // Notifikasi sukses kecil (opsional)
                        console.log('Status updated to ' + newStatus);
                    } else {
                        alert('Gagal memperbarui status: ' + response.message);
                        location.reload(); // Reload jika gagal untuk sinkronisasi data
                    }
                },
                error: function() {
                    selectElement.css('opacity', '1');
                    alert('Terjadi kesalahan koneksi ke server.');
                }
            });
        });
    });
</script>
</body>
</html>