# DOKUMENTASI LENGKAP & BUKU PANDUAN SISTEM CBT NOVA
**Aplikasi:** Computer-Based Testing (CBT) Nova v2  
**Instansi:** SMA Negeri 11 Surabaya  
**Arsitektur:** High-Concurrency Hybrid PHP 8.1+ / MySQL 8.0+ / Vanilla JS & Tailwind CSS  
**Kapasitas:** Teruji dan Teroptimasi untuk 10.000+ Peserta Serentak (High Scalability)  
**Versi Dokumen:** 2.5 (Oktober 2026)  

---

## DAFTAR ISI
1. [BAB 1: GAMBARAN UMUM & ARSITEKTUR SISTEM](#bab-1-gambaran-umum--arsitektur-sistem)
2. [BAB 2: PANDUAN INSTALASI & DEPLOYMENT STEP-BY-STEP](#bab-2-panduan-instalasi--deployment-step-by-step)
3. [BAB 3: SKEMA BASIS DATA & STRATEGI INDEKS](#bab-3-skema-basis-data--strategi-indeks)
4. [BAB 4: KONFIGURASI LINGKUNGAN (ENVIRONMENT PARITY)](#bab-4-konfigurasi-lingkungan-environment-parity)
5. [BAB 5: PANDUAN PENGGUNAAN PANEL ADMIN](#bab-5-panduan-penggunaan-panel-admin)
6. [BAB 6: PANDUAN PENGGUNAAN PANEL GURU](#bab-6-panduan-penggunaan-panel-guru)
7. [BAB 7: ENGINE UJIAN SISWA & ANTI-CHEAT ENGINE](#bab-7-engine-ujian-siswa--anti-cheat-engine)
8. [BAB 8: MODUL DISPLAY PUBLIK & LIVE SCOREBOARD](#bab-8-modul-display-publik--live-scoreboard)
9. [BAB 9: STANDAR OPERASIONAL PROSEDUR (SOP) UJIAN](#bab-9-standar-operasional-prosedur-sop-ujian)
10. [BAB 10: PANDUAN PEMECAHAN MASALAH (TROUBLESHOOTING)](#bab-10-panduan-pemecahan-masalah-troubleshooting)
11. [BAB 11: STANDAR KEAMANAN SISTEM & APPSEC](#bab-11-standar-keamanan-sistem--appsec)

---

## BAB 1: GAMBARAN UMUM & ARSITEKTUR SISTEM

### 1.1 Profil Sistem
CBT Nova adalah platform ujian berbasis komputer dan daring terintegrasi yang dirancang untuk keandalan tinggi (*high reliability*), integritas akademik anti-curang, dan kecepatan respons tinggi di bawah beban ribuan pengguna simultan.

### 1.2 Tech Stack
* **Bahasa Pemrograman:** PHP 8.1 / 8.2 (Strict Types, Match Expressions, PDO Prepared Statements)
* **Basis Data:** MySQL 8.0 / MariaDB 10.6+ dengan InnoDB Engine & Composite Covering Indexes
* **Frontend:** Vanilla JavaScript (ES6+), Tailwind CSS, Bootstrap 5 UI Elements, SweetAlert2, FontAwesome 6
* **Web Server:** Nginx 1.24+ (Reverse Proxy & PHP-FPM) / Apache 2.4 (Laragon Development)
* **Penyimpanan:** Local Fast Storage dengan MIME Magic Bytes Verification & WebP Lossless Image Optimizer

### 1.3 Arsitektur Direktori
```
cbt_nova/
├── admin/                    # Panel Manajemen Utama Administrator
│   ├── bank-soal/            # Modul Pembuatan, Import Word/Excel & Validasi Soal
│   ├── display-publik/       # Manajemen Layar Proyektor Nilai Realtime
│   ├── master/               # Master Data Siswa, Alumni, Guru, Kelas, Mapel
│   ├── master-io/            # Import/Export Data Master (Excel/Spreadsheet)
│   ├── monitoring/           # Live Proktor, Device Lock Manager, Log Pelanggaran
│   ├── jadwal/               # Penjadwalan Ujian, Sesi, & Alokasi Token
│   ├── hasil/                # Rekapitulasi Nilai, Analisis Butir, Cetak PDF/Excel
│   └── pengaturan/           # Backup Database, Konfigurasi Sekolah, Reset Data
├── guru/                     # Panel Khusus Guru Mata Pelajaran
│   ├── bank-soal/            # Bank Soal Mandiri Guru
│   ├── monitoring/           # Monitoring Khusus Kelas Binaan Guru
│   ├── hasil/                # Penilaian Esai Manual & Export Rekap
│   └── display-publik/       # Generator Display Publik Guru
├── siswa/                    # Modul Antarmuka & Mesin Pengerjaan Siswa
│   ├── ajax/                 # Dynamic View Loader (Dashboard, Konfirmasi, Ujian)
│   ├── ajax_save_jawaban.php # Endpoint Auto-Save Jawaban Anti-Debounce
│   ├── ajax_cheat_log.php    # Anti-Cheat Telemetry Recorder
│   └── ujian.php             # Workspace Pengerjaan Ujian Terproteksi
├── config/                   # Konfigurasi Database & Environment
│   ├── database.php          # Konfigurasi Utama (Koneksi PDO, Session, Helper)
│   └── database_prod.php     # Template Konfigurasi Khusus Server Linux
├── includes/                 # Library Global, Helper, Layout Header/Sidebar/Footer
├── sql/                      # DDL Skema, Migrasi, & Idempotent Database Sync
└── docs/                     # Dokumentasi Resmi & Manual Book Sistem
```

---

## BAB 2: PANDUAN INSTALASI & DEPLOYMENT STEP-BY-STEP

### 2.1 Lingkungan Lokal (Windows / Laragon)
1. Pasang aplikasi **Laragon** (PHP 8.1+, Apache, MySQL).
2. Clone repositori ke `C:\laragon\www\cbt_nova`.
3. Buka phpMyAdmin di `http://localhost/phpmyadmin`, buat database `db_axon`.
4. Import skema awal dari [`sql/cbt_nova_schema_10k.sql`](file:///c:/laragon/www/cbt_nova/sql/cbt_nova_schema_10k.sql) lalu jalankan [`sql/sync_production_schema.sql`](file:///c:/laragon/www/cbt_nova/sql/sync_production_schema.sql).
5. Konfigurasi [`config/database.php`](file:///c:/laragon/www/cbt_nova/config/database.php):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'db_axon');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('APP_DEBUG', true);
   ```
6. Akses melalui browser: `http://localhost/cbt_nova/` atau virtual host Laragon.

---

### 2.2 Lingkungan Server Produksi (Linux Ubuntu / Debian / Nginx)

#### Langkah 1: Instalasi Paket & Ekstensi PHP yang Diperlukan
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-gd \
                    php8.2-mbstring php8.2-xml php8.2-zip php8.2-fileinfo \
                    php8.2-curl git unzip
```

#### Langkah 2: Clone Repositori & Sinkronkan Kode
```bash
cd /var/www
git clone https://github.com/MA-habibullah/cbt_nova_v2.git cbt_nova
cd /var/www/cbt_nova
```

#### Langkah 3: Setup Konfigurasi Database Produksi
Salin template produksi ke `config/database.php`:
```bash
cp config/database_prod.php config/database.php
```
Sesuaikan kredensial di `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cbt_nova_v2');
define('DB_USER', 'user_cbt');
define('DB_PASS', 'PasswordKuatDatabase');
define('APP_DEBUG', false);
```

#### Langkah 4: Migrasi Skema Database
```bash
mysql -u user_cbt -p cbt_nova_v2 < sql/sync_production_schema.sql
```

#### Langkah 5: Atur Hak Akses & Kepemilikan File
```bash
sudo chown -R www-data:www-data /var/www/cbt_nova
sudo chmod -R 755 /var/www/cbt_nova
sudo chmod -R 775 /var/www/cbt_nova/assets/uploads
```

#### Langkah 6: Konfigurasi Virtual Host Nginx
Buat file konfigurasi `/etc/nginx/sites-available/cbt_nova`:
```nginx
server {
    listen 80;
    server_name cbt.sman11sby.sch.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name cbt.sman11sby.sch.id;

    root /var/www/cbt_nova;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/cbt.sman11sby.sch.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/cbt.sman11sby.sch.id/privkey.pem;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
```
Aktifkan dan restart service:
```bash
sudo ln -s /etc/nginx/sites-available/cbt_nova /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx php8.2-fpm
```

---

## BAB 3: SKEMA BASIS DATA & STRATEGI INDEKS

Sistem menggunakan 26 tabel relasional yang terindeks secara optimal untuk menangani agregasi data berkecepatan tinggi:

| Nama Tabel | Fungsi Utama | Kunci Indeks Kritis |
| :--- | :--- | :--- |
| `cbt_students` | Data identitas siswa, kelas, status aktif/alumni | `nisn`, `username`, `status`, `class_id` |
| `cbt_teachers` | Data guru pengampu dan akun login | `nip`, `username`, `is_active` |
| `cbt_admins` | Akun superadmin & pengelola sistem | `username`, `email` |
| `cbt_classes` | Data rombongan belajar / tingkat kelas | `jenjang`, `nama_kelas` |
| `cbt_subjects` | Data mata pelajaran | `kode_mapel`, `nama_mapel` |
| `cbt_exams` | Konfigurasi jadwal ujian, durasi, token, opsi acak | `mulai_pada`, `selesai_pada`, `is_active` |
| `cbt_exam_participants` | Status pengerjaan ujian siswa realtime | `(exam_id, status)`, `(student_id, exam_id)` |
| `cbt_questions` | Master bank soal (PG, Esai, Kompleks, Menjodohkan) | `bank_id`, `jenis_soal`, `nomor_urut` |
| `cbt_student_answers` | Rekaman jawaban siswa & nilai per butir | `(participant_id, question_id)` |
| `cbt_device_locks` | Kunci keamanan single-device per akun siswa | `(student_id, device_id)`, `session_id` |
| `cbt_cheat_logs` | Telemetri deteksi kecurangan proktor | `(exam_id, student_id)`, `tipe_pelanggaran` |
| `cbt_display_tokens` | Token publik terenkripsi live scoreboard | `token_hash`, `short_code`, `idx_by_creator` |
| `cbt_activity_logs` | Audit trail seluruh aktivitas pengguna | `(role, type, created_at)` |

---

## BAB 4: KONFIGURASI LINGKUNGAN (ENVIRONMENT PARITY)

Untuk memastikan sistem berjalan tanpa perbedaan antara lokal dan server:
1. **Case-Sensitivity:** Seluruh pemanggilan file menggunakan huruf kecil dan fungsi `dirname(__DIR__, n)`.
2. **Session Handler:** Session cookie path diatur otomatis sesuai `APP_BASEPATH` untuk mencegah tabrakan sesi.
3. **Penyimpanan Foto Siswa:**
   - Format didukung: JPG, PNG, WEBP.
   - Maksimal ukuran: 5 MB.
   - Pemrosesan: Dikonversi ke WebP berkualitas tinggi menggunakan GD Library dengan ukuran optimal (300x300 px).

---

## BAB 5: PANDUAN PENGGUNAAN PANEL ADMIN

### 5.1 Manajemen Master Data Siswa & Alumni
* **Navigasi:** `Admin` $\rightarrow$ `Master Data` $\rightarrow$ `Data Siswa`.
* **Fitur Filter Status:**
  - **Tab Siswa Aktif:** Menampilkan siswa yang terdaftar di kelas berjalan.
  - **Tab Alumni:** Menampilkan riwayat lulusan siswa.
  - **Tab Semua:** Menampilkan keseluruhan data siswa.
* **Fitur Kelulusan Massal (Bulk Alumni):**
  - Pilih checkbox siswa kelas XII $\rightarrow$ Klik tombol **"Luluskan / Ubah ke Alumni"**.
* **Fitur Pemulihan Siswa (Restore Alumni):**
  - Pada tab Alumni, klik tombol **"Kembalikan ke Aktif"** (warna hijau) untuk mengaktifkan kembali siswa dan menetapkan kelas aktifnya.

### 5.2 Manajemen Proktor & Live Monitoring
* **Navigasi:** `Admin` $\rightarrow$ `Monitoring` $\rightarrow$ `Monitoring Ujian`.
* **Tampilan Counter Realtime:**
  - Total Peserta Terdaftar
  - Sedang Mengerjakan (`working`)
  - Selesai (`finished`)
  - Terkunci / Diblokir (`blocked`)
* **Tombol Aksi Proktor:**
  - **Buka Blokir (`unlock_exam`):** Mengembalikan status peserta yang terblokir akibat pelanggaran agar dapat melanjutkan ujian.
  - **Tambah Waktu (`add_time`):** Memberikan kompensasi durasi ujian (+15, +30 menit) kepada siswa tertentu jika terjadi kendala teknis.
  - **Kunci Peserta (`lock_exam`):** Menghentikan pengerjaan siswa yang terindikasi curang secara manual.
  - **Selesaikan Paksa (`finish_exam`):** Menutup paksa sesi ujian siswa jika waktu telah habis atau siswa meninggalkan ruangan.

### 5.3 Manajemen Device Lock (Kunci Perangkat)
* **Navigasi:** `Admin` $\rightarrow$ `Monitoring` $\rightarrow$ `Kunci Perangkat`.
* **Fungsi:** Mengawasi 1 akun siswa yang terikat pada 1 *device token*.
* **Aksi Reset Login:** Jika gawai/laptop siswa rusak saat ujian, Admin/Proktor dapat menekan tombol **"Reset Login"** agar siswa dapat login dari gawai cadangan.

### 5.4 Audit Log Pelanggaran Anti-Cheat
* **Navigasi:** `Admin` $\rightarrow$ `Monitoring` $\rightarrow$ `Log Pelanggaran`.
* **Rekaman Telemetri:**
  - Waktu kejadian (Jam, Menit, Detik).
  - Tipe Pelanggaran: `blur/tab switch` (pindah tab), `screenshot attempt` (PrintScreen/Snipping Tool), `shortcut attempt` (Ctrl+C, Ctrl+V, F12, Ctrl+U).
  - Total akumulasi pelanggaran per siswa.

---

## BAB 6: PANDUAN PENGGUNAAN PANEL GURU

### 6.1 Manajemen Bank Soal Mandiri
* Guru dapat membuat bank soal sesuai mata pelajaran yang diampu.
* Mendukung 4 jenis soal:
  1. **Pilihan Ganda Biasa (Single Choice)**
  2. **Pilihan Ganda Kompleks (Multi Choice)**
  3. **Menjodohkan (Matching)**
  4. **Esai Terbuka (Essay)**

### 6.2 Monitoring Terisolasi (Teacher Scoping)
* Panel monitoring guru secara otomatis hanya menampilkan daftar siswa dan jadwal ujian yang dibuat oleh guru yang bersangkutan (`WHERE teacher_id = ?`).

---

## BAB 7: ENGINE UJIAN SISWA & ANTI-CHEAT ENGINE

### 7.1 Alur Pengerjaan Siswa
```
[1. Halaman Login]
       │
       ▼ (Validasi NISN & Password + Verifikasi Device Token)
[2. Dashboard Siswa] ─── (Pilih Jadwal Ujian Aktif)
       │
       ▼ (Masukkan Token Ujian 6 Karakter)
[3. Konfirmasi Tes]
       │
       ▼ (Buka Fullscreen Lock & Load Soal)
[4. Lembar Ujian CBT]
       │
       ├─► Navigasi Soal Dinamis (Ragu-ragu / Sudah Jawab)
       ├─► Auto-Save Jawaban Realtime (Background Fetch)
       ├─► Anti-Cheat Protection Guard (Blur, Shortcut, Screenshot Block)
       │
       ▼ (Klik Selesai / Waktu Habis)
[5. Submission & Nilai Final]
```

### 7.2 Spesifikasi Proteksi Anti-Curang
1. **Single Device Enforcement:** Siswa tidak dapat login di 2 perangkat berbeda secara bersamaan.
2. **Tab-Switch & Blur Detection:** Setiap kali jendela browser kehilangan fokus, sistem memunculkan peringatan dan mengirim telemetri ke proktor.
3. **Aturan 3x Pelanggaran:** Jika siswa berpindah tab $\ge 3$ kali, layar ujian otomatis **terkunci permanen (BLOCKED)** dan membutuhkan persetujuan proktor untuk dibuka kembali.
4. **Keybinding Lock:** Memblokir tombol `F12` (Inspect Element), `Ctrl+U` (View Source), `Ctrl+C` (Copy), `Ctrl+V` (Paste), `Alt+Tab`, dan `Windows Key`.
5. **Anti-Debounce Auto-Save:** Setiap kali pilihan diklik, jawaban langsung disimpan ke database dengan latensi < 10 ms.

---

## BAB 8: MODUL DISPLAY PUBLIK & LIVE SCOREBOARD

### 8.1 Konfigurasi Layar Tayangan
* **Navigasi:** `Admin/Guru` $\rightarrow$ `Tampilan Publik` $\rightarrow$ `Pengaturan Layar`.
* **Fitur Slot Ujian:** Mendukung multi-jadwal dalam 1 layar bergantian secara otomatis (*carousel mode*).
* **Opsi Masking Nama:** Menyembunyikan 4 karakter tengah nama siswa demi privasi di area publik.
* **Token Hash Keamanan:** URL tayangan dilindungi enkripsi SHA-256 (`short_code` 8 digit) sehingga aman ditampilkan di smart TV tanpa login.

---

## BAB 9: STANDAR OPERASIONAL PROSEDUR (SOP) UJIAN

### 9.1 Tahap Pra-Ujian (H-1)
1. Admin memverifikasi integritas database melalui menu `Pengaturan` $\rightarrow$ `Backup Database`.
2. Pastikan seluruh siswa aktif memiliki status kelas yang valid.
3. Guru mengunggah bank soal dan mengunci kunci jawaban.
4. Admin merilis Jadwal Ujian dan mengaktifkan Token Sesi.

### 9.2 Tahap Saat Ujian Berlangsung (Hari H)
1. Proktor membuka halaman `Monitoring Proktor` di ruang kontrol.
2. Siswa login menggunakan NISN dan Password masing-masing.
3. **Penanganan Kendala Gawai:** Jika HP/Laptop siswa mati mendadak, Proktor membuka menu `Kunci Perangkat` $\rightarrow$ Klik **"Reset Login"** $\rightarrow$ Siswa login ulang di gawai pengganti $\rightarrow$ Jawaban sebelumnya tetap utuh 100%.
4. **Penanganan Siswa Terblokir:** Proktor memeriksa `Log Pelanggaran`, menanyai siswa, lalu menekan tombol **"Buka Blokir"** di monitoring.

### 9.3 Tahap Pasca-Ujian
1. Proktor memastikan seluruh status peserta telah berubah menjadi `finished`.
2. Jika ada siswa yang belum menekan tombol selesai saat jam berakhir, Proktor melakukan **"Selesaikan Paksa (Force Finish)"**.
3. Guru memeriksa jawaban esai di menu `Hasil Ujian` $\rightarrow$ `Koreksi Esai`.
4. Admin mengunduh Rekap Nilai dalam format Excel dan Berita Acara PDF.

---

## BAB 10: PANDUAN PEMECAHAN MASALAH (TROUBLESHOOTING)

| Gejala Masalah | Penyebab Utama | Langkah Penyelesaian Tuntas |
| :--- | :--- | :--- |
| **HTTP 500 saat buka halaman** | Tabel/kolom belum tersinkron di DB produksi. | Jalankan `mysql -u user -p cbt_nova_v2 < sql/sync_production_schema.sql`. |
| **Gagal Upload Foto Siswa** | Modul `php-fileinfo` / `php-gd` belum aktif. | Install modul: `sudo apt install php-fileinfo php-gd` lalu restart `php-fpm`. |
| **Jawaban Siswa Tidak Tersimpan** | Session timeout atau koneksi internet terputus. | Pastikan jaringan WiFi stabil; sistem otomatis menyimpan jawaban lokal (*localStorage*) dan me-retry pengiriman. |
| **Siswa Muncul "Perangkat Terkunci"** | Siswa pernah login di perangkat lain sebelumnya. | Admin/Proktor masuk ke `Monitoring` $\rightarrow$ `Device Lock` $\rightarrow$ Klik **"Reset Login"** pada baris siswa terkait. |
| **Layar Ujian Terkunci (Blocked)** | Siswa melanggar aturan keluar ujian $\ge 3$ kali. | Proktor memeriksa siswa $\rightarrow$ Klik **"Buka Blokir"** pada tabel monitoring. |

---

## BAB 11: STANDAR KEAMANAN SISTEM & APPSEC

1. **Proteksi Injeksi SQL:** 100% query basis data menggunakan *PDO Prepared Statements* dengan binding parameter tipe data ketat.
2. **Proteksi CSRF:** Setiap form POST dan request AJAX divalidasi menggunakan token `csrf_token` sesi unik.
3. **Validasi File Magic Bytes:** Pemeriksaan upload file menggunakan `finfo_open(FILEINFO_MIME_TYPE)` bukan sekadar ekstensi file.
4. **Enkripsi Sandi:** Menggunakan hashing standar industri `password_hash($pass, PASSWORD_BCRYPT)`.
5. **Session Fixation Prevention:** Regenerasi ID sesi secara berkala saat autentikasi berhasil.

---
*Dokumentasi Resmi CBT Nova v2 — Disusun oleh Tim Pengembang & Tim Reliability CBT SMAN 11 Surabaya.*
