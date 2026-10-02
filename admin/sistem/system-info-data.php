<?php
ob_start();
require_once '../../config/database.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$IS_WINDOWS = PHP_OS_FAMILY === 'Windows';

// ── CPU Usage ─────────────────────────────────────────────────────────────
function getCpuPercent(bool $isWindows): float {
    if ($isWindows) {
        // wmic cpu get loadpercentage — tersedia di Windows XP+
        $out = @shell_exec('wmic cpu get loadpercentage /value 2>nul');
        if ($out && preg_match('/LoadPercentage=(\d+)/i', $out, $m)) {
            return (float)$m[1];
        }
        // Fallback: PowerShell (Windows 7+)
        $out = @shell_exec('powershell -NoProfile -Command "Get-WmiObject Win32_Processor | Measure-Object -Property LoadPercentage -Average | Select -ExpandProperty Average" 2>nul');
        return $out ? round((float)trim($out), 1) : 0.0;
    }

    // Linux / macOS
    if (!is_readable('/proc/stat')) {
        // macOS: gunakan vm_stat + sysctl
        $load = @sys_getloadavg();
        if ($load) {
            $cores = (int)(@shell_exec('nproc 2>/dev/null') ?: @shell_exec('sysctl -n hw.ncpu 2>/dev/null') ?: 1);
            return round(min($load[0] / max($cores, 1) * 100, 100), 1);
        }
        return 0.0;
    }

    $read = function () {
        $fh    = @fopen('/proc/stat', 'r');
        if (!$fh) return ['total' => 0, 'idle' => 0];
        $line  = fgets($fh);
        fclose($fh);
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($line)));
        $total = array_sum(array_slice($parts, 1));
        $idle  = (int)($parts[4] ?? 0) + (int)($parts[5] ?? 0);
        return ['total' => $total, 'idle' => $idle];
    };

    $a = $read();
    usleep(200000);
    $b = $read();

    $dt = $b['total'] - $a['total'];
    $di = $b['idle']  - $a['idle'];
    return $dt > 0 ? round(($dt - $di) / $dt * 100, 1) : 0.0;
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

    // Linux
    if (is_readable('/proc/meminfo')) {
        $m = [];
        foreach (file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$key, $val] = explode(':', $line, 2);
            $m[trim($key)] = (int)trim(str_replace('kB', '', $val));
        }
        $totalKB = $m['MemTotal']     ?? 0;
        $availKB = $m['MemAvailable'] ?? (($m['MemFree'] ?? 0) + ($m['Buffers'] ?? 0) + ($m['Cached'] ?? 0));
        $usedKB  = $totalKB - $availKB;
        $totalMB = round($totalKB / 1024);
        $usedMB  = round($usedKB  / 1024);
        $pct     = $totalMB > 0 ? round($usedMB / $totalMB * 100, 1) : 0.0;
        return ['used' => $usedMB, 'total' => $totalMB, 'percent' => $pct];
    }

    // macOS
    $out = @shell_exec('vm_stat 2>/dev/null');
    $sysctl = @shell_exec('sysctl -n hw.memsize 2>/dev/null');
    if ($out && $sysctl) {
        preg_match('/page size of (\d+)/', $out, $ps);
        $pageSize = isset($ps[1]) ? (int)$ps[1] : 4096;
        preg_match('/Pages free:\s+(\d+)/i', $out, $pf);
        preg_match('/Pages inactive:\s+(\d+)/i', $out, $pi);
        $freeBytes  = ((int)($pf[1] ?? 0) + (int)($pi[1] ?? 0)) * $pageSize;
        $totalBytes = (int)trim($sysctl);
        $usedBytes  = $totalBytes - $freeBytes;
        $totalMB = round($totalBytes / (1024 * 1024));
        $usedMB  = round($usedBytes  / (1024 * 1024));
        $pct = $totalMB > 0 ? round($usedMB / $totalMB * 100, 1) : 0.0;
        return ['used' => $usedMB, 'total' => $totalMB, 'percent' => $pct];
    }

    return ['used' => 0, 'total' => 0, 'percent' => 0.0];
}

// ── Storage Usage (cross-platform) ────────────────────────────────────────
function getStorage(): array {
    $path  = __DIR__;
    $free  = @disk_free_space($path)  ?: 0;
    $total = @disk_total_space($path) ?: 0;
    $used  = $total - $free;
    $gb    = fn($b) => round($b / (1024 ** 3), 2);
    return [
        'used'    => $gb($used),
        'free'    => $gb($free),
        'total'   => $gb($total),
        'percent' => $total > 0 ? round($used / $total * 100, 1) : 0.0,
    ];
}

// ── Active Users (via cbt_online_sessions) ───────────────────────────────
function getActiveUsers(PDO $pdo): array {
    $windowSec = 900;

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
    $totalActive = $activeSiswa + $activeGuru + $activeAdmin;

    $totalSiswa = (int)$pdo->query("SELECT COUNT(*) FROM cbt_students")->fetchColumn();
    $totalGuru  = (int)$pdo->query("SELECT COUNT(*) FROM cbt_teachers")->fetchColumn();
    $totalAdmin = (int)$pdo->query("SELECT COUNT(*) FROM cbt_admins")->fetchColumn();
    $total      = $totalSiswa + $totalGuru + $totalAdmin;

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
    // Linux
    $out = @shell_exec('nproc 2>/dev/null');
    if ($out && (int)trim($out) > 0) return (int)trim($out);
    // macOS
    $out = @shell_exec('sysctl -n hw.logicalcpu 2>/dev/null');
    if ($out && (int)trim($out) > 0) return (int)trim($out);
    // Fallback: count /proc/cpuinfo
    if (is_readable('/proc/cpuinfo')) {
        $count = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
        if ($count > 0) return $count;
    }
    return 1;
}

try {
    echo json_encode([
        'cpu'          => getCpuPercent($IS_WINDOWS),
        'cpu_cores'    => getCpuCores($IS_WINDOWS),
        'memory'       => getMemory($IS_WINDOWS),
        'storage'      => getStorage(),
        'active_users' => getActiveUsers($pdo),
        'os'           => PHP_OS_FAMILY,
        'timestamp'    => date('H:i:s'),
    ], JSON_NUMERIC_CHECK);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
