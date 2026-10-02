<?php
/**
 * Migration runner — menjalankan file .sql dari folder sql/.
 * Status migrasi dilacak di tabel cbt_migrations.
 *
 * Cara pakai:
 *   docker exec cbt_nova_web php /var/www/html/migrate.php
 *   docker exec cbt_nova_web php /var/www/html/migrate.php --status
 *   docker exec cbt_nova_web php /var/www/html/migrate.php --seed
 *   docker exec cbt_nova_web php /var/www/html/migrate.php --force=scoring_improvement.sql
 */

// Agar config/database.php tidak error saat dijalankan via CLI
if (PHP_SAPI === 'cli') {
    $_SERVER['HTTP_HOST']   = 'localhost';
    $_SERVER['REQUEST_URI'] = '/';
}
require_once __DIR__ . '/config/database.php';
// DB_HOST, DB_NAME, DB_USER, DB_PASS kini tersedia dari config

// Buat koneksi PDO tersendiri untuk migrasi:
// - emulate_prepares=true  → cegah "unbuffered queries active" pada DDL multi-statement
// - buffered_query=true    → hasil SELECT di-buffer otomatis sebelum statement berikutnya
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES         => true,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]
    );
} catch (PDOException $e) {
    echo "\033[31mDB connection failed: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function color(string $text, string $code): string {
    return PHP_SAPI === 'cli' ? "\033[{$code}m{$text}\033[0m" : $text;
}
function ok(string $s): string   { return color($s, '32'); }
function warn(string $s): string { return color($s, '33'); }
function err(string $s): string  { return color($s, '31'); }
function dim(string $s): string  { return color($s, '2');  }
function bold(string $s): string { return color($s, '1');  }
function out(string $line): void { echo $line . PHP_EOL;   }

/**
 * Parse file SQL menjadi array statement.
 * Mendukung DELIMITER $$ untuk stored procedure.
 */
function parse_statements(string $sql): array {
    $statements = [];
    $delimiter  = ';';
    $buffer     = '';

    foreach (explode("\n", $sql) as $line) {
        // Tangani direktif DELIMITER (perintah MySQL CLI)
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m)) {
            if (trim($buffer) !== '') {
                $stmt = trim($buffer);
                if (!is_comment_only($stmt)) $statements[] = $stmt;
                $buffer = '';
            }
            $delimiter = $m[1];
            continue;
        }

        $buffer .= $line . "\n";

        // Cek apakah baris ini mengakhiri statement dengan delimiter saat ini
        if (str_ends_with(rtrim($line), $delimiter)) {
            // Buang delimiter dari akhir buffer
            $pos  = strrpos($buffer, $delimiter);
            $stmt = trim(substr($buffer, 0, $pos));
            if ($stmt !== '' && !is_comment_only($stmt)) {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }

    // Flush sisa buffer (statement tanpa delimiter di akhir file)
    if (trim($buffer) !== '' && !is_comment_only(trim($buffer))) {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function is_comment_only(string $sql): bool {
    $stripped = preg_replace('/--[^\n]*\n?|\/\*.*?\*\//su', '', $sql);
    return trim($stripped) === '';
}

// ── Bootstrap: buat tabel tracker jika belum ada ──────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS cbt_migrations (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration    VARCHAR(255) NOT NULL UNIQUE,
        applied_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Parse argumen ──────────────────────────────────────────────────────────────

$mode_status = in_array('--status', $argv ?? [], true);
$mode_seed   = in_array('--seed',   $argv ?? [], true);
$force_file  = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--force=')) {
        $force_file = basename(substr($arg, 8));
    }
}

// ── Kumpulkan file .sql (urut alfabet) ────────────────────────────────────────

$sql_dir = __DIR__ . '/sql';
$files   = glob($sql_dir . '/*.sql') ?: [];
sort($files);

if (empty($files)) {
    out(warn('Tidak ada file .sql ditemukan di ' . $sql_dir));
    exit(0);
}

