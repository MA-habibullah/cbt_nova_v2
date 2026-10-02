<?php
/**
 * CBT NOVA - Controller Hapus Jadwal Ujian Guru
 * Lokasi: guru/bank-soal/controllers/bank-soal-test-delete.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/database.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../../../index.php"); 
    exit;
}

$teacher_id = (int)$_SESSION['teacher_id'];
$exam_id    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$id_bank    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($exam_id > 0) {
    // Validasi kepemilikan ujian oleh guru
    $chk = $pdo->prepare("SELECT id, bank_soal_id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
    $chk->execute([$exam_id, $teacher_id]);
    $examData = $chk->fetch();

    if (!$examData) {
        header("Location: ../test.php?id=$id_bank&msg=unauthorized");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // A. Hapus log pelanggaran jika ada
        $pdo->prepare("DELETE FROM cbt_cheat_logs WHERE exam_id = ?")->execute([$exam_id]);

        // B. Hapus semua jawaban siswa
        $sql_ans = "DELETE sa FROM cbt_student_answers sa 
                    JOIN cbt_exam_participants ep ON sa.participant_id = ep.id 
                    WHERE ep.exam_id = ?";
        $pdo->prepare($sql_ans)->execute([$exam_id]);

        // C. Hapus data peserta ujian
        $pdo->prepare("DELETE FROM cbt_exam_participants WHERE exam_id = ?")->execute([$exam_id]);

        // D. Hapus relasi soal ujian
        $pdo->prepare("DELETE FROM cbt_exam_questions WHERE exam_id = ?")->execute([$exam_id]);

        // E. Hapus jadwal ujian utama
        $pdo->prepare("DELETE FROM cbt_exams WHERE id = ? AND teacher_id = ?")->execute([$exam_id, $teacher_id]);

        $pdo->commit();
        log_activity("Guru hapus jadwal ujian ID $exam_id", $teacher_id, 'guru', null, 'ujian');
        header("Location: ../test.php?id=$id_bank&msg=deleted");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: ../test.php?id=$id_bank&msg=error");
        exit;
    }
} else {
    header("Location: ../test.php?id=$id_bank");
    exit;
}
