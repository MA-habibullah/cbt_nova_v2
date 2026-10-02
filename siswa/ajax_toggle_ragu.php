<?php
// Pastikan tidak ada output sebelum JSON
ob_start();
require_once '../config/database.php';

// Proteksi akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
csrf_verify();

$exam_id     = (int)$_POST['exam_id'];
$question_id = (int)$_POST['question_id'];
$is_ragu     = (int)$_POST['ragu']; // Bernilai 1 atau 0
$student_id  = (int)$_SESSION['student_id'];
session_write_close();

try {
    // 1. Ambil ID Partisipasi yang sedang aktif
    $stmtPart = $pdo->prepare("SELECT id FROM cbt_exam_participants WHERE exam_id = ? AND student_id = ? AND status = 'working'");
    $stmtPart->execute([$exam_id, $student_id]);
    $participant = $stmtPart->fetch();

    if (!$participant) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Sesi ujian tidak aktif']);
        exit;
    }

    $participant_id = $participant['id'];

    // 2. High-Throughput Atomic UPSERT status ragu-ragu
    $stmtUpsert = $pdo->prepare("
        INSERT INTO cbt_student_answers (participant_id, question_id, is_ragu, created_at, updated_at) 
        VALUES (?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE is_ragu = VALUES(is_ragu), updated_at = NOW()
    ");
    $stmtUpsert->execute([$participant_id, $question_id, $is_ragu]);

    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}