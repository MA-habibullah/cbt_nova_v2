<?php
require_once dirname(__DIR__, 3) . '/config/database.php';
if (!isset($_SESSION['teacher_id'])) { http_response_code(403); echo json_encode(['status'=>'error']); exit; }
header('Content-Type: application/json');

$exam_id    = (int)($_POST['exam_id'] ?? 0);
$teacher_id = (int) $_SESSION['teacher_id'];

if (!$exam_id) { echo json_encode(['status'=>'error','message'=>'exam_id diperlukan']); exit; }

// Verify exam belongs to this teacher
$exam = query("SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?", [$exam_id, $teacher_id])->fetch();
if (!$exam) { echo json_encode(['status'=>'error','message'=>'Ujian tidak ditemukan']); exit; }

$classes = query(
    "SELECT DISTINCT k.id, k.nama_kelas, k.jenjang
     FROM cbt_exam_participants p
     JOIN cbt_students s ON p.student_id = s.id
     JOIN cbt_classes k ON s.class_id = k.id
     WHERE p.exam_id = ?
     ORDER BY k.jenjang, k.nama_kelas",
    [$exam_id]
)->fetchAll();

echo json_encode(['status' => 'success', 'data' => $classes]);
