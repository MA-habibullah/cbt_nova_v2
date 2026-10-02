<?php
require_once '../../../config/database.php';
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error']);
    exit;
}
header('Content-Type: application/json');

$rows = query(
    "SELECT dt.id, dt.token_hash, dt.label, dt.url, dt.config_json, dt.is_active,
            dt.created_at, dt.created_by_role,
            CASE
                WHEN dt.created_by_role = 'guru' THEN t.nama_lengkap
                ELSE 'Admin'
            END AS creator_name
     FROM cbt_display_tokens dt
     LEFT JOIN cbt_teachers t ON dt.created_by_role = 'guru' AND dt.created_by_id = t.id
     ORDER BY dt.created_at DESC
     LIMIT 50",
    []
)->fetchAll();

// Batch-fetch exam names for collapse/expand display
$all_exam_ids = [];
foreach ($rows as $row) {
    if ($row['config_json']) {
        foreach (json_decode($row['config_json'], true) ?: [] as $s) {
            if (!empty($s['exam_id'])) $all_exam_ids[] = (int)$s['exam_id'];
        }
    }
}
$exam_names = [];
if ($all_exam_ids) {
    $ph = implode(',', array_fill(0, count(array_unique($all_exam_ids)), '?'));
    foreach (query("SELECT id, nama_mapel_ujian FROM cbt_exams WHERE id IN ($ph)", array_values(array_unique($all_exam_ids)))->fetchAll() as $e) {
        $exam_names[$e['id']] = $e['nama_mapel_ujian'];
    }
}

foreach ($rows as &$row) {
    $row['slots_display'] = [];
    if ($row['config_json']) {
        foreach (json_decode($row['config_json'], true) ?: [] as $s) {
            $eid = (int)($s['exam_id'] ?? 0);
            $row['slots_display'][] = [
                'exam_name' => $exam_names[$eid] ?? ('Ujian #' . $eid),
                'class_id'  => $s['class_id'] ?? 'all',
                'top_limit' => $s['top_limit'] ?? 10,
            ];
        }
    }
    unset($row['config_json']);
}
unset($row);

echo json_encode(['status' => 'success', 'data' => $rows]);
