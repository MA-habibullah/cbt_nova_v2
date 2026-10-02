<?php
require_once dirname(__DIR__, 3) . '/config/database.php';
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['status'=>'error']); exit; }
header('Content-Type: application/json');

$tanggal = $_POST['tanggal'] ?? date('Y-m-d');
$exams = query(
    "SELECT id, nama_mapel_ujian, mulai_pada, jenjang
     FROM cbt_exams
     WHERE DATE(mulai_pada) = ? AND status != 'draft'
     ORDER BY mulai_pada ASC",
    [$tanggal]
)->fetchAll();

echo json_encode(['status' => 'success', 'data' => $exams]);
