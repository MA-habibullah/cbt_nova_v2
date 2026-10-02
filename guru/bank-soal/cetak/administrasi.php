<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

$exam_id     = isset($_GET['exam_id'])  ? (int)$_GET['exam_id']  : 0;
$class_id    = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$filter_sesi = isset($_GET['sesi'])     ? $_GET['sesi']          : 'all';

if (!$exam_id || !$class_id) { die("Data tidak lengkap."); }

// Verifikasi exam milik guru ini
$chk = $pdo->prepare("SELECT id FROM cbt_exams WHERE id = ? AND teacher_id = ?");
$chk->execute([$exam_id, $teacher_id]);
if (!$chk->fetch()) { die("Akses ditolak."); }

// Informasi ujian
$stmt_exam = $pdo->prepare("SELECT e.*, s.nama_mapel FROM cbt_exams e JOIN cbt_subjects s ON e.subject_id = s.id WHERE e.id = ?");
$stmt_exam->execute([$exam_id]);
$exam = $stmt_exam->fetch();

// Informasi kelas
$stmt_class = $pdo->prepare("SELECT nama_kelas FROM cbt_classes WHERE id = ?");
$stmt_class->execute([$class_id]);
$nama_kelas = $stmt_class->fetchColumn();

// Setting sekolah
$sch = $pdo->query("SELECT * FROM cbt_settings WHERE id = 1")->fetch();

// Daftar siswa
$query = "SELECT nisn as username, nama_lengkap FROM cbt_students WHERE class_id = ?";
$params = [$class_id];
if ($filter_sesi !== 'all') { $query .= " AND sesi = ?"; $params[] = (int)$filter_sesi; }
$query .= " ORDER BY nama_lengkap ASC";
$stmt_students = $pdo->prepare($query);
$stmt_students->execute($params);
$all_students = $stmt_students->fetchAll();

// Tahun pelajaran otomatis
$tgl_ujian   = strtotime($exam['mulai_pada']);
$bulan_ujian = date('n', $tgl_ujian);
$tahun_ujian = date('Y', $tgl_ujian);
$ta_aktif    = $bulan_ujian > 6
    ? $tahun_ujian . "/" . ($tahun_ujian + 1)
    : ($tahun_ujian - 1) . "/" . $tahun_ujian;

// Pecah 20 baris per halaman
$chunks = array_chunk($all_students, 20);

// Gunakan view template dari admin (shared)
include dirname(__DIR__, 3) . '/admin/hasil/cetak/view-cetak-administrasi.php';
