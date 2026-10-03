<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

// Ambil data filter Kelas
$classes = query("SELECT id, nama_kelas, jenjang FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <style>
        .form-label-sm { font-size: 11px; font-weight: 700; color: #6c757d; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .font-mono-sm { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; }
        #log-loader { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10; }
        .log-group-header { cursor: pointer; transition: background-color .15s ease; }
        .log-group-header:hover { background-color: #f8f9fa; }
    </style>
</head>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/guru/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border rounded-3 me-2 shadow-sm" id="menu-toggle" title="Buka/Tutup Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:40px; height:40px;" title="Kembali ke Monitoring Live">
                    <i class="fas fa-arrow-left text-secondary"></i>
                </a>
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-triangle-exclamation text-danger me-2"></i> Log Pelanggaran Anti-Cheat (Guru)</h5>
                    <small class="text-muted">Audit riwayat deteksi kecurangan pada jadwal ujian Anda</small>
                </div>
            </div>
            <a href="index.php" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1.5 fw-semibold shadow-sm d-flex align-items-center gap-1">
                <i class="fas fa-desktop"></i> Monitoring Live
            </a>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-4">
                    <form id="filterLogForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label-sm">Tanggal Kejadian</label>
                            <input type="date" name="tanggal" id="log-tanggal" value="<?= esc(date('Y-m-d')) ?>" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-sm">Ujian / Mapel</label>
                            <select name="exam_id" id="log-exam" class="form-select rounded-3">
                                <option value="">-- Pilih Tanggal Dulu --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-sm">Kelas Target</label>
                            <select name="class_id" id="log-class" class="form-select rounded-3">
                                <option value="">Semua Kelas</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>">Kelas <?= esc($c['jenjang']) ?> - <?= esc($c['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" onclick="loadLogs()" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-sm">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 overflow-hidden position-relative">
                <div id="log-loader" class="flex-column align-items-center justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                
                <div class="p-4" id="log-table-body">
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function updateExamList() {
        let tgl = $('#log-tanggal').val();
        $.get('fetch_mapel.php', { tanggal: tgl }, function(res) {
            $('#log-exam').html(res);
            loadLogs();
        });
    }

    function loadLogs() {
        const formData = $('#filterLogForm').serialize();
        $.ajax({
            url: 'log_pelanggaran_data.php',
            type: 'GET',
            data: formData,
            beforeSend: function() { 
                $('#log-loader').css('display', 'flex'); 
            },
            success: function(html) {
                $('#log-table-body').html(html);
            },
            error: function() {
                $('#log-table-body').html('<div class="p-4 text-center text-danger fw-semibold"><i class="fas fa-exclamation-circle me-1"></i> Gagal memuat log pelanggaran.</div>');
            },
            complete: function() {
                $('#log-loader').hide();
            }
        });
    }

    $(document).ready(function() {
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
        updateExamList();
        $('#log-tanggal').on('change', updateExamList);
        $('#log-class, #log-exam').on('change', loadLogs);
    });
</script>
</body>
</html>
