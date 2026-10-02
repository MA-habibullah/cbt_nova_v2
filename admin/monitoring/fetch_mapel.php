<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo '<option value="">Sesi berakhir</option>'; exit;
}

$tanggal       = $_GET['tanggal'] ?? date('Y-m-d');
$tanggal_start = $tanggal . ' 00:00:00';
$tanggal_end   = $tanggal . ' 23:59:59';

$sql = "SELECT id, nama_mapel_ujian FROM cbt_exams WHERE mulai_pada BETWEEN ? AND ? AND status != 'draft'";
$mapels = query($sql, [$tanggal_start, $tanggal_end])->fetchAll();

echo '<option value="">-- Semua Mapel Aktif --</option>';
if ($mapels) {
    foreach ($mapels as $m) {
        echo "<option value='{$m['id']}'>".htmlspecialchars($m['nama_mapel_ujian'])."</option>";
    }
} else {
    echo '<option value="">Tidak ada ujian di tanggal ini</option>';
}