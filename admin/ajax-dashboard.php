<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$today_start = date('Y-m-d') . ' 00:00:00';
$today_end   = date('Y-m-d') . ' 23:59:59';

// Peserta sedang mengerjakan
$stmtOnline = $pdo->prepare("
    SELECT s.nama_lengkap, c.nama_kelas, c.jenjang, e.nama_mapel_ujian
    FROM cbt_exam_participants p
    JOIN cbt_students s ON p.student_id = s.id
    JOIN cbt_classes c ON s.class_id = c.id
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.status = 'working'
      AND e.mulai_pada BETWEEN ? AND ?
    ORDER BY p.waktu_mulai DESC
    LIMIT 8
");
$stmtOnline->execute([$today_start, $today_end]);
$pesertaOnline = $stmtOnline->fetchAll();

$stmtCount = $pdo->prepare("
    SELECT COUNT(*) FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.status = 'working' AND e.mulai_pada BETWEEN ? AND ?
");
$stmtCount->execute([$today_start, $today_end]);
$totalWorking = (int)$stmtCount->fetchColumn();

// Chart: partisipan per jam hari ini
$stmtChart = $pdo->prepare("
    SELECT HOUR(p.waktu_mulai) as jam, COUNT(*) as jumlah
    FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE e.mulai_pada BETWEEN ? AND ? AND p.waktu_mulai IS NOT NULL
    GROUP BY HOUR(p.waktu_mulai)
    ORDER BY jam ASC
");
$stmtChart->execute([$today_start, $today_end]);
$chartRaw = $stmtChart->fetchAll();

$chartMap = array_column($chartRaw, 'jumlah', 'jam');
$chartLabels = [];
$chartData   = [];
for ($h = 6; $h <= 18; $h++) {
    $chartLabels[] = sprintf('%02d:00', $h);
    $chartData[]   = (int)($chartMap[$h] ?? 0);
}

// Format peserta untuk JSON
$peserta = [];
foreach ($pesertaOnline as $p) {
    $inisial = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $p['nama_lengkap']))));
    $peserta[] = [
        'nama'    => $p['nama_lengkap'],
        'inisial' => substr($inisial, 0, 2),
        'kelas'   => $p['jenjang'] . ' ' . $p['nama_kelas'],
        'mapel'   => $p['nama_mapel_ujian'],
    ];
}

echo json_encode([
    'total_working' => $totalWorking,
    'peserta'       => $peserta,
    'chart_labels'  => $chartLabels,
    'chart_data'    => $chartData,
]);
