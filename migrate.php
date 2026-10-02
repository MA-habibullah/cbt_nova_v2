<?php
/**
 * ============================================================================
 *  CBT NOVA — ONE-CLICK GITHUB PULL & DATABASE MIGRATOR (CLI RUNNER)
 * ============================================================================
 *  Perintah: php migrate.php
 *
 *  Fungsi:
 *  1. Sinkronisasi kode: git pull origin main dari GitHub.
 *  2. Menghubungkan ke database aktif (config/database.php).
 *  3. Menjalankan skema DDL & migrasi kolom/tabel secara 100% kompatibel.
 *  4. Memverifikasi struktur folder uploads, logs, dan perizinan file.
 *  5. Membersihkan OPcache & menjalankan uji diagnostik kesehatan sistem.
 * ============================================================================
 */

if (php_sapi_name() !== 'cli') {
    die("Akses ditolak! Skrip ini hanya dapat dijalankan melalui terminal CLI (php migrate.php).\n");
}

define('CLI_ROOT', __DIR__);
define('COLOR_RESET', "\033[0m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_RED', "\033[31m");
define('COLOR_CYAN', "\033[36m");
define('COLOR_BOLD', "\033[1m");

function out($msg, $color = COLOR_RESET) {
    echo $color . $msg . COLOR_RESET . "\n";
}

function out_step($step, $title) {
    echo "\n" . COLOR_CYAN . COLOR_BOLD . "[$step] " . $title . COLOR_RESET . "\n";
    echo str_repeat("─", 75) . "\n";
}

echo "\n";
out("╔═════════════════════════════════════════════════════════════════════════╗", COLOR_BLUE);
out("║         CBT NOVA v2 — ONE-CLICK GITHUB PULL & DATABASE MIGRATOR         ║", COLOR_BLUE);
out("║         Instansi: SMAN 11 Surabaya | Environment: " . (PHP_OS_FAMILY === 'Windows' ? 'Windows/Laragon' : 'Linux Production') . "          ║", COLOR_BLUE);
out("╚═════════════════════════════════════════════════════════════════════════╝", COLOR_BLUE);
out("Waktu Eksekusi: " . date('Y-m-d H:i:s T') . "\n");

// ============================================================================
// STEP 1: GIT PULL DARI GITHUB
// ============================================================================
out_step(1, "SINKRONISASI SUMBER KODE DARI GITHUB (GIT PULL)");

$isGitRepo = is_dir(CLI_ROOT . '/.git');
if (!$isGitRepo) {
    out("[-] Direktori .git tidak ditemukan. Melewati langkah git pull.", COLOR_YELLOW);
} else {
    out("[*] Menjalankan: git pull origin main...", COLOR_BLUE);
    
    $currentBranch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>&1') ?? 'main');
    if (empty($currentBranch) || str_contains($currentBranch, 'fatal')) {
        $currentBranch = 'main';
    }
    
    $gitOutput = shell_exec("git pull origin {$currentBranch} 2>&1");
    
    if ($gitOutput === null) {
        out("[-] Perintah git pull gagal dieksekusi melalui shell_exec.", COLOR_YELLOW);
    } else {
        $lines = explode("\n", trim($gitOutput));
        foreach ($lines as $line) {
            if (str_contains($line, 'Already up to date.') || str_contains($line, 'Already up-to-date.')) {
                out("  -> " . $line, COLOR_GREEN);
            } elseif (str_contains($line, 'Updating') || str_contains($line, 'Fast-forward') || str_contains($line, 'files changed')) {
                out("  -> " . $line, COLOR_CYAN);
            } else {
                out("  -> " . $line, COLOR_RESET);
            }
        }
        out("[✓] Sinkronisasi file dan folder dari GitHub selesai.", COLOR_GREEN);
    }
}

// ============================================================================
// STEP 2: KONEKSI BASIS DATA
// ============================================================================
out_step(2, "MEMERIKSA KONEKSI BASIS DATA (PDO)");

$configFile = CLI_ROOT . '/config/database.php';
$configProd = CLI_ROOT . '/config/database_prod.php';

if (!file_exists($configFile)) {
    if (file_exists($configProd)) {
        out("[!] config/database.php tidak ditemukan. Menyalin dari config/database_prod.php...", COLOR_YELLOW);
        copy($configProd, $configFile);
    } else {
        out("[-] FATAL: File config/database.php maupun config/database_prod.php tidak ditemukan!", COLOR_RED);
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
require_once $configFile;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    out("[-] FATAL: Objek PDO koneksi basis data tidak terinisialisasi!", COLOR_RED);
    exit(1);
}

out("[✓] Berhasil terhubung ke Basis Data:", COLOR_GREEN);
out("    Host     : " . DB_HOST);
out("    Database : " . DB_NAME);
out("    User     : " . DB_USER);

// ============================================================================
// DATABASE HELPER FUNCTIONS (PORTABLE & IDEMPOTENT)
// ============================================================================
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

function index_exists(PDO $pdo, string $table, string $indexName): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $stmt->execute([$table, $indexName]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

function table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

// ============================================================================
// STEP 3: EKSEKUSI MIGRASI BASIS DATA
// ============================================================================
out_step(3, "MENJALANKAN MIGRASI & PEMBARUAN SKEMA BASIS DATA");

$appliedMigrations = 0;

// 1. Buat Tabel-tabel Kritis (CREATE TABLE IF NOT EXISTS)
$coreTables = [
    'cbt_display_tokens' => "
        CREATE TABLE IF NOT EXISTS `cbt_display_tokens` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
          `short_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `url` text COLLATE utf8mb4_unicode_ci,
          `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
          `created_by_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
          `created_by_id` bigint unsigned NOT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_token_hash` (`token_hash`),
          UNIQUE KEY `short_code` (`short_code`),
          KEY `idx_by_creator` (`created_by_role`,`created_by_id`,`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'cbt_device_locks' => "
        CREATE TABLE IF NOT EXISTS `cbt_device_locks` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `student_id` bigint unsigned NOT NULL,
          `device_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
          `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_student_device` (`student_id`,`device_id`),
          KEY `idx_device_student` (`student_id`),
          KEY `idx_device_created` (`student_id`,`created_at`),
          KEY `idx_device_session` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'cbt_cheat_logs' => "
        CREATE TABLE IF NOT EXISTS `cbt_cheat_logs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `student_id` bigint unsigned NOT NULL,
          `exam_id` bigint unsigned NOT NULL,
          `tipe_pelanggaran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `waktu_kejadian` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_cheat_exam_student` (`exam_id`,`student_id`),
          KEY `idx_cheat_student_exam` (`student_id`,`exam_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'cbt_online_sessions' => "
        CREATE TABLE IF NOT EXISTS `cbt_online_sessions` (
          `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
          `user_id` int NOT NULL,
          `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
          `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`session_id`),
          KEY `idx_role_last_seen` (`role`,`last_seen`),
          KEY `idx_last_seen` (`last_seen`),
          KEY `idx_user_role` (`user_id`,`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'cbt_activity_logs' => "
        CREATE TABLE IF NOT EXISTS `cbt_activity_logs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int DEFAULT NULL,
          `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'System',
          `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
          `activity` text COLLATE utf8mb4_unicode_ci NOT NULL,
          `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'umum',
          `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_role` (`role`),
          KEY `idx_log_type_created` (`type`,`created_at`),
          KEY `idx_log_user_id` (`user_id`),
          KEY `idx_log_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'cbt_backup_logs' => "
        CREATE TABLE IF NOT EXISTS `cbt_backup_logs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `file_size` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

foreach ($coreTables as $tName => $ddl) {
    if (!table_exists($pdo, $tName)) {
        $pdo->exec($ddl);
        out("  -> [DIBUAT] Tabel: $tName", COLOR_GREEN);
        $appliedMigrations++;
    } else {
        out("  -> [OK] Tabel: $tName (Tersedia)", COLOR_BLUE);
    }
}

// 2. Pembaruan Kolom-kolom Baru
$columnUpdates = [
    [
        'table'      => 'cbt_students',
        'column'     => 'status',
        'alter'      => "ALTER TABLE `cbt_students` ADD `status` ENUM('aktif','alumni','nonaktif') NOT NULL DEFAULT 'aktif' AFTER `is_aktif`",
        'description'=> 'Kolom status aktif/alumni siswa'
    ],
    [
        'table'      => 'cbt_students',
        'column'     => 'foto',
        'alter'      => "ALTER TABLE `cbt_students` ADD `foto` VARCHAR(255) NULL DEFAULT NULL AFTER `nama_lengkap`",
        'description'=> 'Kolom foto profil siswa'
    ],
    [
        'table'      => 'cbt_bank_soal',
        'column'     => 'jenjang',
        'alter'      => "ALTER TABLE `cbt_bank_soal` ADD `jenjang` ENUM('10','11','12') NULL DEFAULT NULL AFTER `subject_id`",
        'description'=> 'Kolom jenjang kelas bank soal'
    ],
    [
        'table'      => 'cbt_exams',
        'column'     => 'distribusi_soal',
        'alter'      => "ALTER TABLE `cbt_exams` ADD `distribusi_soal` JSON NULL DEFAULT NULL AFTER `acak_opsi`",
        'description'=> 'Kolom distribusi kuota jenis soal'
    ],
    [
        'table'      => 'cbt_exam_participants',
        'column'     => 'soal_ids',
        'alter'      => "ALTER TABLE `cbt_exam_participants` ADD `soal_ids` LONGTEXT NULL DEFAULT NULL AFTER `status`",
        'description'=> 'Kolom cache urutan soal peserta'
    ],
    [
        'table'      => 'cbt_exam_participants',
        'column'     => 'class_id',
        'alter'      => "ALTER TABLE `cbt_exam_participants` ADD `class_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `student_id`",
        'description'=> 'Kolom snapshot kelas peserta saat ujian (Historical Immobility)'
    ],
    [
        'table'      => 'cbt_display_tokens',
        'column'     => 'short_code',
        'alter'      => "ALTER TABLE `cbt_display_tokens` ADD `short_code` VARCHAR(12) NULL DEFAULT NULL AFTER `token_hash`",
        'description'=> 'Kolom short_code URL publik'
    ]
];

foreach ($columnUpdates as $col) {
    if (table_exists($pdo, $col['table'])) {
        if (!column_exists($pdo, $col['table'], $col['column'])) {
            try {
                $pdo->exec($col['alter']);
                out("  -> [DITAMBAHKAN] {$col['table']}.{$col['column']} ({$col['description']})", COLOR_GREEN);
                $appliedMigrations++;
            } catch (PDOException $e) {
                out("  -> [GAGAL] {$col['table']}.{$col['column']}: " . $e->getMessage(), COLOR_RED);
            }
        } else {
            out("  -> [OK] Kolom {$col['table']}.{$col['column']} (Tersedia)", COLOR_BLUE);
        }
    }
}

// 3. Penambahan Indeks Skalabilitas 10.000 Peserta (High-Concurrency Optimization)
$indexUpdates = [
    ['table' => 'cbt_exam_participants', 'index' => 'idx_exam_status',             'sql' => "ALTER TABLE `cbt_exam_participants` ADD INDEX `idx_exam_status` (`exam_id`, `status`)"],
    ['table' => 'cbt_exam_participants', 'index' => 'idx_student_exam',            'sql' => "ALTER TABLE `cbt_exam_participants` ADD INDEX `idx_student_exam` (`student_id`, `exam_id`)"],
    ['table' => 'cbt_exam_participants', 'index' => 'idx_ep_class_id',             'sql' => "ALTER TABLE `cbt_exam_participants` ADD INDEX `idx_ep_class_id` (`class_id`)"],
    ['table' => 'cbt_exam_participants', 'index' => 'idx_ep_exam_student_status',  'sql' => "ALTER TABLE `cbt_exam_participants` ADD INDEX `idx_ep_exam_student_status` (`exam_id`, `student_id`, `status`)"],
    ['table' => 'cbt_exam_participants', 'index' => 'idx_ep_exam_status_waktu',     'sql' => "ALTER TABLE `cbt_exam_participants` ADD INDEX `idx_ep_exam_status_waktu` (`exam_id`, `status`, `waktu_mulai`)"],
    ['table' => 'cbt_student_answers',   'index' => 'idx_part_quest',               'sql' => "ALTER TABLE `cbt_student_answers` ADD INDEX `idx_part_quest` (`participant_id`, `question_id`)"],
    ['table' => 'cbt_student_answers',   'index' => 'idx_ans_part_quest_ragu',      'sql' => "ALTER TABLE `cbt_student_answers` ADD INDEX `idx_ans_part_quest_ragu` (`participant_id`, `question_id`, `is_ragu`)"],
    ['table' => 'cbt_question_options',  'index' => 'idx_opt_qid_label',            'sql' => "ALTER TABLE `cbt_question_options` ADD INDEX `idx_opt_qid_label` (`question_id`, `label`(50))"],
    ['table' => 'cbt_device_locks',      'index' => 'idx_student_device',           'sql' => "ALTER TABLE `cbt_device_locks` ADD INDEX `idx_student_device` (`student_id`, `device_id`)"],
    ['table' => 'cbt_cheat_logs',        'index' => 'idx_part_cheat',               'sql' => "ALTER TABLE `cbt_cheat_logs` ADD INDEX `idx_part_cheat` (`exam_id`, `student_id`)"],
    ['table' => 'cbt_cheat_logs',        'index' => 'idx_cheat_exam_student_waktu', 'sql' => "ALTER TABLE `cbt_cheat_logs` ADD INDEX `idx_cheat_exam_student_waktu` (`exam_id`, `student_id`, `waktu_kejadian`)"],
    ['table' => 'cbt_online_sessions',   'index' => 'idx_sess_role_seen',           'sql' => "ALTER TABLE `cbt_online_sessions` ADD INDEX `idx_sess_role_seen` (`role`, `last_seen`)"]
];

foreach ($indexUpdates as $idx) {
    if (table_exists($pdo, $idx['table'])) {
        if (!index_exists($pdo, $idx['table'], $idx['index'])) {
            try {
                $pdo->exec($idx['sql']);
                out("  -> [INDEX DIBUAT] {$idx['table']}.{$idx['index']}", COLOR_GREEN);
                $appliedMigrations++;
            } catch (PDOException $e) {
                out("  -> [INDEX NOTICE] " . $e->getMessage(), COLOR_YELLOW);
            }
        } else {
            out("  -> [OK] Indeks {$idx['table']}.{$idx['index']} (Tersedia)", COLOR_BLUE);
        }
    }
}

// 4. Backfill Data Snapshot Kelas untuk Peserta Ujian Lama
if (table_exists($pdo, 'cbt_exam_participants') && column_exists($pdo, 'cbt_exam_participants', 'class_id')) {
    try {
        $stmtBackfill = $pdo->exec("
            UPDATE cbt_exam_participants p
            JOIN cbt_students s ON p.student_id = s.id
            SET p.class_id = s.class_id
            WHERE p.class_id IS NULL OR p.class_id = 0
        ");
        if ($stmtBackfill > 0) {
            out("  -> [BACKFILL] Berhasil menyinkronkan snapshot class_id pada {$stmtBackfill} riwayat peserta.", COLOR_GREEN);
        } else {
            out("  -> [OK] Seluruh riwayat peserta telah memiliki snapshot class_id.", COLOR_BLUE);
        }
    } catch (PDOException $e) {
        out("  -> [BACKFILL NOTICE] " . $e->getMessage(), COLOR_YELLOW);
    }
}

// ============================================================================
// STEP 4: VERIFIKASI FOLDER & PERIZINAN FILE
// ============================================================================
out_step(4, "VERIFIKASI DIREKTORI & PERIZINAN PENYIMPANAN");

$requiredDirs = [
    CLI_ROOT . '/assets/uploads',
    CLI_ROOT . '/assets/uploads/foto_siswa',
    CLI_ROOT . '/assets/uploads/bank_soal',
    CLI_ROOT . '/assets/uploads/logo',
    CLI_ROOT . '/backups',
    CLI_ROOT . '/logs'
];

foreach ($requiredDirs as $dir) {
    $rel = str_replace(CLI_ROOT . DIRECTORY_SEPARATOR, '', $dir);
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0775, true)) {
            out("  -> [DIBUAT] Direktori: $rel", COLOR_GREEN);
        } else {
            out("  -> [GAGAL] Gagal membuat direktori: $rel", COLOR_RED);
        }
    } else {
        out("  -> [OK] Direktori: $rel (Tersedia)", COLOR_BLUE);
    }

    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($dir, 0775);
    }
}

// ============================================================================
// STEP 5: PEMBERSIHAN CACHE & VERIFIKASI KESEHATAN SISTEM
// ============================================================================
out_step(5, "PEMBERSIHAN CACHE & DIAGNOSTIK KESEHATAN SISTEM");

if (function_exists('opcache_reset')) {
    if (@opcache_reset()) {
        out("[✓] OPcache berhasil di-reset.", COLOR_GREEN);
    } else {
        out("[!] OPcache aktif tetapi tidak dapat di-reset dari CLI.", COLOR_YELLOW);
    }
} else {
    out("[i] OPcache CLI tidak aktif.", COLOR_BLUE);
}

$criticalChecks = [
    "Tabel cbt_students"           => "SELECT 1 FROM cbt_students LIMIT 1",
    "Kolom status di cbt_students" => "SELECT status FROM cbt_students LIMIT 1",
    "Tabel cbt_device_locks"       => "SELECT 1 FROM cbt_device_locks LIMIT 1",
    "Tabel cbt_cheat_logs"         => "SELECT 1 FROM cbt_cheat_logs LIMIT 1",
    "Tabel cbt_display_tokens"     => "SELECT 1 FROM cbt_display_tokens LIMIT 1",
    "Tabel cbt_online_sessions"    => "SELECT 1 FROM cbt_online_sessions LIMIT 1",
    "Tabel cbt_exam_participants"  => "SELECT 1 FROM cbt_exam_participants LIMIT 1",
    "Tabel cbt_questions"          => "SELECT 1 FROM cbt_questions LIMIT 1"
];

$allHealthy = true;
foreach ($criticalChecks as $label => $query) {
    try {
        $pdo->query($query);
        out("  -> [PASS] $label", COLOR_GREEN);
    } catch (PDOException $e) {
        out("  -> [FAIL] $label: " . $e->getMessage(), COLOR_RED);
        $allHealthy = false;
    }
}

// ============================================================================
// RINGKASAN AKHIR
// ============================================================================
echo "\n" . str_repeat("═", 75) . "\n";
if ($allHealthy) {
    out(">>> HASIL: PROSES PULL & MIGRASI SELESAI 100% SUKSES & SISTEM SEHAT! <<<", COLOR_GREEN . COLOR_BOLD);
    out("Aplikasi CBT Nova telah mutakhir dan siap melayani ujian skala penuh.", COLOR_GREEN);
} else {
    out(">>> HASIL: SELESAI DENGAN CATATAN (Beberapa tabel memerlukan perhatian) <<<", COLOR_YELLOW . COLOR_BOLD);
}
out("Waktu Selesai: " . date('Y-m-d H:i:s T') . "\n", COLOR_BLUE);
exit($allHealthy ? 0 : 1);
