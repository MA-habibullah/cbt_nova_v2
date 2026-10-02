<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/database.php';
require_once '../../../includes/helpers.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru')) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Sesi guru tidak valid.']);
    exit;
}

$teacher_id     = (int)($_SESSION['teacher_id'] ?? 0);
$exam_id        = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$bank_soal_id   = isset($_POST['bank_soal_id']) ? (int)$_POST['bank_soal_id'] : (isset($_POST['id_bank']) ? (int)$_POST['id_bank'] : 0);
$participant_id = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;

if ($exam_id <= 0 && $bank_soal_id <= 0 && $participant_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter exam_id, bank_soal_id, atau participant_id wajib diisi.']);
    exit;
}

try {
    if ($participant_id > 0) {
        // Validasi kepemilikan exam
        $stmtChk = $pdo->prepare("SELECT e.teacher_id FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id WHERE p.id = ?");
        $stmtChk->execute([$participant_id]);
        $owner_id = (int)$stmtChk->fetchColumn();
        if ($owner_id !== $teacher_id) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk ujian ini.']);
            exit;
        }

        $res = hitung_dan_simpan_nilai_peserta($pdo, $participant_id);
        if (!empty($res['success'])) {
            log_activity("[Hasil Guru] Hitung ulang nilai peserta ID $participant_id", null, null, $teacher_id, 'hasil');
            echo json_encode([
                'status'  => 'success',
                'message' => 'Nilai peserta berhasil dihitung ulang!',
                'data'    => $res
            ]);
            exit;
        } else {
            echo json_encode([
                'status'  => 'error',
                'message' => $res['error'] ?? 'Gagal menghitung ulang nilai peserta.'
            ]);
            exit;
        }
    } else {
        // Validasi kepemilikan exam / bank soal
        if ($exam_id > 0) {
            $stmtChk = $pdo->prepare("SELECT teacher_id FROM cbt_exams WHERE id = ?");
            $stmtChk->execute([$exam_id]);
            $owner_id = (int)$stmtChk->fetchColumn();
            if ($owner_id !== $teacher_id) {
                echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk ujian ini.']);
                exit;
            }
        } elseif ($bank_soal_id > 0) {
            $stmtChk = $pdo->prepare("SELECT teacher_id FROM cbt_bank_soal WHERE id = ?");
            $stmtChk->execute([$bank_soal_id]);
            $owner_id = (int)$stmtChk->fetchColumn();
            if ($owner_id !== $teacher_id) {
                echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk bank soal ini.']);
                exit;
            }
        }

        $stats = hitung_ulang_nilai_ujian($pdo, $exam_id, $bank_soal_id);
        $target_label = $exam_id > 0 ? "Ujian ID $exam_id" : "Bank Soal ID $bank_soal_id";
        log_activity("[Hasil Guru] Batch hitung ulang nilai $target_label ({$stats['berhasil']} berhasil)", null, null, $teacher_id, 'hasil');

        echo json_encode([
            'status'  => 'success',
            'message' => "Berhasil menghitung ulang nilai {$stats['berhasil']} dari {$stats['total_peserta']} peserta (Rata-rata: {$stats['rata_rata']}).",
            'stats'   => $stats
        ]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
    exit;
}
