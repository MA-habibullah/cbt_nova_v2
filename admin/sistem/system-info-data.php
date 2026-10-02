<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesi berakhir atau tidak memiliki izin akses. Silakan login ulang.']);
    exit;
}
session_write_close();

$IS_WINDOWS = PHP_OS_FAMILY === 'Windows';

// ── Maintenance Actions (POST) ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';

    if (empty($stored) || empty($token) || !hash_equals($stored, $token)) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF Token tidak valid.']);
        exit;
    }

    if ($action === 'clear_opcache') {
        if (function_exists('opcache_reset')) {
            $reset = @opcache_reset();
            if ($reset) {
                echo json_encode(['status' => 'success', 'message' => 'OPcache berhasil dibersihkan.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal me-reset OPcache atau OPcache dinonaktifkan di konfigurasi web server.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Fungsi opcache_reset tidak tersedia pada server ini.']);
        }
        exit;
    }

    if ($action === 'clean_sessions') {
        try {
            $deleted = $pdo->exec("DELETE FROM cbt_online_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            echo json_encode(['status' => 'success', 'message' => "Berhasil membersihkan {$deleted} sesi kedaluwarsa (> 24 jam)."]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal membersihkan sesi: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'db_check') {
        try {
            $criticalTables = [
                'cbt_admins', 'cbt_teachers', 'cbt_students', 'cbt_classes', 'cbt_subjects',
                'cbt_bank_soal', 'cbt_questions', 'cbt_question_options', 'cbt_exams', 'cbt_exam_questions',
                'cbt_exam_participants', 'cbt_student_answers', 'cbt_online_sessions',
                'cbt_device_locks', 'cbt_cheat_logs', 'cbt_activity_logs'
            ];
            $tableResults = [];
            foreach ($criticalTables as $tbl) {
                $check = $pdo->query("CHECK TABLE `$tbl`")->fetch(PDO::FETCH_ASSOC);
                $status = $check['Msg_text'] ?? 'OK';
                $tableResults[$tbl] = ($status === 'OK' || str_contains(strtolower($status), 'already up to date')) ? 'OK' : $status;
            }
            echo json_encode(['status' => 'success', 'message' => 'Pemeriksaan integritas tabel selesai.', 'data' => $tableResults]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memeriksa database: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali.']);
    exit;
}

// ── CPU Usage ─────────────────────────────────────────────────────────────
function getCpuPercent(bool $isWindows): float {
    if ($isWindows) {
        $out = @shell_exec('wmic cpu get loadpercentage /value 2>nul');
        if ($out && preg_match('/LoadPercentage=(\d+)/i', $out, $m)) {
            return (float)$m[1];
        }
        $out = @shell_exec('powershell -NoProfile -Command "Get-WmiObject Win32_Processor | Measure-Object -Property LoadPercentage -Average | Select -ExpandProperty Average" 2>nul');
        return $out ? round((float)trim($out), 1) : 0.0;
    }

    // Linux / Debian 12 / Cloud
    if (is_readable('/proc/stat')) {
        $read = function () {
            $content = @file_get_contents('/proc/stat');
            if (!$content) return ['total' => 0, 'idle' => 0];
            $firstLine = strtok($content, "\r\n");
            $parts = preg_split('/\s+/', trim($firstLine));
            if (empty($parts) || $parts[0] !== 'cpu') return ['total' => 0, 'idle' => 0];
            $total = array_sum(array_slice($parts, 1));
            $idle  = (int)($parts[4] ?? 0) + (int)($parts[5] ?? 0);
            return ['total' => $total, 'idle' => $idle];
        };

        $a = $read();
        if ($a['total'] > 0) {
            usleep(100000); // 100ms sample
            $b = $read();
            $dt = $b['total'] - $a['total'];
            $di = $b['idle']  - $a['idle'];
            if ($dt > 0) {
                return round(max(0, min(100, (($dt - $di) / $dt) * 100)), 1);
            }
        }
    }

    // Fallback: sys_getloadavg()
    $load = @sys_getloadavg();
    if (is_array($load) && isset($load[0])) {
        $cores = getCpuCores(false);
        return round(max(0, min(100, ($load[0] / max($cores, 1)) * 100)), 1);
    }

    return 0.0;
}

// ── Memory Usage ──────────────────────────────────────────────────────────
function getMemory(bool $isWindows): array {
    if ($isWindows) {
        $out = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>nul');
        if ($out && preg_match('/FreePhysicalMemory=(\d+)/i', $out, $mf)
                 && preg_match('/TotalVisibleMemorySize=(\d+)/i', $out, $mt)) {
            $totalKB = (int)$mt[1];
            $freeKB  = (int)$mf[1];
            $usedKB  = $totalKB - $freeKB;
            $totalMB = round($totalKB / 1024);
            $usedMB  = round($usedKB  / 1024);
            $pct     = $totalMB > 0 ? round($usedMB / $totalMB * 100, 1) : 0.0;
            return ['used' => $usedMB, 'total' => $totalMB, 'percent' => $pct];
        }
        return ['used' => 0, 'total' => 0, 'percent' => 0.0];
    }

    // Linux / Debian 12
    if (is_readable('/proc/meminfo')) {
        $m = [];
        $lines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $m[trim($parts[0])] = (int)trim(str_replace('kB', '', $parts[1]));
            }
        }
        $totalKB = $m['MemTotal']     ?? 0;
        $availKB = $m['MemAvailable'] ?? (($m['MemFree'] ?? 0) + ($m['Buffers'] ?? 0) + ($m['Cached'] ?? 0));
        $usedKB  = max(0, $totalKB - $availKB);
        $totalMB = round($totalKB / 1024);
        $usedMB  = round($usedKB  / 1024);
        $pct     = $totalMB > 0 ? round($usedMB / $totalMB * 100, 1) : 0.0;
        return ['used' => $usedMB, 'total' => $totalMB, 'percent' => $pct];
    }

    return ['used' => 0, 'total' => 0, 'percent' => 0.0];
}

// ── Storage Usage ─────────────────────────────────────────────────────────
function getStorage(): array {
    $path  = dirname(__DIR__, 2);
    $free  = @disk_free_space($path);
    $total = @disk_total_space($path);
    if ($free === false || $total === false || $total <= 0) {
        $free  = @disk_free_space('/') ?: 0;
        $total = @disk_total_space('/') ?: 0;
    }
    $free  = $free ?: 0;
    $total = $total ?: 0;
    $used  = max(0, $total - $free);
    $gb    = fn($b) => round($b / (1024 ** 3), 2);
    return [
        'used'    => $gb($used),
        'free'    => $gb($free),
        'total'   => $gb($total),
        'percent' => $total > 0 ? round($used / $total * 100, 1) : 0.0,
    ];
}

// ── Active Users ──────────────────────────────────────────────────────────
function getActiveUsers(PDO $pdo): array {
    $windowSec = 900;
    $activeSiswa = 0;
    $activeGuru  = 0;
    $activeAdmin = 0;

    try {
        $stmt = $pdo->prepare("
            SELECT role, COUNT(DISTINCT user_id) AS cnt
            FROM cbt_online_sessions
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)
              AND role IN ('siswa', 'guru', 'admin')
            GROUP BY role
        ");
        $stmt->execute([$windowSec]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $activeSiswa = (int)($rows['siswa'] ?? 0);
        $activeGuru  = (int)($rows['guru']  ?? 0);
        $activeAdmin = (int)($rows['admin'] ?? 0);
    } catch (\Throwable $e) {}

    $totalActive = $activeSiswa + $activeGuru + $activeAdmin;

    $totalSiswa = 0;
    $totalGuru  = 0;
    $totalAdmin = 0;
    try {
        $totalSiswa = (int)($pdo->query("SELECT COUNT(*) FROM cbt_students")->fetchColumn() ?: 0);
        $totalGuru  = (int)($pdo->query("SELECT COUNT(*) FROM cbt_teachers")->fetchColumn() ?: 0);
        $totalAdmin = (int)($pdo->query("SELECT COUNT(*) FROM cbt_admins")->fetchColumn() ?: 0);
    } catch (\Throwable $e) {}

    $total = $totalSiswa + $totalGuru + $totalAdmin;
    $pct = fn($n) => $total > 0 ? round($n / $total * 100, 1) : 0.0;

    return [
        'active_siswa'  => $activeSiswa,
        'active_guru'   => $activeGuru,
        'active_admin'  => $activeAdmin,
        'total_active'  => $totalActive,
        'total'         => $total,
        'total_siswa'   => $totalSiswa,
        'total_guru'    => $totalGuru,
        'total_admin'   => $totalAdmin,
        'percent_siswa' => $pct($activeSiswa),
        'percent_guru'  => $pct($activeGuru),
        'percent_admin' => $pct($activeAdmin),
        'percent_total' => $pct($totalActive),
        'online_window' => $windowSec / 60,
    ];
}

// ── CPU Core Count ────────────────────────────────────────────────────────
function getCpuCores(bool $isWindows): int {
    if ($isWindows) {
        $out = @shell_exec('wmic cpu get NumberOfLogicalProcessors /value 2>nul');
        if ($out && preg_match('/NumberOfLogicalProcessors=(\d+)/i', $out, $m)) {
            return (int)$m[1];
        }
        $out = @shell_exec('powershell -NoProfile -Command "(Get-WmiObject Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum" 2>nul');
        return $out ? (int)trim($out) : 1;
    }

    if (is_readable('/proc/cpuinfo')) {
        $cpuinfo = @file_get_contents('/proc/cpuinfo') ?: '';
        $count = substr_count($cpuinfo, 'processor');
        if ($count > 0) return $count;
    }
    $out = @shell_exec('nproc 2>/dev/null');
    if ($out && (int)trim($out) > 0) return (int)trim($out);
    return 1;
}

// ── Database Health Metrics ───────────────────────────────────────────────
function getDatabaseHealth(PDO $pdo): array {
    $t0 = microtime(true);
    $ver = 'MariaDB';
    try {
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn() ?: 'MariaDB';
    } catch (\Throwable $e) {}
    $latencyMs = round((microtime(true) - $t0) * 1000, 2);

    $sizeData = ['table_count' => 0, 'size_mb' => 0];
    try {
        $stmtSize = $pdo->query("
            SELECT 
                COUNT(table_name) AS table_count,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        if ($stmtSize) {
            $sizeData = $stmtSize->fetch(PDO::FETCH_ASSOC) ?: ['table_count' => 0, 'size_mb' => 0];
        }
    } catch (\Throwable $e) {}

    // Status variables
    $statusVars = [];
    try {
        $stmtStatus = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Max_used_connections', 'Uptime', 'Questions')");
        if ($stmtStatus) {
            while ($r = $stmtStatus->fetch(PDO::FETCH_ASSOC)) {
                $statusVars[$r['Variable_name']] = $r['Value'];
            }
        }
    } catch (\Throwable $e) {}

    // Max connections
    $maxConn = 151;
    try {
        $stmtMax = $pdo->query("SHOW VARIABLES LIKE 'max_connections'");
        if ($stmtMax) {
            $rMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
            if ($rMax) $maxConn = (int)$rMax['Value'];
        }
    } catch (\Throwable $e) {}

    return [
        'version'           => $ver,
        'latency_ms'        => $latencyMs,
        'table_count'       => (int)($sizeData['table_count'] ?? 0),
        'size_mb'           => (float)($sizeData['size_mb'] ?? 0),
        'threads_connected' => (int)($statusVars['Threads_connected'] ?? 1),
        'max_connections'   => $maxConn,
        'uptime_sec'        => (int)($statusVars['Uptime'] ?? 0),
    ];
}

// ── Directory Permissions Checklist ───────────────────────────────────────
function getDirectoriesHealth(): array {
    $base = dirname(__DIR__, 2);
    $checkDirs = [
        'assets/uploads'            => 'Penyimpanan Utama Upload',
        'assets/uploads/foto_siswa' => 'Folder Foto Profil Siswa',
        'assets/uploads/bank_soal'  => 'Folder Media Gambar Soal',
        'assets/uploads/logo'       => 'Folder Logo Sekolah',
        'backups'                   => 'Folder Berkas Backup Database',
        'logs'                      => 'Folder Catatan Log Aktivitas'
    ];

    $results = [];
    foreach ($checkDirs as $relPath => $label) {
        $fullPath = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $exists   = is_dir($fullPath);
        $writable = $exists && is_writable($fullPath);
        
        $perms = '-';
        if ($exists) {
            $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        }

        $results[] = [
            'path'      => $relPath,
            'label'     => $label,
            'exists'    => $exists,
            'writable'  => $writable,
            'perms'     => $perms
        ];
    }
    return $results;
}

// ── PHP Environment & Extensions ──────────────────────────────────────────
function getPhpEnvironment(): array {
    $requiredExts = [
        'pdo'        => 'PDO Database Core',
        'pdo_mysql'  => 'Driver MySQL/MariaDB',
        'mbstring'   => 'Multibyte String Engine',
        'openssl'    => 'Kriptografi & Keamanan',
        'gd'         => 'Pengolahan Gambar & QR',
        'zip'        => 'Ekspor Excel & Kompresi',
        'curl'       => 'Klien HTTP & Integrasi',
        'json'       => 'Parser Data JSON',
        'fileinfo'   => 'Deteksi Tipe File Upload',
        'redis'      => 'In-Memory Cache (Redis)'
    ];

    $extensions = [];
    foreach ($requiredExts as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $extensions[] = [
            'ext'         => $ext,
            'description' => $desc,
            'status'      => $loaded
        ];
    }

    $opcacheStatus = false;
    $opcacheHitRate = 0.0;
    $opcacheMemoryMB = 0;
    if (function_exists('opcache_get_status')) {
        try {
            $st = @opcache_get_status(false);
            if (is_array($st) && !empty($st['opcache_enabled'])) {
                $opcacheStatus = true;
                $opcacheHitRate = round($st['opcache_statistics']['opcache_hit_rate'] ?? 0, 1);
                $opcacheMemoryMB = round(($st['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 1);
            }
        } catch (\Throwable $e) {}
    }

    return [
        'php_version'          => PHP_VERSION,
        'php_sapi'             => php_sapi_name(),
        'os_name'              => PHP_OS . ' (' . php_uname('m') . ')',
        'server_software'      => $_SERVER['SERVER_SOFTWARE'] ?? 'Web Server',
        'memory_limit'         => ini_get('memory_limit') ?: '512M',
        'max_execution_time'   => (ini_get('max_execution_time') ?: '0') . 's',
        'upload_max_filesize'  => ini_get('upload_max_filesize') ?: '64M',
        'post_max_size'        => ini_get('post_max_size') ?: '64M',
        'max_input_vars'       => ini_get('max_input_vars') ?: '5000',
        'opcache_enabled'      => $opcacheStatus,
        'opcache_hit_rate'     => $opcacheHitRate,
        'opcache_used_mb'      => $opcacheMemoryMB,
        'extensions'           => $extensions
    ];
}

try {
    echo json_encode([
        'cpu'             => getCpuPercent($IS_WINDOWS),
        'cpu_cores'       => getCpuCores($IS_WINDOWS),
        'memory'          => getMemory($IS_WINDOWS),
        'storage'         => getStorage(),
        'active_users'    => getActiveUsers($pdo),
        'database'        => getDatabaseHealth($pdo),
        'directories'     => getDirectoriesHealth(),
        'environment'     => getPhpEnvironment(),
        'os'              => PHP_OS_FAMILY,
        'timestamp'       => date('H:i:s'),
    ], JSON_NUMERIC_CHECK);
} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

