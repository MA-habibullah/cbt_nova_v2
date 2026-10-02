<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}

// --- PROSES HAPUS BY RANGE TANGGAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_log'])) {
    csrf_verify();
    $dari  = $_POST['dari']  ?? '';
    $sampai = $_POST['sampai'] ?? '';

    if ($dari && $sampai) {
        $stmt = $pdo->prepare("DELETE FROM cbt_activity_logs WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$dari, $sampai]);
        $deleted = $stmt->rowCount();
        log_activity("Hapus activity log: $deleted baris (rentang $dari s/d $sampai)", null, null, null, 'sistem');
        header("Location: activity-log.php?msg=deleted&count=$deleted");
        exit;
    }
}


// --- FILTER & PAGINATION ---
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$f_role   = $_GET['f_role']  ?? '';
$f_type   = $_GET['f_type']  ?? '';
$f_dari   = $_GET['f_dari']  ?? '';
$f_sampai = $_GET['f_sampai'] ?? '';
$search   = trim($_GET['search'] ?? '');

$where  = "WHERE 1=1";
$params = [];

if ($f_role)   { $where .= " AND role = ?";              $params[] = $f_role; }
if ($f_type)   { $where .= " AND type = ?";              $params[] = $f_type; }
if ($f_dari)   { $where .= " AND DATE(created_at) >= ?"; $params[] = $f_dari; }
if ($f_sampai) { $where .= " AND DATE(created_at) <= ?"; $params[] = $f_sampai; }
if ($search)   { $where .= " AND (nama LIKE ? OR activity LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; }

$total = $pdo->prepare("SELECT COUNT(*) FROM cbt_activity_logs $where");
$total->execute($params);
$totalData = $total->fetchColumn();
$pages = ceil($totalData / $limit);

$stmt = $pdo->prepare("SELECT * FROM cbt_activity_logs $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
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
                <button class="btn btn-light border me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-history me-2"></i> Log Aktivitas Sistem</h5>
                    <small class="text-muted">Audit trail dan rekam jejak aktivitas seluruh pengguna di sistem</small>
                </div>
            </div>
        </nav>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-0 rounded-0" role="alert">
            <i class="fas fa-trash me-2"></i>
            <strong><?= (int)($_GET['count'] ?? 0) ?> baris</strong> log berhasil dihapus.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="container-fluid px-4 pt-4">

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Cari Nama / Aktivitas</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Role</label>
                            <select name="f_role" class="form-select form-select-sm">
                                <option value="">Semua Role</option>
                                <option value="superadmin" <?= esc($f_role === 'superadmin' ? 'selected' : '') ?>>Superadmin</option>
                                <option value="admin"      <?= esc($f_role === 'admin'      ? 'selected' : '') ?>>Admin</option>
                                <option value="proktor"    <?= esc($f_role === 'proktor'    ? 'selected' : '') ?>>Proktor</option>
                                <option value="guru"       <?= esc($f_role === 'guru'       ? 'selected' : '') ?>>Guru</option>
                                <option value="siswa"      <?= esc($f_role === 'siswa'      ? 'selected' : '') ?>>Siswa</option>
                                <option value="system"     <?= esc($f_role === 'system'     ? 'selected' : '') ?>>System</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Tipe Aktivitas</label>
                            <select name="f_type" class="form-select form-select-sm">
                                <option value="">Semua Tipe</option>
                                <option value="auth"       <?= esc($f_type === 'auth'       ? 'selected' : '') ?>>Auth (Login/Logout)</option>
                                <option value="akses"      <?= esc($f_type === 'akses'      ? 'selected' : '') ?>>Akses Halaman</option>
                                <option value="master"     <?= esc($f_type === 'master'     ? 'selected' : '') ?>>Master Data</option>
                                <option value="import"     <?= esc($f_type === 'import'     ? 'selected' : '') ?>>Import</option>
                                <option value="ujian"      <?= esc($f_type === 'ujian'      ? 'selected' : '') ?>>Ujian / Soal</option>
                                <option value="monitoring" <?= esc($f_type === 'monitoring' ? 'selected' : '') ?>>Monitoring</option>
                                <option value="hasil"      <?= esc($f_type === 'hasil'      ? 'selected' : '') ?>>Hasil / Skor</option>
                                <option value="sistem"     <?= esc($f_type === 'sistem'     ? 'selected' : '') ?>>Sistem</option>
                                <option value="umum"       <?= esc($f_type === 'umum'       ? 'selected' : '') ?>>Umum</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
                            <input type="date" name="f_dari" class="form-control form-control-sm" value="<?= esc($f_dari) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                            <input type="date" name="f_sampai" class="form-control form-control-sm" value="<?= esc($f_sampai) ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-search me-1"></i> Filter</button>
                            <a href="activity-log.php" class="btn btn-light btn-sm border"><i class="fas fa-sync"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Hapus by Range -->
            <div class="card border-0 shadow-sm border-start border-danger border-3 mb-3">
                <div class="card-body py-2">
                    <form method="POST" onsubmit="return confirm('Yakin hapus log pada rentang tanggal ini? Tindakan tidak dapat dibatalkan.')">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label small fw-bold mb-1 text-danger"><i class="fas fa-trash me-1"></i>Hapus Log</label>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="dari" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-auto small text-muted pt-1">s/d</div>
                            <div class="col-md-2">
                                <input type="date" name="sampai" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="hapus_log" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash me-1"></i> Hapus Log
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Log -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
                    <span class="small text-muted">Menampilkan <strong><?= count($logs) ?></strong> dari <strong><?= number_format($totalData) ?></strong> log</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th width="150">Waktu</th>
                                <th width="150">Nama</th>
                                <th width="85">Role</th>
                                <th width="100">Tipe</th>
                                <th>Aktivitas</th>
                                <th width="125">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada log ditemukan.</td></tr>
                            <?php else: ?>
                            <?php
                            $role_badge = [
                                'superadmin' => 'bg-danger',
                                'admin'      => 'bg-primary',
                                'proktor'    => 'bg-info text-dark',
                                'guru'       => 'bg-success',
                                'siswa'      => 'bg-secondary',
                                'system'     => 'bg-dark',
                            ];
                            $type_badge = [
                                'auth'       => ['bg-warning text-dark', 'fa-key'],
                                'akses'      => ['bg-light text-secondary border', 'fa-eye'],
                                'master'     => ['bg-primary', 'fa-users'],
                                'import'     => ['bg-teal-700 text-white', 'fa-file-import'],
                                'ujian'      => ['bg-purple-700 text-white', 'fa-edit'],
                                'monitoring' => ['bg-info text-dark', 'fa-desktop'],
                                'hasil'      => ['bg-success', 'fa-chart-bar'],
                                'sistem'     => ['bg-dark', 'fa-cogs'],
                                'umum'       => ['bg-secondary', 'fa-circle'],
                            ];
                            foreach ($logs as $log):
                                $rbadge = $role_badge[$log['role']] ?? 'bg-secondary';
                                [$tbadge, $ticon] = $type_badge[$log['type'] ?? 'umum'] ?? ['bg-secondary', 'fa-circle'];
                            ?>
                            <tr>
                                <td class="text-nowrap text-muted">
                                    <?= date('d/m/Y', strtotime($log['created_at'])) ?><br>
                                    <strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong>
                                </td>
                                <td class="fw-semibold"><?= htmlspecialchars($log['nama']) ?></td>
                                <td><span class="badge <?= $rbadge ?>"><?= htmlspecialchars($log['role']) ?></span></td>
                                <td>
                                    <span class="badge <?= $tbadge ?> d-flex align-items-center gap-1" style="width:fit-content">
                                        <i class="fas <?= $ticon ?> small"></i>
                                        <?= htmlspecialchars($log['type'] ?? 'umum') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['activity']) ?></td>
                                <td class="text-muted font-monospace"><?= htmlspecialchars($log['ip_address']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                <div class="card-footer bg-white border-top">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-end mb-0 gap-1">
                            <?php 
                            $base_params = http_build_query(array_filter([
                                'search' => $search, 
                                'f_role' => $f_role, 
                                'f_type' => $f_type, 
                                'f_dari' => $f_dari, 
                                'f_sampai' => $f_sampai
                            ]));

                            // Tombol Previous
                            $prevDisabled = ($page <= 1) ? 'disabled' : '';
                            echo "<li class='page-item $prevDisabled'><a class='page-link' href='?page=" . ($page - 1) . "&$base_params'>&laquo;</a></li>";

                            // Logika nomor halaman (tampilkan 2 sebelum dan 2 sesudah)
                            $start_number = ($page > 3) ? $page - 2 : 1;
                            $end_number = ($page < ($pages - 2)) ? $page + 2 : $pages;

                            if ($start_number > 1) {
                                echo "<li class='page-item'><a class='page-link' href='?page=1&$base_params'>1</a></li>";
                                if ($start_number > 2) echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                            }

                            for ($i = $start_number; $i <= $end_number; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= esc($i) ?>&<?= esc($base_params) ?>"><?= esc($i) ?></a>
                                </li>
                            <?php endfor;

                            if ($end_number < $pages) {
                                if ($end_number < $pages - 1) echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                                echo "<li class='page-item'><a class='page-link' href='?page=$pages&$base_params'>$pages</a></li>";
                            }

                            // Tombol Next
                            $nextDisabled = ($page >= $pages) ? 'disabled' : '';
                            echo "<li class='page-item $nextDisabled'><a class='page-link' href='?page=" . ($page + 1) . "&$base_params'>&raquo;</a></li>";
                            ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
