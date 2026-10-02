<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once '../includes/helpers.php';
date_default_timezone_set('Asia/Jakarta');

// Proteksi: Hanya siswa yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit;
}

$exam_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = (int)$_SESSION['student_id'];
session_write_close();
$waktu_selesai = date('Y-m-d H:i:s');

try {
    // 1. Ambil Data Partisipasi + soal_ids (untuk distribusi per-siswa)
    $stmtPart = $pdo->prepare("
        SELECT p.id, p.soal_ids, e.bank_soal_id
        FROM cbt_exam_participants p
        JOIN cbt_exams e ON p.exam_id = e.id
        WHERE p.exam_id = ? AND p.student_id = ? AND p.status NOT IN ('finished', 'blocked')
    ");
    $stmtPart->execute([$exam_id, $student_id]);
    $participant = $stmtPart->fetch();

    if (!$participant) {
        header("Location: index.php");
        exit;
    }

    $participant_id = $participant['id'];

    // Validasi server-side: tombol Selesai hanya boleh dipakai 5 menit terakhir.
    // reason=timeout (auto-submit saat waktu benar-benar habis) selalu lolos.
    $reason = $_GET['reason'] ?? '';
    if ($reason !== 'timeout') {
        $waktu = hitung_sisa_waktu($pdo, $participant_id);
        if ($waktu['sisa_detik'] > 300) {
            $too_early_msg = 'Ujian belum bisa diselesaikan. Tombol Selesai aktif 5 menit sebelum waktu habis.';
            if (isset($_GET['_spa'])) {
                echo json_encode(['status' => 'too_early', 'message' => $too_early_msg]);
                exit;
            }
            header("Location: index.php?view=ujian&id=" . $exam_id . "&msg=too_early");
            exit;
        }
    }

    // 2. Hitung dan simpan nilai menggunakan Centralized Scoring Engine (finalize = true)
    $hasil_skor = hitung_dan_simpan_nilai_peserta($pdo, $participant_id, $waktu_selesai, true);

    if (empty($hasil_skor['success'])) {
        throw new Exception($hasil_skor['error'] ?? 'Gagal menghitung nilai ujian');
    }

    $nilai_akhir    = $hasil_skor['nilai_akhir'];
    $nilai_objektif = $hasil_skor['nilai_objektif'];
    $nilai_esai     = $hasil_skor['nilai_esai'];

    log_activity("Selesai ujian ID $exam_id, nilai akhir: $nilai_akhir (obj: $nilai_objektif, esai: $nilai_esai)", (int)$student_id, null, null, 'ujian');
    if (isset($_GET['_spa'])) { echo json_encode(['status' => 'ok']); exit; }
    header("Location: index.php?msg=ujian_selesai");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Gagal memproses nilai: " . $e->getMessage());
}
