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
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    // Keamanan: pastikan semua participant_id milik ujian guru ini
    $verifyStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id
         WHERE p.id IN ($placeholders) AND e.teacher_id = ?"
    );
    $verifyStmt->execute(array_merge($ids, [$teacher_id]));
    if ((int)$verifyStmt->fetchColumn() !== count($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: peserta bukan milik Anda']); exit;
    }

    if ($action === 'add_time') {
        $menit = max(1, (int)$val);
        query("UPDATE cbt_exam_participants SET tambahan_waktu = ?, waktu_mulai = NOW(), status = 'working' WHERE id IN ($placeholders)",
              array_merge([$menit], $ids));

    } elseif ($action === 'reset_login') {
        $stmtSt = $pdo->prepare("SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders)");
        $stmtSt->execute($ids);
        $studentIds = $stmtSt->fetchAll(PDO::FETCH_COLUMN);

        $stmtS = $pdo->prepare(
            "SELECT dl.session_id FROM cbt_device_locks dl
             INNER JOIN cbt_exam_participants ep ON dl.student_id = ep.student_id
             WHERE ep.id IN ($placeholders) AND dl.session_id IS NOT NULL"
        );
        $stmtS->execute($ids);
        $allSessions = $stmtS->fetchAll(PDO::FETCH_COLUMN);

        query("DELETE FROM cbt_device_locks WHERE student_id IN (SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders))", $ids);

        if (!empty($studentIds)) {
            $stPH = implode(',', array_fill(0, count($studentIds), '?'));
            query("UPDATE cbt_exam_participants SET status = 'ready' WHERE student_id IN ($stPH) AND status != 'finished'", $studentIds);
        }
        foreach ($allSessions as $sid) { destroySessionById($sid); }

    } elseif ($action === 'unlock_exam') {
        query("UPDATE cbt_exam_participants SET status = 'working' WHERE id IN ($placeholders)", $ids);

    } elseif ($action === 'lock_exam') {
        query("UPDATE cbt_exam_participants SET status = 'blocked' WHERE id IN ($placeholders)", $ids);

    } elseif ($action === 'finish_exam') {
        query("UPDATE cbt_exam_participants SET status = 'finished', waktu_selesai = NOW() WHERE id IN ($placeholders) AND status != 'finished'", $ids);
        query("DELETE FROM cbt_device_locks WHERE student_id IN (SELECT student_id FROM cbt_exam_participants WHERE id IN ($placeholders))", $ids);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal']); exit;
    }

    $action_label = [
        'add_time'    => 'Tambah waktu ' . $val . ' menit ke ' . count($ids) . ' peserta',
        'reset_login' => 'Reset login ' . count($ids) . ' peserta',
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
