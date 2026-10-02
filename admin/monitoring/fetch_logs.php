<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div class='p-5 text-center text-red-500'>Sesi berakhir.</div>"; exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$exam_id    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if (!$student_id || !$exam_id) {
    echo "<div class='p-5 text-center text-red-500'>Data tidak valid.</div>";
    exit;
}

// Ambil ID Participant untuk keperluan Kick/Blokir
$participant = query("SELECT id, status FROM cbt_exam_participants WHERE student_id = ? AND exam_id = ?", [$student_id, $exam_id])->fetch();

// Ambil Log Pelanggaran
$logs = query("SELECT * FROM cbt_cheat_logs WHERE student_id = ? AND exam_id = ? ORDER BY waktu_kejadian DESC", [$student_id, $exam_id])->fetchAll();

if (!$logs) {
    echo "<div class='p-10 text-center text-gray-400'>Tidak ada catatan pelanggaran.</div>";
} else {
    echo "<div class='max-h-[450px] overflow-y-auto custom-scroll'>";
    echo "<ul class='divide-y divide-gray-100'>";
    foreach ($logs as $l) {
        echo "<li class='p-4 hover:bg-gray-50'>";
        echo "  <div class='flex justify-between items-start mb-1'>";
        echo "      <span class='text-[10px] font-bold text-rose-600'>" . date('H:i:s', strtotime($l['waktu_kejadian'])) . " WIB</span>";
        echo "      <span class='text-[10px] bg-gray-100 px-2 py-0.5 rounded text-gray-500 tracking-tighter'>" . date('d M Y', strtotime($l['waktu_kejadian'])) . "</span>";
        echo "  </div>";
        echo "  <div class='text-sm font-bold text-gray-800'>" . htmlspecialchars($l['tipe_pelanggaran']) . "</div>";
        
        // FITUR SCREENSHOT: Jika ada file screenshot
        if (!empty($l['screenshot'])) {
            $ss_path = BASE_URL . "assets/img/screenshots/" . htmlspecialchars($l['screenshot']);
            echo "<div class='mt-2'><a href='$ss_path' target='_blank'><img src='$ss_path' class='rounded-lg border shadow-sm max-h-32 hover:opacity-75 transition'></a></div>";
        }
        
        echo "</li>";
    }
    echo "</ul></div>";

    // TOMBOL KICK INDIVIDUAL
    echo "<div class='p-4 bg-gray-50 border-t flex justify-between items-center'>";
    echo "  <div class='text-[10px] font-bold text-gray-400'>TOTAL: " . count($logs) . " PELANGGARAN</div>";
    
    if ($participant && $participant['status'] !== 'blocked') {
        echo "  <button onclick=\"individualKick({$participant['id']})\" class='bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold px-4 py-2 rounded-lg transition shadow-sm'>";
        echo "      <i class='fas fa-user-slash mr-1'></i> BLOKIR SISWA INI";
        echo "  </button>";
    } else {
        echo "  <span class='text-red-600 font-bold text-[10px] uppercase'><i class='fas fa-ban mr-1'></i> Sudah Diblokir</span>";
    }
    echo "</div>";
}
ob_end_flush();