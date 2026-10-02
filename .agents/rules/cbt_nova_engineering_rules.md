# CBT Nova — Agent Guidelines & Mandatory Engineering Rules

Panduan kerja dan aturan baku untuk AI Agent dalam memelihara, merefaktorisasi, dan mengoptimasi codebase **CBT Nova** (`C:\laragon\www\cbt_nova`).

---

## 🛡️ PROTOKOL KESELAMATAN DATA PRODUKSI (PRODUCTION ZERO-BREAKAGE RULES)
Sebelum dan selama melakukan remediasi, patuhi batasan mutlak ini:
1. **DILARANG merusak format soal (Rich Text / MathJax / KaTeX):** Jangan pernah membungkus teks `konten_soal` atau opsi jawaban yang dirender dari database dengan fungsi `htmlspecialchars()` mentah. Hal ini akan mematahkan rumus matematika, tag gambar `<img>`, dan tabel soal.
2. **DILARANG mengubah payload/struktur jawaban siswa:** Struktur parsing jawaban di frontend maupun backend (tipe JSON, ID opsi, radio value) tidak boleh diubah agar data jawaban tersimpan siswa tetap valid dan sinkron.
3. **DILARANG merusak alur autentikasi dan session:** Variabel sesi pengerjaan ujian yang sudah berjalan tidak boleh di-reset secara paksa yang bisa memicu *kick/logout* massal siswa.
4. **Isolasi Mutlak Kunci Jawaban (`is_correct`):** Pada seluruh endpoint siswa (`siswa/ajax_get_soal.php`), query opsi wajib menggunakan proyeksi kolom eksplisit (`SELECT id, question_id, label, value_target FROM cbt_question_options`) agar kolom `is_correct` tidak pernah ditarik ke memori PHP siswa atau terekspos ke inspect element browser.
5. **Proteksi Direktori Media & Foto (`assets/uploads/`):** Seluruh folder unggahan wajib dilindungi `.htaccess` untuk menonaktifkan directory listing (`Options -Indexes`), mematikan engine PHP (`php_flag engine off`), dan memblokir eksekusi file executable/skrip.
6. **Otorisasi Ketat Anti-IDOR:** Seluruh transaksi penyimpanan jawaban (`siswa/ajax_save_jawaban.php`, `siswa/ajax_toggle_ragu.php`) wajib mengambil `student_id` dari `$_SESSION['student_id']` dan memvalidasi status partisipasi aktif (`status = 'working'`).

---

## 🚀 STRICT RULE 1: GIT RELEASE PROTOCOL & ATOMIC COMMITS WORKFLOW
Aturan baku dan protokol wajib bagi Antigravity AI ketika melakukan staging, committing, dan pushing kode ke repositori Git/GitHub:
1. **Prinsip Atomic Commits (Dilarang Monolithic Commit)**:
   - Setiap kali melakukan commit/push, **DILARANG MENGGABUNGKAN** seluruh perubahan berbeda domain ke dalam 1 commit tunggal (dilarang `git commit -am "update"`).
   - Perubahan **WAJIB DIPISAHKAN** secara modular: **1 Fitur / 1 Bugfix / 1 Modul = 1 Commit Terpisah**.
2. **Konvensi Pesan Commit (Conventional Commits)**:
   - Format: `<type>(<scope>): <deskripsi>`
   - `feat`: Penambahan fitur/modul baru.
   - `fix`: Perbaikan bug logika, scoring, atau database.
   - `ui/ux`: Penyesuaian antarmuka, CSS, atau layout.
   - `perf`: Optimasi query, caching, atau konkurensi.
   - `refactor`: Perapian kode tanpa mengubah output.
   - `docs`: Pembaruan panduan Markdown/dokumentasi.
3. **SOP Eksekusi Commit & Push**:
   - **Langkah 1**: Audit file termodifikasi (`git status`, `git diff --stat`).
   - **Langkah 2**: Jalankan linting sintaks PHP wajib 0 error (`powershell -ExecutionPolicy Bypass -File scratch/lint_check.ps1`).
   - **Langkah 3**: Staging & commit modular terpisah per kelompok fitur (`git add <files>`, `git commit -m "..."`).
   - **Langkah 4**: Eksekusi `git push origin HEAD` saat diinstruksikan oleh pengguna.
   - **Langkah 5**: Tampilkan bukti riwayat commit (`git log -n <N> --oneline`).

