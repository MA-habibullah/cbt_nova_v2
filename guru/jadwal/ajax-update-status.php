<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir, silakan login ulang']); exit;
}
csrf_verify();
$teacher_id = $_SESSION['teacher_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']); exit;
}

$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if (!$id || !in_array($status, ['draft', 'aktif', 'selesai'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']); exit;
}

try {
    // Cek lock status bank soal milik exam ini
    $chk = $pdo->prepare("SELECT b.status FROM cbt_exams e JOIN cbt_bank_soal b ON e.bank_soal_id = b.id WHERE e.id = ? AND e.teacher_id = ?");
    $chk->execute([$id, $teacher_id]);
    $row = $chk->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Ujian tidak ditemukan atau bukan milik Anda']); exit;
    }
    if ($row['status'] === 'nonaktif') {
        echo json_encode(['success' => false, 'message' => 'Bank soal dikunci oleh admin']); exit;
    }

    $stmt = $pdo->prepare("UPDATE cbt_exams SET status = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$status, $id, $teacher_id]);
    log_activity("Ubah status ujian ID $id menjadi '$status'", null, null, null, 'ujian');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
