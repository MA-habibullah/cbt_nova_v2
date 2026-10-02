# ATURAN STRUKTUR WORKSPACE: LARANGAN MEMBUAT FOLDER 'docs/' DI ROOT

## 1. ATURAN UTAMA (STRICT RULE)
- **DILARANG KERAS** membuat folder `docs/` di root direktori project (`C:\laragon\www\cbt_nova\docs`).
- Seluruh file dokumentasi, panduan, laporan transformasi, audit keamanan, mockup HTML, walkthrough, maupun rencana implementasi **WAJIB** disimpan di dalam folder `scratch/docs/` (`C:\laragon\www\cbt_nova\scratch\docs/`).

## 2. PENGELOLAAN DOKUMEN & LAPORAN
- **Lokasi Dokumentasi Resmi & Rencana Kerja**: `scratch/docs/` (Implementation Plans, Walkthrough, Panduan).
- **Lokasi Laporan Security Audit**: **HANYA di `scratch/test_security_audit/`** dengan format inkremental `00X_security_audit_report.html` (DILARANG membuat salinan/duplikasi ke `scratch/docs/`).
- **Lokasi Script Verifikasi/Lint/Scratch**: `scratch/`

## 3. PENCEGAHAN REGRESI
- Setiap kali agent membuat file `.md`, `.html`, atau dokumen baru yang bersifat referensi atau laporan, pastikan path target selalu mengarah ke `scratch/docs/` (atau `scratch/test_security_audit/` untuk audit) dan TIDAK PERNAH membuat folder `docs` di root.
