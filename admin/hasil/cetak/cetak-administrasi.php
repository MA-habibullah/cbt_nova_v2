<?php
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); exit;
}

// Ambil parameter filter
$exam_id  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$filter_sesi = isset($_GET['sesi']) ? $_GET['sesi'] : 'all';

if ($exam_id == 0 || $class_id == 0) {
    die("Data tidak lengkap.");
}

// 1. Ambil Informasi Ujian
$stmt_exam = $pdo->prepare("SELECT e.*, s.nama_mapel FROM cbt_exams e JOIN cbt_subjects s ON e.subject_id = s.id WHERE e.id = ?");
$stmt_exam->execute([$exam_id]);
$exam = $stmt_exam->fetch();

// 2. Ambil Informasi Kelas
$stmt_class = $pdo->prepare("SELECT nama_kelas, jenjang FROM cbt_classes WHERE id = ?");
$stmt_class->execute([$class_id]);
$nama_kelas = $stmt_class->fetch();

// Ambil data setting sekolah
$stmt_set = $pdo->query("SELECT * FROM cbt_settings WHERE id = 1");
$sch = $stmt_set->fetch();

// 3. Ambil Daftar Siswa (Username adalah NISN)
$query = "SELECT s.nisn as username, s.nama_lengkap, k.nama_kelas, s.sesi 
              FROM cbt_exam_participants p
              JOIN cbt_students s ON p.student_id = s.id
              JOIN cbt_classes k ON COALESCE(p.class_id, s.class_id) = k.id
              WHERE p.exam_id = ? AND COALESCE(p.class_id, s.class_id) = ?";
    
    $params = [$exam_id, $class_id];

    if ($filter_sesi !== 'all') {
        $query .= " AND s.sesi = ?";
        $params[] = (int)$filter_sesi;
    }

    $query .= " ORDER BY s.sesi ASC, s.nama_lengkap ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $list_students = $stmt->fetchAll();

// --- MENGAMBIL TAHUN PELAJARAN ---

$ta_aktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();
$thn_ajaran = ($ta_aktif) ? $ta_aktif['tahun'] : "2025/2026";

// 4. Pecah data siswa menjadi maksimal 20 baris per halaman
$chunks = array_chunk($list_students, 20);

// Load Template Tampilan
include 'view-cetak-administrasi.php';

