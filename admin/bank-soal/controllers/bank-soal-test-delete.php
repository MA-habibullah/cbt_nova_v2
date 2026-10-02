<?php
/**
 * CBT NATIVE - Controller Hapus Jadwal Ujian
 * Lokasi: admin/bank-soal/controllers/bank-soal-test-delete.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Perbaikan Path Database (Mundur 3 tingkat)
require_once '../../../config/database.php';

// 2. Proteksi Admin (Mencegah ditendang ke index tanpa alasan)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../../index.php"); 
    exit;
}

// 3. Tangkap Parameter
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($exam_id > 0) {
    try {
        $pdo->beginTransaction();

        // A. Hapus log pelanggaran (Cheat Logs) jika ada
        $pdo->prepare("DELETE FROM cbt_cheat_logs WHERE exam_id = ?")->execute([$exam_id]);

        // B. Hapus semua jawaban siswa (Join via cbt_exam_participants)
        $sql_ans = "DELETE sa FROM cbt_student_answers sa 
                    JOIN cbt_exam_participants ep ON sa.participant_id = ep.id 
                    WHERE ep.exam_id = ?";
        $pdo->prepare($sql_ans)->execute([$exam_id]);

        // C. Hapus data peserta ujian
        $pdo->prepare("DELETE FROM cbt_exam_participants WHERE exam_id = ?")->execute([$exam_id]);

        // D. Hapus relasi soal ujian
        $pdo->prepare("DELETE FROM cbt_exam_questions WHERE exam_id = ?")->execute([$exam_id]);

        // E. Hapus jadwal ujian utama
        $pdo->prepare("DELETE FROM cbt_exams WHERE id = ?")->execute([$exam_id]);

        $pdo->commit();
        
        /**
         * PERBAIKAN REDIRECT:
         * Gunakan ../test.php karena file test.php berada satu folder di atas folder controllers
         */
        header("Location: ../test.php?id=$id_bank&msg=deleted");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Jangan di-die, kembalikan ke halaman dengan pesan error
        header("Location: ../test.php?id=$id_bank&msg=error");
        exit;
    }
} else {
    // Jika ID tidak valid, balikkan ke test.php
    header("Location: ../test.php?id=$id_bank");
    exit;
}