<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/database.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = $_SESSION['teacher_id'];

$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verifikasi bank soal milik guru ini
$stmt_bank = $pdo->prepare("SELECT b.*, s.nama_mapel FROM cbt_bank_soal b JOIN cbt_subjects s ON b.subject_id = s.id WHERE b.id = ? AND b.teacher_id = ?");
$stmt_bank->execute([$id_bank, $teacher_id]);
$bank = $stmt_bank->fetch();

if (!$bank) { header("Location: index.php"); exit; }

$locked = ($bank['status'] === 'nonaktif');

// Filter & Pagination
$search      = trim($_GET['q'] ?? '');
$filter_tipe = $_GET['tipe'] ?? '';
$_show       = (int)($_GET['show'] ?? 10);
$limit       = in_array($_show, [10, 25, 50]) ? $_show : 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $limit;

$where_sql = "WHERE bank_soal_id = ?";
$params    = [$id_bank];

if (!empty($filter_tipe)) {
    $where_sql .= " AND tipe = ?";
    $params[] = $filter_tipe;
}
if (!empty($search)) {
    $where_sql .= " AND konten_soal LIKE ?";
    $params[] = '%' . like_escape($search) . '%';
}

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_questions $where_sql");
$total_stmt->execute($params);
$total_soal = (int)$total_stmt->fetchColumn();
$pages = $limit > 0 ? (int)ceil($total_soal / $limit) : 1;

