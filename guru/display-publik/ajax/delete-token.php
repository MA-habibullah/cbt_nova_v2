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
$token_hash = trim($_POST['token_hash'] ?? '');
if (strlen($token_hash) !== 64) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid.']);
    exit;
}

$affected = query(
    "DELETE FROM cbt_display_tokens WHERE token_hash = ? AND created_by_role = 'guru' AND created_by_id = ?",
    [$token_hash, $teacher_id]
)->rowCount();

if ($affected === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan atau bukan milik Anda.']);
    exit;
}

echo json_encode(['status' => 'success']);
