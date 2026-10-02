<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

// Proteksi: hanya guru yang sudah login
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    ob_end_clean();
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'ID Soal tidak valid']);
    exit;
}

try {
    // Verifikasi soal milik bank soal yang dimiliki guru ini
    $stmt = $pdo->prepare("
        SELECT q.id, q.tipe, q.konten_soal, q.tingkat_kesulitan, q.bobot_skor
        FROM cbt_questions q
        JOIN cbt_bank_soal b ON q.bank_soal_id = b.id
        WHERE q.id = ? AND b.teacher_id = ?
    ");
    $stmt->execute([$id, $teacher_id]);
    $soal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$soal) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['error' => 'Soal tidak ditemukan']);
        exit;
    }

    $stmt_opt = $pdo->prepare("SELECT label, value_target, is_correct FROM cbt_question_options WHERE question_id = ? ORDER BY id ASC");
    $stmt_opt->execute([$id]);
    $soal['options'] = $stmt_opt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode($soal);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
