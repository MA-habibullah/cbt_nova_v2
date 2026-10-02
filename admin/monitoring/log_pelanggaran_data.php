<?php
ob_start();
require_once '../../config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div class='p-5 text-center text-danger'>Sesi berakhir.</div>"; exit;
}

$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');
$exam_id  = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';

$params = [$tanggal];
$sql = "SELECT l.*, s.nama_lengkap, s.username, c.nama_kelas, e.nama_mapel_ujian 
        FROM cbt_cheat_logs l
        JOIN cbt_students s ON l.student_id = s.id
        JOIN cbt_classes c ON s.class_id = c.id
        JOIN cbt_exams e ON l.exam_id = e.id
        WHERE DATE(l.waktu_kejadian) = ?";

if($exam_id) { $sql .= " AND l.exam_id = ?"; $params[] = $exam_id; }
if($class_id) { $sql .= " AND s.class_id = ?"; $params[] = $class_id; }

$sql .= " ORDER BY l.student_id, l.exam_id, l.waktu_kejadian DESC";

$data = query($sql, $params)->fetchAll();

if (!$data) {
    echo "<div class='p-5 text-center text-muted fst-italic'>Tidak ada catatan pelanggaran pada filter ini.</div>";
    exit;
}

// Group per (student_id, exam_id)
$groups = [];
foreach ($data as $row) {
    $key = $row['student_id'] . '_' . $row['exam_id'];
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'student_id'      => $row['student_id'],
            'exam_id'         => $row['exam_id'],
            'nama_lengkap'    => $row['nama_lengkap'],
            'username'        => $row['username'],
            'nama_kelas'      => $row['nama_kelas'],
            'nama_mapel_ujian'=> $row['nama_mapel_ujian'],
            'items'           => [],
        ];
    }
    $groups[$key]['items'][] = $row;
}

// Urutkan grup: jumlah pelanggaran terbanyak dulu, tie-break waktu terakhir terbaru
usort($groups, function($a, $b) {
    $cnt = count($b['items']) <=> count($a['items']);
    if ($cnt !== 0) return $cnt;
    return strtotime($b['items'][0]['waktu_kejadian']) <=> strtotime($a['items'][0]['waktu_kejadian']);
});

foreach ($groups as $g) {
    $count    = count($g['items']);
    $collapseId = 'grp-' . $g['student_id'] . '-' . $g['exam_id'];
    $lastTime = date('H:i:s', strtotime($g['items'][0]['waktu_kejadian']));

    echo "<div class='card border-0 shadow-sm rounded-3 mb-2 log-group-card'>
        <div class='log-group-header d-flex justify-content-between align-items-center p-3'
             data-bs-toggle='collapse' href='#{$collapseId}' role='button'
             aria-expanded='false' aria-controls='{$collapseId}'>
            <div class='d-flex align-items-center gap-3'>
                <i class='fas fa-chevron-right small log-group-icon'></i>
                <div>
                    <div class='fw-bold text-dark'>" . htmlspecialchars($g['nama_lengkap']) . "
                        <span class='font-mono-sm text-primary ms-1'>" . htmlspecialchars($g['username']) . "</span>
                    </div>
                    <div class='text-muted small mt-1'>
                        <span class='badge bg-light text-dark border fw-normal me-1'>" . htmlspecialchars($g['nama_kelas']) . "</span>
                        " . htmlspecialchars($g['nama_mapel_ujian']) . "
                    </div>
                </div>
            </div>
            <div class='text-end'>
                <span class='badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2'>
                    {$count} Pelanggaran
                </span>
                <div class='text-muted small mt-1'>Terakhir: {$lastTime}</div>
            </div>
        </div>
        <div class='collapse' id='{$collapseId}'>
            <div class='card-body border-top pt-3'>
                <table class='table table-sm table-borderless mb-0'>
                    <tbody>";
    foreach ($g['items'] as $item) {
        $waktu = date('d M Y H:i:s', strtotime($item['waktu_kejadian']));
        echo "<tr>
            <td class='text-muted font-mono-sm' style='width:180px;'>{$waktu}</td>
            <td>
                <span class='badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1'>
                    <i class='fas fa-exclamation-triangle me-1'></i>" . strtoupper($item['tipe_pelanggaran']) . "
                </span>
            </td>
        </tr>";
    }
    echo "        </tbody>
                </table>
            </div>
        </div>
    </div>";
}
ob_end_flush();