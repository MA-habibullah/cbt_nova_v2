<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Jakarta');
require_once '../config/database.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo json_encode(['status' => 'error']);
    exit;
}

$exam_id    = (int)($_GET['exam_id'] ?? 0);
$student_id = (int)$_SESSION['student_id'];
session_write_close();

$stmt = $pdo->prepare("
    SELECT p.id as participant_id, p.waktu_mulai, p.tambahan_waktu, p.status,
           e.durasi_menit, e.selesai_pada,
           NOW() as now_db
    FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.exam_id = ? AND p.student_id = ?
");
$stmt->execute([$exam_id, $student_id]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['status' => 'error']);
    exit;
}

if ($row['status'] === 'blocked') {
    echo json_encode(['status' => 'blocked']);
    exit;
}

    if ($row['status'] === 'finished') {
        echo json_encode(['status' => 'finished']);
        exit;
    }

    // High-Concurrency: Hitung sisa waktu langsung in-memory dari row data tanpa query kedua
    $waktu_mulai    = strtotime($row['waktu_mulai']);
    $waktu_sekarang = strtotime($row['now_db']);

    if ((int)$row['tambahan_waktu'] > 0) {
        $final_deadline = $waktu_mulai + (int)$row['tambahan_waktu'] * 60;
    } else {
        $waktu_habis_durasi = $waktu_mulai + (int)$row['durasi_menit'] * 60;
        $batas_jadwal       = strtotime($row['selesai_pada']);
        $final_deadline     = min($waktu_habis_durasi, $batas_jadwal);
    }

    $sisa_detik = max(0, $final_deadline - $waktu_sekarang);

    echo json_encode([
        'status'     => 'ok',
        'sisa_detik' => $sisa_detik,
    ]);