$stmt_list = $pdo->prepare("SELECT * FROM cbt_questions $where_sql ORDER BY id ASC LIMIT ? OFFSET ?");
$stmt_list->execute(array_merge($params, [$limit, $offset]));
$listSoal = $stmt_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../../includes/header.php'; ?>
<style>
    .soal-konten p, .options-container p { display: inline; margin-bottom: 0; }
    .soal-konten ol, .soal-konten ul,
    .options-container ol, .options-container ul {
        padding-left: 1.5rem;
        margin-top: 0.4rem;
        margin-bottom: 0.4rem;
        list-style-type: revert;
    }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center">
                <a href="detail.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3 shadow-sm">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="mb-0 fw-bold text-primary">Data Soal: <?= htmlspecialchars($bank['nama_mapel']) ?></h5>
                    <small class="text-muted"><?= htmlspecialchars($bank['nama_bank_soal'] ?? '') ?></small>
                </div>
                <?php if ($locked): ?>
                    <span class="badge bg-danger ms-3 py-2 px-3"><i class="fas fa-lock me-1"></i> Dikunci Admin</span>
                <?php endif; ?>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">

            <?php if ($locked): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-lock fa-2x me-3"></i>
                <div>
                    <strong class="d-block">Bank Soal Terkunci!</strong>
                    Admin telah mengunci bank soal ini. Tambah, edit, dan hapus soal tidak dapat dilakukan.
                    Silakan hubungi admin untuk membuka kunci.
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg'])): $m = $_GET['msg']; ?>
            <div class="alert alert-<?= in_array($m, ['success','updated']) ? 'success' : 'danger' ?> alert-dismissible fade show shadow-sm border-0 mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <?php
                    if ($m === 'success') echo 'Data soal berhasil disimpan!';
                    if ($m === 'updated') echo 'Perubahan soal berhasil disimpan!';
                    if ($m === 'deleted') echo 'Soal berhasil dihapus!';
                    if ($m === 'locked')  echo 'Bank soal dikunci oleh admin, tidak dapat diubah.';
                    if ($m === 'error')   echo 'Terjadi kesalahan sistem.';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-5 d-flex gap-2">
                            <?php if (!$locked): ?>
                            <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahSoal">
                                <i class="fas fa-plus me-2"></i> Tambah Manual
                            </button>
                            <a href="upload.php?id=<?= esc($id_bank) ?>" class="btn btn-success shadow-sm fw-bold">
                                <i class="fas fa-file-import me-2"></i> Import Soal
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary fw-bold" disabled>
                                <i class="fas fa-lock me-2"></i> Tambah Manual
                            </button>
                            <button class="btn btn-secondary fw-bold" disabled>
                                <i class="fas fa-lock me-2"></i> Import Soal
                            </button>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-7">
                            <form method="GET" class="row g-2 justify-content-end">
                                <input type="hidden" name="id" value="<?= esc($id_bank) ?>">
                                <div class="col-md-4">
                                    <select name="tipe" class="form-select border-primary-subtle" onchange="this.form.submit()">
                                        <option value="">-- Semua Tipe --</option>
                                        <option value="pg"           <?= esc($filter_tipe=='pg'?'selected':'') ?>>Pilihan Ganda</option>
                                        <option value="pg_kompleks"  <?= esc($filter_tipe=='pg_kompleks'?'selected':'') ?>>PG Kompleks</option>
                                        <option value="isian"        <?= esc($filter_tipe=='isian'?'selected':'') ?>>Isian Singkat</option>
                                        <option value="benar_salah"  <?= esc($filter_tipe=='benar_salah'?'selected':'') ?>>Benar / Salah</option>
                                        <option value="menjodohkan"  <?= esc($filter_tipe=='menjodohkan'?'selected':'') ?>>Menjodohkan</option>
                                        <option value="essay"        <?= esc($filter_tipe=='essay'?'selected':'') ?>>Essay</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <div class="input-group">
                                        <input type="text" name="q" class="form-control border-primary-subtle" placeholder="Cari konten soal..." value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <?php if (!empty($search) || !empty($filter_tipe)): ?>
                                <div class="col-auto">
                                    <a href="?id=<?= esc($id_bank) ?>" class="btn btn-outline-secondary"><i class="fas fa-sync-alt"></i></a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($listSoal)): ?>
            <div class="text-center py-5 bg-white rounded shadow-sm border">
                <i class="fas fa-file-lines fa-3x text-muted opacity-50 mb-3"></i>
                <p class="text-muted">Belum ada butir soal di bank soal ini.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($listSoal as $s): ?>
            <div class="card border-0 shadow-sm mb-3 overflow-hidden">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom-0">
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase me-2">
                            <?= str_replace('_', ' ', $s['tipe']) ?>
                        </span>
                        <span class="badge bg-light text-dark border me-2">Bobot: <?= $s['bobot_skor'] ?></span>
                        <span class="badge bg-<?= ($s['tingkat_kesulitan']=='sulit'?'danger':($s['tingkat_kesulitan']=='sedang'?'warning':'success')) ?>-subtle text-dark border">
                            <?= ucfirst($s['tingkat_kesulitan']) ?>
                        </span>
                    </div>
                    <?php if (!$locked): ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-white border shadow-sm text-warning" onclick="editSoal(<?= esc($s['id']) ?>)">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-white border shadow-sm text-danger" onclick="hapusSoal(<?= esc($s['id']) ?>)">
                            <i class="fas fa-trash me-1"></i> Hapus
                        </button>
                    </div>
                    <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fas fa-lock me-1"></i> Terkunci</span>
                    <?php endif; ?>
                </div>
                <div class="card-body pt-0">
                    <div class="soal-konten p-3 bg-light rounded mb-3 border-start border-1">
                        <?= inject_domain_to_html($s['konten_soal']) ?>
                    </div>
                    <div class="row g-2">
                        <?php
                        $stmt_opt = $pdo->prepare("SELECT * FROM cbt_question_options WHERE question_id = ? ORDER BY id ASC");
                        $stmt_opt->execute([$s['id']]);
                        foreach ($stmt_opt->fetchAll() as $opt): ?>
                        <?php if ($s['tipe'] === 'menjodohkan'): ?>
                        <div class="col-12">
                            <div class="p-2 border rounded bg-success-subtle border-success small d-flex align-items-center gap-2 flex-wrap">
                                <div class="flex-fill soal-konten"><?= inject_domain_to_html($opt['label']) ?></div>
                                <i class="fas fa-arrow-right text-muted flex-shrink-0 mx-1"></i>
                                <div class="flex-fill soal-konten"><?= inject_domain_to_html($opt['value_target']) ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-md-6">
                            <div class="p-2 border rounded <?= $opt['is_correct'] ? 'bg-success-subtle border-success' : 'bg-white' ?> small">
                                <span class="fw-bold me-2"><?= $opt['label'] ?>.</span>
                                <?= inject_domain_to_html($opt['value_target']) ?>
                                <?php if ($opt['is_correct']): ?>
                                    <i class="fas fa-check-circle text-success float-end mt-1"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':'' ?>">
                        <a class="page-link" href="?id=<?= esc($id_bank) ?>&page=<?= esc($page-1) ?>&q=<?= esc(urlencode($search)) ?>&tipe=<?= esc(urlencode($filter_tipe)) ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php $prev=null; foreach(range(1,$pages) as $i):
                        if($i!=1 && $i!=$pages && abs($i-$page)>2){
                            if($prev!==null && abs($prev-$i)==1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            $prev=$i; continue;
                        } ?>
                    <li class="page-item <?= $page==$i?'active':'' ?>">
                        <a class="page-link" href="?id=<?= esc($id_bank) ?>&page=<?= esc($i) ?>&q=<?= esc(urlencode($search)) ?>&tipe=<?= esc(urlencode($filter_tipe)) ?>"><?= esc($i) ?></a>
                    </li>
                    <?php $prev=$i; endforeach; ?>
                    <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
                        <a class="page-link" href="?id=<?= esc($id_bank) ?>&page=<?= esc($page+1) ?>&q=<?= esc(urlencode($search)) ?>&tipe=<?= esc(urlencode($filter_tipe)) ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
            <div class="text-center text-muted small mt-1">
                Menampilkan <?= count($listSoal) ?> dari <?= $total_soal ?> butir soal
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Tambah Soal -->
<div class="modal fade" id="modalTambahSoal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="controllers/proses-soal.php" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Input Soal Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Pertanyaan Soal</label>
                        <textarea name="konten_soal" id="editorSoal" class="form-control" rows="8" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0 p-3 mb-3">
                            <label class="form-label fw-bold">Pengaturan Soal</label>
                            <div class="mb-3">
                                <label class="small text-muted">Tipe Soal</label>
                                <select name="tipe" id="tipeSoal" class="form-select" onchange="renderInputJawaban()" required>
                                    <option value="pg">Pilihan Ganda</option>
                                    <option value="pg_kompleks">Pilihan Ganda Kompleks</option>
                                    <option value="isian">Isian Singkat</option>
                                    <option value="benar_salah">Benar / Salah</option>
                                    <option value="menjodohkan">Menjodohkan</option>
                                    <option value="essay">Essay / Uraian</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted">Bobot Skor</label>
                                <input type="number" name="bobot_skor" class="form-control" value="1.00" step="0.01">
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted">Tingkat Kesulitan</label>
                                <select name="tingkat_kesulitan" class="form-select">
                                    <option value="mudah">Mudah</option>
                                    <option value="sedang" selected>Sedang</option>
                                    <option value="sulit">Sulit</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <h6 class="fw-bold mb-3">Konfigurasi Jawaban</h6>
                        <div id="containerJawaban" class="p-3 border rounded bg-white"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="simpan_soal" class="btn btn-primary px-5 fw-bold">Simpan Soal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Soal -->
<div class="modal fade" id="modalEditSoal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="controllers/proses-soal.php" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Edit Soal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="soal_id" id="edit_soal_id">
                <input type="hidden" name="bank_soal_id" value="<?= esc($id_bank) ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Pertanyaan Soal</label>
                        <textarea name="konten_soal" id="editorEditSoal" class="form-control" rows="8"></textarea>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0 p-3 mb-3">
                            <label class="form-label fw-bold mb-3">Pengaturan Soal</label>
                            <div class="mb-3" style="display:none">
                                <label class="small text-muted fw-bold">Tipe Soal</label>
                                <select name="tipe" id="edit_tipeSoal" class="form-select border-warning" onchange="renderEditJawaban()">
                                    <option value="pg">Pilihan Ganda</option>
                                    <option value="pg_kompleks">Pilihan Ganda Kompleks</option>
                                    <option value="isian">Isian Singkat</option>
                                    <option value="benar_salah">Benar / Salah</option>
                                    <option value="menjodohkan">Menjodohkan</option>
                                    <option value="essay">Essay / Uraian</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted fw-bold">Bobot Skor</label>
                                <input type="number" name="bobot_skor" id="edit_bobot_skor" class="form-control border-warning" value="1.00" step="0.01">
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted fw-bold">Tingkat Kesulitan</label>
                                <select name="tingkat_kesulitan" id="edit_tingkat_kesulitan" class="form-select border-warning">
                                    <option value="mudah">Mudah</option>
                                    <option value="sedang">Sedang</option>
                                    <option value="sulit">Sulit</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <h6 class="fw-bold mb-3"><i class="fas fa-key me-2"></i>Konfigurasi Jawaban</h6>
                        <div id="containerEditJawaban" class="p-3 border rounded bg-white shadow-sm">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                                <span class="ms-2 text-muted">Memuat data jawaban...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="update_soal" class="btn btn-warning px-5 fw-bold text-dark">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Formula Matematika -->
<div class="modal fade" id="modalFormula" tabindex="-1" aria-labelledby="modalFormulaLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalFormulaLabel"><i class="fas fa-square-root-alt me-2"></i> Sisipkan Formula Matematika</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Ekspresi LaTeX</label>
                    <textarea id="formula-latex" class="form-control font-monospace" rows="3"
                        placeholder="Contoh: \frac{-b \pm \sqrt{b^2-4ac}}{2a}"></textarea>
                    <div class="form-text">Gunakan sintaks LaTeX standar. Contoh: <code>\frac{a}{b}</code>, <code>\sqrt{x}</code>, <code>\sum_{n=1}^{\infty}</code></div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="formula-display">
                    <label class="form-check-label small" for="formula-display">
                        Tampilkan sebagai blok terpisah <span class="text-muted">(display mode — lebih besar, rata tengah)</span>
                    </label>
                </div>
                <div class="border rounded p-3 bg-light text-center" style="min-height:60px;">
                    <div class="text-muted small mb-2">Preview</div>
                    <div id="formula-preview"><span class="text-muted small">—</span></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary fw-bold" id="btn-insert-formula">
                    <i class="fas fa-check me-1"></i> Sisipkan
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= esc(BASE_URL) ?>assets/js/tinymce/tinymce.min.js"></script>
<script>
/**
 * Simpan referensi editor aktif, lalu buka modal formula di level halaman.
 * Pendekatan ini menghindari konflik focus antara TinyMCE iframe dan Swal.
 */
window._formulaTargetEditor   = null;
window._formulaTargetTextarea = null;

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openFormulaForTextarea(taId) {
    window._formulaTargetEditor   = null;
    window._formulaTargetTextarea = document.getElementById(taId);
    document.getElementById('formula-latex').value = '';
    $('#formula-preview').html('<span class="text-muted small">—</span>');
    document.getElementById('formula-display').checked = false;
    new bootstrap.Modal(document.getElementById('modalFormula')).show();
}

function uploadImageForTextarea(taId) {
    var inp = document.getElementById('_matchImgInput');
    if (!inp) {
        inp = document.createElement('input');
        inp.type = 'file';
        inp.id   = '_matchImgInput';
        inp.accept = 'image/jpeg,image/png,image/gif,image/webp';
        inp.style.display = 'none';
        document.body.appendChild(inp);
    }
    inp.value = '';
    inp.onchange = function() {
        var file = inp.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire('Ukuran Terlalu Besar', 'Maksimal 5 MB.', 'warning');
            return;
        }
        var fd = new FormData();
        fd.append('file', file, file.name);
        $.ajax({
            url: 'controllers/upload_handler.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.location) {
                    var ta = document.getElementById(taId);
                    var tag = '<img src="' + res.location + '" style="max-width:200px;height:auto;">';
                    var s = ta.selectionStart, e = ta.selectionEnd;
                    ta.value = ta.value.substring(0, s) + tag + ta.value.substring(e);
                    ta.focus();
                } else {
                    Swal.fire('Gagal', res.error || 'Upload gambar gagal.', 'error');
                }
            },
            error: function() { Swal.fire('Error', 'Koneksi gagal saat upload gambar.', 'error'); }
        });
    };
    inp.click();
}

