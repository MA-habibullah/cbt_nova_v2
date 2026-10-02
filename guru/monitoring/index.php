<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

$classes  = query("SELECT id, jenjang, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang ASC, nama_kelas ASC")->fetchAll();
$listSesi = query("SELECT id, nama_sesi FROM cbt_sesi WHERE is_aktif = 1 ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<style>
    .loading-overlay { display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.7); z-index: 50; }
    .custom-scroll::-webkit-scrollbar { height: 5px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-desktop me-2 text-primary"></i> Monitoring Peserta</h5>
                <div class="d-inline-flex align-items-center gap-2 bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill small fw-bold border border-success border-opacity-25">
                    <span class="spinner-grow spinner-grow-sm text-success" style="width: 0.5rem; height: 0.5rem;"></span>
                    Auto Refresh: <span id="timer-text">30s</span>
                </div>
            </div>
            <div id="last-update" class="small text-muted bg-light px-3 py-1.5 rounded-3 border">
                Update terakhir: --:--:--
            </div>
        </nav>

        <div class="container-fluid px-4">
            <!-- Filter -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <form id="filterForm" class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label text-muted text-uppercase small fw-bold" style="font-size: 0.7rem;">Tanggal Ujian</label>
                        <input type="date" name="tanggal" id="filter-tanggal" value="<?= esc(date('Y-m-d')) ?>" class="form-control form-control-sm rounded-2">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label text-muted text-uppercase small fw-bold" style="font-size: 0.7rem;">Nama Ujian / Test</label>
                        <select name="exam_id" id="filter-mapel" class="form-select form-select-sm rounded-2">
                            <option value="">-- Pilih Tanggal Dulu --</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label text-muted text-uppercase small fw-bold" style="font-size: 0.7rem;">Kelas</label>
                        <select name="class_id" class="form-select form-select-sm rounded-2">
                            <option value="">-- Semua Kelas --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= esc($c['id']) ?>">Kelas <?= esc($c['jenjang']) ?> - <?= htmlspecialchars($c['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label text-muted text-uppercase small fw-bold" style="font-size: 0.7rem;">Sesi</label>
                        <select name="sesi" class="form-select form-select-sm rounded-2">
                            <option value="">-- Semua Sesi --</option>
                            <?php foreach ($listSesi as $s): ?>
                                <option value="<?= esc($s['id']) ?>"><?= htmlspecialchars($s['nama_sesi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <!-- Bulk Actions -->
                <div class="row g-2 mt-3 pt-3 border-top">
                    <div class="col-6 col-md-2">
                        <button type="button" onclick="bulkAction('add_time')" class="btn btn-primary w-100 py-2.5 rounded-3 d-flex flex-column align-items-center shadow-sm">
                            <i class="fas fa-clock mb-1 fs-5"></i>
                            <span class="fw-bold" style="font-size:11px;">+ WAKTU</span>
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="button" onclick="bulkAction('reset_login')" class="btn btn-warning w-100 py-2.5 rounded-3 d-flex flex-column align-items-center shadow-sm text-dark">
                            <i class="fas fa-key mb-1 fs-5"></i>
                            <span class="fw-bold" style="font-size:11px;">RESET LOGIN</span>
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="button" onclick="bulkAction('unlock_exam')" class="btn btn-success w-100 py-2.5 rounded-3 d-flex flex-column align-items-center shadow-sm">
                            <i class="fas fa-door-open mb-1 fs-5"></i>
                            <span class="fw-bold" style="font-size:11px;">BUKA UJIAN</span>
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="button" onclick="bulkAction('lock_exam')" class="btn btn-danger w-100 py-2.5 rounded-3 d-flex flex-column align-items-center shadow-sm">
                            <i class="fas fa-lock mb-1 fs-5"></i>
                            <span class="fw-bold" style="font-size:11px;">KUNCI UJIAN</span>
                        </button>
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="button" onclick="bulkAction('finish_exam')" class="btn btn-dark w-100 py-2.5 rounded-3 d-flex flex-column align-items-center shadow-sm">
                            <i class="fas fa-check-double mb-1 fs-5 text-info"></i>
                            <span class="fw-bold" style="font-size:11px;">SELESAIKAN UJIAN (FORCE)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden position-relative mb-4">
                <div id="loader" class="loading-overlay flex-column align-items-center justify-content-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <span class="mt-2 text-muted small fw-semibold">Memperbarui data...</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern table-monitoring align-middle mb-0" style="min-width: 900px;">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                            <tr>
                                <th class="py-3 px-3 w-12 text-center" width="50"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                <th class="py-3 px-3">Nama Peserta</th>
                                <th class="py-3 px-3 d-none d-sm-table-cell">Kelas / Sesi</th>
                                <th class="py-3 px-3">Mata Pelajaran & Progress</th>
                                <th class="py-3 px-3 text-center">Status & Sisa Waktu</th>
                                <th class="py-3 px-3 text-center d-none d-md-table-cell">Device & IP</th>
                                <th class="py-3 px-3 text-center" width="100">Pelanggaran</th>
                            </tr>
                        </thead>
                        <tbody id="monitoring-data">
                            <tr><td colspan="7" class="p-5 text-center text-muted italic">Memuat data monitoring...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Logs -->
<div class="modal fade" id="modalLogs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content rounded-2xl border-0 shadow-lg">
            <div class="modal-header bg-rose-600 text-white rounded-t-2xl" style="background:#e11d48;">
                <h5 class="modal-title font-bold text-sm"><i class="fas fa-exclamation-triangle me-2"></i> Log Pelanggaran Peserta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="log-content"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let countdown = 30;
    let countdownInterval;

    function updateMapelDropdown() {
        let tgl = $('#filter-tanggal').val();
        $.get('fetch_mapel.php', { tanggal: tgl }, function(res) {
            $('#filter-mapel').html(res);
            loadMonitoring();
        });
    }

    function loadMonitoring() {
        const formData = $('#filterForm').serialize();
        const checkedIds = $('.check-item:checked').map(function() { return $(this).val(); }).get();
        $.ajax({
            url: 'fetch_data.php',
            type: 'GET',
            data: formData,
            beforeSend: function() { 
                $('#loader').addClass('active').show(); 
            },
            success: function(html) {
                $('#monitoring-data').html(html);
                if (checkedIds.length > 0) {
                    checkedIds.forEach(function(id) {
                        $('.check-item[value="' + id + '"]').prop('checked', true);
                    });
                }
                countdown = 30;
                $('#last-update').text('Update terakhir: ' + new Date().toLocaleTimeString('id-ID'));
                // Mulai client-side countdown tiap detik
                clearInterval(countdownInterval);
                countdownInterval = setInterval(function() {
                    document.querySelectorAll('.countdown[data-sisa]').forEach(function(el) {
                        let sisa = parseInt(el.dataset.sisa);
                        if (sisa <= 0) return;
                        sisa--;
                        el.dataset.sisa = sisa;
                        if (sisa <= 0) {
                            el.textContent = 'Waktu Habis';
                            el.className = 'countdown text-red-500 font-bold';
                        } else {
                            const jam   = Math.floor(sisa / 3600);
                            const menit = Math.floor((sisa % 3600) / 60);
                            const detik = sisa % 60;
                            el.textContent = (jam > 0 ? jam + 'j ' : '') + menit + 'm ' + String(detik).padStart(2, '0') + 'd';
                        }
                    });
                }, 1000);
            },
            error: function() {
                $('#monitoring-data').html('<tr><td colspan="7" class="p-5 text-center text-danger fw-semibold"><i class="fas fa-exclamation-circle me-1"></i> Gagal memuat data monitoring. Silakan periksa jaringan.</td></tr>');
            },
            complete: function() {
                $('#loader').removeClass('active').hide();
            }
        });
    }

    function bulkAction(type) {
        let ids = [];
        $('.check-item:checked').each(function() { ids.push($(this).val()); });
        if (ids.length === 0) {
            Swal.fire('Peringatan', 'Silakan pilih peserta terlebih dahulu!', 'warning'); return;
        }
        const config = {
            'add_time':    { title: 'Tambah Waktu', text: 'Menit tambahan:', input: 'number' },
            'reset_login': { title: 'Reset Login?', text: 'Hapus kunci perangkat agar siswa bisa login kembali.' },
            'unlock_exam': { title: 'Buka Ujian?', text: 'Status kembali ke Working (Jawaban Aman).' },
            'lock_exam':   { title: 'Kunci Ujian?', text: 'Blokir akses siswa ini seketika.' },
            'finish_exam': { title: 'Selesaikan Paksa?', text: 'Siswa yang dipilih akan dianggap selesai mengerjakan ujian.' }
        };
        const action = config[type];
        Swal.fire({
            title: action.title, text: action.text,
            input: action.input || null,
            inputValue: action.input === 'number' ? 15 : '',
            showCancelButton: true, cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Proses',
            confirmButtonColor: type === 'finish_exam' ? '#111827' : '#4f46e5'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actions.php', type: 'POST',
                    data: { action: type, ids: ids, value: result.value },
                    dataType: 'text',
                    success: function(response) {
                        let data;
                        try {
                            let s = response.indexOf('{'), e = response.lastIndexOf('}');
                            data = JSON.parse(response.substring(s, e + 1));
                        } catch(err) {
                            Swal.fire('Error', 'Gagal memproses respon server.', 'error'); return;
                        }
                        if (data.status === 'success') {
                            loadMonitoring();
                            Swal.fire('Berhasil', data.message, 'success');
                        } else {
                            Swal.fire('Gagal', data.message, 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Gagal menghubungi server.', 'error'); }
                });
            }
        });
    }

    function viewLogs(studentId, examId) {
        $('#log-content').html('<div class="p-10 text-center"><i class="fas fa-spinner fa-spin text-2xl" style="color:#e11d48;"></i></div>');
        $('#modalLogs').modal('show');
        $.get('fetch_logs.php', { student_id: studentId, exam_id: examId }, function(html) {
            $('#log-content').html(html);
        });
    }

    function individualKick(participantId) {
        Swal.fire({
            title: 'Blokir Siswa?', text: 'Siswa akan dikeluarkan dari ujian!',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, Blokir!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actions.php', type: 'POST',
                    data: { action: 'lock_exam', ids: [participantId] },
                    success: function() {
                        $('#modalLogs').modal('hide');
                        loadMonitoring();
                        Swal.fire('Terblokir!', 'Siswa telah dikeluarkan dari ujian.', 'success');
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        updateMapelDropdown();
        setInterval(() => {
            countdown--;
            $('#timer-text').text(countdown + 's');
            if (countdown <= 0) loadMonitoring();
        }, 1000);
        $('#filter-tanggal').on('change', updateMapelDropdown);
        $('#filterForm select').on('change', loadMonitoring);
        $(document).on('change', '#checkAll', function() {
            $('.check-item').prop('checked', $(this).prop('checked'));
        });
    });
</script>
</body>
</html>
