# CBT Nova — Performance Improvement Plan

> **Konteks:** Saat ujian berlangsung dengan 706 siswa aktif, CPU load server mencapai ~47%.
> Analisis menunjukkan beberapa bottleneck di sisi DB query, polling interval, PHP runtime, dan aset statis.

---

## Ringkasan Eksekutif

| Area | Masalah Utama | Estimasi Dampak |
|---|---|---|
| Database | `DATE()` membunuh index; index belum applied di prod | Tinggi |
| Polling | Resync timer tiap 10s; system-info tiap 3s | Tinggi |
| Session Scan | Baca ratusan file disk tiap 3s | Tinggi |
| Query | 6 query per simpan jawaban; nav 4 query per soal | Sedang-Tinggi |
| PHP Runtime | Tidak ada OPcache | Sedang-Tinggi |
| Frontend | KaTeX ~900KB selalu di-load; aset tidak di-cache | Rendah-Sedang |

---

## Phase 1 — SQL: Database Indexes

**File:** [`sql/performance_improvements.sql`](../sql/performance_improvements.sql)

**Jalankan di server production sebelum phase lain.**

### Yang dilakukan:
1. Diagnostic: cek index yang sudah ada di production
2. Apply semua index dari backup yang mungkin belum terapply
3. Tambah composite index baru: `(teacher_id, mulai_pada)` di `cbt_exams`
4. Verify hasil akhir

### Referensi file kode:
- `guru/monitoring/fetch_data.php:45` — query `WHERE mulai_pada BETWEEN AND teacher_id`
- `siswa/ajax_save_jawaban.php:32-76` — query deadline + participant
- `siswa/ajax_get_nav.php:44-51` — query status jawaban semua soal

### Cara eksekusi:
```bash
mysql -u root -p cbt_nova < sql/performance_improvements.sql
```

### Verification:
```sql
SHOW INDEX FROM cbt_student_answers;   -- harus ada idx_ans_part_question
SHOW INDEX FROM cbt_exam_participants; -- harus ada idx_part_exam_student
SHOW INDEX FROM cbt_exams;             -- harus ada idx_exams_teacher_mulai
```

---

## Phase 2 — Fix `DATE()` → `BETWEEN` (Hot-Path Files)

**Dampak:** MySQL tidak bisa pakai index jika kolom dibungkus fungsi `DATE()`.
Fungsi ini dipakai di file yang dipanggil setiap polling (10 detik).

### File yang diubah:

| File | Baris | Tipe Fix |
|---|---|---|
| `admin/ajax-dashboard.php` | 22, 32, 42 | `DATE(e.mulai_pada) = ?` → `BETWEEN` |
| `admin/index.php` | 28, 34, 43 | `DATE(e.mulai_pada) = ?` → `BETWEEN` |
| `siswa/ajax/view_dashboard.php` | 32 | `CURDATE()` hardcoded → BETWEEN param |
| `admin/monitoring/fetch_mapel.php` | ~10 | `DATE(mulai_pada) = ?` → `BETWEEN` |
| `guru/monitoring/fetch_mapel.php` | ~12 | `DATE(mulai_pada) = ?` → `BETWEEN` |

### Pattern fix (untuk file dengan parameter binding):
```php
// SEBELUM (di atas file):
$today = date('Y-m-d');

// SESUDAH:
$today       = date('Y-m-d');
$today_start = $today . ' 00:00:00';
$today_end   = $today . ' 23:59:59';

// SEBELUM (di query):
AND DATE(e.mulai_pada) = ?
// execute([$today])

// SESUDAH:
AND e.mulai_pada BETWEEN ? AND ?
// execute([$today_start, $today_end])
```

### Pattern fix untuk `CURDATE()` hardcoded (`siswa/ajax/view_dashboard.php:32`):
```php
// SEBELUM:
AND DATE(e.mulai_pada) = CURDATE()
$stmtExams->execute([$student_id]);

// SESUDAH:
AND e.mulai_pada BETWEEN ? AND ?
$stmtExams->execute([
    $student_id,
    date('Y-m-d') . ' 00:00:00',
    date('Y-m-d') . ' 23:59:59'
]);
```

