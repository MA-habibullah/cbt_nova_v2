<?php
ob_start();
require_once '../config/database.php';

// 0. Catat activity logout sebelum session dihancurkan
log_activity('Logout', null, null, null, 'auth');

// 1. Hapus device lock siswa sebelum session di-destroy
if (!empty($_SESSION['student_id'])) {
    $pdo->prepare("DELETE FROM cbt_device_locks WHERE student_id = ?")
        ->execute([$_SESSION['student_id']]);
}

// Hapus sesi aktif agar counter pengguna online langsung berkurang
$pdo->prepare("DELETE FROM cbt_online_sessions WHERE session_id = ?")
    ->execute([session_id()]);

// 2. Hapus semua data session di memori PHP
$_SESSION = array();

// 2. Hancurkan cookie session di browser user
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan session di server
session_destroy();

// 4. Bersihkan output buffer dan redirect ke root index.php
ob_clean();
header("Location: " . BASE_URL . "index.php?pesan=logout");
exit;