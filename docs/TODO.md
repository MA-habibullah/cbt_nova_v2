# CBT Nova — Performance Improvement Checklist

> Lihat detail implementasi di [`PERFORMANCE_PLAN.md`](./PERFORMANCE_PLAN.md)
> Format status: `[ ]` belum · `[~]` sedang · `[x]` selesai

---

## Phase 1 — SQL: Database Indexes
> File: `sql/performance_improvements.sql`
> Eksekusi: `mysql -u root -p cbt_nova < sql/performance_improvements.sql`

- [x] Jalankan BAGIAN 1 (diagnostic) dan catat baseline index yang ada
- [x] Jalankan BAGIAN 2 (apply index dari backup yang mungkin belum ada)
- [x] Jalankan BAGIAN 3 (tambah composite index baru `idx_exams_teacher_mulai`)
- [x] Jalankan BAGIAN 5 (verify hasil akhir)
- [x] Konfirmasi: `SHOW INDEX FROM cbt_student_answers` → ada `idx_ans_part_question`
- [x] Konfirmasi: `SHOW INDEX FROM cbt_exam_participants` → ada `idx_part_exam_student`
- [x] Konfirmasi: `SHOW INDEX FROM cbt_exams` → ada `idx_exams_teacher_mulai`

---

## Phase 2 — Fix `DATE()` → `BETWEEN`

- [x] `admin/ajax-dashboard.php`
  - [x] Tambah `$today_start` dan `$today_end` di baris ~12
  - [x] Ganti `DATE(e.mulai_pada) = ?` → `BETWEEN` di baris 22
  - [x] Update `execute()` baris 26: tambah 2 parameter
  - [x] Ganti `DATE(e.mulai_pada) = ?` → `BETWEEN` di baris 32
  - [x] Update `execute()` baris 35
  - [x] Ganti `DATE(e.mulai_pada) = ?` → `BETWEEN` di baris 42
  - [x] Update `execute()` baris 46

- [x] `admin/index.php`
  - [x] Tambah `$today_start` dan `$today_end` di baris ~12
  - [x] Ganti 3 query DATE() → BETWEEN (baris 28, 34, 43)
  - [x] Update semua `execute()` terkait

- [x] `siswa/ajax/view_dashboard.php`
  - [x] Ganti `DATE(e.mulai_pada) = CURDATE()` → `BETWEEN ? AND ?` di baris 32
  - [x] Update `execute([$student_id])` → `execute([$student_id, $start, $end])`

- [x] `admin/monitoring/fetch_mapel.php`
  - [x] Ganti `DATE(mulai_pada) = ?` → `BETWEEN`
  - [x] Update execute()

- [x] `guru/monitoring/fetch_mapel.php`
  - [x] Ganti `DATE(mulai_pada) = ?` → `BETWEEN`
  - [x] Update execute()

- [x] **Verify:** `grep -rn "DATE(" admin/ajax-dashboard.php admin/index.php siswa/ajax/view_dashboard.php admin/monitoring/fetch_mapel.php guru/monitoring/fetch_mapel.php` → tidak ada hasil
- [x] Test: buka admin dashboard → data hari ini muncul benar
- [x] Test: login siswa → daftar ujian hari ini muncul

---

## Phase 3 — Kurangi Polling Interval

- [x] `siswa/ajax/view_ujian.php` baris 199: ubah `10000` → `30000`
- [x] `admin/sistem/system-info.php` baris 403: ubah `3000` → `5000`
- [x] **Verify:** `grep -n "10000" siswa/ajax/view_ujian.php` → tidak ada hasil
- [x] **Verify:** `grep -n "3000" admin/sistem/system-info.php` → tidak ada hasil
- [x] Test browser DevTools: `ajax_get_waktu.php` dipanggil setiap 30s

---

## Phase 4 — Ganti Session File Scan dengan Query DB

- [x] `admin/sistem/system-info-data.php`
  - [x] Ganti seluruh fungsi `getActiveUsers()` dengan versi query DB
  - [x] Pastikan variabel return (`active_siswa`, `active_guru`, dll.) tetap sama
- [x] **Verify:** `grep -n "glob\|file_get_contents" admin/sistem/system-info-data.php` → tidak ada di fungsi `getActiveUsers`
- [x] Test: panel "Pengguna Login Aktif" di Informasi Sistem masih tampil angka
- [x] Test: angka bertambah saat user baru login

---

## Phase 5 — Gabungkan Query di `ajax_save_jawaban.php`

- [x] `siswa/ajax_save_jawaban.php`
  - [x] Ganti query deadline (baris 32-41) + query partisipasi (baris 73-76) dengan 1 query gabungan
  - [x] Pastikan semua variabel yang dipakai tersedia dari `$row` hasil query gabungan
  - [x] Hapus variabel `$deadlineData` dan `$participant` yang tidak lagi terpisah
- [x] Test: simpan jawaban PG → sukses
- [x] Test: simpan jawaban PG Kompleks → sukses
- [x] Test: simpan jawaban Menjodohkan → sukses
- [x] Test: simpan jawaban Isian → sukses
- [x] Test: jawab setelah waktu habis → response `timeout`
- [x] Test: akun blocked → response `blocked`

