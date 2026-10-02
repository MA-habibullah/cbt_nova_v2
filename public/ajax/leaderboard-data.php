<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

$short_code = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['c'] ?? '');

$registry = ($short_code !== '')
    ? query("SELECT is_active, config_json FROM cbt_display_tokens WHERE short_code = ?", [$short_code])->fetch()
    : null;

if (!$registry) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'URL tidak valid atau tidak ditemukan.']);
    exit;
}

if (!$registry['is_active']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Dashboard ini telah dinonaktifkan.']);
    exit;
}

$slots      = json_decode($registry['config_json'] ?? '[]', true) ?: [];
$slot_index = max(0, (int)($_GET['slot'] ?? 0));
$slot       = $slots[$slot_index] ?? null;

if (!$slot || empty($slot['exam_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Slot tidak valid.']);
    exit;
}

$exam_id   = (int) $slot['exam_id'];
$class_id  = $slot['class_id'] ?? 'all';
$top_limit = in_array((int)($slot['top_limit'] ?? 10), [3, 5, 10]) ? (int)$slot['top_limit'] : 10;
$mask      = (bool)($slot['mask_names'] ?? false);

// Build leaderboard query
if ($class_id === 'all') {
    $rows = $pdo->prepare(
        "SELECT s.nama_lengkap, k.nama_kelas, k.jenjang,
                p.skor_akhir, p.skor_status, p.status
         FROM cbt_exam_participants p
         JOIN cbt_students s ON p.student_id = s.id
         LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
         WHERE p.exam_id = ? AND p.status = 'finished'
         ORDER BY p.skor_akhir DESC
         LIMIT ?"
    );
    $rows->execute([$exam_id, $top_limit]);
} else {
    $rows = $pdo->prepare(
        "SELECT s.nama_lengkap, k.nama_kelas, k.jenjang,
                p.skor_akhir, p.skor_status, p.status
         FROM cbt_exam_participants p
         JOIN cbt_students s ON p.student_id = s.id
         LEFT JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
         WHERE p.exam_id = ? AND COALESCE(p.class_id, s.class_id) = ? AND p.status = 'finished'
         ORDER BY p.skor_akhir DESC
         LIMIT ?"
    );
    $rows->execute([$exam_id, (int)$class_id, $top_limit]);
}
$data = $rows->fetchAll();

// Stats
if ($class_id === 'all') {
    $stats = query(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN p.status = 'finished' THEN 1 ELSE 0 END) as selesai
         FROM cbt_exam_participants p
         WHERE p.exam_id = ?",
        [$exam_id]
    )->fetch();
} else {
    $stats = query(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN p.status = 'finished' THEN 1 ELSE 0 END) as selesai
         FROM cbt_exam_participants p
         JOIN cbt_students s ON p.student_id = s.id
         WHERE p.exam_id = ? AND s.class_id = ?",
        [$exam_id, (int)$class_id]
    )->fetch();
}

$result = [];
foreach ($data as $i => $row) {
    $result[] = [
        'rank'         => $i + 1,
        'nama_display' => $mask ? mask_student_name($row['nama_lengkap']) : $row['nama_lengkap'],
        'kelas'        => ($row['jenjang'] ?? '') . ' - ' . ($row['nama_kelas'] ?? '-'),
        'skor'         => round((float)($row['skor_akhir'] ?? 0), 2),
        'skor_status'  => $row['skor_status'],
    ];
}

echo json_encode([
    'status'        => 'success',
    'data'          => $result,
    'total_peserta' => (int)($stats['total'] ?? 0),
    'selesai'       => (int)($stats['selesai'] ?? 0),
    'updated_at'    => date('H:i:s'),
]);
