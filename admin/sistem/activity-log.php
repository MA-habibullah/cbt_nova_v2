<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// --- 1. PROSES HAPUS LOG BERDASARKAN RENTANG TANGGAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_log'])) {
    csrf_verify();
    $dari   = trim($_POST['dari'] ?? '');
    $sampai = trim($_POST['sampai'] ?? '');

    if ($dari !== '' && $sampai !== '') {
        $stmt = $pdo->prepare("DELETE FROM cbt_activity_logs WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$dari, $sampai]);
        $deleted = $stmt->rowCount();
        log_activity("Hapus activity log: $deleted baris (rentang $dari s/d $sampai)", null, null, null, 'sistem');
        header("Location: activity-log.php?msg=deleted&count=$deleted");
        exit;
    }
}

// --- 2. TANGKAP FILTER & PARAMETER PAGINATION ---
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = max(10, (int)($_GET['limit'] ?? 50)); // Default 50 baris per halaman
$f_role   = trim($_GET['f_role'] ?? '');
$f_type   = trim($_GET['f_type'] ?? '');
$f_dari   = trim($_GET['f_dari'] ?? '');
$f_sampai = trim($_GET['f_sampai'] ?? '');
$search   = trim($_GET['search'] ?? '');

$where  = "WHERE 1=1";
$params = [];

if ($f_role !== '') {
    $where .= " AND role = ?";
    $params[] = $f_role;
}
if ($f_type !== '') {
    $where .= " AND type = ?";
    $params[] = $f_type;
}
if ($f_dari !== '' && $f_sampai !== '') {
    $where .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $f_dari;
    $params[] = $f_sampai;
} elseif ($f_dari !== '') {
    $where .= " AND DATE(created_at) >= ?";
    $params[] = $f_dari;
} elseif ($f_sampai !== '') {
    $where .= " AND DATE(created_at) <= ?";
    $params[] = $f_sampai;
}

if ($search !== '') {
    $where .= " AND (nama LIKE ? OR activity LIKE ? OR ip_address LIKE ?)";
    $s = "%" . like_escape($search) . "%";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
}

