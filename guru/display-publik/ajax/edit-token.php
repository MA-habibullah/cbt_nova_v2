<?php
require_once '../../../config/database.php';
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
header('Content-Type: application/json');
csrf_verify();

$teacher_id = (int)$_SESSION['teacher_id'];
$old_hash   = trim($_POST['old_token_hash'] ?? '');
$slots_raw  = json_decode($_POST['slots'] ?? '[]', true);

if (strlen($old_hash) !== 64 || !is_array($slots_raw)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid.']);
    exit;
}

$slots = [];
foreach ($slots_raw as $s) {
    $exam_id = (int)($s['exam_id'] ?? 0);
    if (!$exam_id) continue;
    // Verify exam belongs to this teacher
    $exam = query("SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?", [$exam_id, $teacher_id])->fetch();
    if (!$exam) continue;
    $slots[] = [
        'exam_id'    => $exam_id,
        'class_id'   => (isset($s['class_id']) && $s['class_id'] === 'all') ? 'all' : (int)($s['class_id'] ?? 0),
        'top_limit'  => in_array((int)($s['top_limit'] ?? 10), [10, 25, 50]) ? (int)$s['top_limit'] : 10,
        'mask_names' => !empty($s['mask_names']),
        'banner_tpl' => trim($s['banner_tpl'] ?? ''),
    ];
}

if (empty($slots)) {
    echo json_encode(['status' => 'error', 'message' => 'Minimal satu jadwal harus dipilih.']);
    exit;
}

// Generate new unique short code (skip collision with own row)
do {
    $new_code = generate_short_code();
    $exists   = query("SELECT id FROM cbt_display_tokens WHERE short_code = ? AND token_hash != ?", [$new_code, $old_hash])->fetch();
} while ($exists);

$new_hash = hash('sha256', $new_code . '_' . time());
$new_url  = BASE_URL . 'public/dashboard.php?c=' . $new_code;

$first_exam = query("SELECT nama_mapel_ujian FROM cbt_exams WHERE id = ?", [$slots[0]['exam_id']])->fetch();
$label = ($first_exam['nama_mapel_ujian'] ?? 'Dashboard') . ' — ' . date('d M Y H:i');

$affected = query(
    "UPDATE cbt_display_tokens SET token_hash = ?, short_code = ?, label = ?, url = ?, config_json = ?, updated_at = NOW()
     WHERE token_hash = ? AND created_by_role = 'guru' AND created_by_id = ?",
    [$new_hash, $new_code, $label, $new_url, json_encode($slots), $old_hash, $teacher_id]
)->rowCount();

if ($affected === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan atau bukan milik Anda.']);
    exit;
}

echo json_encode(['status' => 'success', 'new_url' => $new_url]);
