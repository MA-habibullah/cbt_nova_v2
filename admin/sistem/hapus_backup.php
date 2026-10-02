<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403); echo "Unauthorized"; exit;
}
csrf_verify();

$file = basename($_POST['file'] ?? ''); // basename() cegah path traversal
if (empty($file)) { http_response_code(400); exit; }
$path = __DIR__ . '/../../backups/' . $file;

if (file_exists($path)) {
    unlink($path);
    log_activity("Hapus file backup: $file", null, null, null, 'sistem');
    echo "success";
}