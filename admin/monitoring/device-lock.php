<?php
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Ambil data filter
$classes = query("SELECT id, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas")->fetchAll();
// Sesuaikan nama tabel sesi Anda, di sini saya gunakan cbt_sesi sesuai snipet Anda
$sessions = query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY nama_sesi")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php include '../../includes/header.php'; ?>
    <style>
        .form-label-sm { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; display: block; }
        #deviceContent { min-height: 400px; position: relative; }
        .loading-overlay { display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.7); z-index: 50; }
    </style>
</head>

<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-laptop-code me-2 text-primary"></i> Manajemen Device Lock</h5>
            <div class="text-muted small italic">Mengunci akun siswa pada satu perangkat tertentu</div>
        </nav>

        <div class="container-fluid px-4 pt-4">
            <div class="card border-0 shadow-sm mb-4 rounded-3">
                <div class="card-body p-3">
                    <form id="filterDevice" class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label-sm">Tanggal Lock</label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control form-control-sm onChangeLoad" value="<?= esc(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-sm">Cari Nama / Username</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 onChangeLoad" placeholder="Ketik nama siswa...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-sm">Kelas</label>
                            <select name="class_id" class="form-select form-select-sm onChangeLoad">
                                <option value="">Semua Kelas</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>"><?= esc($c['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-sm">Sesi</label>
                            <select name="sesi" class="form-select form-select-sm onChangeLoad">
                                <option value="">Semua Sesi</option>
                                <?php foreach($sessions as $s): ?>
                                    <option value="<?= esc($s['id']) ?>"><?= esc($s['nama_sesi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="button" onclick="bulkResetDevice()" class="btn btn-danger btn-sm w-100 fw-bold py-2 shadow-sm">
                                <i class="fas fa-unlock-alt me-1"></i> BUKA KUNCI MASAL
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 overflow-hidden border">
                <div id="deviceContent">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted small">Sinkronisasi data perangkat...</p>
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
    function loadDeviceData() {
        let formData = $('#filterDevice').serialize();
        $.ajax({
            url: 'ajax/device-data.php',
            type: 'GET',
            data: formData,
            success: function(data) {
                $('#deviceContent').html(data);
            },
            error: function() {
                $('#deviceContent').html('<div class="p-4 text-danger text-center">Gagal memuat data perangkat. Periksa koneksi server.</div>');
            }
        });
    }

    // id diisi saat reset satu baris, null saat bulk dari checkbox
    function bulkResetDevice(id = null) {
        let selected = [];
        if (id !== null) {
            selected = [id];
        } else {
            $('.checkItem:checked').each(function() { selected.push($(this).val()); });
        }

        if (selected.length === 0) {
            Swal.fire('Peringatan', 'Pilih minimal satu siswa untuk dibuka kuncinya!', 'warning');
            return;
        }

        Swal.fire({
            title: 'Buka Kunci Perangkat?',
            text: selected.length + " siswa akan diizinkan login dari perangkat manapun.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Buka Kunci!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actions.php',
                    type: 'POST',
                    data: { action: 'reset_login', ids: selected },
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Kunci perangkat telah dihapus.', timer: 1500, showConfirmButton: false });
                            loadDeviceData();
                        } else {
                            Swal.fire('Gagal', data.message, 'error');
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
        $('.onChangeLoad').on('keyup change', function() {
            loadDeviceData();
        });
        $(document).on('change', '#checkAll', function() {
            $('.checkItem').prop('checked', $(this).prop('checked'));
        });
    });
</script>
</body>
</html>