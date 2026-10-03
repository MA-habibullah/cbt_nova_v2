<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir. Silakan login ulang.', 'html' => "<div class='p-5 text-center text-danger'>Sesi berakhir. Silakan login ulang.</div>", 'pagination' => '']);
    exit;
}
session_write_close();

$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');
$exam_id  = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = (int)($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 500) {
    $limit = 50;
}

$where = " WHERE 1=1";
$params = [];

if ($tanggal) {
    $where .= " AND DATE(l.waktu_kejadian) = ?";
    $params[] = $tanggal;
}
if ($exam_id) { 
    $where .= " AND l.exam_id = ?"; 
    $params[] = $exam_id; 
}
if ($class_id) { 
    $where .= " AND s.class_id = ?"; 
    $params[] = $class_id; 
}

// 1. Hitung total catatan pelanggaran
$countSql = "SELECT COUNT(*) 
             FROM cbt_cheat_logs l
             JOIN cbt_students s ON l.student_id = s.id
             JOIN cbt_exams e ON l.exam_id = e.id
             $where";
$totalRows = (int)query($countSql, $params)->fetchColumn();
$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 2. Query data pelanggaran per halaman
$sql = "SELECT l.*, s.nama_lengkap, s.username, c.jenjang, c.nama_kelas, e.nama_mapel_ujian 
        FROM cbt_cheat_logs l
        JOIN cbt_students s ON l.student_id = s.id
        LEFT JOIN cbt_classes c ON s.class_id = c.id
        JOIN cbt_exams e ON l.exam_id = e.id
        $where
        ORDER BY l.waktu_kejadian DESC, l.student_id, l.exam_id 
        LIMIT $offset, $limit";

$data = query($sql, $params)->fetchAll();

if (!$data) {
    $emptyHtml = "<div class='p-5 text-center text-muted fst-italic'><i class='fas fa-info-circle me-1'></i> Tidak ada catatan pelanggaran pada filter ini.</div>";
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'      => 'success',
        'html'        => $emptyHtml,
        'pagination'  => '',
        'total'       => 0,
        'page'        => 1,
        'total_pages' => 1
    ]);
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
            'jenjang'         => $row['jenjang'] ?? '',
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

$html = "";
foreach ($groups as $g) {
    $count      = count($g['items']);
    $collapseId = 'grp-' . $g['student_id'] . '-' . $g['exam_id'];
    $lastTime   = date('H:i:s', strtotime($g['items'][0]['waktu_kejadian']));
    $kelasLabel = !empty($g['jenjang']) ? "Kelas {$g['jenjang']} - {$g['nama_kelas']}" : ($g['nama_kelas'] ?? '-');

    $html .= "<div class='card border-0 shadow-sm rounded-3 mb-2 log-group-card'>
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
                        <span class='badge bg-light text-dark border fw-normal me-1'>" . htmlspecialchars($kelasLabel) . "</span>
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
                <div class='table-responsive'>
                    <table class='table table-sm table-borderless mb-0' style='min-width: 320px;'>
                        <tbody>";
    foreach ($g['items'] as $item) {
        $waktu = date('d M Y H:i:s', strtotime($item['waktu_kejadian']));
        $html .= "<tr>
            <td class='text-muted font-mono-sm' style='width:180px;'>{$waktu}</td>
            <td>
                <span class='badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1'>
                    <i class='fas fa-exclamation-triangle me-1'></i>" . strtoupper($item['tipe_pelanggaran']) . "
                </span>
            </td>
        </tr>";
    }
    $html .= "        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>";
}

// 3. Render Pagination Bar
$from = $totalRows > 0 ? ($offset + 1) : 0;
$to   = min($offset + $limit, $totalRows);

$paginationHtml = "<div class='card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top'>
    <div class='small text-muted'>
        Menampilkan <strong>" . number_format($from, 0, ',', '.') . "</strong> &ndash; <strong>" . number_format($to, 0, ',', '.') . "</strong> dari total <strong>" . number_format($totalRows, 0, ',', '.') . "</strong> log pelanggaran
    </div>";

if ($totalPages > 1) {
    $paginationHtml .= "<nav aria-label='Navigasi Halaman'>
        <ul class='pagination pagination-sm mb-0'>
            <li class='page-item " . ($page <= 1 ? 'disabled' : '') . "'>
                <a class='page-link' href='javascript:void(0)' onclick='goToLogPage(1)' title='Halaman Pertama'><i class='fas fa-angle-double-left'></i></a>
            </li>
            <li class='page-item " . ($page <= 1 ? 'disabled' : '') . "'>
                <a class='page-link' href='javascript:void(0)' onclick='goToLogPage(" . ($page - 1) . ")' title='Sebelumnya'><i class='fas fa-angle-left'></i></a>
            </li>";

    $startPage = max(1, $page - 2);
    $endPage   = min($totalPages, $page + 2);
    if ($startPage > 1) {
        $paginationHtml .= "<li class='page-item disabled'><span class='page-link'>&hellip;</span></li>";
    }
    for ($p = $startPage; $p <= $endPage; $p++) {
        $activeClass = ($page === $p) ? 'active' : '';
        $paginationHtml .= "<li class='page-item {$activeClass}'>
            <a class='page-link' href='javascript:void(0)' onclick='goToLogPage({$p})'>{$p}</a>
        </li>";
    }
    if ($endPage < $totalPages) {
        $paginationHtml .= "<li class='page-item disabled'><span class='page-link'>&hellip;</span></li>";
    }

    $paginationHtml .= "<li class='page-item " . ($page >= $totalPages ? 'disabled' : '') . "'>
                <a class='page-link' href='javascript:void(0)' onclick='goToLogPage(" . ($page + 1) . ")' title='Berikutnya'><i class='fas fa-angle-right'></i></a>
            </li>
            <li class='page-item " . ($page >= $totalPages ? 'disabled' : '') . "'>
                <a class='page-link' href='javascript:void(0)' onclick='goToLogPage({$totalPages})' title='Halaman Terakhir'><i class='fas fa-angle-double-right'></i></a>
            </li>
        </ul>
    </nav>";
}
$paginationHtml .= "</div>";

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'      => 'success',
    'html'        => $html,
    'pagination'  => $paginationHtml,
    'total'       => $totalRows,
    'page'        => $page,
    'total_pages' => $totalPages,
    'offset'      => $offset,
    'limit'       => $limit
]);
exit;