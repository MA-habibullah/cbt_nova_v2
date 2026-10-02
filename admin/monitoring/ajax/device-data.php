<?php
ob_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
if (ob_get_length()) ob_clean(); 

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div class='p-4 text-center text-danger'>Sesi berakhir. Silakan login ulang.</div>";
    exit;
}

$tanggal   = $_GET['tanggal'] ?? date('Y-m-d');
$search    = $_GET['search'] ?? '';
$class_id  = $_GET['class_id'] ?? '';
$sesi      = $_GET['sesi'] ?? '';

// 1. Definisikan Parameter awal
$params = [];

// 2. Query Dasar
$sql = "SELECT dl.*, s.nama_lengkap, s.username, c.nama_kelas 
        FROM cbt_device_locks dl
        JOIN cbt_students s ON dl.student_id = s.id
        LEFT JOIN cbt_classes c ON s.class_id = c.id
        WHERE 1=1";

// 3. Tambahkan Filter Tanggal (Berdasarkan kolom created_at di cbt_device_locks)
if($tanggal) {
    $sql .= " AND DATE(dl.created_at) = ?";
    $params[] = $tanggal;
}

// 4. Tambahkan Filter Search
if($search) { 
    $sql .= " AND (s.nama_lengkap LIKE ? OR s.username LIKE ?)"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}

// 5. Tambahkan Filter Kelas
if($class_id) { 
    $sql .= " AND c.id = ?"; 
    $params[] = $class_id; 
}

// 6. Tambahkan Filter Sesi
if($sesi) { 
    $sql .= " AND s.sesi = ?"; 
    $params[] = $sesi; 
}

$sql .= " ORDER BY dl.created_at DESC"; 

try {
    // Perbaikan: Simpan hasil ke $results agar sesuai dengan foreach di bawah
    $results = query($sql, $params)->fetchAll();
} catch (PDOException $e) {
    echo "<div class='p-4 text-center text-danger'>Terjadi kesalahan database: " . $e->getMessage() . "</div>";
    exit;
}

if (!$results) {
    echo "<div class='p-5 text-center text-muted'>
            <i class='fas fa-search fa-3x mb-3 opacity-25'></i><br>
            Tidak ada perangkat yang terkunci dengan kriteria tersebut.
          </div>";
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
        <thead class="bg-light">
            <tr>
                <th width="40" class="ps-3 text-center">
                    <input type="checkbox" id="checkAll" class="form-check-input">
                </th>
                <th>Siswa</th>
                <th>Device ID / Browser</th>
                <th>Alamat IP</th>
                <th>Waktu Lock</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($results as $row): ?>
            <tr>
                <td class="ps-3 text-center">
                    <input type="checkbox" value="<?= esc($row['id']) ?>" class="checkItem form-check-input">
                </td>
                <td>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($row['nama_kelas']) ?> | <?= $row['username'] ?></small>
                </td>
                <td>
                    <code class="small text-danger" style="font-size: 0.7rem;"><?= $row['device_id'] ?></code>
                    <div class="small text-muted mt-1" style="font-size: 0.65rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($row['user_agent']) ?>
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark border fw-normal"><?= $row['ip_address'] ?></span>
                </td>
                <td>
                    <div class="small fw-bold"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                    <div class="text-muted" style="font-size: 0.7rem;"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger border-0" 
                            onclick="bulkResetDevice(<?= esc($row['id']) ?>)" 
                            title="Buka Kunci">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php ob_end_flush(); ?>