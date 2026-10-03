# CBT Nova — Master Agent Guidelines, Engineering Rules & UI/UX Design System (SSOT)

Panduan kerja, standar arsitektur, kepatuhan keamanan, tata kelola Git, struktur direktori, dan sistem desain antarmuka terpadu (Single Source of Truth) untuk AI Agent dalam memelihara, merefaktorisasi, dan mengembangkan codebase **CBT Nova** (`C:\laragon\www\cbt_nova`).

---

## 📑 DAFTAR ISI & STRUKTUR ATURAN

1. [🛡️ Protokol Keselamatan Data Produksi (Zero-Breakage Rules)](#️-protokol-keselamatan-data-produksi-zero-breakage-rules)
2. [🚀 Strict Rule 1: Git Release Protocol & Atomic Commits Workflow](#-strict-rule-1-git-release-protocol--atomic-commits-workflow)
3. [📁 Strict Rule 2: Aturan Struktur Workspace & Larangan Folder 'docs/' di Root](#-strict-rule-2-aturan-struktur-workspace--larangan-folder-docs-di-root)
4. [🔒 Strict Rule 3: Post-Development Security Audit & Incremental Reports](#-strict-rule-3-post-development-security-audit--incremental-reports)
5. [🕒 Strict Rule 4: Implementation Plans & Walkthroughs (Dokumentasi Harian)](#-strict-rule-4-implementation-plans--walkthroughs-dokumentasi-harian)
6. [🏛️ Strict Rule 5: CBT Nova Architectural Standards (Native PHP Modular + MariaDB InnoDB)](#️-strict-rule-5-cbt-nova-architectural-standards-native-php-modular--mariadb-innodb)
7. [⚡ Strict Rule 6: Concurrency Optimization & Session Lock Mitigation](#-strict-rule-6-concurrency-optimization--session-lock-mitigation)
8. [💾 Strict Rule 7: Safe Code Cleanup & Zero Deletion Policy](#-strict-rule-7-safe-code-cleanup--zero-deletion-policy)
9. [🧪 Strict Rule 8: Mandatory Testing & Code Integrity Checklist](#-strict-rule-8-mandatory-testing--code-integrity-checklist)
10. [⏱️ Strict Rule 9: Exam Integrity, Device Lock & Anti-Cheat Guard](#️-strict-rule-9-exam-integrity-device-lock--anti-cheat-guard)
11. [📦 Strict Rule 10: Standardized JSON API & AJAX Response Contract](#-strict-rule-10-standardized-json-api--ajax-response-contract)
12. [🔑 Strict Rule 11: Environment Configuration & Credentials Protection](#-strict-rule-11-environment-configuration--credentials-protection)
13. [📎 Strict Rule 12: Secure File Uploads & Media Validation](#-strict-rule-12-secure-file-uploads--media-validation)
14. [🛑 Strict Rule 13: User-Safe Error Handling & Production Logging](#-strict-rule-13-user-safe-error-handling--production-logging)
15. [🎨 Strict Rule 14: CBT Nova Unified UI/UX Design System & Frontend Standards (WCAG 2.1 AAA Compliant)](#-strict-rule-14-cbt-nova-unified-uiux-design-system--frontend-standards-wcag-21-aaa-compliant)

---

## 🛡️ PROTOKOL KESELAMATAN DATA PRODUKSI (ZERO-BREAKAGE RULES)

Sebelum dan selama melakukan remediasi atau pengembangan fitur, patuhi batasan mutlak ini:
1. **DILARANG merusak format soal (Rich Text / MathJax / KaTeX):** Jangan pernah membungkus teks `konten_soal` atau opsi jawaban yang dirender dari database dengan fungsi `htmlspecialchars()` mentah. Hal ini akan mematahkan rumus matematika, tag gambar `<img>`, dan tabel soal.
2. **DILARANG mengubah payload/struktur jawaban siswa:** Struktur parsing jawaban di frontend maupun backend (tipe JSON, ID opsi, radio value) tidak boleh diubah agar data jawaban tersimpan siswa tetap valid dan sinkron.
3. **DILARANG merusak alur autentikasi dan session:** Variabel sesi pengerjaan ujian yang sudah berjalan tidak boleh di-reset secara paksa yang bisa memicu *kick/logout* massal siswa.
4. **Isolasi Mutlak Kunci Jawaban (`is_correct`):** Pada seluruh endpoint siswa (`siswa/ajax_get_soal.php`), query opsi wajib menggunakan proyeksi kolom eksplisit (`SELECT id, question_id, label, value_target FROM cbt_question_options`) agar kolom `is_correct` tidak pernah ditarik ke memori PHP siswa atau terekspos ke inspect element browser.
5. **Proteksi Direktori Media & Foto (`assets/uploads/`):** Seluruh folder unggahan wajib dilindungi `.htaccess` untuk menonaktifkan directory listing (`Options -Indexes`), mematikan engine PHP (`php_flag engine off`), dan memblokir eksekusi file executable/skrip.
6. **Otorisasi Ketat Anti-IDOR:** Seluruh transaksi penyimpanan jawaban (`siswa/ajax_save_jawaban.php`, `siswa/ajax_toggle_ragu.php`) wajib mengambil `student_id` dari `$_SESSION['student_id']` dan memvalidasi status partisipasi aktif (`status = 'working'`).

---

## 🚀 STRICT RULE 1: GIT RELEASE PROTOCOL & ATOMIC COMMITS WORKFLOW

Aturan baku dan protokol wajib bagi Antigravity AI ketika melakukan staging, committing, dan pushing kode ke repositori Git/GitHub di proyek **CBT Nova**:

### 📌 1. Prinsip Atomic Commits (Dilarang Monolithic Commit)
Setiap instruksi push ke GitHub **TIDAK BOLEH** menggabungkan seluruh perubahan berbeda domain ke dalam 1 commit tunggal (`git commit -am "update"` dilarang keras).

Perubahan **WAJIB DIPISAHKAN** secara modular berdasarkan domain/fitur/tujuannya:
- **1 Modul / 1 Fitur / 1 Bugfix = 1 Commit Terpisah.**
- Pengguna dapat melacak, mengaudit, dan me-revert fitur secara independen tanpa mempengaruhi fitur lain.

### 🏷️ 2. Konvensi Pesan Commit (Conventional Commits)
Gunakan format standar Conventional Commits:
`<type>(<scope>): <deskripsi singkat perubahan dalam huruf kecil>`

**Prefix Tipe Commit:**
- **`feat`**     : Penambahan fitur atau modul baru (contoh: `feat(recalculate): implement batch score recalculation`).
- **`fix`**      : Perbaikan bug fungsional, logika, scoring, atau database (contoh: `fix(scoring): upgrade multi-type scoring engine`).
- **`ui/ux`**    : Perbaikan styling CSS, kontras warna, accessibility, atau layout shift (contoh: `ui/ux(table): enhance contrast for dark badges`).
- **`perf`**     : Optimasi performa, query N+1, debounce, atau pengurangan beban server.
- **`refactor`** : Pembersihan kode atau modularisasi fungsi tanpa mengubah perilaku output.
- **`docs`**     : Pembaruan dokumentasi atau panduan Markdown.

### 🛠️ 3. SOP Workflow Sebelum & Saat Push
Sebelum melakukan commit dan push, Antigravity **WAJIB** menjalankan tahapan berikut:

#### Langkah 1: Audit Status Perubahan
```bash
git status
git diff --stat
```
Petakan seluruh file yang dimodifikasi ke dalam kelompok commit atomic yang logis.

#### Langkah 2: Verifikasi Sintaks (Zero Syntax Error Guard)
Jalankan linting sintaks PHP secara menyeluruh:
```powershell
powershell -ExecutionPolicy Bypass -File scratch/lint_check.ps1
```
Pastikan output menunjukkan **0 Syntax Error** sebelum melanjutkan.

#### Langkah 3: Staging & Commit Modular (Satu per Satu)
Lakukan staging dan commit terpisah untuk setiap kelompok file:
```bash
# Contoh Commit 1: Scoring Logic
git add includes/helpers.php siswa/proses_selesai_ujian.php
git commit -m "fix(scoring): upgrade multi-type scoring engine and preserve manual essay grades"

# Contoh Commit 2: Recalculate AJAX Endpoints & UI
git add admin/hasil/ajax/recalculate.php guru/hasil/ajax/recalculate.php admin/hasil/index.php guru/hasil/index.php
git commit -m "feat(recalculate): implement batch and individual score recalculation for admin and guru"

# Contoh Commit 3: File I/O & Export
git add admin/laporan/export_excel.php admin/master-io/*
git commit -m "fix(file-io): clean output buffers and support safe xlsx/xls import on linux"
```

#### Langkah 4: Push ke Remote Repository
```bash
git push origin HEAD
```

#### Langkah 5: Tampilkan Bukti Riwayat Git
Tampilkan riwayat commit terbaru untuk memvalidasi pemisahan commit:
```bash
git log -n <jumlah_commit> --oneline
```

---

## 📁 STRICT RULE 2: ATURAN STRUKTUR WORKSPACE & LARANGAN FOLDER 'DOCS/' DI ROOT

### 1. Aturan Utama (Strict Rule)
- **DILARANG KERAS** membuat folder `docs/` di root direktori project (`C:\laragon\www\cbt_nova\docs`).
- Seluruh file dokumentasi, panduan, laporan transformasi, audit keamanan, mockup HTML, walkthrough, maupun rencana implementasi **WAJIB** disimpan di dalam folder `scratch/docs/` (`C:\laragon\www\cbt_nova\scratch\docs/`).

### 2. Pengelolaan Dokumen & Laporan
- **Lokasi Dokumentasi Resmi & Rencana Kerja**: `scratch/docs/` (Implementation Plans, Walkthrough, Panduan).
- **Lokasi Laporan Security Audit**: **HANYA di `scratch/test_security_audit/`** dengan format inkremental `00X_security_audit_report.html` (DILARANG membuat salinan/duplikasi ke `scratch/docs/`).
- **Lokasi Script Verifikasi / Lint / Scratch**: `scratch/`
- **Wadah File Karantina / Backup Kode Lama**: `scratch/quarantine/`
- Dilarang membuat berkas temporary/scratch sembarangan di root project maupun di direktori modul produksi (`admin/`, `guru/`, `siswa/`, `auth/`, `config/`, dll.).

### 3. Pencegahan Regresi
- Setiap kali agent membuat file `.md`, `.html`, atau dokumen baru yang bersifat referensi atau laporan, pastikan path target selalu mengarah ke `scratch/docs/` (atau `scratch/test_security_audit/` untuk audit) dan TIDAK PERNAH membuat folder `docs` di root.

---

## 🔒 STRICT RULE 3: POST-DEVELOPMENT SECURITY AUDIT & INCREMENTAL REPORTS

- **WAJIB MENJALANKAN AUDIT KEAMANAN SETELAH SELESAI MODIFIKASI PROGRAM**:
  - Setiap kali selesai membuat, memodifikasi, atau merefaktorisasi fitur program/kode aplikasi, **WAJIB** mengeksekusi skrip audit keamanan bawaan:
    ```powershell
    # Eksekusi security audit runner (C:\laragon\www\cbt_nova\scratch\test_security_audit\test_security_audit.php)
    php "C:\laragon\www\cbt_nova\scratch\test_security_audit\test_security_audit.php" --target "C:\laragon\www\cbt_nova" --no-saas --output "scratch/test_security_audit/00X_security_audit_report.html"
    ```
  - **Aturan Penomoran Laporan Audit (Incremental Audit Report)**:
    1. Cek file laporan terakhir di folder `C:\laragon\www\cbt_nova\scratch\test_security_audit/`.
    2. Laporan pertama: `001_security_audit_report.html`, laporan berikutnya `002_security_audit_report.html`, `003_security_audit_report.html`, dst.
    3. **Dilarang menimpa (*overwrite*)** laporan audit sebelumnya agar riwayat dan tren peningkatan keamanan serta optimasi performa terdokumentasi rapi.
  - Periksa hasil temuan audit untuk memastikan tidak ada celah keamanan kritis: SQL Injection (wajib PDO Prepared Statements), XSS (wajib `htmlspecialchars`), CSRF bypass (wajib `csrf_verify()`), Insecure Upload, Path Traversal, dan Session Hijacking.

---

## 🕒 STRICT RULE 4: IMPLEMENTATION PLANS & WALKTHROUGHS (Dokumentasi Harian)

Setiap kali pekerjaan dimulai dan diselesaikan, agen **WAJIB SECARA OTOMATIS** mengelola dan mencatat progres ke dalam **dua berkas dokumentasi harian** di `C:\laragon\www\cbt_nova\scratch\docs\` berdasarkan tanggal lokal sistem yang sedang berjalan (`YYYY-MM-DD`):
- `C:\laragon\www\cbt_nova\scratch\docs\YYYY-MM-DD_Implementation_Plans_Harian.md`
- `C:\laragon\www\cbt_nova\scratch\docs\YYYY-MM-DD_Walkthrough_Harian.md`

**Prosedur Otomatisasi Harian:**
1. **Deteksi Tanggal Lokal**: Deteksi tanggal lokal sesi saat ini dari sistem metadata (format `YYYY-MM-DD`).
2. **Auto-Create File Baru**: Jika file harian untuk tanggal hari ini belum tersedia di `scratch/docs/`, agen **WAJIB LANGPUNGSUNG MEMBUAT** kedua file tersebut secara otomatis pada respons pertama.
3. **Pemisahan Ketat Antar-Hari**: Dilarang mencampur log pekerjaan hari ini ke file hari kemarin.
4. **Append & Penomoran Tahap Real-Time**: Setiap pekerjaan yang dieksekusi ditambahkan ke file harian dengan timestamp detail WIB (`## 🕒 [HH:MM - HH:MM WIB] Tahap X: ...`).
5. **Beban Dokumentasi**:
   - **Fitur Baru / Refactoring Besar**: Sertakan analisis, perubahan file, SQL/migrasi, test suite, dan hasil verifikasi.
   - **Perbaikan Bug Kecil / Minor Tweaks**: Gunakan format **Compact Log** (10–25 baris): *Waktu + Root Cause + Files Changed + Solution + Quick Verification Result*.

---

## 🏛️ STRICT RULE 5: CBT NOVA ARCHITECTURAL STANDARDS (Native PHP Modular + MariaDB InnoDB)

- **Tumpukan Teknologi (Technology Stack)**:
  - **Backend**: Native PHP 8.x (Modular MVC/Procedural SPA berbasis AJAX).
  - **Database**: MariaDB / MySQL 8.x (`ENGINE=InnoDB`, Database: `db_axon` / `db_cbt`, Charset: `utf8mb4`).
  - **Frontend Core**: HTML5 + Vanilla JS / jQuery 3.6 + Bootstrap 5.3 + KaTeX (Math LaTeX) + SweetAlert2.
  - **Modul Inti**:
    - `siswa/`: Portal siswa, core pengerjaan soal ujian, timer countdown, auto-save AJAX, anti-cheat screen.
    - `guru/`: Panel guru (Bank soal, jadwal ujian, koreksi esai, rekap nilai kelas).
    - `admin/`: Panel administrator (Master siswa/guru/kelas, manajemen token, monitoring proktor, backup sistem).
    - `public/`: Display publik (Leaderboard / Live Score `public/dashboard.php`).
    - `auth/`: Autentikasi terpadu (Siswa, Guru, Admin) + Device Lock.
    - `config/`: Konfigurasi terpusat (`config/database.php`).
    - `includes/`: Header, footer, sidebar, dan helper global (`helpers.php`).
    - `assets/`: Aset statis lokal (CSS, JS, gambar logo, stimulus gambar soal di `assets/uploads/soal/`).

---

## ⚡ STRICT RULE 6: CONCURRENCY OPTIMIZATION & SESSION LOCK MITIGATION

- **Wajib Memanggil `session_write_close()`**:
  - Pada seluruh endpoint AJAX yang dipanggil berulang, berkala, atau beruntun oleh siswa (`siswa/ajax_get_soal.php`, `siswa/ajax_save_jawaban.php`, `siswa/ajax_get_nav.php`, `siswa/ajax_get_waktu.php`, `siswa/ajax_cheat_log.php`), **WAJIB memanggil `session_write_close()`** segera setelah data session selesai dibaca di awal script.
  - Hal ini mutlak untuk mencegah **PHP File Session Lock Contention** yang menyebabkan *worker pool exhaustion* dan HTTP 504 Gateway Timeout saat ratusan siswa ujian serentak.
- **Eliminasi Kalkulasi On-The-Fly Berlebihan**:
  - Endpoint `ajax_save_jawaban.php` harus bekerja secara *Pure Raw Upsert* (1 query insert/update jawaban).
  - Pengecekan kunci jawaban dan kalkulasi skor total dilakukan secara batch saat ujian diselesaikan di `proses_selesai_ujian.php`.
- **Eliminasi Chained AJAX Request**:
  - Hindari memicu request AJAX kedua (seperti memanggil `ajax_get_nav.php`) di dalam callback `ajax_save_jawaban.php`.
  - Pembaruan status visual nomor soal di browser wajib dilakukan langsung di sisi client (DOM manipulation) tanpa roundtrip server kedua.

---

## 💾 STRICT RULE 7: SAFE CODE CLEANUP & ZERO DELETION POLICY

- **Verifikasi Sebelum Modifikasi / Pengarsipan**:
  - Jangan menghapus file sembarangan.
  - Seluruh file redundan, dead code, backup lama, atau artefak sementara yang dipindahkan **WAJIB diisolasi ke dalam `C:\laragon\www\cbt_nova\scratch\quarantine\`** dengan mempertahankan struktur subfolder aslinya.
  - Tujuannya adalah agar riwayat kode lama tetap dapat diaudit, dicek, atau dipulihkan kembali kapan pun tanpa resiko data hilang.

---

## 🧪 STRICT RULE 8: MANDATORY TESTING & CODE INTEGRITY CHECKLIST

Setiap kali menyelesaikan tahapan perbaikan atau refactoring, agen **WAJIB** mengeksekusi checklist pengujian berikut:
1. **PHP Syntax Linting (`php -l`)**:
   - Pastikan seluruh file PHP yang dimodifikasi lolos linting tanpa syntax error (`powershell -ExecutionPolicy Bypass -File scratch/lint_check.ps1`).
2. **Runtime Verification Check**:
   - Jalankan skrip verifikasi `scratch/verify_runtime.php` untuk memastikan seluruh rute, include file, dan koneksi database PDO berjalan normal.
3. **Universal Security Audit Runner (`scratch/test_security_audit/test_security_audit.php`)**:
   - Eksekusi skrip audit keamanan dan hasilkan laporan incremental di `scratch/test_security_audit/`:
     ```powershell
     php "C:\laragon\www\cbt_nova\scratch\test_security_audit\test_security_audit.php" --target "C:\laragon\www\cbt_nova" --no-saas --output "scratch/test_security_audit/00X_security_audit_report.html"
     ```
4. **Database Concurrency & Index Integrity**:
   - Pastikan tabel beban tinggi (`cbt_student_answers`, `cbt_exam_participants`, `cbt_online_sessions`) menggunakan `ENGINE=InnoDB` dan memiliki index foreign key yang tepat.

---

## ⏱️ STRICT RULE 9: EXAM INTEGRITY, DEVICE LOCK & ANTI-CHEAT GUARD

- **Ketahanan Konkurensi Tinggi & Integritas Ujian**:
  - Saat siswa mengerjakan ujian, data jawaban wajib ditransmisikan dengan andal.
  - Validasi *Single Device Lock* (1 perangkat aktif per akun siswa di `cbt_device_locks`) tidak boleh dilewati (*bypass*).
  - Deteksi kecurangan (*tab switch*, *window blur*, *fullscreen exit*, *screenshot gesture*) wajib dicatat dengan timestamp akurat di tabel `cbt_cheat_logs`.

---

## 📦 STRICT RULE 10: STANDARDIZED JSON API & AJAX RESPONSE CONTRACT

- **Konsistensi Format Respons AJAX / JSON**:
  - Seluruh endpoint backend yang melayani AJAX siswa, guru, atau admin wajib mengirim header `header('Content-Type: application/json')` dan mengembalikan struktur JSON standar:
    ```json
    {
      "status": "success",
      "message": "Deskripsi status operasi",
      "data": {}
    }
    ```
  - Gunakan status terstandarisasi: `"success"`, `"error"`, `"blocked"`, `"timeout"`, atau `"session_expired"`.
  - Dilarang membocorkan PHP warning, notice, atau stack trace internal ke dalam respons JSON (selalu gunakan `ob_start()` dan `try-catch`).

---

## 🔑 STRICT RULE 11: ENVIRONMENT CONFIGURATION & CREDENTIALS PROTECTION

- **Proteksi Kredensial Database**:
  - Konfigurasi database dikelola terpusat di [`config/database.php`](file:///c:/laragon/www/cbt_nova/config/database.php) dengan konstanta (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_DEBUG`, `APP_BASEPATH`).
  - Dilarang menuliskan hardcoded kredensial database di dalam file controller/view individual.
  - Mode production wajib menyetel `define('APP_DEBUG', false)` untuk mencegah kebocoran informasi sistem.

---

## 📎 STRICT RULE 12: SECURE FILE UPLOADS & MEDIA VALIDATION

- **Validasi Ketat Berkas Unggahan**:
  - Fitur unggah gambar soal TinyMCE (`upload_handler.php`), import Excel (`.xlsx`), dan Word (`.docx`) wajib divalidasi MIME type dan ukuran filenya (maksimal 5 MB).
  - File gambar soal disimpan di direktori terisolasi `assets/uploads/soal/` dengan penamaan teracak unik (`soal_YYYYMMDD_His_uniqid.jpg`).
  - Gambar soal disajikan langsung sebagai file statis oleh web server dan di-cache via header `.htaccess`.

---

## 🛑 STRICT RULE 13: USER-SAFE ERROR HANDLING & PRODUCTION LOGGING

- **Keamanan Pesan Kesalahan & Pencatatan Log**:
  - Dilarang menampilkan detail stack trace internal atau database driver error (`PDOException`, query SQL mentah) ke antarmuka pengguna siswa saat ujian.
  - Setiap error ditangkap menggunakan `try-catch` terstruktur.
  - Aktivitas penting (Login, Logout, Mulai Ujian, Selesai Ujian, Reset Login Siswa) dicatat ke dalam tabel `cbt_activity_logs` via fungsi `log_activity()`.

---

## 🎨 STRICT RULE 14: CBT NOVA UNIFIED UI/UX DESIGN SYSTEM & FRONTEND STANDARDS (WCAG 2.1 AAA COMPLIANT)

**Single Source of Truth (SSOT) — WCAG 2.1 AAA Compliant, Multi-Device & Production Grade**

Panduan dan standar baku antarmuka pengguna (UI), pengalaman pengguna (UX), responsivitas mobile, aksesibilitas, layout dokumen cetak A4, display publik, dan ruang ujian siswa untuk seluruh modul di aplikasi **CBT Nova** (`admin/`, `guru/`, `siswa/`, `public/`).

---

### 🎨 1. PRINSIP DASAR DESAIN, TIPOGRAFI & AKSESIBILITAS (WCAG 2.1 AAA)

1. **Rasio Kontras Warna Teks & Latar (WCAG AAA)**:
   - Rasio kontras teks normal minimal **7:1** (dan minimal **4.5:1** untuk teks tebal/besar $\ge 18\text{pt}$).
   - **Larangan Keras**: Dilarang menggunakan teks abu-abu pudar seperti `#94a3b8` atau `#cbd5e1` di atas latar terang (`#ffffff` / `#f8fafc`).
   - **Palet Warna Teks Terstandarisasi**:
     - `Teks Primer / Judul / Header`: `#0f172a` atau `#1e293b` (Slate 900/800).
     - `Teks Sekunder / Body / Label Input`: `#334155` atau `#475569` (Slate 700/600).
     - `Teks Keterangan Redup (Muted Subtext)`: `#64748b` (Slate 500 - tetap memenuhi batas WCAG 4.5:1).
     - `Sidebar Heading / Nav Category`: `#475569` (Wajib terlihat jelas dan tegas pada latar putih).
2. **Hirarki Tipografi & Truncation**:
   - Seluruh judul navbar menggunakan utilitas `.navbar-title-truncate` agar judul halaman tidak terpotong atau tumpang-tindih pada layar smartphone (360px–414px).
   - Font family utama: `Inter`, `-apple-system`, `BlinkMacSystemFont`, `"Segoe UI"`, `Roboto`, `sans-serif`.
   - Font family untuk Token / Kode / NISN / IP: `SFMono-Regular`, `Menlo`, `Monaco`, `Consolas`, `monospace`.
3. **Scannable & Bebas Friksi**:
   - Informasi utama (nama item, kode, jumlah butir, status) harus langsung terbaca dalam 2 detik pertama tanpa membebani mata pengguna.
   - Tombol aksi primer diletakkan paling menonjol, sedangkan aksi sekunder/destruktif dirapikan dalam dropdown menu untuk menghemat ruang horizontal tabel.

---

### 📐 2. ANATOMI HALAMAN STANDAR (PAGE ANATOMY)

Setiap halaman modul utama di CBT Nova mengikuti urutan layout terstruktur:

```text
┌────────────────────────────────────────────────────────────────────────┐
│ TOP NAVBAR: Title (.navbar-title-truncate), Subtitle, Toggle, Profile  │
├────────────────────────────────────────────────────────────────────────┤
│ BREADCRUMBS: Dashboard / [Modul Induk] / [Halaman Aktif]               │
├────────────────────────────────────────────────────────────────────────┤
│ FLASH ALERTS: Feedback Sukses / Gagal / Warning (Bootstrap 5 Alert)   │
├────────────────────────────────────────────────────────────────────────┤
│ TOP METRIC CARDS (4 Kolom): Total Data, Aktif, Sub-item, Baru         │
├────────────────────────────────────────────────────────────────────────┤
│ ACTION TOOLBAR: Judul Section + Badge Total + Tombol Primary & Export  │
├────────────────────────────────────────────────────────────────────────┤
│ UNIFIED FILTER BAR: Search Live + Dropdown Filter + Limit + Reset      │
├────────────────────────────────────────────────────────────────────────┤
│ DATA TABLE CONTAINER: Card Wrapper + Table-Responsive + Table-Hover    │
├────────────────────────────────────────────────────────────────────────┤
│ PAGINATION & FOOTER: Info Baris "X-Y dari Z" + Pagination Links        │
└────────────────────────────────────────────────────────────────────────┘
```

---

### 📱 3. STANDAR SHELL LAYOUT & NAVIGASI SIDEBAR

1. **Sticky Sidebar Desktop**:
   - `position: sticky; top: 0; height: 100vh; overflow-y: auto;`
   - Sidebar tidak boleh ikut tergeser saat konten utama atau tabel di-scroll.
2. **Mobile Drawer Off-Canvas**:
   - Menggunakan transisi CSS transform yang halus (`transition: transform 0.3s ease-in-out`).
   - Dilengkapi tombol tutup silang `<button type="button" class="btn-close d-lg-none" id="btn-close-sidebar" ...>` di bagian header sidebar agar pengguna mobile dapat menutup navigasi dengan 1 sentuhan.
3. **Container Viewport Safety & Pencegahan Double Horizontal Scrollbar**:
   - `#content { min-width: 0; max-width: 100%; overflow-x: hidden; }`
   - `#wrapper, body { overflow-x: hidden; }`
   - **Aturan Pencegahan Double Scrollbar:** Jika tabel menggunakan `style="min-width: 1000px;"`, kontainer `.card` atau `.card-body` pembungkus **DILARANG** menambahkan `overflow-x: auto` atau `overflow-x: scroll`. Hanya kontainer `.table-responsive` yang berhak mengelola scroll horizontal agar pengguna tidak mengalami "dua scrollbar bertumpuk".

---

### 📊 4. KARTU METRIK RINGKAS (TOP OVERVIEW METRIC CARDS)

Gunakan grid `row g-3 mb-4` dengan 4 kartu metrik berpenampilan modern:
```html
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">TOTAL BANK SOAL</span>
                    <h3 class="fw-bold mb-0 mt-1 text-dark"><?= number_format($totalData, 0, ',', '.') ?></h3>
                    <small class="text-muted"><i class="fas fa-folder me-1 text-primary"></i>Keterangan ringkas</small>
                </div>
                <div class="rounded-3 p-3 bg-primary-subtle text-primary">
                    <i class="fas fa-database fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Ulangi untuk Success (Hijau), Info (Cyan), Warning (Kuning) -->
</div>
```

**Palet Warna Semantik Metrik:**
- `bg-primary-subtle text-primary`: Total Master / Data Induk.
- `bg-success-subtle text-success`: Status Aktif / Terbuka / Selesai.
- `bg-info-subtle text-info`: Sub-item / Partisipan / Komposisi.
- `bg-warning-subtle text-warning`: Item Baru / Pending / Peringatan.

---

### 🔍 5. STANDAR ACTION BAR, FILTER TOOLBAR & TOUCH TARGETS

1. **Responsive Flex / Grid Layout**:
   - Toolbar filter wajib menggunakan Grid Bootstrap responsif: `col-12 col-sm-6 col-lg-...` atau `flex-wrap gap-2`.
   - Pada layar smartphone (< 768px), elemen filter otomatis tersusun vertikal (*stacked*) rapi tanpa overflow horizontal.
2. **Touch Target Ergonomis (Mobile-First)**:
   - Tinggi elemen form kontrol (`input`, `select`, `button`) minimal **38px - 44px** pada tampilan mobile.
   - Tombol ikon aksi `.btn-action-icon` diatur minimal **38x38px** agar mudah ditekan jari (*fat-finger friendly*).
3. **Standarisasi Pelabelan & Dropdown**:
   - Format Dropdown Kelas: `Kelas [Jenjang] - [Nama Kelas]` (contoh: `Kelas 10 - X IPA 1`).
   - Format Filter Jadwal/Ujian: Menggunakan label baku `Nama Ujian / Test`.
   - Format Pencarian: Menggunakan placeholder `Ketik nama siswa, NISN, atau username...` dengan icon search terpadu dan debounce input (300ms).

---

### 📑 6. STANDAR TABEL DATA & ISOLASI SCROLL HORIZONTAL

1. **Wajib Pembungkus `.table-responsive`**:
   - Seluruh tabel data wajib berada di dalam container `.table-responsive` dengan scroll horizontal terisolasi.
   - **Isolasi Scroll:** Dilarang membuat halaman atau `#wrapper` / `body` bergeser ke samping. Scroll horizontal hanya boleh terjadi di dalam kontainer tabel.
2. **Styling Header `<thead>`**:
   - Menggunakan kelas `.table-light.text-secondary.small.text-uppercase.fw-semibold` dengan latar `#f8fafc` / `#f1f5f9` dan `letter-spacing: 0.5px`.
3. **Lebar Minimum Tabel Berkolom Padat**:
   - Tabel dengan kolom $\ge 6$, aksi multi-tombol, token, atau rincian tanggal wajib memiliki `style="min-width: 1000px;"` s/d `style="min-width: 1150px;"` agar isi kolom tidak tertekan (*squeezed*) atau patah baris berantakan.
4. **Scrollbar Ramping & Elegan**:
   ```css
   .table-responsive {
       width: 100%;
       max-width: 100%;
       overflow-x: auto !important;
       -webkit-overflow-scrolling: touch;
   }
   .table-responsive::-webkit-scrollbar {
       height: 7px;
   }
   .table-responsive::-webkit-scrollbar-track {
       background: #f1f5f9;
       border-radius: 4px;
   }
   .table-responsive::-webkit-scrollbar-thumb {
       background: #cbd5e1;
       border-radius: 4px;
   }
   .table-responsive::-webkit-scrollbar-thumb:hover {
       background: #94a3b8;
   }
   ```

---

### 🪟 7. STANDAR POP-UP & MODAL DIALOG

1. **Centered & Scrollability Wajib**:
   - Seluruh modal form (tambah, edit, salin, import, formula, konfigurasi) **WAJIB** menyertakan class:
     ```html
     <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable ...">
     ```
   - Hal ini memastikan header dan footer modal tetap terlihat di viewport, dan bagian body modal dapat di-scroll dengan mulus di perangkat layar kecil (< 576px).
2. **Tombol Close & Pencegahan Ghost Overlay**:
   - Modal wajib memiliki `<button type="button" class="btn-close" data-bs-dismiss="modal"></button>`.
   - Hindari manipulasi backdrop manual yang dapat menyebabkan `modal-backdrop` tertinggal atau memblokir interaksi klik setelah modal ditutup.
3. **Form Double-Submit Protection**:
   - Seluruh form modal wajib menonaktifkan tombol submit (`disabled`) dan menampilkan indikator loading saat proses penyimpanan berlangsung:
     ```javascript
     $('#formTambah').on('submit', function() {
         $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...');
     });
     ```

---

### 🏷️ 8. STANDAR BADGE, PILLS, PROGRESS BAR & AVATAR

1. **Formula Warna Badge Soft Pastel Berkontras Tinggi**:
   - **Sukses / Aktif / Selesai**: `.badge.bg-success-subtle.text-success.rounded-pill` (Latar `#dcfce7`, teks `#15803d`).
   - **Info / Jenjang / Tipe**: `.badge.bg-info-subtle.text-info-emphasis` (Latar `#e0f2fe`, teks `#0369a1`).
   - **Primary / NISN / Kode**: `.badge.bg-primary-subtle.text-primary.font-monospace` (Latar `#dbeafe`, teks `#1d4ed8`).
   - **Warning / Ragu / Pending**: `.badge.bg-warning-subtle.text-warning-emphasis` (Latar `#fef3c7`, teks `#b45309`).
   - **Danger / Nonaktif / Terkunci**: `.badge.bg-danger-subtle.text-danger.rounded-pill` (Latar `#fee2e2`, teks `#b91c1c`).
   - **Secondary / Draft**: `.badge.bg-secondary-subtle.text-secondary` (Latar `#f1f5f9`, teks `#475569`).
2. **Avatar Inisial Pengguna**:
   ```html
   <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary fw-bold" style="width:32px;height:32px;font-size:13px;">
       <?= strtoupper(mb_substr($nama, 0, 1)) ?>
   </div>
   ```
3. **Sinkronisasi Progress Bar**:
   - Pembilang dan penyebut progress bar pengerjaan/soal wajib sinkron dengan kuota riil soal (`soal_ids` limit).

---

### ⚡ 9. MICRO-INTERACTIONS, STATE ASINKRON & JAVASCRIPT STANDARDS

1. **SweetAlert2 Confirmation**:
   - Seluruh aksi hapus dan nonaktifkan data wajib memicu SweetAlert2 dengan informasi detail nama item dan deteksi keterkaitan relasi data aktif.
2. **Empty State Component**:
   - Jika data kosong (`empty($listData)`), tampilkan placeholder informatif dengan icon `fa-3x opacity-25` dan tombol CTA pembuatan data baru.
3. **Aturan State Loading Asinkron & Isolasi Pointer-Events**:
   - Seluruh fungsi AJAX (Monitoring, Autosave, Device Lock, Filter Live) **WAJIB** membersihkan overlay loading dan mengembalikan `pointer-events: auto !important;` pada callback `complete: function()`:
     ```javascript
     $.ajax({
         url: 'endpoint.php',
         type: 'POST',
         data: payload,
         beforeSend: function() {
             $('#loader').show();
             $('body').css('pointer-events', 'none');
         },
         success: function(res) {
             // Tangani respons
         },
         error: function(xhr) {
             // Tangani error
         },
         complete: function() {
             $('#loader').hide();
             $('body').css('pointer-events', 'auto');
         }
     });
     ```
   - **DILARANG** membiarkan elemen loading/spinner memiliki style `pointer-events: none` yang tertinggal di DOM setelah respons selesai (baik respons *success* maupun *error*).

---

### 📝 10. STANDAR ANTARMUKA UJIAN SISWA (EXAM PORTAL & WORKSPACE)

Antarmuka pengerjaan ujian siswa (`siswa/ujian.php`) dirancang khusus untuk kenyamanan membaca jangka panjang (60–120 menit), bebas distraksi, tahan gangguan jaringan, dan responsif di berbagai perangkat (Ponsel, Tablet, Laptop, Chromebook):

1. **Question Palette Grid (Nomor Navigasi Soal)**:
   - **Touch Target**: Minimal ukuran tombol nomor `40x40px` (desktop) dan `44x44px` (mobile).
   - **Status Warna Kontras & Tegas**:
     - *Belum Dijawab*: Latar netral `#f1f5f9`, border `#cbd5e1`, teks `#334155`.
     - *Ragu-ragu*: Latar kuning `#fef3c7`, border `#f59e0b`, teks `#b45309` (dengan ikon centang/ragu).
     - *Sudah Dijawab*: Latar biru primer `#1d4ed8` atau hijau `#15803d` solid, teks `#ffffff` putih kontras.
     - *Nomor Aktif Saat Ini*: Border tebal dengan highlight fokus (`outline: 3px solid #0284c7; font-weight: 800;`).
2. **Sticky Exam Header & Countdown Timer**:
   - Timer hitung mundur sisa waktu ujian wajib **terkunci di posisi atas (*sticky*)** (`position: sticky; top: 0; z-index: 1000;`).
   - Tipografi timer menggunakan font monospace tebal (`font-family: monospace; font-size: 1.15rem; font-weight: 700;`).
   - Waktu kritis ($\le 5\text{ menit}$): Timer otomatis berubah warna menjadi merah peringatan (`#ef4444`) dengan indikator berkedip halus.
3. **Stimulus Soal, Switcher Ukuran Font & Formula Matematika (KaTeX)**:
   - **Font Size Switcher**: Sediakan 3 pilihan ukuran font siswa (`A-` 14px / `A` 16px / `A+` 18px) yang tersimpan otomatis di `localStorage`.
   - **Responsivitas Gambar Soal**: Seluruh gambar stimulus soal wajib memiliki `max-width: 100%; height: auto; border-radius: 8px;` dan mendukung perbesaran klik (*modal zoom preview*).
   - **Formula Rumus Matematika (KaTeX / MathJax)**: Wajib terbungkus kontainer dengan `overflow-x: auto; max-width: 100%;` agar rumus panjang tidak mematahkan layout ponsel.
4. **Ergonomi Action Bar Bawah (Footer Navigasi)**:
   - Tombol **Sebelumnya**, **Ragu-ragu**, dan **Selanjutnya** wajib mudah dijangkau oleh ibu jari di layar smartphone.
   - Tombol **Selesai Ujian**: Wajib divalidasi sesuai aturan bisnis (baru aktif saat mendekati batas akhir waktu ujian atau setelah seluruh butir selesai dikonfirmasi).

---

### 🖨️ 11. STANDAR MODE CETAK KERTAS & DOKUMEN PDF (A4 PRINT ENGINE)

Seluruh dokumen administrasi ujian yang dicetak (Cetak Kartu Ujian `print-kartu.php`, Berita Acara `cetak-administrasi.php`, Daftar Hadir `cetak-kehadiran.php`, Rekap Nilai) wajib mematuhi standar cetak kertas A4:

1. **Standarisasi Aturan CSS `@media print`**:
   ```css
   @page {
       size: A4 portrait;
       margin: 10mm 10mm 10mm 10mm;
   }
   @page :left { margin: 10mm; }
   @page :right { margin: 10mm; }

   @media print {
       body {
           background: #ffffff !important;
           font-size: 11pt;
           color: #000000 !important;
           -webkit-print-color-adjust: exact !important;
           print-color-adjust: exact !important;
       }
       .d-print-none, #sidebar, .navbar, .btn, .pagination, .breadcrumb, footer, .alert {
           display: none !important;
       }
       .table {
           border-color: #000000 !important;
           width: 100% !important;
       }
       .table th, .table td {
           border: 1px solid #000000 !important;
           padding: 6px 8px !important;
       }
       .page-break {
           page-break-after: always;
           break-after: page;
       }
       .avoid-break, .card-kartu-ujian, tr {
           page-break-inside: avoid !important;
           break-inside: avoid !important;
       }
   }
   ```
2. **Layout Cetak Kartu Peserta Siswa**:
   - Format Grid 2 Kolom x 4 Baris (Total 8 kartu per lembar kertas A4).
   - Setiap kartu dibatasi garis potong putus-putus halus (*dashed border* `#94a3b8`) dengan dimensi pas tanpa overflow ke lembar kedua.
   - Memuat logo sekolah resmi, foto siswa, NISN, username, password, dan QR-code/barcode valid.

---

### 📺 12. STANDAR DISPLAY PUBLIK (LAYAR TV / PROYEKTOR TOKEN)

Antarmuka display publik (`public/index.php` dan `admin/display-publik/` / `guru/display-publik/`) dirancang untuk keterbacaan optimal dari jarak jauh (5–10 meter di aula/lobi sekolah):

1. **Hero Token Display & Tipografi Jarak Jauh**:
   - Ukuran font token ujian ekstra besar: $\ge 48\text{px}$ up to $72\text{px}$ (`font-size: 4rem; font-weight: 800; font-family: monospace;`).
   - Jarak antar karakter token renggang: `letter-spacing: 6px;`.
   - Warna kontras tinggi: Background gelap modern `#0f172a` dengan teks token menyala `#38bdf8` / `#4ade80` atau latar putih bersih dengan border solid tebal.
2. **Animasi Auto-Flipper Token**:
   - Transisi perubahan token menggunakan animasi CSS flip / fade yang mulus tanpa kedip mengganggu (*flicker-free*).
3. **Jam Digital Server Real-Time**:
   - Jam digital sinkron server dengan detak detik waktu nyata WIB (`font-size: 1.5rem; font-weight: 700;`).
4. **Indikator Masa Berlaku Token**:
   - Progress bar atau circular countdown yang menunjukkan sisa menit sebelum token diperbarui atau kedaluwarsa.