// ── Ambil daftar migrasi yang sudah diterapkan ────────────────────────────────

$applied = $pdo->query("SELECT migration, applied_at FROM cbt_migrations ORDER BY applied_at")
               ->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Mode --status ──────────────────────────────────────────────────────────────

if ($mode_status) {
    out(bold('Status Migrasi:'));
    out(str_repeat('─', 60));
    foreach ($files as $path) {
        $name = basename($path);
        if (isset($applied[$name])) {
            out(ok('  [✓] ' . $name) . dim('  — ' . $applied[$name]));
        } else {
            out(warn('  [ ] ' . $name) . dim('  — belum diterapkan'));
        }
    }
    out(str_repeat('─', 60));
    $done    = count(array_intersect_key($applied, array_flip(array_map('basename', $files))));
    $pending = count($files) - $done;
    out("  Applied: " . ok($done) . "  |  Pending: " . ($pending ? warn($pending) : $pending));
    exit(0);
}

// ── Mode --seed ────────────────────────────────────────────────────────────────
// Tandai semua file sebagai applied tanpa menjalankannya.
// Gunakan saat migrasi sudah diterapkan manual sebelum runner ini ada.

if ($mode_seed) {
    out(bold('Seed migrasi (tandai semua sebagai applied):'));
    out(str_repeat('─', 60));
    $stmt = $pdo->prepare("INSERT IGNORE INTO cbt_migrations (migration) VALUES (?)");
    foreach ($files as $path) {
        $name = basename($path);
        if (isset($applied[$name])) {
            out(dim("  skip  $name  (sudah tercatat)"));
        } else {
            $stmt->execute([$name]);
            out(ok("  seed  $name"));
        }
    }
    out(str_repeat('─', 60));
    out(ok('Seed selesai.'));
    exit(0);
}

// ── Mode --force ───────────────────────────────────────────────────────────────

if ($force_file !== null) {
    $target = $sql_dir . '/' . $force_file;
    if (!file_exists($target)) {
        out(err("File tidak ditemukan: $force_file"));
        exit(1);
    }
    $files = [$target];
    $pdo->prepare("DELETE FROM cbt_migrations WHERE migration = ?")->execute([$force_file]);
    unset($applied[$force_file]);
    out(warn("--force: $force_file akan dijalankan ulang."));
}

// ── Jalankan migrasi pending ───────────────────────────────────────────────────

out(bold('CBT Nova — Migration Runner'));
out(str_repeat('─', 60));

$ran     = 0;
$skipped = 0;
$errors  = 0;

foreach ($files as $path) {
    $name = basename($path);

    if (isset($applied[$name])) {
        out(dim("  skip  $name"));
        $skipped++;
        continue;
    }

    out("  run   " . bold($name) . ' ...');

    $sql = file_get_contents($path);
    if ($sql === false) {
        out(err("    Gagal membaca file."));
        $errors++;
        continue;
    }

    $statements  = parse_statements($sql);
    $file_errors = 0;

    foreach ($statements as $stmt) {
        try {
            // query() + closeCursor() agar result set SELECT (diagnostic/log)
            // selalu di-consume sebelum statement berikutnya dijalankan.
            $result = $pdo->query($stmt);
            if ($result instanceof PDOStatement) {
                $result->closeCursor();
            }
        } catch (PDOException $e) {
            out(err("    ✗ " . $e->getMessage()));
            $file_errors++;
        }
    }

    if ($file_errors === 0) {
        $pdo->prepare("INSERT INTO cbt_migrations (migration) VALUES (?)")->execute([$name]);
        out(ok("    ✓ selesai."));
        $ran++;
    } else {
        $errors++;
    }
}

out(str_repeat('─', 60));
out("  Ran: " . ok($ran) . "  |  Skipped: " . dim($skipped) . "  |  Errors: " . ($errors ? err($errors) : $errors));
exit($errors > 0 ? 1 : 0);
