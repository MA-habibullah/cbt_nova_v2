<?php
ob_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (ob_get_length()) ob_end_clean();
    header("Location: " . BASE_URL . "index.php"); exit;
}

$file = basename($_GET['file'] ?? ''); // basename() cegah path traversal
if (empty($file)) { 
    if (ob_get_length()) ob_end_clean();
    http_response_code(400); exit; 
}
$path = __DIR__ . '/../../backups/' . $file;

if (file_exists($path)) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}