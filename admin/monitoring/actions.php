<?php
/**
 * PENTING: File ini murni API. 
 * Pastikan TIDAK ADA spasi, enter, atau karakter apapun sebelum tag <?php
 */

// 1. Mulai buffering untuk membungkus semua output yang bocor
ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';

// 2. Buang semua output yang tertangkap (seperti Sidebar/Header yang tidak sengaja ter-include)
if (ob_get_length()) {
    ob_end_clean(); 
}

// 3. Set header JSON murni
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

csrf_verify();

// Ambil input dari POST
$action = $_POST['action'] ?? '';
$ids    = $_POST['ids'] ?? [];
$val    = $_POST['value'] ?? 0;

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada data yang dipilih']);
    exit;
}

// Pastikan IDS adalah array bilangan bulat
if (!is_array($ids)) {
    $ids = [$ids];
}
$ids = array_map('intval', $ids);
$ids = array_filter($ids, function($v) { return $v > 0; });
if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Data ID peserta tidak valid']);
    exit;
}

// Buat placeholders (?,?,?) sesuai jumlah ID
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    if ($action === 'add_time') {
        /**
         * TAMBAH WAKTU
         * Reset waktu_mulai = NOW() agar timer siswa mulai dari 0 sesuai tambahan_waktu.
         * tambahan_waktu diset langsung (replace), bukan akumulasi.
         */
        $menit = (int)$val;
        if ($menit <= 0) $menit = 15;

        $sql = "UPDATE cbt_exam_participants SET tambahan_waktu = ?, waktu_mulai = NOW(), status = 'working' WHERE id IN ($placeholders)";
        $params = array_merge([$menit], $ids);
        query($sql, $params);
    } 
    
    elseif ($action === 'reset_login') {
        /**
         * RESET LOGIN (Device Lock)
         * Logika Ganda: Menangani ID dari tabel device_locks maupun exam_participants
         */

        // Kumpulkan student_id dari KEDUA sumber SEBELUM device lock dihapus
        // Sumber 1: ID langsung dari tabel device_locks (dari halaman device-lock.php)
        $stmtSt1 = $pdo->prepare("SELECT student_id FROM cbt_device_locks WHERE id IN ($placeholders)");
        $stmtSt1->execute($ids);
        $studentIds1 = $stmtSt1->fetchAll(PDO::FETCH_COLUMN);

        // Sumber 2: student_id via exam_participants (dari halaman monitoring)
        $stmtSt2 = $pdo->prepare("SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders)");
        $stmtSt2->execute($ids);
        $studentIds2 = $stmtSt2->fetchAll(PDO::FETCH_COLUMN);

        $allStudentIds = array_values(array_unique(array_merge($studentIds1, $studentIds2)));

        // Ambil session_id dari device locks yang ditemukan
        $stmtS1 = $pdo->prepare("SELECT session_id FROM cbt_device_locks WHERE id IN ($placeholders) AND session_id IS NOT NULL");
        $stmtS1->execute($ids);
        $sessions1 = $stmtS1->fetchAll(PDO::FETCH_COLUMN);

        $stmtS2 = $pdo->prepare("SELECT dl.session_id FROM cbt_device_locks dl
                                  INNER JOIN cbt_exam_participants ep ON dl.student_id = ep.student_id
                                  WHERE ep.id IN ($placeholders) AND dl.session_id IS NOT NULL");
        $stmtS2->execute($ids);
        $sessions2 = $stmtS2->fetchAll(PDO::FETCH_COLUMN);

        $allSessions = array_unique(array_merge($sessions1, $sessions2));

        // 1. Hapus langsung dari tabel device_locks berdasarkan ID (dari device-lock.php)
        $sqlDeleteDirect = "DELETE FROM cbt_device_locks WHERE id IN ($placeholders)";
        query($sqlDeleteDirect, $ids);

        // 2. Hapus berdasarkan student_id via exam_participants (dari monitoring.php)
        $sqlDeleteLink = "DELETE FROM cbt_device_locks WHERE student_id IN (SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders))";
        query($sqlDeleteLink, $ids);

        // 3. Update status peserta berdasarkan student_id yang sudah dikumpulkan
        if (!empty($allStudentIds)) {
            $stPlaceholders = implode(',', array_fill(0, count($allStudentIds), '?'));
            query(
                "UPDATE cbt_exam_participants SET status = 'ready' WHERE student_id IN ($stPlaceholders) AND status != 'finished'",
                $allStudentIds
            );
        }

        // 4. Hancurkan file sesi aktif siswa agar langsung ter-logout
        foreach ($allSessions as $sid) {
            destroySessionById($sid);
        }
        
    }
    elseif ($action === 'unlock_exam') {
        /**
         * Membuka blokir tanpa mereset waktu
         */
        $sql = "UPDATE cbt_exam_participants SET status = 'working' WHERE id IN ($placeholders)";
        query($sql, $ids);
    }

    elseif ($action === 'lock_exam') {
        /**
         * KUNCI UJIAN
         */
        $sql = "UPDATE cbt_exam_participants SET status = 'blocked' WHERE id IN ($placeholders)";
        query($sql, $ids);
    }

    elseif ($action === 'finish_exam') {
        /**
         * SELESAIKAN UJIAN SECARA PAKSA & HITUNG NILAI OTOMATIS
         */
        foreach ($ids as $pid) {
            hitung_dan_simpan_nilai_peserta($pdo, (int)$pid);
        }
        
        // Hapus juga lock perangkatnya agar siswa bisa ikut ujian lain di masa depan tanpa hambatan
        $sqlDeleteLock = "DELETE FROM cbt_device_locks WHERE student_id IN (SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders))";
        query($sqlDeleteLock, $ids);
    }

    $action_label = [
        'add_time'    => 'Tambah waktu ' . $val . ' menit ke ' . count($ids) . ' peserta',
        'reset_login' => 'Reset login ' . count($ids) . ' peserta',
        'unlock_exam' => 'Buka blokir ujian ' . count($ids) . ' peserta',
        'lock_exam'   => 'Kunci ujian ' . count($ids) . ' peserta',
        'finish_exam' => 'Paksa selesai ujian ' . count($ids) . ' peserta',
    ];
    log_activity('[Monitoring Admin] ' . ($action_label[$action] ?? $action), null, null, null, 'monitoring');

    // Berikan respon sukses
    echo json_encode(['status' => 'success', 'message' => 'Tindakan berhasil diterapkan ke ' . count($ids) . ' data']);
    exit;

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}