<?php
require_once dirname(__DIR__, 3) . '/config/database.php';
if (!isset($_SESSION['teacher_id'])) { http_response_code(403); echo json_encode(['status'=>'error']); exit; }
header('Content-Type: application/json');

$teacher_id = (int)$_SESSION['teacher_id'];
$token_hash = trim($_GET['token_hash'] ?? '');
if (strlen($token_hash) !== 64) { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }

$row = query(
    "SELECT label, url, config_json FROM cbt_display_tokens WHERE token_hash = ? AND created_by_role = 'guru' AND created_by_id = ?",
    [$token_hash, $teacher_id]
)->fetch();
if (!$row) { echo json_encode(['status'=>'error','message'=>'Tidak ditemukan.']); exit; }

// Decode config — prefer stored config_json, fallback to decrypting from URL
$slots_raw = null;
if ($row['config_json']) {
    $slots_raw = json_decode($row['config_json'], true);
} elseif ($row['url']) {
    // Extract token from URL and decrypt
    $parsed = parse_url($row['url']);
    parse_str($parsed['query'] ?? '', $qs);
    $t = $qs['t'] ?? '';
    if ($t) {
        $config = decrypt_display_config($t);
        if ($config) $slots_raw = $config['slots'] ?? [$config];
    }
}

if (!$slots_raw) { echo json_encode(['status'=>'error','message'=>'Konfigurasi tidak ditemukan.']); exit; }

// Enrich each slot with exam name, date, and available classes
$slots = [];
foreach ($slots_raw as $s) {
    $exam_id = (int)($s['exam_id'] ?? 0);
    if (!$exam_id) continue;
    $exam = query("SELECT id, nama_mapel_ujian, DATE(mulai_pada) as exam_date FROM cbt_exams WHERE id = ?", [$exam_id])->fetch();
    if (!$exam) continue;
    $classes = query(
        "SELECT DISTINCT c.id, c.jenjang, c.nama_kelas FROM cbt_classes c
         INNER JOIN cbt_exam_participants ep ON COALESCE(ep.class_id, st.class_id) = c.id
         INNER JOIN cbt_students st ON ep.student_id = st.id
         WHERE ep.exam_id = ? ORDER BY c.jenjang, c.nama_kelas",
        [$exam_id]
    )->fetchAll();
    $slots[] = [
        'exam_id'    => $exam_id,
        'exam_name'  => $exam['nama_mapel_ujian'],
        'exam_date'  => $exam['exam_date'],
        'class_id'   => $s['class_id'] ?? 'all',
        'classes'    => $classes,
        'top_limit'  => (int)($s['top_limit'] ?? 10),
        'mask_names' => !empty($s['mask_names']),
        'banner_tpl' => $s['banner_tpl'] ?? '',
    ];
}

echo json_encode(['status' => 'success', 'label' => $row['label'], 'slots' => $slots]);