### Anti-pattern:
- JANGAN: `WHERE DATE(kolom) = ?`
- JANGAN: `WHERE YEAR(kolom) = ? AND MONTH(kolom) = ?`
- OK: `WHERE kolom BETWEEN '2024-01-01 00:00:00' AND '2024-01-01 23:59:59'`

### Verification:
```bash
grep -n "DATE(" admin/ajax-dashboard.php admin/index.php \
     siswa/ajax/view_dashboard.php \
     admin/monitoring/fetch_mapel.php guru/monitoring/fetch_mapel.php
# Harus tidak ada hasil
```

---

## Phase 3 — Kurangi Polling Interval

### `siswa/ajax/view_ujian.php` baris 199
```javascript
// DARI:
_resyncInterval = setInterval(function() { ... }, 10000);
// JADI:
_resyncInterval = setInterval(function() { ... }, 30000);
```
**Dampak:** 706 siswa × dari 6 req/mnt → 2 req/mnt = hemat ~470 DB query/menit.

### `admin/sistem/system-info.php` baris 403
```javascript
// DARI:
setInterval(fetchData, 3000);
// JADI:
setInterval(fetchData, 5000);
```

### Verification:
```bash
grep -n "10000" siswa/ajax/view_ujian.php    # harus tidak ada, diganti 30000
grep -n "3000"  admin/sistem/system-info.php # harus tidak ada, diganti 5000
```
Di browser DevTools → Network: konfirmasi interval request yang baru.

---

## Phase 4 — Ganti Session File Scan dengan Query DB

**File:** `admin/sistem/system-info-data.php`

**Masalah:** Fungsi `getActiveUsers()` baris 133-161 membaca semua file session
(`glob()` + `file_get_contents()` per file) setiap 3 detik → I/O intensif.

### Ganti seluruh blok glob/scan dengan:
```php
function getActiveUsers(PDO $pdo): array {
    $windowSec  = 900;
    $cutoffTime = date('Y-m-d H:i:s', time() - $windowSec);

    $stmt = $pdo->prepare("
        SELECT role, COUNT(DISTINCT user_id) AS jumlah
        FROM cbt_activity_logs
        WHERE created_at >= ?
          AND role IN ('siswa', 'guru', 'admin')
        GROUP BY role
    ");
    $stmt->execute([$cutoffTime]);

    $counts = ['siswa' => 0, 'guru' => 0, 'admin' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['role']] = (int)$row['jumlah'];
    }

    $activeSiswa = $counts['siswa'];
    $activeGuru  = $counts['guru'];
    $activeAdmin = $counts['admin'];

    $totalActive = $activeSiswa + $activeGuru + $activeAdmin;

    // Ambil total terdaftar (tetap sama seperti sebelumnya)
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
```

### Verification:
- Panel "Pengguna Login Aktif" masih menampilkan angka
- Tidak ada `glob()` atau `file_get_contents` tersisa di fungsi ini
- `EXPLAIN` query pakai index `idx_log_created_at`

---

## Phase 5 — Gabungkan Query di `ajax_save_jawaban.php`

**File:** `siswa/ajax_save_jawaban.php`

**Masalah:** Query deadline (baris 32-41) dan query partisipasi (baris 73-76) adalah
2 round-trip ke DB yang bisa jadi 1.

### Ganti kedua query awal dengan 1 query gabungan:
```php
$stmtCombined = $pdo->prepare("
    SELECT
        p.id             AS participant_id,
        p.status,
        p.waktu_mulai,
        p.tambahan_waktu,
        e.durasi_menit,
        e.selesai_pada,
        NOW()            AS now_db
    FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.exam_id = ? AND p.student_id = ?
");
$stmtCombined->execute([$exam_id, $student_id]);
$row = $stmtCombined->fetch();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi ujian tidak ditemukan']);
    exit;
}

// Validasi deadline (dari $row)
$waktu_mulai = strtotime($row['waktu_mulai']);
$now         = strtotime($row['now_db']);
// ... sisa logika deadline sama

// Validasi status (dari $row yang sama)
if ($row['status'] === 'finished') { ... }
if ($row['status'] === 'blocked')  { ... }

$participant_id = $row['participant_id'];
```

### Verification:
- Test simpan jawaban PG, kompleks, menjodohkan, isian — semua sukses
- Test jawab setelah waktu habis → `timeout`
- Test akun blocked → `blocked`

---

## Phase 6 — Cache Nav di Session (`ajax_get_nav.php`)

