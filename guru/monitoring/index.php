<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
$teacher_id = (int)$_SESSION['teacher_id'];

$classes  = query("SELECT id, nama_kelas FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas")->fetchAll();
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
<body class="bg-gray-50 font-sans">
<div class="flex min-h-screen flex-col md:flex-row">
    <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <main class="flex-1 w-full overflow-x-hidden p-4 md:p-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Monitoring Peserta</h1>
                <div class="flex items-center gap-2 bg-green-100 text-green-700 px-4 py-1.5 rounded-full text-xs font-bold border border-green-200 mt-2">
                    <span class="animate-pulse h-2 w-2 rounded-full bg-green-500"></span>
                    Auto Refresh: <span id="timer-text">30s</span>
                </div>
            </div>
            <div id="last-update" class="text-xs bg-white px-4 py-2 rounded-lg border shadow-sm text-gray-400">
                Update terakhir: --:--:--
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
            <form id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Tanggal Ujian</label>
                    <input type="date" name="tanggal" id="filter-tanggal" value="<?= esc(date('Y-m-d')) ?>" class="w-full border border-gray-300 p-2.5 rounded-xl text-sm outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Mata Pelajaran</label>
                    <select name="exam_id" id="filter-mapel" class="w-full border border-gray-300 p-2.5 rounded-xl text-sm outline-none">
                        <option value="">-- Pilih Tanggal Dulu --</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Kelas</label>
                    <select name="class_id" class="w-full border border-gray-300 p-2.5 rounded-xl text-sm outline-none">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= esc($c['id']) ?>"><?= htmlspecialchars($c['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Sesi</label>
                    <select name="sesi" class="w-full border border-gray-300 p-2.5 rounded-xl text-sm outline-none">
                        <option value="">-- Semua Sesi --</option>
                        <?php foreach ($listSesi as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= htmlspecialchars($s['nama_sesi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <!-- Bulk Actions -->
            <div class="row g-2 mt-4 pt-4 border-top">
                <div class="col-6 col-md-2">
                    <button onclick="bulkAction('add_time')" class="w-100 bg-primary border-0 text-white p-3 rounded-lg flex flex-col items-center shadow-sm">
                        <i class="fas fa-clock mb-1 text-lg"></i>
                        <span class="fw-bold" style="font-size:10px;">+ WAKTU</span>
                    </button>
                </div>
                <div class="col-6 col-md-2">
                    <button onclick="bulkAction('reset_login')" class="w-100 bg-warning border-0 text-dark p-3 rounded-lg flex flex-col items-center shadow-sm">
                        <i class="fas fa-key mb-1 text-lg"></i>
                        <span class="fw-bold" style="font-size:10px;">RESET LOGIN</span>
                    </button>
                </div>
                <div class="col-6 col-md-2">
                    <button onclick="bulkAction('unlock_exam')" class="w-100 bg-success border-0 text-white p-3 rounded-lg flex flex-col items-center shadow-sm">
                        <i class="fas fa-door-open mb-1 text-lg"></i>
                        <span class="fw-bold" style="font-size:10px;">BUKA UJIAN</span>
                    </button>
                </div>
                <div class="col-6 col-md-2">
                    <button onclick="bulkAction('lock_exam')" class="w-100 bg-danger border-0 text-white p-3 rounded-lg flex flex-col items-center shadow-sm">
                        <i class="fas fa-lock mb-1 text-lg"></i>
                        <span class="fw-bold" style="font-size:10px;">KUNCI UJIAN</span>
                    </button>
                </div>
                <div class="col-6 col-md-4">
                    <button onclick="bulkAction('finish_exam')" class="w-100 bg-dark border-0 text-white p-3 rounded-lg flex flex-col items-center shadow-sm">
                        <i class="fas fa-check-double mb-1 text-lg text-info"></i>
                        <span class="fw-bold" style="font-size:10px;">SELESAIKAN UJIAN (FORCE)</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="relative bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div id="loader" class="loading-overlay flex flex-col items-center justify-center">
                <div class="w-8 h-8 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
            </div>
            <div class="overflow-x-auto custom-scroll w-full">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead class="bg-gray-50 text-gray-400 uppercase text-[10px] font-bold tracking-wider">
                        <tr>
                            <th class="p-4 border-b w-12 text-center"><input type="checkbox" id="checkAll"></th>
                            <th class="p-4 border-b">Nama Peserta</th>
                            <th class="p-4 border-b">Kelas / Sesi</th>
                            <th class="p-4 border-b">Mata Pelajaran & Progress</th>
                            <th class="p-4 border-b text-center">Status & Sisa Waktu</th>
                            <th class="p-4 border-b text-center">Device & IP</th>
                            <th class="p-4 border-b text-center">Pelanggaran</th>
                        </tr>
                    </thead>
                    <tbody id="monitoring-data" class="divide-y divide-gray-100 text-sm">
                        <tr><td colspan="7" class="p-10 text-center text-gray-400 italic">Memuat data monitoring...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
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
            beforeSend: function() { $('#loader').show(); },
            success: function(html) {
                $('#monitoring-data').html(html);
                if (checkedIds.length > 0) {
                    checkedIds.forEach(function(id) {
                        $('.check-item[value="' + id + '"]').prop('checked', true);
                    });
                }
                $('#loader').hide();
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
