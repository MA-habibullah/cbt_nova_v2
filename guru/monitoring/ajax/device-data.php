<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean(); 

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo "<div class='p-4 text-center text-danger'>Sesi berakhir. Silakan login ulang.</div>";
    exit;
}

$teacher_id = (int)$_SESSION['teacher_id'];
$tanggal   = trim($_GET['tanggal'] ?? date('Y-m-d'));
$search    = trim($_GET['search'] ?? '');
$class_id  = trim($_GET['class_id'] ?? '');
$sesi      = trim($_GET['sesi'] ?? '');

// 1. Definisikan Parameter awal (diikat ke guru)
$params = [$teacher_id, $teacher_id];

// 2. Query Dasar dibatasi pada siswa yang terkait dengan guru
$sql = "SELECT dl.*, s.nama_lengkap, s.username, s.nisn, c.nama_kelas, c.jenjang 
        FROM cbt_device_locks dl
        JOIN cbt_students s ON dl.student_id = s.id
        LEFT JOIN cbt_classes c ON s.class_id = c.id
        WHERE (
            s.id IN (
                SELECT DISTINCT ep.student_id 
                FROM cbt_exam_participants ep
                JOIN cbt_exams e ON ep.exam_id = e.id
                WHERE e.teacher_id = ?
            )
            OR s.class_id IN (
                SELECT DISTINCT class_id FROM cbt_exams WHERE teacher_id = ?
            )
        )";

// 3. Tambahkan Filter Tanggal
if ($tanggal) {
    $sql .= " AND DATE(dl.created_at) = ?";
    $params[] = $tanggal;
}

// 4. Tambahkan Filter Search
if ($search) { 
    $sql .= " AND (s.nama_lengkap LIKE ? OR s.username LIKE ? OR s.nisn LIKE ?)"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}

// 5. Tambahkan Filter Kelas
if ($class_id) { 
    $sql .= " AND c.id = ?"; 
    $params[] = $class_id; 
}

// 6. Tambahkan Filter Sesi
if ($sesi) { 
    $sql .= " AND s.sesi = ?"; 
    $params[] = $sesi; 
}

$sql .= " ORDER BY dl.created_at DESC"; 

try {
    $results = query($sql, $params)->fetchAll();
} catch (PDOException $e) {
    echo "<div class='p-4 text-center text-danger fw-semibold'><i class='fas fa-exclamation-triangle me-1'></i> Terjadi kesalahan database: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

if (!$results) {
    echo "<div class='p-5 text-center text-muted no-data'>
            <div class='mb-3'>
                <div class='avatar-placeholder rounded-circle bg-light d-inline-flex align-items-center justify-content-center text-muted' style='width: 70px; height: 70px; border: 2px dashed #cbd5e1;'>
                    <i class='fas fa-laptop-code fa-2x opacity-50'></i>
                </div>
            </div>
            <h6 class='fw-bold text-dark mb-1'>Tidak Ada Perangkat Terkunci</h6>
            <p class='small text-muted mb-0'>Tidak ditemukan data perangkat terkunci pada lingkup kelas/ujian Anda dengan filter pencarian yang dipilih.</p>
          </div>";
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" style="min-width: 950px;">
        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
            <tr>
                <th width="45" class="ps-3 text-center">
                    <input type="checkbox" id="checkAll" class="form-check-input">
                </th>
                <th style="min-width: 260px;">Peserta / Siswa</th>
                <th style="min-width: 130px;">Kelas & Sesi</th>
                <th style="min-width: 250px;">Device ID & Browser</th>
                <th style="min-width: 140px;">Alamat IP</th>
                <th style="min-width: 150px;">Waktu Terkunci</th>
                <th class="text-center pe-3" width="100">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($results as $row): 
                $initial = !empty($row['nama_lengkap']) ? strtoupper(substr($row['nama_lengkap'], 0, 1)) : '?';
            ?>
            <tr>
                <td class="ps-3 text-center">
                    <input type="checkbox" value="<?= esc($row['id']) ?>" class="checkItem form-check-input">
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-placeholder rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; font-size: 14px;">
                            <?= htmlspecialchars($initial) ?>
                        </div>
                        <div>
                            <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                            <div class="d-flex align-items-center gap-1 mt-0.5">
                                <span class="badge bg-primary-subtle text-primary font-monospace small" style="font-size: 0.7rem;"><?= htmlspecialchars($row['username']) ?></span>
                                <?php if (!empty($row['nisn'])): ?>
                                    <span class="text-muted small" style="font-size: 0.7rem;">NISN: <?= htmlspecialchars($row['nisn']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if (!empty($row['nama_kelas'])): ?>
                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill"><?= htmlspecialchars($row['nama_kelas']) ?></span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted border">-</span>
                    <?php endif; ?>
                    <div class="small text-muted mt-0.5">
                        <i class="fas fa-clock me-1 text-secondary" style="font-size: 0.65rem;"></i>Sesi <?= esc($row['sesi'] ?? '1') ?>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <span class="font-monospace text-dark small bg-light border px-2 py-0.5 rounded text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($row['device_id'] ?? '-') ?>">
                            <i class="fas fa-fingerprint me-1 text-muted"></i><?= htmlspecialchars($row['device_id'] ?? '-') ?>
                        </span>
                        <small class="text-muted text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($row['user_agent'] ?? '-') ?>">
                            <i class="fas fa-globe me-1"></i><?= htmlspecialchars(substr($row['user_agent'] ?? 'Browser tidak dikenal', 0, 45)) ?>...
                        </small>
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark border font-monospace">
                        <i class="fas fa-network-wired me-1 text-primary"></i><?= htmlspecialchars($row['ip_address'] ?? '127.0.0.1') ?>
                    </span>
                </td>
                <td>
                    <div class="small fw-semibold text-dark">
                        <i class="fas fa-calendar-alt me-1 text-muted"></i><?= date('d/m/Y', strtotime($row['created_at'])) ?>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-clock me-1 text-muted"></i><?= date('H:i:s', strtotime($row['created_at'])) ?> WIB
                    </div>
                </td>
                <td class="text-center pe-3">
                    <button type="button" class="btn btn-sm btn-outline-danger px-2.5 py-1" onclick="bulkResetDevice(<?= (int)$row['id'] ?>)" title="Buka Kunci Perangkat Ini">
                        <i class="fas fa-unlock-alt me-1"></i> Buka Kunci
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
