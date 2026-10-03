<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$teacher_id = (int)$_SESSION['teacher_id'];

// Ambil kelas yang diampu guru
$classes = query("
    SELECT DISTINCT c.id, c.nama_kelas, c.jenjang 
    FROM cbt_classes c
    JOIN cbt_exams e ON e.class_id = c.id
    WHERE e.teacher_id = ? AND c.is_aktif = 1
    ORDER BY c.jenjang, c.nama_kelas
", [$teacher_id])->fetchAll();

// Jika belum ada kelas dari ujian, ambil semua kelas aktif
if (empty($classes)) {
    $classes = query("SELECT id, nama_kelas, jenjang FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas")->fetchAll();
}

$sessions = query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY nama_sesi")->fetchAll();

// Metrik Ringkasan (dibatasi lingkup guru)
$metric_total_lock = (int)query("
    SELECT COUNT(DISTINCT dl.id) 
    FROM cbt_device_locks dl
    JOIN cbt_students s ON dl.student_id = s.id
    WHERE (
        s.id IN (
            SELECT DISTINCT ep.student_id FROM cbt_exam_participants ep 
            JOIN cbt_exams e ON ep.exam_id = e.id WHERE e.teacher_id = ?
        )
        OR s.class_id IN (
            SELECT DISTINCT class_id FROM cbt_exams WHERE teacher_id = ?
        )
    )
", [$teacher_id, $teacher_id])->fetchColumn();

$metric_today_lock = (int)query("
    SELECT COUNT(DISTINCT dl.id) 
    FROM cbt_device_locks dl
    JOIN cbt_students s ON dl.student_id = s.id
    WHERE DATE(dl.created_at) = CURDATE() AND (
        s.id IN (
            SELECT DISTINCT ep.student_id FROM cbt_exam_participants ep 
            JOIN cbt_exams e ON ep.exam_id = e.id WHERE e.teacher_id = ?
        )
        OR s.class_id IN (
            SELECT DISTINCT class_id FROM cbt_exams WHERE teacher_id = ?
        )
    )
", [$teacher_id, $teacher_id])->fetchColumn();

$metric_total_ujian = (int)query("SELECT COUNT(*) FROM cbt_exams WHERE teacher_id = ?", [$teacher_id])->fetchColumn();
$metric_total_kelas = count($classes);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <style>
        #deviceContent { min-height: 350px; position: relative; }
        .loading-overlay { display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.7); z-index: 50; }
        .table-responsive {
            width: 100%;
            max-width: 100%;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .table-responsive::-webkit-scrollbar {
            height: 7px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/guru/includes/sidebar.php'; ?>
    
    <div id="content" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border rounded-3 me-2 shadow-sm" id="menu-toggle" title="Buka/Tutup Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:40px; height:40px;" title="Kembali ke Monitoring Live">
                    <i class="fas fa-arrow-left text-secondary"></i>
                </a>
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-laptop-code text-primary me-2"></i> Manajemen Device Lock</h5>
                    <small class="text-muted">Proteksi integritas ujian dengan membatasi satu perangkat aktif per akun siswa</small>
                </div>
            </div>
            <a href="index.php" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1.5 fw-semibold shadow-sm d-flex align-items-center gap-1">
                <i class="fas fa-desktop"></i> Monitoring Live
            </a>
        </nav>

        <div class="container-fluid px-4 py-3">
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= esc(BASE_URL) ?>guru/index.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= esc(BASE_URL) ?>guru/monitoring/index.php" class="text-decoration-none text-muted"><i class="fas fa-desktop me-1"></i>Monitoring</a></li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Device Lock</li>
                </ol>
            </nav>

            <!-- Top Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">TOTAL TERKUNCI</span>
                                <h3 class="fw-bold mb-0 mt-1 text-danger"><?= number_format($metric_total_lock, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-lock me-1 text-danger"></i>Siswa kelas ujian Anda</small>
                            </div>
                            <div class="rounded-3 p-3 bg-danger-subtle text-danger">
                                <i class="fas fa-laptop-code fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">TERKUNCI HARI INI</span>
                                <h3 class="fw-bold mb-0 mt-1 text-warning"><?= number_format($metric_today_lock, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-calendar-day me-1 text-warning"></i>Login device tanggal <?= date('d/m/Y') ?></small>
                            </div>
                            <div class="rounded-3 p-3 bg-warning-subtle text-warning">
                                <i class="fas fa-user-lock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">TOTAL JADWAL UJIAN</span>
                                <h3 class="fw-bold mb-0 mt-1 text-primary"><?= number_format($metric_total_ujian, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-calendar-alt me-1 text-primary"></i>Jadwal aktif & selesai</small>
                            </div>
                            <div class="rounded-3 p-3 bg-primary-subtle text-primary">
                                <i class="fas fa-book-reader fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">TOTAL KELAS</span>
                                <h3 class="fw-bold mb-0 mt-1 text-success"><?= number_format($metric_total_kelas, 0, ',', '.') ?></h3>
                                <small class="text-muted"><i class="fas fa-school me-1 text-success"></i>Rombel diampu</small>
                            </div>
                            <div class="rounded-3 p-3 bg-success-subtle text-success">
                                <i class="fas fa-layer-group fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-shield-alt text-primary me-2"></i>Daftar Kunci Perangkat Siswa</h6>
                        <span id="lockCounterBadge" class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Memuat data...</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?= esc(BASE_URL) ?>guru/monitoring/index.php" class="btn btn-sm btn-outline-primary shadow-sm fw-semibold">
                            <i class="fas fa-desktop me-1"></i> Monitoring Live
                        </a>
                        <a href="<?= esc(BASE_URL) ?>guru/monitoring/log-pelanggaran.php" class="btn btn-sm btn-outline-warning shadow-sm fw-semibold text-dark">
                            <i class="fas fa-exclamation-triangle me-1"></i> Log Pelanggaran
                        </a>
                        <button type="button" onclick="bulkResetDevice()" class="btn btn-sm btn-danger shadow-sm fw-bold px-3">
                            <i class="fas fa-unlock-alt me-1"></i> Buka Kunci Masal
                        </button>
                    </div>
                </div>
            </div>

            <!-- Unified Filter Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form id="filterDevice" class="row g-2 align-items-end">
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-calendar-day me-1"></i>Tanggal Lock</label>
                            <input type="date" name="tanggal" id="filter_tanggal" class="form-control form-control-sm onChangeLoad" value="<?= esc(date('Y-m-d')) ?>">
                        </div>

                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-search me-1"></i>Cari Siswa / Username</label>
                            <input type="text" name="search" id="filter_search" class="form-control form-control-sm onChangeLoad" placeholder="Ketik nama siswa atau username...">
                        </div>

                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-school me-1"></i>Kelas</label>
                            <select name="class_id" id="filter_class" class="form-select form-select-sm onChangeLoad">
                                <option value="">Semua Kelas</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>">Kelas <?= esc($c['jenjang']) ?> - <?= esc($c['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-clock me-1"></i>Sesi Ujian</label>
                            <select name="sesi" id="filter_sesi" class="form-select form-select-sm onChangeLoad">
                                <option value="">Semua Sesi</option>
                                <?php foreach($sessions as $s): ?>
                                    <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6 col-12 d-flex gap-2">
                            <button type="button" onclick="loadDeviceData()" class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                                <i class="fas fa-sync-alt me-1"></i> Refresh
                            </button>
                            <button type="button" onclick="resetFilter()" class="btn btn-light border btn-sm text-secondary px-3" title="Reset Semua Filter">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div id="deviceContent">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted small fw-semibold">Sinkronisasi data perangkat...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const CSRF_TOKEN = '<?= csrf_token() ?>';

    function loadDeviceData() {
        let formData = $('#filterDevice').serialize();
        $.ajax({
            url: 'ajax/device-data.php',
            type: 'GET',
            data: formData,
            beforeSend: function() {
                $('#deviceContent').html(`
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted small fw-semibold">Memuat data perangkat terkunci...</p>
                    </div>
                `);
            },
            success: function(data) {
                $('#deviceContent').html(data);
                let count = $('#deviceContent').find('tbody tr').not('.no-data').length;
                if ($('#deviceContent').find('.no-data').length > 0) {
                    count = 0;
                }
                $('#lockCounterBadge').text(count + ' perangkat ditemukan');
            },
            error: function() {
                $('#deviceContent').html('<div class="p-4 text-danger text-center fw-semibold"><i class="fas fa-exclamation-circle me-1"></i> Gagal memuat data perangkat. Periksa koneksi server.</div>');
                $('#lockCounterBadge').text('Gagal memuat');
            }
        });
    }

    function resetFilter() {
        $('#filter_tanggal').val('<?= date('Y-m-d') ?>');
        $('#filter_search').val('');
        $('#filter_class').val('');
        $('#filter_sesi').val('');
        loadDeviceData();
    }

    function bulkResetDevice(id = null) {
        let selected = [];
        if (id !== null) {
            selected = [id];
        } else {
            $('.checkItem:checked').each(function() { selected.push($(this).val()); });
        }

        if (selected.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Pilih minimal satu siswa untuk dibuka kuncinya!',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        Swal.fire({
            title: 'Buka Kunci Perangkat?',
            text: selected.length + " siswa akan diizinkan login kembali dari perangkat lain.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-unlock-alt me-1"></i> Ya, Buka Kunci!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actions.php',
                    type: 'POST',
                    data: { 
                        action: 'reset_login', 
                        ids: selected,
                        csrf_token: CSRF_TOKEN
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'success') {
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Berhasil', 
                                text: 'Kunci perangkat berhasil dibuka.', 
                                timer: 1500, 
                                showConfirmButton: false 
                            });
                            loadDeviceData();
                        } else {
                            Swal.fire('Gagal', data.message || 'Terjadi kesalahan sistem.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        loadDeviceData();
        
        let searchTimeout;
        $('#filter_search').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                loadDeviceData();
            }, 300);
        });

        $('#filter_tanggal, #filter_class, #filter_sesi').on('change', function() {
            loadDeviceData();
        });

        $(document).on('change', '#checkAll', function() {
            $('.checkItem').prop('checked', $(this).prop('checked'));
        });

        $(document).on('change', '.checkItem', function() {
            if (!$(this).prop('checked')) {
                $('#checkAll').prop('checked', false);
            } else {
                let allChecked = $('.checkItem:checked').length === $('.checkItem').length;
                $('#checkAll').prop('checked', allChecked);
            }
        });
    });
</script>
</body>
</html>
