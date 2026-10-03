<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir']); exit;
}
csrf_verify();
$teacher_id = (int)$_SESSION['teacher_id'];

$action = $_POST['action'] ?? '';
$ids    = $_POST['ids'] ?? [];
$val    = $_POST['value'] ?? 0;

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada data yang dipilih']); exit;
}
if (!is_array($ids)) $ids = [$ids];
$ids = array_map('intval', $ids);
$ids = array_filter($ids, function($v) { return $v > 0; });

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Data ID tidak valid']); exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    if ($action === 'reset_login') {
        /**
         * RESET LOGIN (Device Lock) untuk Guru
         * Mendukung ID dari tabel cbt_device_locks maupun cbt_exam_participants
         */
        // Sumber 1: ID dari tabel cbt_device_locks
        $stmtSt1 = $pdo->prepare("SELECT student_id FROM cbt_device_locks WHERE id IN ($placeholders)");
        $stmtSt1->execute($ids);
        $studentIds1 = $stmtSt1->fetchAll(PDO::FETCH_COLUMN);

        // Sumber 2: ID dari tabel cbt_exam_participants
        $stmtSt2 = $pdo->prepare("SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders)");
        $stmtSt2->execute($ids);
        $studentIds2 = $stmtSt2->fetchAll(PDO::FETCH_COLUMN);

        $allStudentIds = array_values(array_unique(array_filter(array_merge($studentIds1, $studentIds2))));

        if (empty($allStudentIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Data siswa tidak ditemukan']); exit;
        }

        // Keamanan: Pastikan siswa yang di-reset berada di lingkup kelas / ujian guru ini
        $stPH = implode(',', array_fill(0, count($allStudentIds), '?'));
        $verifySql = "
            SELECT COUNT(DISTINCT s.id) 
            FROM cbt_students s
            WHERE s.id IN ($stPH) AND (
                s.id IN (
                    SELECT DISTINCT ep.student_id FROM cbt_exam_participants ep 
                    JOIN cbt_exams e ON ep.exam_id = e.id WHERE e.teacher_id = ?
                )
                OR s.class_id IN (
                    SELECT DISTINCT class_id FROM cbt_exams WHERE teacher_id = ?
                )
            )
        ";
        $verifyStmt = $pdo->prepare($verifySql);
        $verifyStmt->execute(array_merge($allStudentIds, [$teacher_id, $teacher_id]));
        $allowedCount = (int)$verifyStmt->fetchColumn();

        if ($allowedCount === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: siswa bukan pada lingkup ujian/kelas Anda']); exit;
        }

        // Ambil session id untuk dihapus
        $stmtS1 = $pdo->prepare("SELECT session_id FROM cbt_device_locks WHERE id IN ($placeholders) AND session_id IS NOT NULL");
        $stmtS1->execute($ids);
        $sessions1 = $stmtS1->fetchAll(PDO::FETCH_COLUMN);

        $stmtS2 = $pdo->prepare("SELECT session_id FROM cbt_device_locks WHERE student_id IN ($stPH) AND session_id IS NOT NULL");
        $stmtS2->execute($allStudentIds);
        $sessions2 = $stmtS2->fetchAll(PDO::FETCH_COLUMN);

        $allSessions = array_unique(array_merge($sessions1, $sessions2));

        // Hapus device lock
        query("DELETE FROM cbt_device_locks WHERE id IN ($placeholders)", $ids);
        query("DELETE FROM cbt_device_locks WHERE student_id IN ($stPH)", $allStudentIds);

        // Update status peserta ke 'ready' jika sedang 'working' / 'blocked'
        query("UPDATE cbt_exam_participants SET status = 'ready' WHERE student_id IN ($stPH) AND status != 'finished'", $allStudentIds);

        // Hancurkan session
        foreach ($allSessions as $sid) {
            if (!empty($sid)) destroySessionById($sid);
        }

        log_activity('[Monitoring Guru] Reset login ' . count($allStudentIds) . ' siswa', null, null, null, 'monitoring');
        echo json_encode(['status' => 'success', 'message' => 'Kunci perangkat berhasil dibuka']);
        exit;
    }

    // Untuk tindakan exam_participants (add_time, unlock_exam, lock_exam, finish_exam):
    $verifyStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id
         WHERE p.id IN ($placeholders) AND e.teacher_id = ?"
    );
    $verifyStmt->execute(array_merge($ids, [$teacher_id]));
    if ((int)$verifyStmt->fetchColumn() !== count($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: peserta bukan milik ujian Anda']); exit;
    }

    if ($action === 'add_time') {
        $menit = max(1, (int)$val);
        query("UPDATE cbt_exam_participants SET tambahan_waktu = ?, waktu_mulai = NOW(), status = 'working' WHERE id IN ($placeholders)",
              array_merge([$menit], $ids));

    } elseif ($action === 'unlock_exam') {
        query("UPDATE cbt_exam_participants SET status = 'working' WHERE id IN ($placeholders)", $ids);

    } elseif ($action === 'lock_exam') {
        query("UPDATE cbt_exam_participants SET status = 'blocked' WHERE id IN ($placeholders)", $ids);

    } elseif ($action === 'finish_exam') {
        foreach ($ids as $pid) {
            hitung_dan_simpan_nilai_peserta($pdo, (int)$pid, null, true);
        }
        query("DELETE FROM cbt_device_locks WHERE student_id IN (SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders))", $ids);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal']); exit;
    }

    $action_label = [
        'add_time'    => 'Tambah waktu ' . $val . ' menit ke ' . count($ids) . ' peserta',
        'unlock_exam' => 'Buka blokir ujian ' . count($ids) . ' peserta',
        'lock_exam'   => 'Kunci ujian ' . count($ids) . ' peserta',
        'finish_exam' => 'Paksa selesai ujian ' . count($ids) . ' peserta',
    ];
    log_activity('[Monitoring Guru] ' . ($action_label[$action] ?? $action), null, null, null, 'monitoring');

    echo json_encode(['status' => 'success', 'message' => 'Tindakan berhasil diterapkan ke ' . count($ids) . ' data']);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
