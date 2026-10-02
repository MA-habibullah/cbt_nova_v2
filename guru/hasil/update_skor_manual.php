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

    // Update skor jawaban
    $pdo->prepare("UPDATE cbt_student_answers SET skor_didapat = ?, is_graded = 1 WHERE id = ?")
        ->execute([$new_skor, $answer_id]);

    // Ambil bobot per kategori dari ujian ini
    $stmtBobotExam = $pdo->prepare("
        SELECT
            SUM(CASE WHEN q.tipe != 'essay' THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
            SUM(CASE WHEN q.tipe  = 'essay' THEN q.bobot_skor ELSE 0 END) AS bobot_essay
        FROM cbt_exam_questions eq
        JOIN cbt_questions q ON eq.question_id = q.id
        WHERE eq.exam_id = (SELECT exam_id FROM cbt_exam_participants WHERE id = ?)
    ");
    $stmtBobotExam->execute([$participant_id]);
    $bobotExam  = $stmtBobotExam->fetch();
    $bobot_obj_e = (float)($bobotExam['bobot_obj']   ?? 0);
    $bobot_ess_e = (float)($bobotExam['bobot_essay'] ?? 0);

    // Ambil skor terkini per kategori
    $stmtSkorKat = $pdo->prepare("
        SELECT
            SUM(CASE WHEN q.tipe != 'essay' THEN a.skor_didapat ELSE 0 END) AS skor_obj,
            SUM(CASE WHEN q.tipe  = 'essay' THEN a.skor_didapat ELSE 0 END) AS skor_essay
        FROM cbt_student_answers a
        JOIN cbt_questions q ON a.question_id = q.id
        WHERE a.participant_id = ?
    ");
    $stmtSkorKat->execute([$participant_id]);
    $skorKat    = $stmtSkorKat->fetch();
    $skor_obj_v  = (float)($skorKat['skor_obj']   ?? 0);
    $skor_ess_v  = (float)($skorKat['skor_essay'] ?? 0);

    // Formula 50:50
    $new_nilai_obj  = ($bobot_obj_e > 0) ? round($skor_obj_v  / $bobot_obj_e * 100, 2) : 0.0;
    $new_nilai_esai = ($bobot_ess_e > 0) ? round($skor_ess_v  / $bobot_ess_e * 100, 2) : 0.0;
    $has_obj_e  = $bobot_obj_e  > 0;
    $has_ess_e  = $bobot_ess_e  > 0;
    if ($has_obj_e && $has_ess_e) {
        $new_nilai_akhir = round(($new_nilai_obj * 0.5) + ($new_nilai_esai * 0.5), 2);
    } elseif ($has_obj_e) {
        $new_nilai_akhir = $new_nilai_obj;
    } elseif ($has_ess_e) {
        $new_nilai_akhir = $new_nilai_esai;
    } else {
        $new_nilai_akhir = 0.0;
    }

    // Cek apakah semua essay sudah dinilai
    $stmtCekEssay = $pdo->prepare("
        SELECT COUNT(*) FROM cbt_student_answers a
        JOIN cbt_questions q ON a.question_id = q.id
        WHERE a.participant_id = ? AND q.tipe = 'essay' AND a.is_graded = 0
    ");
    $stmtCekEssay->execute([$participant_id]);
    $sisa_belum = (int)$stmtCekEssay->fetchColumn();
    $new_skor_status = ($sisa_belum === 0) ? 'final' : 'pending';

    // Update ke tabel peserta
    $updatePart = $pdo->prepare("
        UPDATE cbt_exam_participants
        SET skor_akhir     = ?,
            nilai_objektif = ?,
            nilai_esai     = ?,
            skor_status    = ?
        WHERE id = ?
    ");
    $updatePart->execute([$new_nilai_akhir, $new_nilai_obj, $new_nilai_esai, $new_skor_status, $participant_id]);

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
