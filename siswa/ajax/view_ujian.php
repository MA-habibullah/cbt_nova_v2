<?php
require_once '../../config/database.php';
require_once '../../includes/helpers.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo '<div class="alert alert-danger m-4">Sesi tidak valid.</div>';
    exit;
}

if (!isset($_GET['id'])) {
    echo '<script>loadView("dashboard");</script>';
    exit;
}

$exam_id    = (int)$_GET['id'];
$student_id = $_SESSION['student_id'];

$stmt = $pdo->prepare("
    SELECT p.*, e.nama_mapel_ujian, e.durasi_menit, e.selesai_pada as batas_waktu_server,
           NOW() as waktu_sekarang_db
    FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.exam_id = ? AND p.student_id = ?
    AND p.status IN ('working', 'ready')
");
$stmt->execute([$exam_id, $student_id]);
$data = $stmt->fetch();

if (!$data) {
    echo '<script>loadView("dashboard", { msg: "sesi_berakhir" });</script>';
    exit;
}

$waktu      = hitung_sisa_waktu($pdo, (int)$data['id']);
$sisa_detik = $waktu['sisa_detik'];
?>

<nav class="exam-header sticky-top py-2">
    <div class="container-fluid px-3 px-md-4 d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex align-items-center gap-2 exam-title-group">
            <!-- Tombol Reload Pintar (Soft Refresh) -->
            <button type="button" onclick="internalRefresh()" class="btn btn-light btn-sm border-0 shadow-sm flex-shrink-0" title="Muat Ulang Soal" aria-label="Muat Ulang">
                <i class="fas fa-sync-alt text-primary"></i>
            </button>
            <h6 class="mb-0 fw-bold text-dark text-truncate"><?= htmlspecialchars($data['nama_mapel_ujian']) ?></h6>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-2 exam-meta-group">
            <div class="timer-pill" id="timerPill">
                <i class="far fa-clock"></i>
                <span class="timer-box" id="timer">00:00:00</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-primary rounded-pill d-lg-none" id="btn-toggle-nav" aria-label="Nomor Soal" title="Nomor Soal">
                    <i class="fas fa-th"></i>
                </button>
                <button class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm d-none" id="btn-finish-exam">SELESAI</button>
            </div>
        </div>
    </div>
</nav>
<div class="nav-drawer-backdrop" id="navDrawerBackdrop"></div>

<!-- Anti-Screenshot & Privacy Screen Layer -->
<style>
    @media print { body { display: none !important; } }
    #privacy-screen { position: fixed; inset: 0; background: #ffffff; z-index: 2147483647; display: none; }
</style>
<div id="privacy-screen"></div>

<div class="exam-page-wrap container-fluid px-3 px-md-4 mt-3 mt-md-4">
    <div class="row exam-layout-row">
        <div class="col-12 col-lg-9 exam-main-col mb-5">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 p-lg-5 exam-question-card">
                <div class="exam-progress mb-3" id="examProgress">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-muted" id="progressText">Soal 1 dari -</span>
                    </div>
                    <div class="progress exam-progress-bar" style="height:6px;">
                        <div class="progress-bar" id="progressBarFill" role="progressbar" style="width:0%"></div>
                    </div>
                </div>

                <div id="soal-container">
                    <div class="skeleton-loader">
                        <div class="skeleton-line skeleton-badge"></div>
                        <div class="skeleton-line w-100"></div>
                        <div class="skeleton-line w-75"></div>
                        <div class="skeleton-line w-50 mb-4"></div>
                        <div class="skeleton-option"></div>
                        <div class="skeleton-option"></div>
                        <div class="skeleton-option"></div>
                        <div class="skeleton-option"></div>
                    </div>
                </div>

                <hr class="my-3 my-md-4 my-lg-5">

                <div class="exam-action-bar">
                    <button class="btn btn-light border exam-btn-prev" id="btn-prev" aria-label="Soal Sebelumnya" title="Soal Sebelumnya">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button type="button" class="btn exam-btn-ragu" id="btnRagu" aria-pressed="false">
                        <i class="fas fa-flag me-2"></i>RAGU-RAGU
                    </button>
                    <button class="btn btn-primary exam-btn-next" id="btn-next" aria-label="Soal Berikutnya" title="Soal Berikutnya">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-3 exam-nav-col" id="navContainer">
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 sticky-lg-top exam-nav-card">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Nomor Soal</h6>
                <div class="d-flex flex-wrap gap-2 justify-content-start" id="nav-numbers"></div>
                <div class="nav-summary small text-muted text-center mt-3 pt-2 border-top" id="navSummary">0 dari 0 terjawab</div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var examId        = <?= $exam_id ?>;
    var sisaWaktu     = <?= $sisa_detik ?>;
    var currentNumber = 1;
    var totalSoal     = 0;
    var _finishCalled = false;

    function showTimer(s) {
        var h = Math.floor(s / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;
        $('#timer').text(
            (h < 10 ? '0' + h : h) + ':' +
            (m < 10 ? '0' + m : m) + ':' +
            (sec < 10 ? '0' + sec : sec)
        );

        // Urgensi visual: pill & tombol SELESAI berubah warna saat waktu menipis
        var $pill   = $('#timerPill');
        var $finish = $('#btn-finish-exam');
        $pill.removeClass('timer-warning timer-danger');
        if (s <= 60) {
            $pill.addClass('timer-danger');
            $finish.removeClass('btn-outline-danger').addClass('btn-danger');
        } else if (s <= 300) {
            $pill.addClass('timer-warning');
            $finish.removeClass('btn-outline-danger').addClass('btn-danger');
        } else {
            $finish.removeClass('btn-danger').addClass('btn-outline-danger');
        }

        // Tombol Selesai hanya muncul & aktif 5 menit terakhir sebelum deadline
        if (s <= 300) {
            $finish.removeClass('d-none');
        } else {
            $finish.addClass('d-none');
        }

        // Jika sedang berada di soal terakhir, sinkronkan tampilan tombol Next
        // begitu window 5 menit terbuka/tertutup tanpa perlu re-navigasi soal
        if (totalSoal > 0 && currentNumber === totalSoal) {
            if (s <= 300) {
                $('#btn-next').html('SELESAI UJIAN').addClass('btn-success is-finish').removeClass('btn-primary');
            } else {
                $('#btn-next').html('<i class="fas fa-arrow-right"></i>').addClass('btn-primary').removeClass('btn-success is-finish');
            }
        }
    }

    function skeletonHtml() {
        return '<div class="skeleton-loader">' +
            '<div class="skeleton-line skeleton-badge"></div>' +
            '<div class="skeleton-line w-100"></div>' +
            '<div class="skeleton-line w-75"></div>' +
            '<div class="skeleton-line w-50 mb-4"></div>' +
            '<div class="skeleton-option"></div>' +
            '<div class="skeleton-option"></div>' +
            '<div class="skeleton-option"></div>' +
            '<div class="skeleton-option"></div>' +
        '</div>';
    }

    function updateProgress() {
        if (totalSoal > 0) {
            $('#progressText').text('Soal ' + currentNumber + ' dari ' + totalSoal);
            $('#progressBarFill').css('width', Math.round((currentNumber / totalSoal) * 100) + '%');
        }
    }

    function showBlockedAlert() {
        if (_finishCalled) return;
        _finishCalled = true;
        clearInterval(_timerInterval);
        clearInterval(_resyncInterval);
        Swal.fire({
            title: 'AKUN TERBLOKIR!',
            text: 'Terlalu banyak pelanggaran terdeteksi. Ujian Anda dihentikan oleh sistem.',
            icon: 'error',
            allowOutsideClick: false,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Keluar'
        }).then(function() { loadView('dashboard'); });
    }

    function autoFinishUjian() {
        if (_finishCalled) return;
        _finishCalled = true;
        clearInterval(_timerInterval);
        clearInterval(_resyncInterval);
        $('#timer').text('00:00:00');
        $('#timerPill').addClass('timer-danger');
        Swal.fire({
            title: 'Waktu Habis!',
            text: 'Sistem akan mengirimkan jawaban Anda secara otomatis.',
            icon: 'warning', timer: 3000, showConfirmButton: false, allowOutsideClick: false
        }).then(function() { spaSelesai(examId, 'timeout'); });
    }

    // Fungsi Terpadu untuk Log Pelanggaran & Alert
    function logSecurityViolation(type) {
        if (_finishCalled) return;
        
        $.post('ajax_cheat_log.php', { 
            exam_id: examId, 
            type: type,
            waktu: new Date().toISOString()
        }, function(res) {
            if (res && res.status === 'blocked') {
                showBlockedAlert();
            } else {
                Swal.fire({
                    title: 'Keamanan AXON CBT',
                    text: 'Tindakan mencurigakan (' + type + ') terdeteksi dan telah dicatat oleh sistem!',
                    icon: 'warning',
                    confirmButtonColor: '#4e73df'
                });
            }
        }, 'json');
    }

    // Tampilkan nilai awal timer sebelum tick pertama
    if (sisaWaktu > 0) {
        showTimer(sisaWaktu);
    } else {
        autoFinishUjian();
    }

    // Assign to shell globals so loadView() can clear them on navigation
    _timerInterval = setInterval(function() {
        sisaWaktu--;
        if (sisaWaktu <= 0) {
            autoFinishUjian();
            return;
        }
        showTimer(sisaWaktu);
    }, 1000);

    // High-Concurrency SRE: Perpanjang interval sinkronisasi waktu dan beri jitter acak
    // agar 10.000 siswa tidak memukul server secara serentak (mencegah Thundering Herd).
    var jitter = Math.floor(Math.random() * 30000); // 0-30 detik jitter
    _resyncInterval = setInterval(function() {
        $.get('ajax_get_waktu.php', { exam_id: examId }, function(res) {
            if (res.status === 'ok') {
                sisaWaktu = res.sisa_detik;
            } else if (res.status === 'blocked') {
                showBlockedAlert();
            } else if (res.status === 'finished') {
                autoFinishUjian();
            }
        }, 'json');
    }, 120000 + jitter);

    function finishExamConfirm() {
        Swal.fire({
            title: 'Selesai Ujian?',
            text: 'Periksa kembali jawaban Anda. Ujian tidak bisa diulang!',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Selesai',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) spaSelesai(examId);
        });
    }

    function refreshNavSummary() {
        var answered = $('#nav-numbers .no-box.answered').length;
        var ragu     = $('#nav-numbers .no-box.ragu').length;
        $('#navSummary').text(answered + ' dari ' + totalSoal + ' terjawab' + (ragu > 0 ? ' · ' + ragu + ' ragu-ragu' : ''));
        updateProgress();
    }

    var _saveTimers = {};
    function saveJawabanToServer(qId, val, isAnswered) {
        // 1. Instant DOM Update tanpa chained request
        var $targetBox = $('#nav-numbers .no-box[data-no="' + currentNumber + '"]');
        if (isAnswered) {
            $targetBox.addClass('answered');
        } else {
            $targetBox.removeClass('answered');
        }
        refreshNavSummary();

        // 2. Buffer LocalStorage untuk redundansi koneksi klien
        try {
            var storageKey = 'cbt_ans_' + examId;
            var stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
            stored[qId] = { jawaban: val, waktu: Date.now() };
            localStorage.setItem(storageKey, JSON.stringify(stored));
        } catch (e) {}

        // 3. Debounce 300ms + Random Jitter 0-150ms agar server tidak terkena thundering herd
        if (_saveTimers[qId]) {
            clearTimeout(_saveTimers[qId]);
        }
        var delay = 300 + Math.floor(Math.random() * 150);
        _saveTimers[qId] = setTimeout(function() {
            delete _saveTimers[qId];
            $.post('ajax_save_jawaban.php', {
                exam_id: examId,
                question_id: qId,
                jawaban: val
            }, function(res) {
                if (res && res.status === 'blocked') {
                    showBlockedAlert();
                }
            }, 'json');
        }, delay);
    }

    window.loadSoal = function loadSoal(num) {
        currentNumber = num;
        $('#btn-prev, #btn-next').prop('disabled', true);
        $('#soal-container').fadeOut(100, function() {
            $(this).html(skeletonHtml()).fadeIn(100);
            $.get('ajax_get_soal.php', { exam_id: examId, no: num }, function(res) {
                $('#soal-container').html(res.html);
                renderMath(document.getElementById('soal-container'));
                
                // Jika grid nomor belum dimuat, panggil updateNav sekali, selain itu update via DOM lokal
                if ($('#nav-numbers').children().length === 0) {
                    updateNav();
                } else {
                    $('#nav-numbers .no-box').removeClass('active');
                    $('#nav-numbers .no-box[data-no="' + num + '"]').addClass('active');
                    updateProgress();
                }

                $('#btn-prev').prop('disabled', num === 1);
                $('#btn-next').prop('disabled', false);
                if (num === totalSoal && sisaWaktu <= 300) {
                    $('#btn-next').html('SELESAI UJIAN').addClass('btn-success is-finish').removeClass('btn-primary');
                } else {
                    $('#btn-next').html('<i class="fas fa-arrow-right"></i>').addClass('btn-primary').removeClass('btn-success is-finish');
                }
                $('#btnRagu').toggleClass('is-active', res.is_ragu == 1).attr('aria-pressed', res.is_ragu == 1 ? 'true' : 'false');
            }, 'json');
        });
    }

    function updateNav() {
        $.get('ajax_get_nav.php', { exam_id: examId, current: currentNumber }, function(res) {
            $('#nav-numbers').html(res.html);
            totalSoal = res.total;
            refreshNavSummary();
        }, 'json');
    }

    // Namespaced events so they can be safely re-bound on next view load
    $(document).off('change.ujian').on('change.ujian', '.answer-input', function() {
        var input = $(this);
        var val   = input.val();
        var hasAnswer = false;
        if (input.attr('type') === 'checkbox') {
            val = $('.answer-input:checked').map(function() { return $(this).val(); }).get();
            hasAnswer = (val.length > 0);
        } else {
            hasAnswer = true;
        }
        if (input.attr('type') === 'radio') {
            $('.option-item').removeClass('selected');
            input.closest('.option-item').addClass('selected');
        } else {
            input.closest('.option-item').toggleClass('selected', input.is(':checked'));
        }
        saveJawabanToServer($('#q_id').val(), val, hasAnswer);
    });

    // Toggle dropdown menjodohkan
    $(document).off('click.ujian-match-toggle').on('click.ujian-match-toggle', '.matching-selected-display', function(e) {
        e.stopPropagation();
        var $wrapper = $(this).closest('.matching-custom-select');
        var $dd = $wrapper.find('.matching-options-dropdown');
        $('.matching-options-dropdown').not($dd).hide();
        if ($dd.is(':visible')) {
            $dd.hide();
        } else {
            $dd.css({ top: $(this).outerHeight() + 'px', left: '0', width: $(this).outerWidth() + 'px' }).show();
        }
    });

    // Pilih opsi menjodohkan
    $(document).off('click.ujian-match-pick').on('click.ujian-match-pick', '.matching-option', function(e) {
        e.stopPropagation();
        var $wrapper = $(this).closest('.matching-custom-select');
        var idx = parseInt($(this).data('idx'));
        var val = (idx >= 0 && window._matchChoicesData && window._matchChoicesData[idx] !== undefined)
                    ? window._matchChoicesData[idx] : '';
        var displayHtml = (idx >= 0)
                    ? $(this).html()
                    : '<span class="text-muted">-- Pilih Jawaban --</span>';

        $wrapper.find('.selected-content').html(displayHtml);
        $wrapper.find('.matching-input').val(val);
        $wrapper.find('.matching-options-dropdown').hide();
        $wrapper.find('.matching-option').removeClass('bg-primary-subtle fw-semibold');
        if (idx >= 0) $(this).addClass('bg-primary-subtle fw-semibold');

        // Render KaTeX pada pilihan yang tampil
        renderMath($wrapper.find('.selected-content')[0]);

        // Simpan semua jawaban menjodohkan
        var mapping = {};
        var hasAnswer = false;
        $('#soal-container .matching-input').each(function() {
            var rowId = $(this).data('row-id');
            var v     = $(this).val();
            if (v !== '') {
                mapping[rowId] = v;
                hasAnswer = true;
            }
        });
        saveJawabanToServer($('#q_id').val(), mapping, hasAnswer);
    });

    // Tutup semua dropdown saat klik di luar
    $(document).off('click.ujian-match-outside').on('click.ujian-match-outside', function() {
        $('.matching-options-dropdown').hide();
    });

    $(document).off('click.ujian-ragu').on('click.ujian-ragu', '#btnRagu', function() {
        var $btn = $(this);
        var newState = !$btn.hasClass('is-active');
        $btn.toggleClass('is-active', newState).attr('aria-pressed', newState ? 'true' : 'false');
        
        var $targetBox = $('#nav-numbers .no-box[data-no="' + currentNumber + '"]');
        $targetBox.toggleClass('ragu', newState);
        refreshNavSummary();

        $.post('ajax_toggle_ragu.php', {
            exam_id: examId, question_id: $('#q_id').val(), ragu: newState ? 1 : 0
        }, function(res) {
            if (res && res.status === 'blocked') {
                showBlockedAlert();
            }
        }, 'json');
    });

    $('#btn-next').off('click.ujian').on('click.ujian', function() {
        if (currentNumber < totalSoal) {
            loadSoal(currentNumber + 1);
        } else if (sisaWaktu <= 300) {
            finishExamConfirm();
        } else {
            Swal.fire({
                title: 'Belum Bisa Diselesaikan',
                text: 'Tombol Selesai aktif 5 menit sebelum waktu ujian habis',
                icon: 'info',
                timer: 2500,
                showConfirmButton: false
            });
        }
    });

    $('#btn-prev').off('click.ujian').on('click.ujian', function() {
        if (currentNumber > 1) loadSoal(currentNumber - 1);
    });

    $('#btn-toggle-nav').off('click.ujian').on('click.ujian', function() {
        $('#navContainer').addClass('show');
        $('#navDrawerBackdrop').addClass('show');
    });

    function closeNavDrawer() {
        $('#navContainer').removeClass('show');
        $('#navDrawerBackdrop').removeClass('show');
    }

    $('#navDrawerBackdrop').off('click.ujian').on('click.ujian', closeNavDrawer);

    // Tutup drawer otomatis saat siswa memilih nomor soal di mobile
    $(document).off('click.ujian-navclose').on('click.ujian-navclose', '.no-box', function() {
        if (window.innerWidth < 992) closeNavDrawer();
    });

    $('#btn-finish-exam').off('click.ujian').on('click.ujian', function() {
        finishExamConfirm();
    });

    // --- SISTEM KEAMANAN TERPADU (MOBILE & DESKTOP) ---

    // 1. Deteksi Multi-Touch (3 Jari - Screenshot Gesture)
    $(document).on('touchstart.security', function(e) {
        if (e.originalEvent.touches.length >= 3) {
            document.body.style.opacity = "0";
            logSecurityViolation("Screenshot");
        }
    });

    $(document).on('touchend.security', function() {
        setTimeout(function() {
            document.body.style.opacity = "1";
        }, 1500);
    });

    // 2. Deteksi App-Switcher & Kehilangan Fokus
    window._onBlurUjian = function() {
        document.body.style.opacity = "0";
        $('#privacy-screen').show();
        
        window._blurCheatTimeout = setTimeout(function() {
            logSecurityViolation("Pindah Aplikasi/Blur");
        }, 300);
    };

    window._onFocusUjian = function() {
        clearTimeout(window._blurCheatTimeout);
        document.body.style.opacity = "1";
        $('#privacy-screen').hide();
    };

    window.addEventListener('blur', window._onBlurUjian);
    window.addEventListener('focus', window._onFocusUjian);

    // 3. Blokir Fungsi Native & Copy-Paste
    $(document).on('copy.security cut.security paste.security', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Aksi Dilarang',
            text: 'Fungsi Copy, Cut, dan Paste dinonaktifkan demi keamanan ujian.',
            icon: 'error',
            timer: 2000,
            showConfirmButton: false
        });
        return false;
    });

    // Anti-Screenshot (PrintScreen)
    $(window).on('keyup.security', function(e) {
        if (e.key === 'PrintScreen' || (e.ctrlKey && e.key === 'p')) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(""); 
            }
            $('#privacy-screen').show().fadeOut(1000);
            logSecurityViolation("Shortcut Screenshot/Print");
            return false;
        }
    });

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            $('#privacy-screen').show();
            document.body.style.opacity = "0";
        } else {
            $('#privacy-screen').hide();
            document.body.style.opacity = "1";
        }
    });

    loadSoal(1);
})();
</script>