**File:** `siswa/ajax_get_nav.php`

**Masalah:** Setiap call (per pindah soal + per simpan jawaban) jalankan 3 query +
`seeded_shuffle()` dengan seed yang sama → hasil identik, tidak perlu dihitung ulang.

### Tambahkan session cache setelah validasi role:
```php
$cache_key = "nav_{$exam_id}_{$student_id}";

if (!isset($_SESSION[$cache_key])) {
    $stmtPart = $pdo->prepare("SELECT id FROM cbt_exam_participants WHERE exam_id = ? AND student_id = ?");
    $stmtPart->execute([$exam_id, $student_id]);
    $part = $stmtPart->fetch();
    if (!$part) { echo json_encode(['html' => '', 'total' => 0]); exit; }

    $stmtExam = $pdo->prepare("SELECT acak_soal FROM cbt_exams WHERE id = ?");
    $stmtExam->execute([$exam_id]);
    $exam = $stmtExam->fetch();

    $stmtAllQ = $pdo->prepare("SELECT question_id FROM cbt_exam_questions WHERE exam_id = ? ORDER BY id ASC");
    $stmtAllQ->execute([$exam_id]);
    $ids = $stmtAllQ->fetchAll(PDO::FETCH_COLUMN);

    if ((int)($exam['acak_soal'] ?? 0) && count($ids) > 1) {
        seeded_shuffle($ids, crc32($student_id . '_exam_' . $exam_id));
    }

    $_SESSION[$cache_key] = [
        'participant_id' => (int)$part['id'],
        'ids'            => $ids,
    ];
}

$cached         = $_SESSION[$cache_key];
$participant_id = $cached['participant_id'];
$all_question_ids = $cached['ids'];

// Lanjut ke query status jawaban saja (1 query tersisa)
```

**Catatan penting:** Cache ini aman karena:
- `participant_id` tidak berubah selama sesi
- Urutan soal (`seeded_shuffle`) deterministik — seed sama, hasil sama
- Yang berubah (status jawaban/ragu) masih di-fetch fresh dari DB

### Verification:
- Navigasi bolak-balik soal: urutan tetap konsisten
- Status answered/ragu update benar setelah jawab
- Tidak ada error session overflow

---

## Phase 7 — OPcache Docker

**File baru:** `docker/php-apache/opcache.ini`
**File diubah:** `docker/php-apache/Dockerfile`

### `docker/php-apache/opcache.ini`:
```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.validate_timestamps=1
```

### Tambahkan di `Dockerfile` (setelah `docker-php-ext-install`):
```dockerfile
&& docker-php-ext-install mysqli pdo pdo_mysql zip gd opcache \
```
```dockerfile
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
```

### Verification:
```bash
docker compose build && docker compose up -d
# Buka browser → /info.php (buat sementara berisi phpinfo())
# Cari: "OPcache" → Opcode caching: Enabled
```

---

## Phase 8 — Cache Header Aset Statis (`.htaccess`)

**File:** `.htaccess`

### Tambahkan setelah blok RewriteRule yang ada:
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css               "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
    ExpiresByType image/png              "access plus 1 month"
    ExpiresByType image/jpeg             "access plus 1 month"
    ExpiresByType image/gif              "access plus 1 month"
    ExpiresByType image/svg+xml          "access plus 1 month"
    ExpiresByType application/font-woff2 "access plus 3 months"
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "\.(css|js|png|jpg|gif|svg|woff2)$">
        Header set Cache-Control "public, max-age=604800"
    </FilesMatch>
