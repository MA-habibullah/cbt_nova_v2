<?php
require_once dirname(__DIR__, 3) . '/config/database.php';
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['status'=>'error']); exit; }
header('Content-Type: application/json');
csrf_verify();

$slots_raw = json_decode($_POST['slots'] ?? '[]', true);

if (!is_array($slots_raw)) {
    echo json_encode(['status' => 'error', 'message' => 'Format data tidak valid.']);
    exit;
}

$slots = [];
foreach ($slots_raw as $s) {
    $exam_id = (int)($s['exam_id'] ?? 0);
    if (!$exam_id) continue;
    $slots[] = [
        'exam_id'    => $exam_id,
        'class_id'   => (isset($s['class_id']) && $s['class_id'] === 'all') ? 'all' : (int)($s['class_id'] ?? 0),
        'top_limit'  => in_array((int)($s['top_limit'] ?? 10), [10, 25, 50]) ? (int)$s['top_limit'] : 10,
        'mask_names' => !empty($s['mask_names']),
        'banner_tpl' => trim($s['banner_tpl'] ?? ''),
    ];
}

if (empty($slots)) {
    echo json_encode(['status' => 'error', 'message' => 'Pilih minimal satu jadwal ujian.']);
    exit;
}

// Generate unique short code (retry on collision)
do {
    $short_code = generate_short_code();
    $exists = query("SELECT id FROM cbt_display_tokens WHERE short_code = ?", [$short_code])->fetch();
} while ($exists);

$token_hash = hash('sha256', $short_code . '_' . time());
$url        = BASE_URL . 'public/dashboard.php?c=' . $short_code;

$first_exam_id = $slots[0]['exam_id'] ?? 0;
$exam_row = $first_exam_id
    ? query("SELECT nama_mapel_ujian FROM cbt_exams WHERE id = ?", [$first_exam_id])->fetch()
    : null;
$label    = ($exam_row['nama_mapel_ujian'] ?? 'Dashboard') . ' — ' . date('d M Y H:i');
$admin_id = (int)$_SESSION['admin_id'];

query(
    "INSERT INTO cbt_display_tokens (token_hash, short_code, label, url, config_json, created_by_role, created_by_id)
     VALUES (?, ?, ?, ?, ?, 'admin', ?)",
    [$token_hash, $short_code, $label, $url, json_encode($slots), $admin_id]
);

echo json_encode(['status' => 'success', 'url' => $url, 'token_hash' => $token_hash]);
