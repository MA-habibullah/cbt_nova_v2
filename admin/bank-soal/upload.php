<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data bank soal
$stmt_b = $pdo->prepare("SELECT b.*, s.nama_mapel, s.kode_mapel FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ?");
$stmt_b->execute([$id_bank]);
$bank = $stmt_b->fetch();

if (!$bank) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="<?= esc(BASE_URL) ?>admin/bank-soal/detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark">Import Soal Massal</h5>
                            <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"><?= esc($bank['kode_bank_soal']) ?></span>
                        </div>
                        <small class="text-muted"><?= esc($bank['nama_bank_soal']) ?> &bull; <?= esc($bank['nama_mapel']) ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <?php
            $msg = $_GET['msg'] ?? '';
            $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
            ?>
            <?php if ($msg === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center" role="alert">
                    <div class="fs-3 text-success me-3"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Import Butir Soal Berhasil!</h6>
                        <span class="small"><?= $count ?> butir soal berhasil diekstrak dan disimpan ke database bank soal.</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center" role="alert">
                    <div class="fs-3 text-danger me-3"><i class="fas fa-times-circle"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Import Gagal!</h6>
                        <span class="small">Terjadi kesalahan saat memproses file. Pastikan struktur kolom tabel sesuai template resmi.</span>
                        <?php if (!empty($_GET['error_info'])): ?>
                            <br><small class="text-muted font-monospace"><?= htmlspecialchars(urldecode($_GET['error_info'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error_data'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center" role="alert">
                    <div class="fs-3 text-warning me-3"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Data Belum Lengkap!</h6>
                        <span class="small">Pastikan berkas file sudah dipilih dan berformat .xlsx atau .docx.</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <!-- Left Column: Template & LaTeX Cheatsheet -->
                <div class="col-lg-5">
                    <!-- Template Card -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-3">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                <i class="fas fa-download fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Langkah 1: Unduh Template</h6>
                                <small class="text-muted">Gunakan template standar format resmi</small>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-secondary small mb-3">Silakan unduh template resmi di bawah ini dan isi data butir soal sesuai petunjuk kolom:</p>
                            
                            <div class="d-grid gap-2 mb-3">
                                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/generate_template_excel.php" class="btn btn-outline-success rounded-3 py-2 text-start d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-file-excel fs-4 text-success"></i>
                                        <div>
                                            <div class="fw-bold text-dark small">Template Excel (.xlsx)</div>
                                            <div class="text-muted" style="font-size: 11px;">Rekomendasi untuk soal teks & pilihan ganda</div>
                                        </div>
                                    </div>
                                    <i class="fas fa-download text-muted small"></i>
                                </a>

                                <a href="<?= esc(BASE_URL) ?>admin/bank-soal/exports/generate_template.php" class="btn btn-outline-primary rounded-3 py-2 text-start d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-file-word fs-4 text-primary"></i>
                                        <div>
                                            <div class="fw-bold text-dark small">Template Word (.docx)</div>
                                            <div class="text-muted" style="font-size: 11px;">Mendukung format tabel dan paragraf kaya</div>
                                        </div>
                                    </div>
                                    <i class="fas fa-download text-muted small"></i>
                                </a>
                            </div>

                            <div class="alert alert-warning border-0 rounded-3 p-3 small mb-0">
                                <div class="d-flex gap-2">
                                    <i class="fas fa-triangle-exclamation text-warning mt-1"></i>
                                    <div>
                                        <strong>Penting:</strong> Jangan mengubah baris judul kolom (header) pada template agar parser sistem membaca dengan presisi.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LaTeX Formula Guide -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-3">
                            <div class="p-2 bg-info-subtle text-info rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                <i class="fas fa-square-root-variable fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Rumus Matematika (LaTeX)</h6>
                                <small class="text-muted">Notasi KaTeX otomatis ter-render</small>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-secondary small mb-2">Ketik notasi LaTeX langsung pada teks soal atau opsi jawaban:</p>
                            <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                                <li class="p-2 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                    <span class="text-secondary">Pangkat / Akar:</span>
                                    <code class="text-primary">$x^2 + \sqrt{y} = z$</code>
                                </li>
                                <li class="p-2 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                    <span class="text-secondary">Pecahan:</span>
                                    <code class="text-primary">$\frac{a}{b}$</code>
                                </li>
                                <li class="p-2 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                    <span class="text-secondary">Display Blok:</span>
                                    <code class="text-primary">$$\int_0^\infty f(x)dx$$</code>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Upload Forms -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                    <i class="fas fa-cloud-arrow-up fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Langkah 2: Unggah File Soal</h6>
                                    <small class="text-muted">Pilih metode file yang ingin diimpor</small>
                                </div>
                            </div>
                            <!-- Tab Switcher -->
                            <ul class="nav nav-pills small" id="uploadTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active rounded-pill px-3 py-1 fw-semibold" id="excel-tab" data-bs-toggle="pill" data-bs-target="#excel-pane" type="button" role="tab">
                                        <i class="fas fa-file-excel me-1 text-success"></i> Excel (.xlsx)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-pill px-3 py-1 fw-semibold" id="word-tab" data-bs-toggle="pill" data-bs-target="#word-pane" type="button" role="tab">
                                        <i class="fas fa-file-word me-1 text-primary"></i> Word (.docx)
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="card-body p-4">
                            <div class="tab-content" id="uploadTabContent">
                                
                                <!-- Excel Pane -->
                                <div class="tab-pane fade show active" id="excel-pane" role="tabpanel">
                                    <form action="../bank-soal/controllers/proses-upload-soal.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                                        
                                        <div class="mb-4">
                                            <label class="form-label small fw-semibold text-secondary text-uppercase">Pilih File Spreadsheet Excel</label>
                                            <input type="file" name="file_soal" class="form-control rounded-3 py-2 border-secondary-subtle" accept=".xlsx, .xls" required>
                                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">Format berkas yang didukung: Microsoft Excel Worksheet (.xlsx, .xls)</small>
                                        </div>

                                        <div class="alert alert-info border-0 rounded-3 p-3 small mb-4 bg-primary-subtle text-primary-emphasis">
                                            <div class="d-flex gap-2">
                                                <i class="fas fa-circle-info mt-1"></i>
                                                <div>
                                                    <strong>Catatan Stimulus Gambar:</strong> Untuk butir soal yang memiliki gambar, Anda dapat langsung mengunggah gambar melalui TinyMCE editor di menu <em>Kelola Butir Soal</em> setelah proses import selesai.
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="import_soal" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                            <i class="fas fa-cloud-arrow-up"></i> Proses Import Soal Excel
                                        </button>
                                    </form>
                                </div>

                                <!-- Word Pane -->
                                <div class="tab-pane fade" id="word-pane" role="tabpanel">
                                    <form action="../bank-soal/controllers/proses-upload-word.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                                        
                                        <div class="mb-4">
                                            <label class="form-label small fw-semibold text-secondary text-uppercase">Pilih File Dokumen Word</label>
                                            <input type="file" name="file_soal" class="form-control rounded-3 py-2 border-secondary-subtle" accept=".docx, .doc" required>
                                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">Format berkas yang didukung: Microsoft Word Document (.docx, .doc)</small>
                                        </div>

                                        <div class="alert alert-info border-0 rounded-3 p-3 small mb-4 bg-primary-subtle text-primary-emphasis">
                                            <div class="d-flex gap-2">
                                                <i class="fas fa-circle-info mt-1"></i>
                                                <div>
                                                    <strong>Petunjuk Tabel Word:</strong> Pastikan tabel pada template tidak terhapus dan setiap butir soal berada dalam baris tabel yang rapi.
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="import_soal" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                            <i class="fas fa-cloud-arrow-up"></i> Proses Import Soal Word
                                        </button>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>