---

## 📁 STRICT RULE 2: SCRATCH & TEMPORARY FILES
- **WADAH FILE UJI COBA & DOKUMEN SEMENTARA**:
  - Semua berkas uji coba (*scratch scripts*, *test runner*, *audit parser*, *benchmark dummy*), dokumen analisis sementara, catatan pengembang, atau berkas karantina **WAJIB ditempatkan di folder `C:\laragon\www\cbt_nova\scratch\`**.
  - Dilarang membuat berkas temporary/scratch sembarangan di root project maupun di direktori modul produksi (`admin/`, `guru/`, `siswa/`, `auth/`, `config/`, dll.).

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
2. **Auto-Create File Baru**: Jika file harian untuk tanggal hari ini belum tersedia di `scratch/docs/`, agen **WAJIB LANGSUNG MEMBUAT** kedua file tersebut secara otomatis pada respons pertama.
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
   - Pastikan seluruh file PHP yang dimodifikasi lolos linting tanpa syntax error.
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

## 🎨 STRICT RULE 14: CBT NOVA UI/UX DESIGN SYSTEM & COMPONENT STANDARDS
Seluruh antarmuka admin, guru, siswa, dan public display wajib mematuhi standar desain terpadu (diadaptasi dari `admin/bank-soal/index.php`):
1. **Top Metric Overview**: Selalu sediakan baris ringkasan metrik 4 kolom (`row g-3 mb-4` dengan container `.card.border-0.shadow-sm.rounded-3.bg-white` dan icon wrapper `.bg-{color}-subtle.text-{color}`).
2. **Unified Action & Filter Toolbar**: Satukan toolbar aksi primer/sekunder dan form filter terintegrasi (Search, Dropdown Kategori, Jenjang, Status, Limit, dan Reset).
3. **Table & Badge Hierarchy**:
   - Thead: `.table-light.text-secondary.small.text-uppercase.fw-semibold` dengan `letter-spacing: 0.5px`.
   - Kode Unik / NISN / NIP: Badge `.badge.bg-primary-subtle.text-primary.font-monospace`.
   - Jenjang Kelas: Badge `.badge.bg-info-subtle.text-info-emphasis`.
   - Avatar Inisial Bulat: Container `.avatar-placeholder.rounded-circle.bg-secondary-subtle.text-secondary`.
   - Status Aktif/Terbuka: Badge pill `.badge.bg-success-subtle.text-success.rounded-pill`.
   - Status Non-Aktif/Terkunci: Badge pill `.badge.bg-danger-subtle.text-danger.rounded-pill`.
4. **Ergonomic Action Bar**: Tombol primer (Kelola Soal / Edit) paling menonjol, dipadukan dengan dropdown menu aksi sekunder untuk menghemat ruang tabel.
5. **Horizontal Scrollable Data Table (Scroll Horizontal Terisolasi di Dalam Tabel)**:
   - **Isolasi Scroll:** Scroll horizontal **WAJIB** berada di dalam kontainer tabel (`.table-responsive` pada card), dan **DILARANG** membuat seluruh halaman/body/wrapper web bergeser ke samping.
   - Pada wrapper halaman wajib dipastikan `#content` memiliki `min-width: 0; max-width: 100%; overflow-x: hidden;` serta `#wrapper` dan `body` memiliki `overflow-x: hidden;`.
   - Seluruh tabel data (`.card` + `.table-responsive`) yang memiliki banyak kolom atau informasi padat (>= 6 kolom, aksi multi-tombol, token, atau rincian tanggal) **WAJIB** dilengkapi dengan atribut `style="min-width: 1000px;"` s/d `style="min-width: 1150px;"` (disesuaikan dengan kepadatan data) agar struktur kolom tidak terjepit (*squeezed*) atau patah baris berantakan pada layar laptop/tablet.
   - Sediakan styling scrollbar horizontal kustom yang ramping dan elegan:
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
6. **Human-Error Guard**: Konfirmasi destruktif (Hapus/Nonaktifkan) wajib menggunakan SweetAlert2 dengan deteksi relasi data aktif.
7. **Form Double-Submit Protection**: Tombol submit form modal wajib otomatis disabled dan beranimasi loading saat proses penyimpanan berlangsung.
