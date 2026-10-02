<?php
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Hapus log pelanggaran berdasarkan rentang tanggal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_log'])) {
    csrf_verify();
    $dari   = $_POST['dari']   ?? '';
    $sampai = $_POST['sampai'] ?? '';
    if ($dari && $sampai && $dari <= $sampai) {
        $stmt = $pdo->prepare("DELETE FROM cbt_cheat_logs WHERE DATE(waktu_kejadian) BETWEEN ? AND ?");
        $stmt->execute([$dari, $sampai]);
        $deleted = $stmt->rowCount();
        log_activity("Hapus log pelanggaran: $deleted baris (rentang $dari s/d $sampai)");
        header("Location: log-pelanggaran.php?msg=deleted&count=" . $deleted);
        exit;
    }
}

// Ambil data awal untuk filter Kelas
$classes = query("SELECT id, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY nama_kelas ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php include '../../includes/header.php'; ?>
    <style>
        .form-label-sm { font-size: 11px; font-weight: 700; color: #6c757d; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .font-mono-sm { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; }
        /* Loader Overlay */
        #log-loader { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10; }
        .log-group-header { cursor: pointer; transition: background-color .15s ease; }
        .log-group-header:hover { background-color: #f8f9fa; }
        .log-group-icon { transition: transform .2s ease; }
        .log-group-header[aria-expanded="true"] .log-group-icon { transform: rotate(90deg); }
    </style>
</head>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-danger"></i> Riwayat Pelanggaran</h5>
            <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="fas fa-arrow-left me-1"></i> Monitoring
            </a>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= (int)$_GET['count'] ?> log pelanggaran</strong> berhasil dihapus dari database.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-4">
                    <form id="filterLogForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label-sm">Tanggal Kejadian</label>
                            <input type="date" name="tanggal" id="log-tanggal" value="<?= esc(date('Y-m-d')) ?>" class="form-control rounded-3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-sm">Ujian / Mapel</label>
                            <select name="exam_id" id="log-exam" class="form-select rounded-3">
                                <option value="">-- Pilih Tanggal --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-sm">Kelas</label>
                            <select name="class_id" id="log-class" class="form-select rounded-3">
                                <option value="">Semua Kelas</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>"><?= esc($c['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" onclick="loadLogs()" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-sm">
                                <i class="fas fa-filter me-2"></i> FILTER DATA
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-danger mb-3"><i class="fas fa-trash-alt me-2"></i>Hapus Riwayat Log</h6>
                    <form method="POST" class="row g-2 align-items-end"
                          onsubmit="return confirm('Yakin hapus semua log pelanggaran pada rentang tanggal ini?\nTindakan ini tidak dapat dibatalkan.')">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                        <div class="col-auto">
                            <label class="form-label-sm">Dari Tanggal</label>
                            <input type="date" name="dari" class="form-control form-control-sm rounded-3" required>
                        </div>
                        <div class="col-auto d-flex align-items-end pb-1">
                            <span class="text-muted small">s/d</span>
                        </div>
                        <div class="col-auto">
                            <label class="form-label-sm">Sampai Tanggal</label>
                            <input type="date" name="sampai" class="form-control form-control-sm rounded-3" required>
                        </div>
                        <div class="col-auto d-flex align-items-end">
                            <button type="submit" name="hapus_log" class="btn btn-danger btn-sm rounded-3 fw-bold px-3">
                                <i class="fas fa-trash me-1"></i> Hapus Log
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 overflow-hidden position-relative">
                <div id="log-loader" class="flex-column align-items-center justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                
                <div class="p-3" id="log-table-body">
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
            beforeSend: function() { $('#log-loader').css('display', 'flex'); },
            success: function(html) {
                $('#log-table-body').html(html);
                $('#log-loader').hide();
            }
        });
    }

    $(document).ready(function() {
        updateExamList();
        $('#log-tanggal').on('change', updateExamList);
        $('#log-class, #log-exam').on('change', loadLogs);
    });
</script>
</body>
</html>