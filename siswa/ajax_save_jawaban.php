<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once '../includes/helpers.php';

// Set zona waktu dan header JSON
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

// 1. Proteksi Sesi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login kembali']);
    exit;
}
csrf_verify();

// 2. Ambil Input & Rilis Session Lock Segera (Eliminasi Worker Starvation)
$exam_id     = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
$student_id  = (int)$_SESSION['student_id'];
$jawaban     = $_POST['jawaban'] ?? ''; 

// Rilis lock session agar request berikutnya dari siswa tidak terblokir
session_write_close();

if ($exam_id === 0 || $question_id === 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
    exit;
}

try {
    // --- 3. VALIDASI DEADLINE & STATUS PARTISIPASI (1 Query Cepat) ---
    $stmtPart = $pdo->prepare("
        SELECT p.id as participant_id, p.waktu_mulai, p.tambahan_waktu, p.status,
               e.durasi_menit, e.selesai_pada,
               NOW() as now_db
        FROM cbt_exam_participants p
        JOIN cbt_exams e ON p.exam_id = e.id
        WHERE p.exam_id = ? AND p.student_id = ?
    ");
    $stmtPart->execute([$exam_id, $student_id]);
    $row = $stmtPart->fetch();

    if (!$row) {
        throw new Exception("Sesi ujian tidak ditemukan");
    }

    if ($row['status'] === 'finished') {
        throw new Exception("Ujian sudah selesai, jawaban tidak dapat diubah");
    }

    if ($row['status'] === 'blocked') {
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'blocked', 'message' => 'Akun Anda sedang diblokir']);
        exit;
    }

    // Kalkulasi deadline in-memory (tanpa query duplikat)
    $waktu_mulai    = strtotime($row['waktu_mulai']);
    $waktu_sekarang = strtotime($row['now_db']);

    if ((int)$row['tambahan_waktu'] > 0) {
        $final_deadline = $waktu_mulai + ((int)$row['tambahan_waktu'] * 60);
    } else {
        $waktu_habis_durasi = $waktu_mulai + ((int)$row['durasi_menit'] * 60);
        $batas_jadwal       = strtotime($row['selesai_pada']);
        $final_deadline     = min($waktu_habis_durasi, $batas_jadwal);
    }

    if ($waktu_sekarang > $final_deadline) {
        $pdo->prepare("
            UPDATE cbt_exam_participants
            SET status = 'finished', waktu_selesai = NOW(), tambahan_waktu = 0
            WHERE id = ? AND status = 'working'
        ")->execute([$row['participant_id']]);

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'status'  => 'timeout',
            'message' => 'Waktu pengerjaan Anda sudah berakhir!'
        ]);
        exit;
    }
    // --- END VALIDASI ---

    // 4. Formatting Jawaban
    $jawaban_final = "";
    if (is_array($jawaban) || is_object($jawaban)) {
        $jawaban_final = json_encode($jawaban);
    } else {
        $jawaban_final = trim((string)$jawaban);
    }

    // 5. ULTRA HIGH-THROUGHPUT ATOMIC UPSERT (Single Roundtrip)
    // Memanfaatkan UNIQUE KEY (participant_id, question_id) untuk 1 query instan
    $participant_id = (int)$row['participant_id'];

    $stmtUpsert = $pdo->prepare("
        INSERT INTO cbt_student_answers 
            (participant_id, question_id, jawaban_simpan, created_at, updated_at)
        VALUES 
            (?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            jawaban_simpan = VALUES(jawaban_simpan),
            updated_at = NOW()
    ");
    $stmtUpsert->execute([$participant_id, $question_id, $jawaban_final]);

    // Sukses
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'Jawaban berhasil disimpan']);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}