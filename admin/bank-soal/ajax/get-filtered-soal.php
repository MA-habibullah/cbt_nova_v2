<?php
error_reporting(0);
header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/config/database.php';

// 1. Tangkap Parameter
// Perhatikan: Select2 mengirim 'q' untuk pencarian teks
$search = isset($_GET['q']) ? $_GET['q'] : ''; 
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

try {
    // 2. Query ke tabel cbt_bank_soal
    // Pastikan nama tabel Anda memang cbt_bank_soal
    $query = "SELECT id, nama_bank_soal AS text FROM cbt_bank_soal WHERE 1=1";
    $params = [];

    // Filter berdasarkan Mata Pelajaran (jika ada)
    if ($subject_id > 0) {
        $query .= " AND subject_id = ?";
        $params[] = $subject_id;
    }

    // Filter Pencarian
    if (!empty($search)) {
        $query .= " AND nama_bank_soal LIKE ?";
        $params[] = "%$search%";
    }

    $query .= " LIMIT 20";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Output JSON
    echo json_encode([
        'results' => $data ? $data : []
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'results' => [],
        'error' => $e->getMessage()
    ]);
}
exit;