function registerFormulaButton(editor) {
    editor.ui.registry.addButton('insertformula', {
        text: 'Σ Formula',
        tooltip: 'Sisipkan Formula Matematika (LaTeX)',
        onAction: function() {
            window._formulaTargetEditor = editor;
            document.getElementById('formula-latex').value = '';
            $('#formula-preview').html('<span class="text-muted small">—</span>');
            document.getElementById('formula-display').checked = false;
            var modal = new bootstrap.Modal(document.getElementById('modalFormula'));
            modal.show();
        }
    });
}

const baseTinyConfig = {
    license_key: 'gpl',
    promotion: false,
    branding: false,
    height: 300,
    menubar: false,
    content_style: 'img { max-width: 100%; height: auto; border-radius: 4px; display: block; margin: 4px auto; }',
    relative_urls: false,
    remove_script_host: false,
    convert_urls: true,
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
    toolbar: 'undo redo | bold italic underline | charmap | insertformula | bullist numlist | link image table | removeformat',
    images_reuse_filename: true,
    images_upload_handler: function(blobInfo, progress) {
        return new Promise(function(resolve, reject) {
            if (blobInfo.blob().size > 5 * 1024 * 1024) {
                reject({ message: 'Ukuran gambar maksimal 5 MB.', remove: true });
                return;
            }
            var formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', 'controllers/upload_handler.php');
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) progress(e.loaded / e.total * 100);
            };
            xhr.onload = function() {
                try { var res = JSON.parse(xhr.responseText); } catch(e) { reject({ message: 'Respons tidak valid', remove: true }); return; }
                if (xhr.status >= 200 && xhr.status < 300 && res.location) {
                    resolve(res.location);
                } else {
                    Swal.fire('Upload Gagal', res.error || 'Gagal mengupload gambar.', 'error');
                    reject({ message: res.error || 'Upload gagal', remove: true });
                }
            };
            xhr.onerror = function() { reject({ message: 'Koneksi gagal', remove: true }); };
            xhr.send(formData);
        });
    },
    setup: function(editor) {
        registerFormulaButton(editor);
        editor.on('change keyup', function() { editor.save(); });
    }
};

