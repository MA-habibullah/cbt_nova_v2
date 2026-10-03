---
description: CBT Nova Unified UI/UX Design System and Frontend Component Standards (WCAG 2.1 AAA Compliant & Multi-Device Exam Standards)
globs: admin/**/*.php, guru/**/*.php, siswa/**/*.php, public/**/*.php
---

# CBT Nova — Unified UI/UX Design System & Front-End Standards
**Single Source of Truth (SSOT) — WCAG 2.1 AAA Compliant, Multi-Device & Production Grade**

Panduan dan standar baku antarmuka pengguna (UI), pengalaman pengguna (UX), responsivitas mobile, aksesibilitas, layout dokumen cetak A4, display publik, dan ruang ujian siswa untuk seluruh modul di aplikasi **CBT Nova** (`admin/`, `guru/`, `siswa/`, `public/`).

---

## 🎨 1. PRINSIP DASAR DESAIN, TIPOGRAFI & AKSESIBILITAS (WCAG 2.1 AAA)

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

## 📐 2. ANATOMI HALAMAN STANDAR (PAGE ANATOMY)

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

## 📱 3. STANDAR SHELL LAYOUT & NAVIGASI SIDEBAR

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

## 📊 4. KARTU METRIK RINGKAS (TOP OVERVIEW METRIC CARDS)

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

## 🔍 5. STANDAR ACTION BAR, FILTER TOOLBAR & TOUCH TARGETS

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

## 📑 6. STANDAR TABEL DATA & ISOLASI SCROLL HORIZONTAL

1. **Wajib Pembungkus `.table-responsive`**:
   - Seluruh tabel data wajib berada di dalam container `.table-responsive` dengan scroll horizontal terisolasi.
   - **Isolasi Scroll:** Dilarang membuat halaman atau `#wrapper` / `body` bergeser ke samping. Scroll horizontal hanya boleh terjadi di dalam kontainer tabel.
2. **Styling Header `<thead>`**:
   - Menggunakan kelas `.table-light.text-secondary.small.text-uppercase.fw-semibold` dengan latar `#f8fafc` / `#f1f5f9` dan `letter-spacing: 0.5px`.
3. **Lebar Minimum Tabel Berkolom Padat**:
   - Tabel dengan kolom $\ge 6$, aksi multi-tombol, token, atau rincian tanggal wajib memiliki `style="min-width: 1000px;"` s/d `style="min-width: 1150px;"` agar isi kolom tidak tertekan (*squeezed*) atau patah baris berantakan.
4. **Scrollbar Ramping & Elegan**:
   ```css
   .table-responsive::-webkit-scrollbar { height: 7px; }
   .table-responsive::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
   .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
   .table-responsive::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
   ```

---

## 🪟 7. STANDAR POP-UP & MODAL DIALOG

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

## 🏷️ 8. STANDAR BADGE, PILLS, PROGRESS BAR & AVATAR

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

## ⚡ 9. MICRO-INTERACTIONS, STATE ASINKRON & JAVASCRIPT STANDARDS

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

## 📝 10. STANDAR ANTARMUKA UJIAN SISWA (EXAM PORTAL & WORKSPACE)

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

## 🖨️ 11. STANDAR MODE CETAK KERTAS & DOKUMEN PDF (A4 PRINT ENGINE)

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

## 📺 12. STANDAR DISPLAY PUBLIK (LAYAR TV / PROYEKTOR TOKEN)

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
