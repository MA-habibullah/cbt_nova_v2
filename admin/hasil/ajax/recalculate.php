<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/database.php';
require_once '../../../includes/helpers.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Sesi admin tidak valid.']);
    exit;
}

$exam_id        = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$bank_soal_id   = isset($_POST['bank_soal_id']) ? (int)$_POST['bank_soal_id'] : (isset($_POST['id_bank']) ? (int)$_POST['id_bank'] : 0);
$participant_id = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;

if ($exam_id <= 0 && $bank_soal_id <= 0 && $participant_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter exam_id, bank_soal_id, atau participant_id wajib diisi.']);
    exit;
}

try {
    if ($participant_id > 0) {
        $res = hitung_dan_simpan_nilai_peserta($pdo, $participant_id);
        if (!empty($res['success'])) {
            log_activity("[Hasil Admin] Hitung ulang nilai peserta ID $participant_id", null, (int)($_SESSION['admin_id'] ?? 1), null, 'hasil');
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
        $stats = hitung_ulang_nilai_ujian($pdo, $exam_id, $bank_soal_id);
        $target_label = $exam_id > 0 ? "Ujian ID $exam_id" : "Bank Soal ID $bank_soal_id";
        log_activity("[Hasil Admin] Batch hitung ulang nilai $target_label ({$stats['berhasil']} berhasil)", null, (int)($_SESSION['admin_id'] ?? 1), null, 'hasil');

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