</IfModule>
```

### Verification:
- DevTools → Network → reload → file CSS/JS lokal ada header `Cache-Control`
- Reload kedua: status `304 Not Modified` untuk aset statis

---

## Phase 9 — Kurangi `log_activity` dari Page Load

**Masalah:** Setiap kali guru membuka halaman, ada INSERT ke `cbt_activity_logs`
yang tidak memberikan nilai diagnostik berarti.

### Hapus dari:
- `guru/hasil/index.php:10` — `log_activity('Akses halaman Hasil Ujian', ...)`
- `guru/monitoring/index.php:10` — `log_activity('Akses halaman Monitoring Peserta', ...)`

### Pertahankan log di:
- `auth/proses-login.php` — login berhasil/gagal
- `auth/logout.php` — logout
- `siswa/proses_mulai_ujian.php` — mulai ujian
- `siswa/proses_selesai_ujian.php` — selesai ujian
- `siswa/ajax_cheat_log.php` — pelanggaran

### Verification:
```bash
grep -n "log_activity" guru/hasil/index.php guru/monitoring/index.php
# Harus tidak ada hasil
```

---

## Phase 10 — Lazy-Load KaTeX

**File:** `siswa/index.php`

**Masalah:** KaTeX (~900KB JS + CSS) di-load untuk semua siswa meskipun
soal mereka tidak mengandung formula matematika.

### Hapus dari `<head>`:
```html
<!-- HAPUS baris ini: -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
```

### Hapus dari sebelum `</body>`:
```html
<!-- HAPUS baris ini: -->
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
```

### Ganti fungsi `renderMath()` dengan versi lazy-load:
```javascript
var _katexLoaded  = false;
var _katexLoading = false;
var _katexQueue   = [];

function renderMath(el) {
    if (!el) return;
    if (!el.textContent.includes('$')) return; // tidak ada formula, skip

    if (_katexLoaded) {
        renderMathInElement(el, {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$',  right: '$',  display: false }
            ],
            throwOnError: false
        });
        return;
    }

    _katexQueue.push(el);
    if (_katexLoading) return;
    _katexLoading = true;

    var css = document.createElement('link');
    css.rel  = 'stylesheet';
    css.href = 'https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css';
    document.head.appendChild(css);

    var s1 = document.createElement('script');
    s1.src = 'https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js';
    s1.onload = function() {
        var s2 = document.createElement('script');
        s2.src = 'https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js';
        s2.onload = function() {
            _katexLoaded = true;
            var opts = {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$',  right: '$',  display: false }
                ],
                throwOnError: false
            };
            _katexQueue.forEach(function(qEl) { renderMathInElement(qEl, opts); });
            _katexQueue = [];
        };
        document.head.appendChild(s2);
    };
    document.head.appendChild(s1);
}
```

### Verification:
- Soal tanpa formula `$`: Network tab tidak ada request `katex.min.js`
- Soal dengan formula: KaTeX ter-load dan render benar
- Tidak ada JS error di console

---

## Phase 11 — Final Verification

### Grep checks:
```bash
# 1. Tidak ada DATE() di hot-path files
grep -rn "DATE(" admin/ajax-dashboard.php admin/index.php \
     siswa/ajax/view_dashboard.php \
     admin/monitoring/fetch_mapel.php guru/monitoring/fetch_mapel.php

# 2. Polling interval sudah diubah
grep -n "10000" siswa/ajax/view_ujian.php
grep -n "3000"  admin/sistem/system-info.php

# 3. OPcache di Dockerfile
grep "opcache" docker/php-apache/Dockerfile

# 4. Tidak ada log_activity page load di guru
grep -n "Akses halaman" guru/hasil/index.php guru/monitoring/index.php

# 5. Tidak ada glob/file_get_contents di system-info-data.php
grep -n "glob\|file_get_contents" admin/sistem/system-info-data.php
```

### Fungsional test:
- [ ] Login siswa → dashboard muncul, daftar ujian tampil
- [ ] Mulai ujian → soal muncul, timer berjalan, jawaban tersimpan
- [ ] Pindah soal → nav update benar
- [ ] Selesai ujian → skor tersimpan, redirect dashboard
- [ ] Login guru → halaman monitoring tampil, data real-time
- [ ] Login admin → dashboard + system info tampil angka benar
- [ ] System info "Pengguna Aktif" menampilkan angka (bukan 0)

---

## Urutan Eksekusi yang Disarankan

```
Phase 1 (SQL)  →  Phase 7 (OPcache Docker rebuild)  →  Phase 2 (DATE fix)
→  Phase 3 (Interval)  →  Phase 4 (Session scan)  →  Phase 5 (Query merge)
→  Phase 6 (Nav cache)  →  Phase 8 (.htaccess)  →  Phase 9 (log_activity)
→  Phase 10 (KaTeX)  →  Phase 11 (Verify all)
```

> **Catatan:** Phase 1 dan 7 bisa dilakukan tanpa downtime.
> Phase 2-6 adalah perubahan PHP — deploy bisa dilakukan satu per satu.
