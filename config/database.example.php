<?php
/**
 * ============================================================
 *  KONFIGURASI APLIKASI AXON CBT (TEMPLATE CONTOH)
 *  Salin file ini menjadi 'database.php' dan sesuaikan konfigurasi.
 * ============================================================
 */

// ────────────────────────────────────────────────────────────
//  USER CONFIGURATION
// ────────────────────────────────────────────────────────────

/**
 * BASE PATH — sesuaikan dengan lokasi deploy:
 *   '/'           → root domain / port
 *   '/cbt_nova/'  → subfolder
 */
if (!defined('APP_BASEPATH')) {
    $reqUri = $_SERVER['REQUEST_URI'] ?? '';
    $scrName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_contains($reqUri, '/cbt_nova/') || str_contains($scrName, '/cbt_nova/')) {
        define('APP_BASEPATH', '/cbt_nova/');
    } else {
        define('APP_BASEPATH', '/');
    }
}

/**
 * DEBUG MODE — set true saat development, false saat production
 */
define('APP_DEBUG', false);

/**
 * KONFIGURASI DATABASE
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_axon');
define('DB_USER', 'root');
define('DB_PASS', '');

// ────────────────────────────────────────────────────────────
//  SISTEM — jangan ubah bagian di bawah ini
// ────────────────────────────────────────────────────────────

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', APP_BASEPATH);
    session_start();
}

// Deteksi protokol
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $protocol = rtrim($_SERVER['HTTP_X_FORWARDED_PROTO'], '/') . '://';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}

$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $domain . APP_BASEPATH);

require_once __DIR__ . '/../includes/helpers.php';

// Koneksi Database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]
    );
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    die('Koneksi database gagal. Periksa konfigurasi DB_HOST, DB_NAME, DB_USER, DB_PASS.');
}

// Auto-logout setelah 2 jam tidak aktif
if (isset($_SESSION['role'])) {
    $__timeout = 7200; // 2 jam dalam detik
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > $__timeout) {
        $__is_xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        try {
            $pdo->prepare("DELETE FROM cbt_online_sessions WHERE session_id = ?")
                ->execute([session_id()]);
        } catch (\Throwable $e) { /* silent */ }
        session_unset();
        session_destroy();
        if ($__is_xhr) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'session_expired']);
            exit;
        }
        header("Location: " . BASE_URL . "index.php?pesan=sesi_berakhir");
        exit;
    }
    $_SESSION['_last_activity'] = time();
    if (!isset($_SESSION['_last_db_ping']) || (time() - $_SESSION['_last_db_ping']) >= 60) {
        $_SESSION['_last_db_ping'] = time();
        try {
            $pdo->prepare("UPDATE cbt_online_sessions SET last_seen = NOW() WHERE session_id = ?")
                ->execute([session_id()]);
        } catch (\Throwable $e) { /* silent */ }
    }
}
unset($__timeout);

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    $token  = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!$stored || !hash_equals($stored, $token)) {
        $is_xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        if ($is_xhr) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid, muat ulang halaman.']);
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html><html><body><p>Akses ditolak: token keamanan tidak valid. <a href="javascript:history.back()">Kembali</a></p></body></html>';
        }
        exit;
    }
}

function log_activity(string $activity, ?int $user_id = null, ?string $nama = null, ?string $role = null, string $type = 'umum'): void {
    global $pdo;
    try {
        $uid   = $user_id ?? ($_SESSION['user_id'] ?? null);
        $uname = $nama    ?? ($_SESSION['nama']     ?? 'System');
        $urole = $role    ?? ($_SESSION['role']     ?? 'system');
        $ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0';
        $pdo->prepare("INSERT INTO cbt_activity_logs (user_id, nama, role, activity, type, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$uid, $uname, $urole, $activity, $type, $ip]);
    } catch (\Throwable $e) { /* silent */ }
}

function get_app_settings(): array {
    $ttl = 300;
    if (isset($_SESSION['_app_settings_ts']) && (time() - $_SESSION['_app_settings_ts']) < $ttl) {
        return $_SESSION['_app_settings'] ?? [];
    }
    global $pdo;
    try {
        $row = $pdo->query("SELECT nama_sekolah, logo FROM cbt_settings LIMIT 1")->fetch();
        $_SESSION['_app_settings']    = $row ?: [];
        $_SESSION['_app_settings_ts'] = time();
        return $_SESSION['_app_settings'];
    } catch (\Throwable $e) {
        return [];
    }
}
