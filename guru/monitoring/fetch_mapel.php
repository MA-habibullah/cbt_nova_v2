<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo '<option value="">Sesi berakhir</option>'; exit;
}
$teacher_id    = (int)$_SESSION['teacher_id'];
$tanggal       = $_GET['tanggal'] ?? date('Y-m-d');
$tanggal_start = $tanggal . ' 00:00:00';
$tanggal_end   = $tanggal . ' 23:59:59';

$sql   = "SELECT e.id, e.nama_mapel_ujian, s.nama_mapel 
          FROM cbt_exams e 
          LEFT JOIN cbt_subjects s ON e.subject_id = s.id 
          WHERE e.mulai_pada BETWEEN ? AND ? AND e.status != 'draft' AND e.teacher_id = ?
          ORDER BY e.nama_mapel_ujian ASC";
$mapels = query($sql, [$tanggal_start, $tanggal_end, $teacher_id])->fetchAll();

echo '<option value="">-- Semua Ujian / Test Aktif --</option>';
if ($mapels) {
    foreach ($mapels as $m) {
        $namaTest = trim($m['nama_mapel_ujian'] ?? '');
        $namaMapel = trim($m['nama_mapel'] ?? '');
        $label = $namaTest;
        if (!empty($namaMapel) && strcasecmp($namaMapel, $namaTest) !== 0) {
            $label .= " - " . $namaMapel;
        }
        echo "<option value='{$m['id']}'>" . htmlspecialchars($label) . "</option>";
    }
} else {
    echo '<option value="">Tidak ada ujian Anda di tanggal ini</option>';
}
ob_end_flush();