function initMainEditors() {
    tinymce.remove('#editorSoal');
    tinymce.remove('#editorEditSoal');
    tinymce.init({ ...baseTinyConfig, selector: '#editorSoal', height: 400 });
    tinymce.init({ ...baseTinyConfig, selector: '#editorEditSoal', height: 400 });
}

function initEditorJawaban(selector = '.editor-jawaban') {
    tinymce.remove(selector);
    tinymce.init({
        ...baseTinyConfig,
        selector: selector,
        height: 180,
        toolbar: 'bold italic | charmap | insertformula | image | removeformat',
        setup: function(editor) {
            registerFormulaButton(editor);
            editor.on('change keyup', function() { editor.save(); });
            editor.on('init', function() {
                const el = document.getElementById(editor.id);
                if (el && el.value) editor.setContent(el.value);
            });
        }
    });
}

function renderInputJawaban() {
    const tipe = $('#tipeSoal').val();
    tinymce.remove('.match-editor');
    $('#containerJawaban').html(generateJawabanHTML(tipe, 'tambah'));
    if (tipe === 'menjodohkan') {
        setTimeout(() => initMatchEditors('.match-editor'), 100);
    } else {
        initEditorJawaban();
    }
}

function renderEditJawaban(tipe, dataOptions = null) {
    $('#containerEditJawaban').html(generateJawabanHTML(tipe, 'edit', dataOptions));

    if (tipe === 'menjodohkan') {
        setTimeout(() => initMatchEditors('.match-editor'), 100);
        return;
    }

    if (dataOptions) {
        setTimeout(() => {
            dataOptions.forEach(opt => {
                const ta = document.getElementById(`edit_jawaban_${opt.label}`);
                if (ta) ta.value = opt.value_target;
                if (opt.is_correct == 1) {
                    const nameMap = { 'pg': 'kunci_pg', 'pg_kompleks': 'kunci_complex[]', 'benar_salah': 'kunci_bs' };
                    const name = nameMap[tipe];
                    if (name) $(`input[name="${name}"][value="${opt.label}"]`).prop('checked', true);
                }
            });
            initEditorJawaban('.editor-jawaban');
        }, 50);
    } else {
        initEditorJawaban('.editor-jawaban');
    }
}

