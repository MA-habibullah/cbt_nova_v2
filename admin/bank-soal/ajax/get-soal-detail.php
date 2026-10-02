<?php
/**
 * File: admin/bank-soal/ajax/get-soal-detail.php
 * Deskripsi: Mengambil detail soal dan opsi jawaban dalam format JSON
 */

// 1. Bersihkan buffer untuk mencegah spasi/karakter aneh keluar sebelum JSON
ob_start();

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// 2. Perbaikan Path: Naik 2 tingkat (dari ajax/ ke bank-soal/ lalu ke admin/) 
// lalu masuk ke config/database.php
$config_path = __DIR__ . '/../../../config/database.php';

if (file_exists($config_path)) {
    require_once $config_path;
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database config not found at ' . $config_path]);
    exit;
}

// 3. Pastikan output selalu JSON
header('Content-Type: application/json');

// Proteksi: admin atau guru yang sudah login
if (!isset($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') !== 'guru') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // Ambil data utama soal
        $stmt = $pdo->prepare("SELECT id, tipe, konten_soal, tingkat_kesulitan, bobot_skor FROM cbt_questions WHERE id = ?");
        $stmt->execute([$id]);
        $soal = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($soal) {
            // Ambil data pilihan jawaban
            $stmt_opt = $pdo->prepare("SELECT label, value_target, is_correct FROM cbt_question_options WHERE question_id = ? ORDER BY id ASC");
            $stmt_opt->execute([$id]);
            $options = $stmt_opt->fetchAll(PDO::FETCH_ASSOC);

            // Gabungkan data
            $soal['options'] = $options;

            // Bersihkan buffer dan kirim JSON
            ob_end_clean();
            echo json_encode($soal);
            exit;
        } else {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['error' => 'Soal tidak ditemukan di database']);
        }
    } catch (PDOException $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'ID Soal tidak valid atau kosong']);
}