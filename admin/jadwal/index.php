<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// 1. TANGKAP PARAMETER FILTER & PAGINATION
$page           = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit          = isset($_GET['limit']) ? max(10, (int)$_GET['limit']) : 50; // Default 50 baris
$search         = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_jenjang = isset($_GET['jenjang']) ? trim($_GET['jenjang']) : 'all';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$tgl_mulai      = isset($_GET['tgl_mulai']) ? trim($_GET['tgl_mulai']) : '';
$tgl_selesai    = isset($_GET['tgl_selesai']) ? trim($_GET['tgl_selesai']) : '';

// 2. BANGUN KLAUSA WHERE SECARA DINAMIS
$where = "";
$params = [];

// Filter Jenjang
if ($filter_jenjang !== 'all' && in_array($filter_jenjang, ['10', '11', '12'])) {
    $where .= " AND e.jenjang = ?";
    $params[] = $filter_jenjang;
}

// Filter Status Ujian
if ($filter_status !== 'all' && in_array($filter_status, ['draft', 'aktif', 'selesai'])) {
    $where .= " AND e.status = ?";
    $params[] = $filter_status;
}

// Filter Pencarian (Nama Ujian / Mapel / Bank Soal / Guru)
if ($search !== '') {
    $where .= " AND (e.nama_mapel_ujian LIKE ? OR s.nama_mapel LIKE ? OR b.nama_bank_soal LIKE ? OR t.nama_lengkap LIKE ?)";
    $searchWildcard = "%" . like_escape($search) . "%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

// Filter Rentang Tanggal
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

// 3. HITUNG TOTAL DATA UNTUK PAGINATION
$countQuery = "SELECT COUNT(DISTINCT e.id)
               FROM cbt_exams e
               JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
               JOIN cbt_subjects s ON e.subject_id = s.id
               JOIN cbt_teachers t ON e.teacher_id = t.id
               WHERE 1=1 $where";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();

// Hitung Pagination
$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 4. AMBIL DATA JADWAL UJIAN DENGAN LIMIT & OFFSET
$dataQuery = "SELECT e.*, b.nama_bank_soal, s.nama_mapel, t.nama_lengkap as nama_guru,
                     COUNT(DISTINCT eq.id) AS jumlah_soal,
                     COUNT(DISTINCT ep.id) AS jumlah_peserta
              FROM cbt_exams e
              JOIN cbt_bank_soal b ON e.bank_soal_id = b.id
              JOIN cbt_subjects s ON e.subject_id = s.id
              JOIN cbt_teachers t ON e.teacher_id = t.id
              LEFT JOIN cbt_exam_questions eq ON eq.exam_id = e.id
              LEFT JOIN cbt_exam_participants ep ON ep.exam_id = e.id
              WHERE 1=1 $where
              GROUP BY e.id
              ORDER BY e.mulai_pada DESC, e.id DESC
              LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$limit, $offset]);
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($dataParams);
$exams = $stmt->fetchAll();