function generateJawabanHTML(tipe, mode, data = null) {
    let html = '';
    const prefix = (mode === 'edit') ? 'edit_' : '';

    if (tipe === 'pg' || tipe === 'pg_kompleks') {
        const isComplex = (tipe === 'pg_kompleks');
        html = `<div class="table-responsive"><table class="table table-bordered align-middle">
            <thead class="bg-light text-center"><tr><th width="70">Kunci</th><th width="50">Label</th><th>Isi Pilihan Jawaban</th></tr></thead><tbody>`;
        ['A','B','C','D','E'].forEach(l => {
            html += `<tr>
                <td class="text-center">
                    <input type="${isComplex?'checkbox':'radio'}" name="${isComplex?'kunci_complex[]':'kunci_pg'}" value="${l}" class="form-check-input" ${!isComplex?'required':''}>
                </td>
                <td class="text-center fw-bold">${l}</td>
                <td><textarea name="jawaban_${l}" class="editor-jawaban" id="${prefix}jawaban_${l}"></textarea></td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    } else if (tipe === 'benar_salah') {
        html = `<div class="p-3 bg-light rounded">
            <div class="text-center mb-2 small text-muted fw-bold">PILIH KUNCI JAWABAN:</div>
            <div class="d-flex gap-4 p-4 bg-white border rounded justify-content-center">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="kunci_bs" value="B" id="${prefix}bsB" required>
                    <label class="form-check-label text-success fw-bold" for="${prefix}bsB">BENAR</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="kunci_bs" value="S" id="${prefix}bsS" required>
                    <label class="form-check-label text-danger fw-bold" for="${prefix}bsS">SALAH</label>
                </div>
            </div>
        </div>`;
    } else if (tipe === 'menjodohkan') {
        html = `<div class="alert alert-info py-2 small mb-3"><i class="fas fa-info-circle me-1"></i>
            Klik <b>Σ</b> untuk formula LaTeX, klik <b><i class="fas fa-image"></i></b> untuk upload gambar.</div>
            <div class="row fw-bold small text-muted mb-1 px-1">
                <div class="col-5">Pernyataan / Label (Kiri)</div>
                <div class="col-5">Pasangan Jawaban (Kanan)</div>
            </div>`;
        html += `<div id="${prefix}matching-rows">`;
        if (data && mode === 'edit') {
            data.forEach((opt, idx) => {
                const kiriId  = `${prefix}match_kiri_${idx}`;
                const kananId = `${prefix}match_kanan_${idx}`;
                html += matchRow(kiriId, kananId, escHtml(opt.label), escHtml(opt.value_target), true);
            });
        } else {
            html += matchRow(`${prefix}match_kiri_0`, `${prefix}match_kanan_0`, '', '', false);
        }
        html += `</div><button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addMatchRow('${prefix}matching-rows')"><i class="fas fa-plus me-1"></i> Tambah Baris</button>`;
    } else if (tipe === 'isian') {
        const kunciList = (data && data.length > 0) ? data.map(d => d.value_target) : [''];
        const rows = kunciList.map((val, i) => `
        <div class="input-group mb-2 kunci-row">
            <input type="text" name="kunci_teks[]" class="form-control"
                   placeholder="Variasi jawaban ke-${i+1}" value="${String(val).replace(/"/g,'&quot;')}">
            <button type="button" class="btn btn-outline-danger btn-hapus-kunci" title="Hapus variasi ini">
                <i class="fas fa-times"></i>
            </button>
        </div>`).join('');
        html = `<label class="small text-muted mb-2 d-block">Kunci Jawaban <small class="text-secondary">(tambah variasi untuk multi-jawaban yang dianggap benar, case-insensitive)</small></label>
            <div id="kunci-container">${rows}</div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btn-tambah-kunci">
                <i class="fas fa-plus me-1"></i>Tambah Variasi Jawaban
            </button>`;
    } else if (tipe === 'essay') {
        const val = (data && data[0]) ? data[0].value_target : '';
        html = `<label class="small text-muted mb-2">Rubrik / Kunci (Opsional)</label>
                <textarea name="kunci_teks[]" class="form-control" rows="3" placeholder="Masukkan rubrik penilaian atau kata kunci...">${val}</textarea>`;
    }
    return html;
}

function matchTextareaGroup(name, id, val, placeholder) {
    return `<textarea name="${name}" id="${id}" class="match-editor" placeholder="${placeholder}" required>${val}</textarea>`;
}

function matchRow(kiriId, kananId, kiriVal, kananVal, showDelete) {
    const del = showDelete
        ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMatchRow(this)"><i class="fas fa-trash"></i></button>`
        : '';
    return `<div class="row g-2 mb-3 align-items-start">
        <div class="col-5">${matchTextareaGroup('match_kiri[]', kiriId, kiriVal, 'Pernyataan / Label')}</div>
        <div class="col-5">${matchTextareaGroup('match_kanan[]', kananId, kananVal, 'Jawaban Pasangan')}</div>
        <div class="col-2 pt-1">${del}</div>
    </div>`;
}

function removeMatchRow(btn) {
    var $row = $(btn).closest('.row.g-2');
    $row.find('textarea.match-editor').each(function() {
        var ed = tinymce.get(this.id);
        if (ed) ed.remove();
    });
    $row.remove();
}

function initMatchEditors(selector) {
    if (!document.querySelector(selector)) return;
    tinymce.remove(selector);
    tinymce.init({
        ...baseTinyConfig,
        selector: selector,
        height: 160,
        toolbar: 'bold italic underline | charmap | insertformula | image | removeformat',
        setup: function(editor) {
            registerFormulaButton(editor);
            editor.on('change keyup', function() { editor.save(); });
        }
    });
}

function addMatchRow(targetId) {
    const ts      = Date.now();
    const kiriId  = `match_kiri_${ts}`;
    const kananId = `match_kanan_${ts}`;
    $(`#${targetId}`).append(matchRow(kiriId, kananId, '', '', true));
    setTimeout(() => {
        initMatchEditors(`#${kiriId}`);
        initMatchEditors(`#${kananId}`);
    }, 50);
}

function editSoal(id) {
    tinymce.remove('#editorEditSoal');
    tinymce.remove('.editor-jawaban');
    tinymce.remove('.match-editor');
    $.ajax({
        url: 'ajax/get-soal-detail.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            if (data.error) { Swal.fire('Error', data.error, 'error'); return; }
            $('#edit_soal_id').val(data.id);
            $('#edit_bobot_skor').val(data.bobot_skor);
            $('#edit_tingkat_kesulitan').val(data.tingkat_kesulitan);
            $('#edit_tipeSoal').val(data.tipe);
            renderEditJawaban(data.tipe, data.options);
            tinymce.init({
                ...baseTinyConfig,
                selector: '#editorEditSoal',
                setup: function(editor) {
                    registerFormulaButton(editor);
                    editor.on('init', function() { editor.setContent(data.konten_soal); });
                }
            });
            $('#modalEditSoal').modal('show');
        },
        error: function() { Swal.fire('Error', 'Gagal mengambil data soal', 'error'); }
    });
}

function hapusSoal(id) {
    Swal.fire({
        title: 'Hapus Soal?',
        text: 'Seluruh data jawaban terkait juga akan terhapus!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: 'controllers/proses-soal.php',
            method: 'POST',
            data: { action: 'delete', id: id, bank_id: <?= esc($id_bank) ?> },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                if (res.status === 'ok') {
                    Swal.fire({ icon: 'success', title: 'Terhapus!', timer: 1200, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Gagal menghapus soal.', 'error');
                }
            },
            error: function() { Swal.fire('Error', 'Gagal terhubung ke server.', 'error'); }
        });
    });
}

