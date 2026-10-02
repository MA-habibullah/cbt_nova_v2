---
description: CBT Nova Git Release Protocol & Atomic Commits Workflow
globs: **/*
---

# CBT Nova — Git Release Protocol & Atomic Commits Workflow

Aturan baku dan protokol wajib bagi Antigravity AI ketika melakukan staging, committing, dan pushing kode ke repositori Git/GitHub di proyek **CBT Nova**.

---

## 📌 1. PRINSIP ATOMIC COMMITS (DILARANG MONOLITHIC COMMIT)

Setiap instruksi push ke GitHub **TIDAK BOLEH** menggabungkan seluruh perubahan berbeda domain ke dalam 1 commit tunggal (`git commit -am "update"` dilarang keras).

Perubahan **WAJIB DIPISAHKAN** secara modular berdasarkan domain/fitur/tujuannya:
- **1 Modul / 1 Fitur / 1 Bugfix = 1 Commit Terpisah.**
- Pengguna dapat melacak, mengaudit, dan me-revert fitur secara independen tanpa mempengaruhi fitur lain.

---

## 🏷️ 2. KONVENSI PESAN COMMIT (CONVENTIONAL COMMITS)

Gunakan format standar Conventional Commits:
`<type>(<scope>): <deskripsi singkat perubahan dalam huruf kecil>`

### Prefix Tipe Commit:
- **`feat`**     : Penambahan fitur atau modul baru (contoh: `feat(recalculate): implement batch score recalculation`).
- **`fix`**      : Perbaikan bug fungsional, logika, scoring, atau database (contoh: `fix(scoring): upgrade multi-type scoring engine`).
- **`ui/ux`**    : Perbaikan styling CSS, kontras warna, accessibility, atau layout shift (contoh: `ui/ux(table): enhance contrast for dark badges`).
- **`perf`**     : Optimasi performa, query N+1, debounce, atau pengurangan beban server.
- **`refactor`** : Pembersihan kode atau modularisasi fungsi tanpa mengubah perilaku output.
- **`docs`**     : Pembaruan dokumentasi atau panduan Markdown.

---

## 🛠️ 3. SOP WORKFLOW SEBELUM & SAAT PUSH

Sebelum melakukan commit dan push, Antigravity **WAJIB** menjalankan tahapan berikut:

### Langkah 1: Audit Status Perubahan
```bash
git status
git diff --stat
```
Petakan seluruh file yang dimodifikasi ke dalam kelompok commit atomic yang logis.

### Langkah 2: Verifikasi Sintaks (Zero Syntax Error Guard)
Jalankan linting sintaks PHP secara menyeluruh:
```powershell
powershell -ExecutionPolicy Bypass -File scratch/lint_check.ps1
```
Pastikan output menunjukkan **0 Syntax Error** sebelum melanjutkan.

### Langkah 3: Staging & Commit Modular (Satu per Satu)
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

### Langkah 4: Push ke Remote Repository
```bash
git push origin HEAD
```

### Langkah 5: Tampilkan Bukti Riwayat Git
Tampilkan riwayat commit terbaru untuk memvalidasi pemisahan commit:
```bash
git log -n <jumlah_commit> --oneline
```
