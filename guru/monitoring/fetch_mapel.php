<?php
ob_start();
require_once '../../config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo '<option value="">Sesi berakhir</option>'; exit;
}
$teacher_id    = (int)$_SESSION['teacher_id'];
$tanggal       = $_GET['tanggal'] ?? date('Y-m-d');
$tanggal_start = $tanggal . ' 00:00:00';
$tanggal_end   = $tanggal . ' 23:59:59';

$sql   = "SELECT id, nama_mapel_ujian FROM cbt_exams WHERE mulai_pada BETWEEN ? AND ? AND status != 'draft' AND teacher_id = ?";
$mapels = query($sql, [$tanggal_start, $tanggal_end, $teacher_id])->fetchAll();

echo '<option value="">-- Semua Mapel Aktif --</option>';
if ($mapels) {
    foreach ($mapels as $m) {
        echo "<option value='{$m['id']}'>".htmlspecialchars($m['nama_mapel_ujian'])."</option>";
    }
} else {
    echo '<option value="">Tidak ada ujian Anda di tanggal ini</option>';
}
ob_end_flush();
