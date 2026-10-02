<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
}
csrf_verify();
$teacher_id = (int)$_SESSION['teacher_id'];

$answer_id = isset($_POST['id'])   ? (int)$_POST['id']     : 0;
$new_skor  = isset($_POST['skor']) ? (float)$_POST['skor'] : 0;

if ($answer_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID Jawaban tidak valid']); exit;
}

try {
    $pdo->beginTransaction();

    // Ambil participant_id, bobot asli soal, dan verifikasi kepemilikan guru
    $stmt = $pdo->prepare(
        "SELECT a.participant_id, q.bobot_skor FROM cbt_student_answers a
         JOIN cbt_questions q ON a.question_id = q.id
         JOIN cbt_exam_participants p ON a.participant_id = p.id
         JOIN cbt_exams e ON p.exam_id = e.id
         WHERE a.id = ? AND e.teacher_id = ?"
    );
    $stmt->execute([$answer_id, $teacher_id]);
    $ans = $stmt->fetch();

    if (!$ans) {
        throw new Exception("Data tidak ditemukan atau akses ditolak.");
    }

    $participant_id = $ans['participant_id'];
    // Clamp skor antara 0 dan bobot asli soal
    $new_skor = max(0, min((float)$ans['bobot_skor'], $new_skor));

    // Update skor jawaban manual
    $pdo->prepare("UPDATE cbt_student_answers SET skor_didapat = ?, is_graded = 1 WHERE id = ?")
        ->execute([$new_skor, $answer_id]);

    // Hitung ulang seluruh nilai peserta secara terpusat
    $calc = hitung_dan_simpan_nilai_peserta($pdo, (int)$participant_id);
    if (!$calc['success']) {
        throw new Exception($calc['error'] ?? 'Gagal menghitung nilai peserta.');
    }

    $new_nilai_akhir = (float)($calc['nilai_akhir'] ?? 0);
    $new_nilai_obj   = (float)($calc['nilai_objektif'] ?? 0);
    $new_nilai_esai  = (float)($calc['nilai_esai'] ?? 0);
    $new_skor_status = $calc['skor_status'] ?? 'final';

    $pdo->commit();
    log_activity("Update skor manual — jawaban ID $answer_id, skor baru: $new_skor, nilai akhir: $new_nilai_akhir", null, null, null, 'hasil');

    echo json_encode([
        'status'         => 'success',
        'message'        => 'Skor berhasil diperbarui',
        'total_akhir'    => $new_nilai_akhir,
        'nilai_objektif' => $new_nilai_obj,
        'nilai_esai'     => $new_nilai_esai,
        'nilai_akhir'    => $new_nilai_akhir,
        'skor_status'    => $new_skor_status,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