// --- 3. HITUNG TOTAL DATA UNTUK PAGINATION ---
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_activity_logs $where");
$totalStmt->execute($params);
$totalData = (int)$totalStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalData / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// --- 4. AMBIL DATA LOG DENGAN PREPARED STATEMENT ---
$stmt = $pdo->prepare("SELECT * FROM cbt_activity_logs $where ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
$dataParams = array_merge($params, [$limit, $offset]);
$stmt->execute($dataParams);
$logs = $stmt->fetchAll();

// Helper URL Pagination
function build_log_url($targetPage) {
    $query = $_GET;
    $query['page'] = $targetPage;
    return '?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

<style>
    .badge-role-superadmin { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .badge-role-admin      { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    .badge-role-proktor    { background-color: #cffafe; color: #0e7490; border: 1px solid #a5f3fc; }
    .badge-role-guru       { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-role-siswa      { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .badge-role-system     { background-color: #1e293b; color: #ffffff; border: 1px solid #0f172a; }

    .badge-type-auth       { background-color: #fef08a; color: #854d0e; border: 1px solid #fde047; }
    .badge-type-akses      { background-color: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
    .badge-type-master     { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    .badge-type-import     { background-color: #ccfbf1; color: #115e59; border: 1px solid #99f6e4; }
    .badge-type-ujian      { background-color: #ede9fe; color: #6b21a8; border: 1px solid #ddd6fe; }
    .badge-type-monitoring { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .badge-type-hasil      { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-type-sistem     { background-color: #0f172a; color: #ffffff; }
    .badge-type-umum       { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }

    .table-log tbody tr { transition: background-color 0.15s ease; }
    .table-log tbody tr:hover { background-color: #f8fafc; }
    .ip-badge { font-family: 'Fira Code', monospace; font-size: 0.78rem; background: #f1f5f9; padding: 3px 7px; border-radius: 5px; border: 1px solid #e2e8f0; color: #334155; }
</style>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center w-100">
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-history me-2"></i> Log Aktivitas Sistem</h5>
                    <small class="text-muted">Audit trail dan rekam jejak aktivitas seluruh pengguna di sistem</small>
                </div>
            </div>
        </nav>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mx-4 mt-4 mb-0" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong><?= (int)($_GET['count'] ?? 0) ?> baris</strong> riwayat log berhasil dibersihkan dari sistem.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="container-fluid px-4 pt-4 pb-5">

            <!-- Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3 align-items-end" id="filterForm">
                        <div class="col-12 col-md-3">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">PENCARIAN</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama / aktivitas / IP..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">ROLE PENGGUNA</label>
                            <select name="f_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Role</option>
                                <option value="superadmin" <?= esc($f_role === 'superadmin' ? 'selected' : '') ?>>Superadmin</option>
                                <option value="admin"      <?= esc($f_role === 'admin'      ? 'selected' : '') ?>>Admin</option>
                                <option value="proktor"    <?= esc($f_role === 'proktor'    ? 'selected' : '') ?>>Proktor</option>
                                <option value="guru"       <?= esc($f_role === 'guru'       ? 'selected' : '') ?>>Guru</option>
                                <option value="siswa"      <?= esc($f_role === 'siswa'      ? 'selected' : '') ?>>Siswa</option>
                                <option value="system"     <?= esc($f_role === 'system'     ? 'selected' : '') ?>>System</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">TIPE AKTIVITAS</label>
                            <select name="f_type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Tipe</option>
                                <option value="auth"       <?= esc($f_type === 'auth'       ? 'selected' : '') ?>>Auth (Login/Logout)</option>
                                <option value="akses"      <?= esc($f_type === 'akses'      ? 'selected' : '') ?>>Akses Halaman</option>
                                <option value="master"     <?= esc($f_type === 'master'     ? 'selected' : '') ?>>Master Data</option>
                                <option value="import"     <?= esc($f_type === 'import'     ? 'selected' : '') ?>>Import Data</option>
                                <option value="ujian"      <?= esc($f_type === 'ujian'      ? 'selected' : '') ?>>Ujian / Soal</option>
                                <option value="monitoring" <?= esc($f_type === 'monitoring' ? 'selected' : '') ?>>Monitoring</option>
                                <option value="hasil"      <?= esc($f_type === 'hasil'      ? 'selected' : '') ?>>Hasil / Nilai</option>
                                <option value="sistem"     <?= esc($f_type === 'sistem'     ? 'selected' : '') ?>>Sistem</option>
                                <option value="umum"       <?= esc($f_type === 'umum'       ? 'selected' : '') ?>>Umum</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">DARI TANGGAL</label>
                            <input type="date" name="f_dari" class="form-control form-control-sm" value="<?= htmlspecialchars($f_dari) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">SAMPAI TANGGAL</label>
                            <input type="date" name="f_sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($f_sampai) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-6 col-md-1">
                            <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">BARIS</label>
                            <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="25"  <?= esc($limit === 25  ? 'selected' : '') ?>>25</option>
                                <option value="50"  <?= esc($limit === 50  ? 'selected' : '') ?>>50</option>
                                <option value="100" <?= esc($limit === 100 ? 'selected' : '') ?>>100</option>
                                <option value="200" <?= esc($limit === 200 ? 'selected' : '') ?>>200</option>
                            </select>
                        </div>

                        <?php if ($search !== '' || $f_role !== '' || $f_type !== '' || $f_dari !== '' || $f_sampai !== '' || $limit !== 50): ?>
                        <div class="col-12 text-end mt-2">
                            <a href="activity-log.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i> Reset Filter</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Hapus Log by Range Card -->
            <div class="card border-0 shadow-sm border-start border-danger border-4 mb-4">
                <div class="card-body p-3">
                    <form method="POST" action="" id="formHapusLog" class="row g-2 align-items-center">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                        <input type="hidden" name="hapus_log" value="1">
                        <div class="col-12 col-md-auto">
                            <span class="small fw-bold text-danger"><i class="fas fa-trash-alt me-1"></i> Bersihkan Log Lama:</span>
                        </div>
                        <div class="col-6 col-md-2">
                            <input type="date" name="dari" id="hapus_dari" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-auto small text-muted">s/d</div>
                        <div class="col-6 col-md-2">
                            <input type="date" name="sampai" id="hapus_sampai" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="button" onclick="confirmHapusLog()" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash me-1"></i> Hapus Rentang Log
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Log Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-list-ul me-2 text-primary"></i>Rekam Aktivitas Sistem</h6>
                        <small class="text-muted">Total Ditemukan: <strong><?= number_format($totalData, 0, ',', '.') ?></strong> Catatan Log</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        Halaman <?= $page ?> dari <?= $totalPages ?>
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-log align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-secondary small text-uppercase fw-bold">
                                <th width="140">Waktu</th>
                                <th width="200">Nama Pengguna</th>
                                <th width="110" class="text-center">Role</th>
                                <th width="120" class="text-center">Tipe</th>
                                <th>Deskripsi Aktivitas</th>
                                <th width="150" class="text-center">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0 fw-bold">Tidak ada riwayat log aktivitas yang sesuai dengan filter.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php
                            $role_badge_class = [
                                'superadmin' => 'badge-role-superadmin',
                                'admin'      => 'badge-role-admin',
                                'proktor'    => 'badge-role-proktor',
                                'guru'       => 'badge-role-guru',
                                'siswa'      => 'badge-role-siswa',
                                'system'     => 'badge-role-system',
                            ];

                            $type_badge_meta = [
                                'auth'       => ['badge-type-auth', 'fa-key', 'Auth'],
                                'akses'      => ['badge-type-akses', 'fa-eye', 'Akses'],
                                'master'     => ['badge-type-master', 'fa-database', 'Master'],
                                'import'     => ['badge-type-import', 'fa-file-import', 'Import'],
                                'ujian'      => ['badge-type-ujian', 'fa-pen-nib', 'Ujian'],
                                'monitoring' => ['badge-type-monitoring', 'fa-desktop', 'Monitoring'],
                                'hasil'      => ['badge-type-hasil', 'fa-chart-line', 'Hasil'],
                                'sistem'     => ['badge-type-sistem', 'fa-cogs', 'Sistem'],
                                'umum'       => ['badge-type-umum', 'fa-circle', 'Umum'],
                            ];

                            foreach ($logs as $log):
                                $rClass = $role_badge_class[$log['role']] ?? 'badge-role-siswa';
                                [$tClass, $tIcon, $tLabel] = $type_badge_meta[$log['type'] ?? 'umum'] ?? ['badge-type-umum', 'fa-circle', ucfirst($log['type'] ?? 'umum')];
                            ?>
                            <tr>
                                <td class="text-nowrap">
                                    <div class="small fw-bold text-dark"><i class="far fa-calendar-alt text-muted me-1"></i><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                    <div class="small text-muted"><i class="far fa-clock text-muted me-1"></i><?= date('H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($log['nama']) ?></div>
                                    <?php if (!empty($log['user_id'])): ?>
                                        <small class="text-muted">ID: #<?= (int)$log['user_id'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $rClass ?> px-2 py-1 text-uppercase font-monospace" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($log['role']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $tClass ?> px-2 py-1 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                        <i class="fas <?= $tIcon ?>"></i>
                                        <?= htmlspecialchars($tLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark small lh-base"><?= htmlspecialchars($log['activity']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="ip-badge" title="Alamat IP Klien"><?= htmlspecialchars($log['ip_address'] ?: '-') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1 || $totalData > 0): ?>
                <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="small text-muted">
                        Menampilkan <strong><?= $totalData > 0 ? ($offset + 1) : 0 ?></strong> &ndash; <strong><?= min($offset + $limit, $totalData) ?></strong> dari total <strong><?= number_format($totalData, 0, ',', '.') ?></strong> log aktivitas
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navigasi Halaman">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- First & Prev -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_log_url(1) ?>" title="Halaman Pertama"><i class="fas fa-angle-double-left"></i></a>
                            </li>
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_log_url($page - 1) ?>" title="Sebelumnya"><i class="fas fa-angle-left"></i></a>
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
                                    <a class="page-link" href="<?= build_log_url($p) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($endPage < $totalPages): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>

                            <!-- Next & Last -->
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_log_url($page + 1) ?>" title="Berikutnya"><i class="fas fa-angle-right"></i></a>
                            </li>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= build_log_url($totalPages) ?>" title="Halaman Terakhir"><i class="fas fa-angle-double-right"></i></a>
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

    function confirmHapusLog() {
        var dari = document.getElementById('hapus_dari').value;
        var sampai = document.getElementById('hapus_sampai').value;

        if (!dari || !sampai) {
            Swal.fire({
                icon: 'warning',
                title: 'Rentang Tanggal Belum Lengkap',
                text: 'Harap tentukan tanggal awal dan tanggal akhir pembersihan log.'
            });
            return;
        }

        Swal.fire({
            title: 'Hapus Log Aktivitas?',
            html: 'Seluruh riwayat log dari tanggal <strong>' + dari + '</strong> sampai <strong>' + sampai + '</strong> akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-1"></i> Ya, Hapus Sekarang',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('formHapusLog').submit();
            }
        });
    }
</script>
</body>
</html>
