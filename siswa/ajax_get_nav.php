<?php
require_once '../config/database.php';
require_once '../includes/helpers.php';

// Proteksi: Hanya siswa yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo json_encode(['html' => 'Unauthorized', 'total' => 0]);
    exit;
}

$exam_id    = (int)$_GET['exam_id'];
$current_no = (int)$_GET['current']; // Nomor yang sedang dibuka siswa
$student_id = $_SESSION['student_id'];

// Cache participant_id + question order: deterministic per student+exam, avoids 3 queries per nav call
$cache_key = "nav_{$exam_id}_{$student_id}";
if (isset($_SESSION[$cache_key])) {
    $participant_id   = $_SESSION[$cache_key]['participant_id'];
    $all_question_ids = $_SESSION[$cache_key]['ids'];
    session_write_close();
} else {
    // 1. Ambil ID Partisipasi + soal_ids (untuk distribusi per-siswa)
    $stmtPart = $pdo->prepare("SELECT id, soal_ids FROM cbt_exam_participants WHERE exam_id = ? AND student_id = ?");
    $stmtPart->execute([$exam_id, $student_id]);
    $participant = $stmtPart->fetch();

    if (!$participant) {
        session_write_close();
        echo json_encode(['html' => '', 'total' => 0]);
        exit;
    }

    $participant_id = $participant['id'];

    // 2. Ambil Pengaturan Acak Soal dari Ujian
    $stmtExam = $pdo->prepare("SELECT acak_soal FROM cbt_exams WHERE id = ?");
    $stmtExam->execute([$exam_id]);
    $exam      = $stmtExam->fetch();
    $acak_soal = (int)($exam['acak_soal'] ?? 0);

    // 3. Ambil Semua ID Soal dalam Urutan Dasar
    // Jika soal_ids terisi (distribusi per-siswa aktif), gunakan itu; jika tidak, ambil dari cbt_exam_questions
    $soal_ids_json = $participant['soal_ids'] ?? null;
    if ($soal_ids_json) {
        $all_question_ids = json_decode($soal_ids_json, true) ?: [];
    } else {
        $stmtAllQ = $pdo->prepare("SELECT question_id FROM cbt_exam_questions WHERE exam_id = ? ORDER BY id ASC");
        $stmtAllQ->execute([$exam_id]);
        $all_question_ids = $stmtAllQ->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4. Acak Urutan jika Diaktifkan (seed sama dengan ajax_get_soal.php)
    if ($acak_soal && count($all_question_ids) > 1) {
        $seed_soal = crc32($student_id . '_exam_' . $exam_id);
        seeded_shuffle($all_question_ids, $seed_soal);
    }

    $_SESSION[$cache_key] = ['participant_id' => $participant_id, 'ids' => $all_question_ids];
    session_write_close();
}

if (empty($all_question_ids)) {
    echo json_encode(['html' => '', 'total' => 0]);
    exit;
}

// 5. Ambil Status Jawaban (selalu fresh — berubah tiap simpan jawaban)
$stmtNav = $pdo->prepare("
    SELECT q.id, sa.jawaban_simpan, sa.is_ragu
    FROM cbt_questions q
    LEFT JOIN cbt_student_answers sa ON q.id = sa.question_id AND sa.participant_id = ?
    WHERE q.id IN (" . implode(',', array_fill(0, count($all_question_ids), '?')) . ")
");
$stmtNav->execute(array_merge([$participant_id], $all_question_ids));
$answer_map = [];
foreach ($stmtNav->fetchAll() as $row) {
    $answer_map[$row['id']] = $row;
}

$html = "";
$total = count($all_question_ids);

// 6. Render Navigasi Sesuai Urutan yang Sudah Diacak
foreach ($all_question_ids as $index => $q_id) {
    $no = $index + 1;
    $item = $answer_map[$q_id] ?? ['jawaban_simpan' => null, 'is_ragu' => 0];

    $classes = [];
    if ($no == $current_no) {
        $classes[] = "active";
    }
    if ($item['is_ragu'] == 1) {
        $classes[] = "ragu";
    } elseif (!empty($item['jawaban_simpan'])) {
        $decoded = json_decode($item['jawaban_simpan'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (count($decoded) > 0) $classes[] = "answered";
        } else {
            $classes[] = "answered";
        }
    }

    $classAttr = implode(' ', $classes);
    $html .= "<div class='no-box $classAttr' data-no='$no' onclick='loadSoal($no)'>$no</div>";
}

// Kembalikan JSON berisi HTML navigasi dan total soal
echo json_encode([
    'html' => $html,
    'total' => $total
]);