$(document).ready(function() {
    renderMath(document.getElementById('content'));
    initMainEditors();
    renderInputJawaban();

    // --- Modal Formula ---
    var formulaModalEl = document.getElementById('modalFormula');
    formulaModalEl.addEventListener('shown.bs.modal', function() {
        document.getElementById('formula-latex').focus();
    });
    function updateFormulaPreview() {
        var latex = document.getElementById('formula-latex').value.trim();
        var prev  = document.getElementById('formula-preview');
        var disp  = document.getElementById('formula-display').checked;
        if (!latex) { $('#formula-preview').html('<span class="text-muted small">—</span>'); return; }
        try { katex.render(latex, prev, { throwOnError: false, displayMode: disp }); }
        catch(e) { $('#formula-preview').html('<span class="text-danger small">Formula tidak valid</span>'); }
    }
    document.getElementById('formula-latex').addEventListener('input', updateFormulaPreview);
    document.getElementById('formula-display').addEventListener('change', updateFormulaPreview);
    document.getElementById('btn-insert-formula').addEventListener('click', function() {
        var latex = document.getElementById('formula-latex').value.trim();
        if (!latex) { alert('Formula tidak boleh kosong!'); return; }
        var disp = document.getElementById('formula-display').checked;
        var wrap = disp ? '$$' + latex + '$$' : '$' + latex + '$';
        if (window._formulaTargetEditor) {
            window._formulaTargetEditor.insertContent(wrap);
        } else if (window._formulaTargetTextarea) {
            var ta = window._formulaTargetTextarea;
            var s = ta.selectionStart, e = ta.selectionEnd;
            ta.value = ta.value.substring(0, s) + wrap + ta.value.substring(e);
            ta.selectionStart = ta.selectionEnd = s + wrap.length;
            ta.focus();
        }
        bootstrap.Modal.getInstance(formulaModalEl).hide();
        window._formulaTargetTextarea = null;
    });

    $('#modalTambahSoal').on('show.bs.modal', function() {
        $(this).find('form')[0].reset();
        const ed = tinymce.get('editorSoal');
        if (ed) ed.setContent('');
        $('#tipeSoal').val('pg');
        renderInputJawaban();
    });

    $('#modalEditSoal, #modalTambahSoal').on('hidden.bs.modal', function() {
        $(this).removeAttr('aria-hidden');
        document.body.focus();
        tinymce.remove('#editorEditSoal');
        tinymce.remove('.editor-jawaban');
        tinymce.remove('.match-editor');
    });

    $(document).on('submit', 'form', function(e) {
        if (typeof tinymce !== 'undefined') {
            tinymce.editors.forEach(editor => editor.save());
        }
        const form = $(this);
        const tipe = form.find('select[name="tipe"]').val();
        if (tipe === 'pg' && form.find('input[name="kunci_pg"]:checked').length === 0) {
            e.preventDefault();
            Swal.fire('Peringatan', 'Pilih kunci jawaban yang benar!', 'warning');
            return false;
        }
        if (tipe === 'pg_kompleks' && form.find('input[name="kunci_complex[]"]:checked').length === 0) {
            e.preventDefault();
            Swal.fire('Peringatan', 'Pilih minimal satu jawaban benar!', 'warning');
            return false;
        }
        const rawContent = form.find('textarea[name="konten_soal"]').val();
        if (rawContent && rawContent.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() === '') {
            e.preventDefault();
            Swal.fire('Peringatan', 'Konten soal tidak boleh kosong!', 'warning');
            return false;
        }
        return true;
    });

    $('#menu-toggle').click(function(e) { e.preventDefault(); $('#wrapper').toggleClass('toggled'); });

    // Multi-kunci isian: tambah variasi
    $(document).on('click', '#btn-tambah-kunci', function() {
        const idx = $('#kunci-container .kunci-row').length;
        const row = `<div class="input-group mb-2 kunci-row">
            <input type="text" name="kunci_teks[]" class="form-control" placeholder="Variasi jawaban ke-${idx+1}">
            <button type="button" class="btn btn-outline-danger btn-hapus-kunci" title="Hapus variasi ini">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
        $('#kunci-container').append(row);
    });
    $(document).on('click', '.btn-hapus-kunci', function() {
        if ($('#kunci-container .kunci-row').length > 1) {
            $(this).closest('.kunci-row').remove();
        }
    });
});
</script>
</body>
</html>
