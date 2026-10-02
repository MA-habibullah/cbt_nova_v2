<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); 
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); 
    exit;
}
csrf_verify();

$teacher_id = (int)$_SESSION['teacher_id'];
$exam_id    = (int)($_POST['exam_id'] ?? 0);
$mode       = $_POST['mode'] ?? '';

$exam = $pdo->prepare("SELECT id, bank_soal_id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
$exam->execute([$exam_id, $teacher_id]);
$exam = $exam->fetch();
if (!$exam) {
    echo json_encode(['success' => false, 'message' => 'Ujian tidak ditemukan atau bukan milik Anda']); 
    exit;
}

$valid_tipes  = ['pg', 'pg_kompleks', 'isian', 'benar_salah', 'menjodohkan', 'essay'];
$valid_levels = ['mudah', 'sedang', 'sulit'];

$distribusi_tipe      = null;
$distribusi_kesulitan = null;
$jumlah_soal_limit    = null;

if ($mode === 'total') {
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    if ($jumlah <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jumlah soal harus lebih dari 0']); 
        exit;
    }
    $jumlah_soal_limit = $jumlah;

} elseif ($mode === 'tipe') {
    $distribusi = $_POST['distribusi'] ?? [];
    $tipe_data  = [];
    foreach ($distribusi as $tipe => $jumlah) {
        $jumlah = (int)$jumlah;
        if ($jumlah <= 0 || !in_array($tipe, $valid_tipes, true)) continue;
        $tipe_data[$tipe] = $jumlah;
    }
    if (empty($tipe_data)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada distribusi jenis soal yang valid']); 
        exit;
    }
    $distribusi_tipe   = json_encode($tipe_data);
    $jumlah_soal_limit = array_sum($tipe_data);

} elseif ($mode === 'kesulitan') {
    $distribusi = $_POST['distribusi'] ?? [];
    $level_data = [];
    foreach ($distribusi as $level => $jumlah) {
        $jumlah = (int)$jumlah;
        if ($jumlah <= 0 || !in_array($level, $valid_levels, true)) continue;
        $level_data[$level] = $jumlah;
    }
    if (empty($level_data)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada distribusi tingkat kesulitan yang valid']); 
        exit;
    }
    $distribusi_kesulitan = json_encode($level_data);
    $jumlah_soal_limit    = array_sum($level_data);

} else {
    echo json_encode(['success' => false, 'message' => 'Mode tidak valid']); 
    exit;
}

$pdo->prepare(
    "UPDATE cbt_exams SET jumlah_soal_limit = ?, distribusi_tipe = ?, distribusi_kesulitan = ? WHERE id = ? AND teacher_id = ?"
)->execute([$jumlah_soal_limit, $distribusi_tipe, $distribusi_kesulitan, $exam_id, $teacher_id]);

log_activity("Guru set distribusi soal ujian ID $exam_id: mode=$mode, limit=$jumlah_soal_limit", $teacher_id, 'guru', null, 'ujian');

echo json_encode([
    'success' => true,
    'jumlah'  => $jumlah_soal_limit,
    'message' => 'Distribusi disimpan. Tiap siswa akan mendapat ' . $jumlah_soal_limit . ' soal secara acak saat memulai ujian.'
]);
