<?php
session_start();
require_once '../../config/database.php';

// Set header agar outputnya adalah JSON
header('Content-Type: application/json');

// Cek Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir, silakan login ulang']);
    exit;
}
csrf_verify();

// Cek apakah data dikirim lewat POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';

    // Validasi input
    if (empty($id) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }

    // Daftar status yang diizinkan (sesuai Enum database Anda)
    $allowed_status = ['draft', 'aktif', 'selesai'];
    if (!in_array($status, $allowed_status)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }

    try {
        // Lakukan UPDATE hanya untuk kolom status
        $stmt = $pdo->prepare("UPDATE cbt_exams SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$status, $id]);

        if ($result) {
            log_activity("Ubah status ujian ID $id menjadi '$status'", null, null, null, 'ujian');
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan']);
}