<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir. Silakan login ulang.', 'html' => '<div class="p-4 text-center text-danger">Sesi berakhir. Silakan login ulang.</div>', 'pagination' => '']);
    exit;
}
session_write_close();
$teacher_id = (int)$_SESSION['teacher_id'];

$tanggal  = $_GET['tanggal'] ?? '';
$exam_id  = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = (int)($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 500) {
    $limit = 50;
}

$where = " WHERE e.teacher_id = ?";
$params = [$teacher_id];

if ($tanggal) {
    $where .= " AND DATE(cl.waktu_kejadian) = ?";
    $params[] = $tanggal;
}
if ($exam_id) {
    $where .= " AND cl.exam_id = ?";
    $params[] = $exam_id;
}
if ($class_id) {
    $where .= " AND s.class_id = ?";
    $params[] = $class_id;
}

// 1. Hitung total records untuk pagination
$countSql = "SELECT COUNT(*) 
             FROM cbt_cheat_logs cl
             JOIN cbt_students s ON cl.student_id = s.id
             JOIN cbt_exams e ON cl.exam_id = e.id
             $where";
$totalRows = (int)query($countSql, $params)->fetchColumn();
$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 2. Query data log pelanggaran per halaman
$sql = "SELECT cl.*, s.nama_lengkap, s.username, s.nisn, c.jenjang, c.nama_kelas, e.nama_mapel_ujian
        FROM cbt_cheat_logs cl
        JOIN cbt_students s ON cl.student_id = s.id
        LEFT JOIN cbt_classes c ON s.class_id = c.id
        JOIN cbt_exams e ON cl.exam_id = e.id
        $where
        ORDER BY cl.waktu_kejadian DESC 
        LIMIT $offset, $limit";
$logs = query($sql, $params)->fetchAll();

if (empty($logs)) {
    $emptyHtml = '<div class="p-5 text-center text-muted">
            <i class="fas fa-shield-alt fa-3x text-success mb-3 opacity-50"></i>
            <h6 class="fw-bold">Tidak Ada Pelanggaran Ditemukan</h6>
            <p class="small mb-0">Seluruh siswa tertib atau belum ada log tercatat pada filter ini.</p>
          </div>';
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

// Group by student
$grouped = [];
foreach ($logs as $l) {
    $kelasDisplay = !empty($l['jenjang']) ? "Kelas {$l['jenjang']} - {$l['nama_kelas']}" : ($l['nama_kelas'] ?? '-');
    $grouped[$l['student_id']]['info'] = [
        'nama'     => $l['nama_lengkap'],
        'username' => $l['username'],
        'nisn'     => $l['nisn'],
        'kelas'    => $kelasDisplay,
        'mapel'    => $l['nama_mapel_ujian']
    ];
    $grouped[$l['student_id']]['logs'][] = $l;
}

$html = '<div class="accordion" id="accordionLogs">';
$idx = 0;
foreach ($grouped as $sId => $g) {
    $idx++;
    $collapseClass = ($idx === 1) ? 'show' : '';
    $collapsedAttr = ($idx > 1) ? 'collapsed' : '';
    $html .= '
    <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
        <h2 class="accordion-header" id="heading' . $idx . '">
            <button class="accordion-button ' . $collapsedAttr . ' bg-white py-3 px-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $idx . '">
                <div class="d-flex align-items-center justify-content-between w-100 me-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">' . esc($g['info']['nama']) . '</h6>
                            <small class="text-muted">' . esc($g['info']['username']) . ' &bull; ' . esc($g['info']['kelas'] ?? '-') . ' &bull; ' . esc($g['info']['mapel']) . '</small>
                        </div>
                    </div>
                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-semibold">
                        ' . count($g['logs']) . ' Kali Pelanggaran
                    </span>
                </div>
            </button>
        </h2>
        <div id="collapse' . $idx . '" class="accordion-collapse collapse ' . $collapseClass . '" data-bs-parent="#accordionLogs">
            <div class="accordion-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 180px;">Waktu</th>
                                <th style="width: 200px;">Jenis Pelanggaran</th>
                                <th>Keterangan / Detail Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>';
    $no = 1;
    foreach ($g['logs'] as $log) {
        $jenis = strtolower($log['jenis_pelanggaran'] ?? '');
        $badgeClass = 'bg-secondary';
        if (strpos($jenis, 'tab') !== false || strpos($jenis, 'blur') !== false) $badgeClass = 'bg-warning text-dark';
        elseif (strpos($jenis, 'fullscreen') !== false) $badgeClass = 'bg-danger text-white';
        elseif (strpos($jenis, 'screenshot') !== false || strpos($jenis, 'key') !== false) $badgeClass = 'bg-dark text-white';

        $waktuKejadian = date('d/m/Y H:i:s', strtotime($log['waktu_kejadian']));
        $html .= '<tr>
            <td class="text-center fw-bold text-muted">' . ($no++) . '</td>
            <td class="font-monospace small text-muted">
                <i class="far fa-clock me-1"></i>' . $waktuKejadian . '
            </td>
            <td>
                <span class="badge ' . $badgeClass . ' px-2.5 py-1.5 rounded-pill fw-semibold">
                    ' . esc($log['jenis_pelanggaran']) . '
                </span>
            </td>
            <td class="small text-secondary">
                ' . esc($log['keterangan'] ?? '-') . '
            </td>
        </tr>';
    }
    $html .= '  </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';
}
$html .= '</div>';

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

