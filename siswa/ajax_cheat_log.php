<?php
ob_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) exit;
csrf_verify();

$exam_id    = (int)$_POST['exam_id'];
$student_id = (int)$_SESSION['student_id'];
session_write_close();
$type       = $_POST['type'] ?? 'Tab Switch';

try {
    // Catat ke tabel cbt_cheat_logs
    $stmt = $pdo->prepare("INSERT INTO cbt_cheat_logs (student_id, exam_id, tipe_pelanggaran, waktu_kejadian) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$student_id, $exam_id, $type]);

    // Opsi: Hitung jumlah pelanggaran, jika >= 3 otomatis ganti status jadi 'blocked'
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM cbt_cheat_logs WHERE student_id = ? AND exam_id = ?");
    $stmtCount->execute([$student_id, $exam_id]);
    $count = $stmtCount->fetchColumn();

    $response = ['status' => 'logged', 'count' => $count];

    if ($count >= 3) {
        $pdo->prepare("UPDATE cbt_exam_participants SET status = 'blocked' WHERE student_id = ? AND exam_id = ?")
            ->execute([$student_id, $exam_id]);
        $response['status'] = 'blocked';
        log_activity("Diblokir dari ujian ID $exam_id setelah $count pelanggaran ($type)", (int)$student_id, null, null, 'monitoring');
    } else {
        log_activity("Deteksi pelanggaran di ujian ID $exam_id: $type (ke-$count)", (int)$student_id, null, null, 'monitoring');
    }

    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    exit;
}