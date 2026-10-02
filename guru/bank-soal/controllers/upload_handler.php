<?php
ob_start();
require_once '../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
csrf_verify();

$imageFolder = dirname(__DIR__, 3) . '/assets/uploads/soal/';

if (!is_dir($imageFolder)) {
    mkdir($imageFolder, 0755, true);
}

reset($_FILES);
$temp = current($_FILES);

if (!is_uploaded_file($temp['tmp_name'] ?? '')) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Data gambar tidak ditemukan.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $temp['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedMimes)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'MIME type file tidak valid.']);
    exit;
}

$fileExtension     = strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($fileExtension, $allowedExtensions)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Format file tidak diizinkan.']);
    exit;
}

if ($temp['size'] > 5 * 1024 * 1024) { // 5 MB
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Ukuran gambar maksimal 5 MB.']);
    exit;
}

// Validasi MIME type — pastikan file benar-benar gambar, bukan file berbahaya berganti ekstensi
$imageInfo = @getimagesize($temp['tmp_name']);
if ($imageInfo === false) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'File bukan gambar yang valid.']);
    exit;
}

$mime = $imageInfo['mime'];
$src  = match($mime) {
    'image/jpeg' => imagecreatefromjpeg($temp['tmp_name']),
    'image/png'  => imagecreatefrompng($temp['tmp_name']),
    'image/webp' => imagecreatefromwebp($temp['tmp_name']),
    'image/gif'  => imagecreatefromgif($temp['tmp_name']),
    default      => false,
};

ob_end_clean(); // Buang semua warning yang tertangkap, lalu kirim JSON bersih

if (!$src) {
    http_response_code(400);
    echo json_encode(['error' => 'Format gambar tidak dapat diproses.']);
    exit;
}

// Resize ke maks 1200px (pertahankan aspek rasio)
$orig_w = imagesx($src);
$orig_h = imagesy($src);
$max    = 1200;
if ($orig_w > $max || $orig_h > $max) {
    $ratio = min($max / $orig_w, $max / $orig_h);
    $new_w = (int) round($orig_w * $ratio);
    $new_h = (int) round($orig_h * $ratio);
} else {
    $new_w = $orig_w;
    $new_h = $orig_h;
}

$dst   = imagecreatetruecolor($new_w, $new_h);
$white = imagecolorallocate($dst, 255, 255, 255);
imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);
imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
imagedestroy($src);

$fileName    = 'soal_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
$fileToWrite = $imageFolder . $fileName;

if (!imagejpeg($dst, $fileToWrite, 85)) {
    imagedestroy($dst);
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan file. Periksa permission direktori uploads.']);
    exit;
}

imagedestroy($dst);

// Lacak file yang diupload dalam session untuk cleanup orphan saat INSERT
if (!isset($_SESSION['pending_uploads'])) $_SESSION['pending_uploads'] = [];
$_SESSION['pending_uploads'][] = $fileName;

echo json_encode(['location' => BASE_URL . 'assets/uploads/soal/' . $fileName]);