---

## Phase 6 — Cache Nav di Session (`ajax_get_nav.php`)

- [x] `siswa/ajax_get_nav.php`
  - [x] Tambah blok cache session setelah validasi role (sebelum query pertama)
  - [x] Gunakan `$cache_key = "nav_{$exam_id}_{$student_id}"`
  - [x] Cache: `participant_id` + `ids` (ordered question IDs)
  - [x] Hanya jalankan 3 query + shuffle jika cache belum ada
- [x] Test: navigasi soal 1→5→3→1 → urutan konsisten
- [x] Test: status answered/ragu update benar setelah simpan jawaban
- [x] Test: tidak ada error session-related di PHP error log

---

## Phase 7 — OPcache Docker

- [x] Buat file `docker/php-apache/opcache.ini` (lihat PERFORMANCE_PLAN.md untuk isi)
- [x] Edit `docker/php-apache/Dockerfile`:
  - [x] Tambah `opcache` ke baris `docker-php-ext-install`
  - [x] Tambah `COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini`
- [x] `docker compose build` → berhasil tanpa error
- [x] `docker compose up -d` → container running
- [x] **Verify:** buka `/info.php` sementara → cari "OPcache" → "Opcode caching: Enabled"
- [x] Hapus `/info.php` setelah verifikasi

---

## Phase 8 — Cache Header Aset Statis

- [x] `.htaccess`: tambahkan blok `mod_expires` + `mod_headers` setelah `RewriteRule`
- [x] **Verify:** DevTools → Network → `assets/*.css` ada header `Cache-Control: public, max-age=604800`
- [x] **Verify:** reload kedua → status `304 Not Modified` untuk aset statis

---

## Phase 9 — Kurangi `log_activity` dari Page Load

- [x] `guru/hasil/index.php` baris 10: hapus `log_activity('Akses halaman Hasil Ujian', ...)`
- [x] `guru/monitoring/index.php` baris 10: hapus `log_activity('Akses halaman Monitoring Peserta', ...)`
- [x] **Verify:** `grep -n "Akses halaman" guru/hasil/index.php guru/monitoring/index.php` → tidak ada hasil
- [x] Test: buka halaman Hasil Ujian guru → tidak ada error
- [x] Test: buka halaman Monitoring guru → tidak ada error
- [x] Test: login/logout masih masuk ke `cbt_activity_logs`

---

## Phase 10 — Lazy-Load KaTeX

- [x] `siswa/index.php`
  - [x] Hapus `<link>` KaTeX CSS dari `<head>`
  - [x] Hapus 2 `<script>` KaTeX dari sebelum `</body>`
  - [x] Ganti fungsi `renderMath()` dengan versi lazy-load (lihat PERFORMANCE_PLAN.md)
- [x] Test: buka soal tanpa formula → Network tab tidak ada request `katex.min.js`
- [x] Test: buka soal dengan formula `$x^2$` → KaTeX ter-load dan render benar
- [x] Test: pindah ke soal lain dengan formula → KaTeX tidak di-load ulang
- [x] Tidak ada JS error di console

---

## Phase 11 — Final Verification

### Grep checks:
```bash
# Tidak ada DATE() di hot-path files
grep -rn "DATE(" admin/ajax-dashboard.php admin/index.php \
     siswa/ajax/view_dashboard.php \
     admin/monitoring/fetch_mapel.php guru/monitoring/fetch_mapel.php

# Interval sudah diubah
grep -n "10000" siswa/ajax/view_ujian.php
grep -n ", 3000" admin/sistem/system-info.php

# OPcache di Dockerfile
grep "opcache" docker/php-apache/Dockerfile

# Tidak ada log_activity page load
grep -n "Akses halaman" guru/hasil/index.php guru/monitoring/index.php

# Tidak ada session scan
grep -n "glob\|file_get_contents" admin/sistem/system-info-data.php
```

### Functional test end-to-end:
- [x] Login siswa → dashboard tampil
- [x] Mulai ujian → soal muncul, timer berjalan
- [x] Simpan jawaban → nav update (kotak nomor berubah warna)
- [x] Centang ragu-ragu → kotak berubah kuning
- [x] Pindah soal bolak-balik → urutan konsisten
- [x] Selesai ujian → skor tersimpan, redirect dashboard
- [x] Login guru → monitoring tampil data
- [x] Login admin → dashboard + system info tampil

---

## Ringkasan Progress

| Phase | Judul | Status |
|---|---|---|
| 1 | SQL: Database Indexes | `[x]` |
| 2 | Fix DATE() → BETWEEN | `[x]` |
| 3 | Kurangi Polling Interval | `[x]` |
| 4 | Ganti Session Scan → DB Query | `[x]` |
| 5 | Merge Query save_jawaban | `[x]` |
| 6 | Cache Nav di Session | `[x]` |
| 7 | OPcache Docker | `[x]` |
| 8 | Cache Header .htaccess | `[x]` |
| 9 | Kurangi log_activity | `[x]` |
| 10 | Lazy-Load KaTeX | `[x]` |
| 11 | Final Verification | `[x]` |
