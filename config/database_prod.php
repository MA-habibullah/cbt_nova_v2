<?php
/**
 * ============================================================
 *  KONFIGURASI APLIKASI CBT NOVA (PRODUCTION ENVIRONMENT)
 *  Gunakan file ini untuk server produksi.
 * ============================================================
 */

// ────────────────────────────────────────────────────────────
//  USER CONFIGURATION
// ────────────────────────────────────────────────────────────

/**
 * BASE PATH — sesuaikan dengan lokasi deploy:
 *
 *   '/'           → root domain / port
 *                   Contoh: domain.id  |  localhost:8080  |  192.168.1.10
 *
 *   '/cbt_nova/'  → subfolder
 *                   Contoh: localhost/cbt_nova
 *
 * PENTING: selalu diawali dan diakhiri dengan '/'
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
 * KONFIGURASI DATABASE (PRODUCTION)
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'cbt_nova_v2');
define('DB_USER', 'user_cbt');
define('DB_PASS', 'Admin-sma-11');

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

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_path', APP_BASEPATH);
    session_start();
}

// Deteksi protokol — mendukung reverse proxy (Nginx, Caddy, Cloudflare, dll.)
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
        // Hapus online session sebelum destroy
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
    // Perbarui last_seen dengan throttling (maksimal 1x per 60 detik per sesi untuk efisiensi I/O)
    if (!isset($_SESSION['_last_db_ping']) || (time() - $_SESSION['_last_db_ping']) >= 60) {
        $_SESSION['_last_db_ping'] = time();
        try {
            $pdo->prepare("UPDATE cbt_online_sessions SET last_seen = NOW() WHERE session_id = ?")
                ->execute([session_id()]);
        } catch (\Throwable $e) { /* silent */ }
    }
}
unset($__timeout);

/** CSRF: generate token (simpan ke session) */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** CSRF: verifikasi token — die/json jika tidak valid */
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

/** Activity log: catat aktivitas user */
function log_activity(string $activity, ?int $user_id = null, ?string $nama = null, ?string $role = null, string $type = 'umum'): void {
    global $pdo;
    try {
        $uid   = $user_id ?? ($_SESSION['user_id'] ?? null);
        $uname = $nama    ?? ($_SESSION['nama']     ?? 'System');
        $urole = $role    ?? ($_SESSION['role']     ?? 'system');
        $ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0';
        $pdo->prepare("INSERT INTO cbt_activity_logs (user_id, nama, role, activity, type, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$uid, $uname, $urole, $activity, $type, $ip]);
    } catch (\Throwable $e) {
        // silent — jangan sampai logging menghentikan flow aplikasi
    }
}

/**
 * App settings dengan session cache (TTL 5 menit).
 * Menghindari query berulang ke cbt_settings di setiap halaman.
 */
function get_app_settings(): array {
    $ttl = 300; // 5 menit
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

/** Query helper */
function query(string $sql, array $params = []): PDOStatement {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Hapus file session siswa secara paksa berdasarkan session ID */
function destroySessionById(string $session_id): void {
    if (empty($session_id)) return;

    $save_path = session_save_path();
    if (empty($save_path)) $save_path = sys_get_temp_dir();

    // Tangani format "N;/path"
    if (strpos($save_path, ';') !== false) {
        $save_path = substr($save_path, strrpos($save_path, ';') + 1);
    }

    $file = rtrim($save_path, '/') . '/sess_' . $session_id;
    if (file_exists($file)) @unlink($file);
}

/** Secret key for public display URL encryption (AES-256 needs 32 bytes) */
define('DISPLAY_SECRET', hash('sha256', 'cbt_nova_display_secret_v1', true));

/**
 * Enkripsi konfigurasi dashboard publik menjadi token URL-safe.
 * Menggunakan AES-256-CBC; IV 16 byte di-prepend ke ciphertext, lalu base64url-encoded.
 */
function encrypt_display_config(array $config): string {
    $json = json_encode($config);
    $iv   = random_bytes(16);
    $enc  = openssl_encrypt($json, 'AES-256-CBC', DISPLAY_SECRET, OPENSSL_RAW_DATA, $iv);
    return rtrim(strtr(base64_encode($iv . $enc), '+/', '-_'), '=');
}

/**
 * Dekripsi token dashboard publik. Return null jika token tidak valid.
 */
function decrypt_display_config(string $token): ?array {
    $padded = $token . str_repeat('=', (4 - strlen($token) % 4) % 4);
    $raw    = base64_decode(strtr($padded, '-_', '+/'));
    if ($raw === false || strlen($raw) < 17) return null;
    $iv   = substr($raw, 0, 16);
    $enc  = substr($raw, 16);
    $json = openssl_decrypt($enc, 'AES-256-CBC', DISPLAY_SECRET, OPENSSL_RAW_DATA, $iv);
    if ($json === false) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Samarkan nama siswa untuk tampilan publik.
 * Contoh: "Ahmad Nugroho" → "A***d N***oho"
 */
function generate_short_code(): string {
    $alpha = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $bytes = random_bytes(10);
    $code  = '';
    for ($i = 0; $i < 10; $i++) {
        $code .= $alpha[ord($bytes[$i]) % strlen($alpha)];
    }
    return $code;
}

function mask_student_name(string $name): string {
    $words = explode(' ', trim($name));
    return implode(' ', array_map(function (string $w): string {
        $len = mb_strlen($w);
        if ($len <= 2) return $w;
        return mb_substr($w, 0, 1) . str_repeat('*', max(1, $len - 2)) . mb_substr($w, -1);
    }, $words));
}
