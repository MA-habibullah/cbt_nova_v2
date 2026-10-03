---
description: CBT Nova Unified UI/UX Design System and Frontend Component Standards (WCAG 2.1 AAA Compliant)
globs: admin/**/*.php, guru/**/*.php, siswa/**/*.php, public/**/*.php
---

# CBT Nova — Unified UI/UX Design System & Front-End Standards
**Single Source of Truth (SSOT) — WCAG 2.1 AAA Compliant & Production Grade**

Standar antarmuka pengguna (UI), pengalaman pengguna (UX), responsivitas mobile, dan aksesibilitas terpadu untuk seluruh modul di aplikasi **CBT Nova** (Admin, Guru, Siswa, dan Public Display).

---

## 🎨 1. PRINSIP DASAR DESAIN, TIPOGRAFI & AKSESIBILITAS (WCAG 2.1 AAA)

1. **Rasio Kontras Warna Teks & Latar (WCAG AAA)**:
   - Rasio kontras teks normal minimal **7:1** (dan minimal **4.5:1** untuk teks tebal/besar).
   - **Larangan Keras**: Dilarang menggunakan teks abu-abu pudar seperti `#94a3b8` atau `#cbd5e1` di atas latar terang (`#ffffff` / `#f8fafc`).
   - **Palet Warna Teks Terstandarisasi**:
     - `Teks Primer / Judul / Header`: `#0f172a` atau `#1e293b` (Slate 900/800).
     - `Teks Sekunder / Body / Label Input`: `#334155` atau `#475569` (Slate 700/600).
     - `Teks Keterangan Redup (Muted Subtext)`: `#64748b` (Slate 500 - tetap memenuhi batas 4.5:1).
     - `Sidebar Heading / Nav Category`: `#475569` (Wajib terlihat jelas pada latar putih).
2. **Hirarki Tipografi & Truncation**:
   - Seluruh judul navbar menggunakan utilitas `.navbar-title-truncate` agar judul halaman tidak terpotong atau tumpang-tindih pada layar ponsel (360px–414px).
   - Font family utama: `Inter`, `-apple-system`, `BlinkMacSystemFont`, `"Segoe UI"`, `Roboto`, `sans-serif`.
3. **Scannable & Bebas Friksi**:
   - Informasi utama (nama item, kode, jumlah butir, status) harus langsung terbaca dalam 2 detik pertama tanpa membebani mata pengguna.
   - Tombol aksi primer diletakkan paling mudah dijangkau, sedangkan aksi sekunder/destruktif dirapikan dalam dropdown menu untuk menghemat ruang horizontal tabel.

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
   - Menggunakan transisi CSS transform yang halus.
   - Dilengkapi tombol tutup silang `<button type="button" class="btn-close d-lg-none" ...>` di bagian header sidebar agar pengguna mobile dapat menutup navigasi dengan 1 sentuhan.
3. **Container Viewport Safety**:
   - `#content { min-width: 0; max-width: 100%; overflow-x: hidden; }`
   - `#wrapper, body { overflow-x: hidden; }`

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
   - Format Dropdown Kelas: `[Jenjang] - [Nama Kelas]` (contoh: `10 - X IPA 1`).
   - Format Filter Jadwal/Ujian: Menggunakan label baku `Nama Ujian / Test`.

---

## 📑 6. STANDAR TABEL DATA & ISOLASI SCROLL HORIZONTAL

1. **Wajib Pembungkus `.table-responsive`**:
   - Seluruh tabel data wajib berada di dalam container `.table-responsive` dengan scroll horizontal terisolasi.
   - **Isolasi Scroll:** Dilarang membuat halaman atau `#wrapper` / `body` bergeser ke samping. Scroll horizontal hanya boleh terjadi di dalam kontainer tabel.
2. **Styling Header `<thead>`**:
   - Menggunakan kelas `.table-light.text-secondary.small.text-uppercase.fw-semibold` dengan latar `#f8fafc` / `#f1f5f9` dan `letter-spacing: 0.5px`.
3. **Lebar Minimum Tabel Berkolom Padat**:
   - Tabel dengan kolom $\ge 6$, aksi multi-tombol, atau tanggal wajib memiliki `style="min-width: 1000px;"` s/d `style="min-width: 1150px;"` agar isi kolom tidak tertekan (*squeezed*) atau patah baris berantakan.
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
   <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary fw-bold" style="width:28px;height:28px;font-size:12px;">
       <?= strtoupper(mb_substr($nama, 0, 1)) ?>
   </div>
   ```
3. **Sinkronisasi Progress Bar**:
   - Pembilang dan penyebut progress bar pengerjaan/soal wajib sinkron dengan kuota riil soal (`soal_ids` limit).

---

## ⚡ 9. MICRO-INTERACTIONS & JAVASCRIPT STANDARDS

1. **SweetAlert2 Confirmation**:
   - Seluruh aksi hapus dan nonaktifkan data wajib memicu SweetAlert2 dengan informasi detail nama item dan deteksi keterkaitan relasi data aktif.
2. **Empty State Component**:
   - Jika data kosong (`empty($listData)`), tampilkan placeholder informatif dengan icon `fa-3x opacity-25` dan tombol CTA pembuatan data baru.
