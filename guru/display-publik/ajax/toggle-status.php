<?php
require_once dirname(__DIR__, 3) . '/config/database.php';
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
header('Content-Type: application/json');
csrf_verify();

$teacher_id = (int)$_SESSION['teacher_id'];
$token_hash = trim($_POST['token_hash'] ?? '');
$action     = $_POST['action'] ?? '';

if (strlen($token_hash) !== 64 || !in_array($action, ['activate', 'deactivate'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid.']);
    exit;
}

$is_active = ($action === 'activate') ? 1 : 0;
$affected  = query(
    "UPDATE cbt_display_tokens SET is_active = ?, updated_at = NOW()
     WHERE token_hash = ? AND created_by_role = 'guru' AND created_by_id = ?",
    [$is_active, $token_hash, $teacher_id]
)->rowCount();

if ($affected === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan atau bukan milik Anda.']);
    exit;
}

log_activity(
    ($is_active ? 'Aktifkan' : 'Nonaktifkan') . ' dashboard publik: ' . substr($token_hash, 0, 8) . '...',
    null, null, null, 'umum'
);
echo json_encode(['status' => 'success', 'is_active' => $is_active]);
