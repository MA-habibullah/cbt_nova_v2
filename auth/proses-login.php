<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = $_GET['role'] ?? 'siswa';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Verifikasi CSRF Token dengan Graceful Redirect
    $token  = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    if (empty($stored) || empty($token) || !hash_equals($stored, $token)) {
        // Generate token baru agar form siap digunakan kembali
        csrf_token();
        header("Location: " . BASE_URL . "index.php?pesan=csrf_expired&tab=" . urlencode($role));
        exit;
    }

    // 1. Tentukan tabel dan folder tujuan (needed before captcha redirect uses $role)
    switch ($role) {
        case 'siswa':
            $table    = 'cbt_students';
            $redirect = 'siswa/index.php';
            break;
        case 'guru':
            $table    = 'cbt_teachers';
            $redirect = 'guru/index.php';
            break;
        case 'admin':
            $table    = 'cbt_admins';
            $redirect = 'admin/index.php';
            break;
        default:
            header("Location: " . BASE_URL . "index.php?pesan=role_tidak_valid");
            exit;
    }

    // Validasi CAPTCHA
    $captcha_input = strtoupper(trim($_POST['captcha'] ?? ''));
    if (empty($captcha_input) || empty($_SESSION['captcha_code']) || $captcha_input !== $_SESSION['captcha_code']) {
        unset($_SESSION['captcha_code']);
        header("Location: " . BASE_URL . "index.php?pesan=captcha_salah&tab=" . $role);
        exit;
    }
    unset($_SESSION['captcha_code']); // Consume after successful validation

    // 2. Ambil data user (siswa bisa login dengan username ATAU nisn)
    if ($role === 'siswa') {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE username = ? OR nisn = ?");
        $stmt->execute([$username, $username]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE username = ?");
        $stmt->execute([$username]);
    }
    $user = $stmt->fetch();

    // 3. Verifikasi Password
    if ($user && password_verify($password, $user['password'])) {
        
        // --- LOGIKA KHUSUS SISWA: DEVICE LOCK ---
        if ($role == 'siswa') {
            // Cek apakah akun siswa aktif
            if ($user['is_aktif'] == 0) {
                header("Location: " . BASE_URL . "index.php?pesan=akun_nonaktif");
                exit;
            }

            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            // Ambil IP client asli; cek header proxy/Docker terlebih dahulu
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // X-Forwarded-For bisa berisi daftar IP: "client, proxy1, proxy2"
                $ipAddress = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ipAddress = $_SERVER['HTTP_X_REAL_IP'];
            } else {
                $ipAddress = $_SERVER['REMOTE_ADDR'];
            }
            $deviceFingerprint = md5($userAgent); // Sidik jari unik browser

            // Cari kunci perangkat yang aktif (dalam 24 jam terakhir)
            $stmtLock = $pdo->prepare("SELECT * FROM cbt_device_locks 
                                       WHERE student_id = ? 
                                       AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmtLock->execute([$user['id']]);
            $currentLock = $stmtLock->fetch();

            if ($currentLock) {
                // Jika ada kunci aktif, bandingkan sidik jarinya
                if ($currentLock['device_id'] !== $deviceFingerprint) {
                    // Perangkat berbeda -> Tolak akses (Hubungi proktor untuk reset)
                    header("Location: " . BASE_URL . "index.php?pesan=device_locked");
                    exit;
                }
                // Jika perangkat sama, perbarui timestamp & IP terbaru
                $pdo->prepare("UPDATE cbt_device_locks SET ip_address = ?, user_agent = ?, created_at = NOW() WHERE id = ?")
                    ->execute([$ipAddress, $userAgent, $currentLock['id']]);
                $newLockId = $currentLock['id'];
            } else {
                // Jika tidak ada kunci (atau sudah lewat 24 jam), bersihkan yang lama & buat kunci baru
                $pdo->prepare("DELETE FROM cbt_device_locks WHERE student_id = ?")->execute([$user['id']]);

                $insLock = $pdo->prepare("INSERT INTO cbt_device_locks (student_id, device_id, user_agent, ip_address, created_at)
                                          VALUES (?, ?, ?, ?, NOW())");
                $insLock->execute([$user['id'], $deviceFingerprint, $userAgent, $ipAddress]);
                $newLockId = $pdo->lastInsertId();
            }

            // Simpan data spesifik siswa ke session
            $_SESSION['student_id'] = $user['id'];
            $_SESSION['class_id']   = $user['class_id'];
            $_SESSION['sesi']       = $user['sesi'];
        }

        // --- LOGIKA UMUM: SET SESSION ---
        session_regenerate_id(true);

        // Simpan session_id ke device lock agar admin bisa paksa logout siswa saat reset login
        if ($role == 'siswa' && isset($newLockId)) {
            $pdo->prepare("UPDATE cbt_device_locks SET session_id = ? WHERE id = ?")
                ->execute([session_id(), $newLockId]);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama']    = $user['nama_lengkap'];
        $_SESSION['role']    = $role;

        if ($role == 'admin') {
            $_SESSION['admin_id']    = $user['id'];
            $_SESSION['admin_level'] = $user['role'];
        }

        if ($role == 'guru') {
            $_SESSION['teacher_id'] = $user['id'];
        }

        // Catat sesi aktif untuk penghitungan pengguna online
        $pdo->prepare("INSERT INTO cbt_online_sessions (session_id, user_id, role, last_seen)
                       VALUES (?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), role = VALUES(role), last_seen = NOW()")
            ->execute([session_id(), $user['id'], $role]);

        log_activity('Login berhasil', $user['id'], $user['nama_lengkap'], $role, 'auth');
        header("Location: " . BASE_URL . $redirect);
        exit;

    } else {
        log_activity('Login gagal — username: ' . $username, null, $username, $role, 'auth');
        header("Location: " . BASE_URL . "index.php?pesan=gagal&tab=" . $role);
        exit;
    }
} else {
    header("Location: " . BASE_URL . "index.php");
    exit;
}