// Helper URL Pagination
function build_pagination_url($targetPage) {
    $query = $_GET;
    $query['page'] = $targetPage;
    return '?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="id">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

    <style>
        /* Style untuk Dropdown Status Dinamis */
        .select-status { font-size: 0.8rem; font-weight: bold; border-radius: 6px; padding: 4px 8px; cursor: pointer; transition: all 0.3s; }
        .status-aktif { background-color: #198754; color: white; border-color: #198754; }
        .status-draft { background-color: #6c757d; color: white; border-color: #6c757d; }
        .status-selesai { background-color: #dc3545; color: white; border-color: #dc3545; }
        .select-status:focus { box-shadow: none; color: white; }
    </style>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center w-100">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Manajemen Jadwal Ujian</h5>
                    <small class="text-muted">Kelola jadwal pelaksanaan, token, status sesi, dan durasi ujian</small>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <!-- Filter & Search Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3 align-items-end" id="filterForm">
                        <div class="col-12 col-md-3">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">PENCARIAN</label>
                            <div class="input-group">
                                <input type="text" name="q" class="form-control form-control-sm" placeholder="Nama ujian / mapel / guru..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">JENJANG</label>
                            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= esc($filter_jenjang === 'all' ? 'selected' : '') ?>>Semua Jenjang</option>
                                <option value="10" <?= esc($filter_jenjang === '10' ? 'selected' : '') ?>>Kelas 10</option>
                                <option value="11" <?= esc($filter_jenjang === '11' ? 'selected' : '') ?>>Kelas 11</option>
                                <option value="12" <?= esc($filter_jenjang === '12' ? 'selected' : '') ?>>Kelas 12</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">STATUS</label>
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= esc($filter_status === 'all' ? 'selected' : '') ?>>Semua Status</option>
                                <option value="draft" <?= esc($filter_status === 'draft' ? 'selected' : '') ?>>Draft</option>
                                <option value="aktif" <?= esc($filter_status === 'aktif' ? 'selected' : '') ?>>Aktif</option>
                                <option value="selesai" <?= esc($filter_status === 'selesai' ? 'selected' : '') ?>>Selesai</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">DARI TANGGAL</label>
                            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_mulai) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">SAMPAI TANGGAL</label>
                            <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_selesai) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-6 col-md-1">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">BARIS</label>
                            <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="25" <?= esc($limit === 25 ? 'selected' : '') ?>>25</option>
                                <option value="50" <?= esc($limit === 50 ? 'selected' : '') ?>>50</option>
                                <option value="100" <?= esc($limit === 100 ? 'selected' : '') ?>>100</option>
                                <option value="200" <?= esc($limit === 200 ? 'selected' : '') ?>>200</option>
                            </select>
                        </div>

                        <?php if ($search !== '' || $filter_jenjang !== 'all' || $filter_status !== 'all' || $tgl_mulai !== '' || $tgl_selesai !== '' || $limit !== 50): ?>
                        <div class="col-12 text-end mt-2">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i> Reset Filter</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Daftar Jadwal Pelaksanaan</h6>
                        <small class="text-muted">Total Ditemukan: <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> Jadwal Ujian</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        Halaman <?= $page ?> dari <?= $totalPages ?>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-center text-secondary small text-uppercase fw-bold">
                                <th width="50">No</th>
                                <th class="text-start">Ujian / Mapel</th>
                                <th width="100">Jenjang</th>
                                <th width="160">Waktu Mulai</th>
                                <th width="100">Durasi</th>
                                <th width="90">Soal</th>
                                <th width="110">Peserta</th>
                                <th width="110">Token</th>
                                <th width="140">Status (Klik Ubah)</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-0 fw-bold">Tidak ada jadwal ujian yang sesuai dengan filter.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exams as $i => $e): ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?= $offset + $i + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($e['nama_mapel_ujian']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($e['nama_mapel']) ?> &bull; <i class="fas fa-user-tie ms-1 me-1"></i><?= htmlspecialchars($e['nama_guru']) ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                            Kelas <?= htmlspecialchars($e['jenjang']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="small fw-bold text-dark"><?= date('d M Y', strtotime($e['mulai_pada'])) ?></div>
                                        <div class="small text-muted"><?= date('H:i', strtotime($e['mulai_pada'])) ?> - <?= date('H:i', strtotime($e['selesai_pada'])) ?> WIB</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= (int)$e['durasi_menit'] ?> Menit</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle"><?= (int)$e['jumlah_soal'] ?> Butir</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= (int)$e['jumlah_peserta'] ?> Siswa</span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$e['is_token_aktif'] === 1 && !empty($e['token'])): ?>
                                            <code class="fw-bold fs-6 text-dark bg-light px-2 py-1 border rounded"><?= htmlspecialchars($e['token']) ?></code>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <select class="form-select form-select-sm select-status update-status-ajax <?= 'status-' . htmlspecialchars($e['status']) ?>" 
                                                data-id="<?= (int)$e['id'] ?>">
                                            <option value="draft" <?= esc($e['status'] === 'draft' ? 'selected' : '') ?>>DRAFT</option>
                                            <option value="aktif" <?= esc($e['status'] === 'aktif' ? 'selected' : '') ?>>AKTIF</option>
                                            <option value="selesai" <?= esc($e['status'] === 'selesai' ? 'selected' : '') ?>>SELESAI</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= esc(BASE_URL) ?>admin/bank-soal/test.php?id=<?= (int)$e['bank_soal_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary shadow-sm" 
                                           title="Kelola Jadwal & Soal">
                                            <i class="fas fa-cog me-1"></i> Kelola
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1 || $totalRows > 0): ?>
                <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="small text-muted">
                        Menampilkan <strong><?= $totalRows > 0 ? ($offset + 1) : 0 ?></strong> &ndash; <strong><?= min($offset + $limit, $totalRows) ?></strong> dari total <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> jadwal ujian
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navigasi Halaman">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- First & Prev -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_pagination_url(1) ?>" title="Halaman Pertama"><i class="fas fa-angle-double-left"></i></a>
                            </li>
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_pagination_url($page - 1) ?>" title="Sebelumnya"><i class="fas fa-angle-left"></i></a>
                            </li>

                            <!-- Page Numbers with Ellipsis -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage   = min($totalPages, $page + 2);
                            if ($startPage > 1) {
                                echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                            }
                            for ($p = $startPage; $p <= $endPage; $p++):
                            ?>
                                <li class="page-item <?= ($page === $p) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= build_pagination_url($p) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($endPage < $totalPages): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>

                            <!-- Next & Last -->
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_pagination_url($page + 1) ?>" title="Berikutnya"><i class="fas fa-angle-right"></i></a>
                            </li>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_pagination_url($totalPages) ?>" title="Halaman Terakhir"><i class="fas fa-angle-double-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="bg-white text-center py-3 border-top mt-auto no-print">
            <small class="text-muted">CBT Nova &copy; <?= date('Y') ?> &bull; SMAN 11 Surabaya</small>
        </footer>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $("#menu-toggle").click(function(e) { 
        e.preventDefault(); 
        $("#wrapper").toggleClass("toggled"); 
    });

    $(document).ready(function() {
        // Fungsi AJAX Update Status Realtime
        $('.update-status-ajax').on('change', function() {
            const examId = $(this).data('id');
            const newStatus = $(this).val();
            const selectElement = $(this);

            selectElement.css('opacity', '0.5');

            $.ajax({
                url: 'ajax-update-status.php',
                type: 'POST',
                data: { id: examId, status: newStatus, csrf_token: '<?= csrf_token() ?>' },
                dataType: 'json',
                success: function(response) {
                    selectElement.css('opacity', '1');
                    if (response.success) {
                        selectElement.removeClass('status-aktif status-draft status-selesai')
                                     .addClass('status-' + newStatus);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Status Diperbarui',
                            text: 'Status ujian berhasil diubah menjadi ' + newStatus.toUpperCase(),
                            timer: 1200,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire('Gagal', response.message || 'Gagal memperbarui status', 'error');
                        location.reload();
                    }
                },
                error: function() {
                    selectElement.css('opacity', '1');
                    Swal.fire('Error', 'Terjadi kesalahan koneksi ke server.', 'error');
                }
            });
        });
    });
</script>
</body>
</html>