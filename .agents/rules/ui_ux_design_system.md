---
description: CBT Nova Unified UI/UX Design System and Frontend Component Standards
globs: admin/**/*.php, guru/**/*.php, siswa/**/*.php, public/**/*.php
---

# CBT Nova — Unified UI/UX Design System & Frontend Component Standards

Standar antarmuka pengguna (UI) dan pengalaman pengguna (UX) terpadu untuk seluruh modul di aplikasi **CBT Nova** (Admin, Guru, Siswa, dan Public Display), diadaptasi dan distandarisasi dari implementasi terbaik di `admin/bank-soal/index.php`.

---

## 🎨 1. PRINSIP DASAR DESAIN (CORE DESIGN PRINCIPLES)

1. **Scannable & Hierarkis**: Informasi utama (nama item, kode, jumlah butir, status) harus langsung terbaca dalam 2 detik pertama tanpa membebani mata pengguna.
2. **Ergonomis & Bebas Friksi**: Tombol aksi primer diletakkan paling mudah dijangkau, sedangkan aksi sekunder/destruktif dirapikan dalam dropdown menu untuk menghemat ruang horizontal tabel.
3. **Responsif & Bebas Overflow Horizontal**: Seluruh grid, filter toolbar, dan kartu metrik wajib beradaptasi dengan sempurna pada resolusi desktop standar (1366×768), tablet, maupun layar lebar.
4. **Proteksi Human-Error**: Setiap aksi berbahaya (hapus data, nonaktifkan, kunci akses) wajib diverifikasi melalui SweetAlert2 interaktif dengan pesan peringatan relasi data.

---

## 📊 2. ANATOMI HALAMAN STANDAR (PAGE ANATOMY)

Setiap halaman modul utama di CBT Nova harus mengikuti urutan layout berikut:

```
┌────────────────────────────────────────────────────────────────────────┐
│ TOP NAVBAR: Title, Subtitle, Toggle Sidebar, User Profile              │
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
│ PAGINATION & FOOTER: Info Baris "X-Y dari Z" + Pagination Bullets       │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 💎 3. KATALOG KOMPONEN & KELAS BOOTSTRAP RESMI

### A. Kartu Metrik Ringkas (Top Overview Metric Cards)
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

### B. Action Bar & Unified Filter Toolbar
Satukan area kontrol ke dalam card yang rapi dan seragam:
```html
<!-- Action Bar -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes text-primary me-2"></i>Daftar Item</h6>
            <span class="badge bg-light text-secondary border px-2 py-1"><?= $total ?> item ditemukan</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button class="btn btn-sm btn-outline-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalSecondary">
                <i class="fas fa-file-export me-1"></i> Export / Backup
            </button>
            <button class="btn btn-sm btn-primary shadow-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus-circle me-1"></i> Tambah Data Baru
            </button>
        </div>
    </div>
</div>

<!-- Unified Filter Form -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <!-- Search Input -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-search me-1"></i>Pencarian</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Ketik kata kunci..." value="<?= esc($search) ?>">
            </div>
            <!-- Dropdown Filters (Col-lg-2) -->
            <!-- Limit Baris Dropdown (10, 25, 50, 100, default: 50) -->
            <!-- Tombol Terapkan & Reset (Col-lg-2) -->
        </form>
    </div>
</div>
```

---

### C. Standard Table Layout & Badge System
Tabel menggunakan thead abu-abu terang dengan teks semi-bold uppercase:
```html
<div class="card border-0 shadow-sm rounded-3 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                <tr>
                    <th class="text-center py-3" width="50">#</th>
                    <th class="py-3">Identitas Utama</th>
                    <th class="py-3">Kategori / Relasi</th>
                    <th class="py-3 text-center">Komposisi / Data</th>
                    <th class="py-3 text-center">Status</th>
                    <th class="py-3">Waktu</th>
                    <th class="text-center py-3" width="180">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data Rows -->
            </tbody>
        </table>
    </div>
    <!-- Footer Pagination -->
</div>
```

**Standard Badge Styles:**
- **Kode Unik / NISN / NIP / Username**: `.badge.bg-primary-subtle.text-primary.border.border-primary-subtle.font-monospace`
- **Tingkat Kelas / Jenjang**: `.badge.bg-info-subtle.text-info-emphasis.border.border-info-subtle.px-2.py-1.rounded-2`
- **Status Aktif / Terbuka**: `.badge.bg-success-subtle.text-success.border.border-success-subtle.px-2.py-1.rounded-pill`
- **Status Non-Aktif / Terkunci**: `.badge.bg-danger-subtle.text-danger.border.border-danger-subtle.px-2.py-1.rounded-pill`
- **Avatar Inisial Guru / Pengguna**:
  ```html
  <div class="avatar-placeholder rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary fw-bold" style="width:28px;height:28px;font-size:12px;">
      <?= strtoupper(mb_substr($nama, 0, 1)) ?>
  </div>
  ```

---

### D. Action Buttons & Dropdown Menu
Kelompokkan aksi agar tidak melebar:
```html
<div class="d-flex align-items-center justify-content-center gap-1">
    <!-- Tombol Primer (Paling Sering Digunakan) -->
    <a href="detail.php?id=<?= $id ?>" class="btn btn-sm btn-primary fw-semibold px-2">
        <i class="fas fa-list-check me-1"></i> Kelola
    </a>
    
    <!-- Menu Dropdown Aksi Sekunder -->
    <div class="dropdown">
        <button class="btn btn-sm btn-light border dropdown-toggle shadow-none" type="button" data-bs-toggle="dropdown">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
            <li><button class="dropdown-item btn-edit" data-id="<?= $id ?>"><i class="fas fa-edit text-primary me-2"></i> Edit</button></li>
            <li><button class="dropdown-item btn-toggle"><i class="fas fa-lock text-warning me-2"></i> Kunci/Buka</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><a href="javascript:void(0)" class="dropdown-item text-danger btn-hapus" data-id="<?= $id ?>"><i class="fas fa-trash-alt me-2"></i> Hapus</a></li>
        </ul>
    </div>
</div>
```

---

## ⚡ 4. MICRO-INTERACTIONS & JAVASCRIPT STANDARDS

1. **SweetAlert2 Confirmation**:
   - Seluruh aksi hapus dan nonaktifkan data wajib memicu SweetAlert2 dengan informasi detail nama item dan deteksi keterkaitan relasi (misal relasi jadwal ujian/jawaban siswa).
2. **Double-Submit Protection on Forms**:
   - Pada form submit modal, tombol wajib otomatis disabled dan menampilkan spinner:
   ```javascript
   $('#formTambah').on('submit', function() {
       $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...');
   });
   ```
3. **Empty State Component**:
   - Jika `empty($listData)`, tampilkan placeholder informatif dengan icon `fa-3x opacity-25` dan tombol CTA pembuatan data baru.
