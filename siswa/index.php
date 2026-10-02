<?php
require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; -webkit-touch-callout: none; -webkit-user-select: none; user-select: none; }

        /* Dashboard */
        .navbar-siswa { background: white; border-bottom: 1px solid #e3e6f0; }
        .card-profile { border: none; border-radius: 15px; background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
        .exam-card { border: none; border-radius: 12px; transition: 0.3s; border-left: 5px solid #4e73df; }
        .exam-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .badge-status { font-size: 0.75rem; padding: 5px 12px; border-radius: 50px; }

        /* Konfirmasi */
        .card-confirm { border: none; border-radius: 20px; }
        .info-label { color: #858796; font-size: 0.85rem; text-transform: uppercase; font-weight: 700; }
        .info-value { color: #4e73df; font-weight: 700; font-size: 1.1rem; word-break: break-word; }
        .alert-warning-custom { background-color: #fff4e5; border-left: 5px solid #ffa117; color: #664d03; }
        .warning-icon { font-size: 2rem; }

        /* Ujian */
        :root { --primary-color: #4e73df; --success-color: #1cc88a; --warning-color: #f6c23e; }
        .exam-header { background: white; border-bottom: 2px solid #e3e6f0; z-index: 1000; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .no-box { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;
                  border-radius: 10px; border: 2px solid #d1d3e2; cursor: pointer; font-weight: 700; transition: 0.2s; }
        .no-box.active { border-color: var(--primary-color); background: #eef2ff; color: var(--primary-color); transform: scale(1.1); }
        .no-box.answered { background: var(--success-color); color: white; border-color: var(--success-color); }
        .no-box.ragu { background: var(--warning-color); color: white; border-color: var(--warning-color); }
        .option-item { border: 2px solid #eaecf4; border-radius: 12px; padding: 15px 44px 15px 15px; margin-bottom: 12px;
                       cursor: pointer; transition: 0.2s; display: flex; align-items: flex-start; position: relative; }
        .option-item:hover { border-color: var(--primary-color); background: #f8f9fc; }
        .option-item:active { transform: scale(0.98); }
        .option-item.selected { border-color: var(--primary-color); background: #f0f3ff; box-shadow: 0 2px 8px rgba(78,115,223,0.15); }
        .option-item.selected::after { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
                                       position: absolute; top: 15px; right: 15px; color: var(--primary-color); }
        .timer-box { font-size: 1.4rem; font-weight: 800; color: var(--primary-color); letter-spacing: 1px; font-variant-numeric: tabular-nums; }
        .question-text { font-size: 1.15rem; color: #2e384d; line-height: 1.7; }

        /* Timer pill: warna berubah sesuai urgensi waktu tersisa */
        .timer-pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: #eef2ff; color: var(--primary-color);
            padding: 6px 16px; border-radius: 50px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .timer-pill .timer-box { font-size: 1.1rem; }
        .timer-pill.timer-warning { background: #fff8e6; color: #b8860b; }
        .timer-pill.timer-warning .timer-box { color: #b8860b; }
        .timer-pill.timer-danger { background: #fdecea; color: #d33; animation: timerPulse 1s ease-in-out infinite; }
        .timer-pill.timer-danger .timer-box { color: #d33; }
        @keyframes timerPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }

        /* Progress "Soal X dari Y" di atas kartu soal */
        .exam-progress-bar { background-color: #eaecf4; border-radius: 50px; overflow: hidden; }
        .exam-progress-bar .progress-bar { background-color: var(--primary-color); transition: width 0.3s ease; }

        /* Skeleton loader saat memuat soal berikutnya */
        .skeleton-line, .skeleton-option {
            background: linear-gradient(90deg, #eceff5 25%, #f6f7fb 37%, #eceff5 63%);
            background-size: 400% 100%;
            animation: skeletonShine 1.4s ease infinite;
            border-radius: 8px;
        }
        .skeleton-line { height: 14px; margin-bottom: 10px; }
        .skeleton-badge { width: 130px; height: 26px; border-radius: 50px; margin-bottom: 16px; }
        .skeleton-option { height: 54px; margin-bottom: 12px; border-radius: 12px; }
        @keyframes skeletonShine { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
        @media (prefers-reduced-motion: reduce) {
            .skeleton-line, .skeleton-option, .timer-pill.timer-danger { animation: none; }
        }

        /* Batasi ukuran gambar agar tidak merusak layout */
        .question-text img { max-width: 100%; height: auto; border-radius: 6px; display: block; margin: 8px auto; }
        .option-item img   { max-height: 120px; width: auto; max-width: 100%; border-radius: 4px; vertical-align: middle; }
        .matching-options-dropdown img, .selected-content img { max-height: 80px; width: auto; max-width: 100%; border-radius: 4px; vertical-align: middle; }

        /* Menjodohkan */
        .matching-custom-select { position: relative; }
        .matching-selected-display { transition: border-color 0.2s, box-shadow 0.2s; }
        .matching-selected-display:hover { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(78,115,223,0.1); }
        .matching-options-dropdown .matching-option { cursor: pointer; transition: background 0.15s; font-size: 0.95rem; }
        .matching-options-dropdown .matching-option:hover { background: #f0f3ff; }
        .matching-options-dropdown .matching-option:last-child { border-bottom: none !important; }
        .matching-row { transition: box-shadow 0.2s; }
        .matching-row:hover { box-shadow: 0 4px 12px rgba(78,115,223,0.1) !important; }
        .matching-question-text { font-size: 0.97rem; line-height: 1.6; color: #2e384d; }

        /* Exam header: dua kelompok flex agar bisa dipecah jadi 2 baris di mobile */
        .exam-title-group { min-width: 0; flex: 1 1 auto; }
        .exam-title-group h6 { min-width: 0; }
        .exam-meta-group { flex: 0 0 auto; }

        /* Nomor soal: panel geser (drawer) di layar < 992px */
        .nav-drawer-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1040;
        }
        .nav-drawer-backdrop.show { display: block; }

        /* Touch target minimum di navbar/header mobile */
        .navbar-siswa .btn,
        .exam-header .btn { min-height: 40px; }

        /* Layout halaman ujian: batasi lebar total & lebar panel nomor soal tetap
           supaya tidak melebar berlebihan di layar sangat lebar */
        :root { --exam-header-height: 64px; }
        .exam-page-wrap { max-width: 1400px; margin-left: auto; margin-right: auto; }
        .exam-nav-card { top: calc(var(--exam-header-height) + 16px); }

        @media (min-width: 992px) {
            .exam-layout-row { flex-wrap: nowrap; }
            .exam-main-col { flex: 1 1 auto; max-width: 800px; }
            .exam-nav-col { flex: 0 0 300px; max-width: 300px; }
        }

        /* Action bar (Sebelumnya/Ragu-ragu/Berikutnya): Sebelumnya & Berikutnya konsisten
           sebagai tombol ikon panah bulat; Ragu-ragu di tengah. Satu baris, merata. */
        .exam-action-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .exam-btn-prev, .exam-btn-next {
            width: 48px; height: 48px; padding: 0; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; font-size: 1rem;
        }
        .exam-btn-next.is-finish {
            width: auto; height: auto; padding: 10px 24px; border-radius: 50px; font-weight: 700;
        }
        .exam-btn-ragu {
            flex: 0 0 auto;
            border: 2px solid #eaecf4;
            background: #f8f9fc;
            color: #b8860b;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 50px;
            padding: 0 18px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            transition: 0.2s;
        }
        .exam-btn-ragu:hover { border-color: var(--warning-color); }
        .exam-btn-ragu.is-active { background: var(--warning-color); border-color: var(--warning-color); color: #fff; }

        /* KaTeX & Formula Responsive Wrapping */
        .katex-display {
            overflow-x: auto !important;
            overflow-y: hidden !important;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
            padding: 6px 0;
        }
        .katex {
            font-size: 1.05em;
            max-width: 100%;
        }
        .soal-konten {
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .soal-konten table {
            max-width: 100%;
        }

        @media (max-width: 991.98px) {
            #navContainer {
                position: fixed;
                top: 0;
                right: -100%;
                width: 85%;
                max-width: 320px;
                height: 100vh;
                z-index: 1050;
                background: #f8f9fc;
                padding: 1rem;
                overflow-y: auto;
                transition: right 0.25s ease;
            }
            #navContainer.show { right: 0; }
            #navContainer .card { box-shadow: none !important; position: static !important; top: auto !important; margin-bottom: 0 !important; }

            /* Action bar jadi bottom bar fixed supaya selalu terjangkau tanpa scroll */
            .exam-action-bar {
                position: fixed;
                left: 0; right: 0; bottom: 0;
                background: #fff;
                padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
                box-shadow: 0 -4px 16px rgba(0,0,0,0.08);
                z-index: 1030;
            }
            .exam-question-card { padding-bottom: 92px !important; }
        }

        @media (max-width: 767.98px) {
            /* Header dashboard: paksa tombol jadi satu kelompok, teks disembunyikan di layar sempit */
            .dashboard-nav-actions .btn { padding-left: 0.65rem; padding-right: 0.65rem; }

            /* Header ujian: pecah jadi 2 baris — judul di atas, timer+aksi di bawah */
            .exam-title-group { flex: 1 1 100%; }
            .exam-meta-group { flex: 1 1 100%; }
            .timer-box { font-size: 1.15rem; }
            .exam-header #btn-finish-exam { padding-left: 1.1rem; padding-right: 1.1rem; font-size: 0.85rem; }
            .exam-header .container-fluid { row-gap: 6px; }

            .question-text { font-size: 1.05rem; }

            /* Konfirmasi ujian */
            .warning-icon { font-size: 1.4rem; }
            .info-value { font-size: 0.95rem; }
            .card-confirm { border-radius: 16px; }
            .h4-mobile { font-size: 1.3rem; }
        }

        /* Landscape mobile: viewport pendek, rapatkan padding vertikal */
        @media (max-width: 991.98px) and (orientation: landscape) {
            .exam-header { padding-top: 4px; padding-bottom: 4px; }
            .exam-question-card { padding: 12px !important; padding-bottom: 90px !important; }
            .question-text { font-size: 1rem; }
            .exam-progress { margin-bottom: 8px !important; }
        }
    </style>
</head>
<body oncontextmenu="return false;" onselectstart="return false;">

<?php include 'fs_overlay.php'; ?>

<div id="app-content">
    <div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
        <div class="spinner-border text-primary" role="status"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function renderMath(el) {
    if (!el || typeof renderMathInElement === 'undefined') return;
    var content = el.textContent || el.innerText || '';
    if (content.indexOf('$') === -1) return;
    try {
        renderMathInElement(el, {
            delimiters: [
                { left: '$$', right: '$$', display: true  },
                { left: '$',  right: '$',  display: false }
            ],
            throwOnError: false
        });
    } catch(e) {}
}
</script>
<script>
// Global state — ujian view modifies these so loadView() can clean them up
var _timerInterval  = null;
var _resyncInterval = null;
var CSRF_TOKEN      = '<?= csrf_token() ?>';

// Kirim CSRF token pada semua AJAX POST siswa
$.ajaxSetup({ headers: { 'X-CSRF-Token': CSRF_TOKEN } });

function loadView(view, params) {
    // Clean up ujian resources
    clearInterval(_timerInterval);
    clearInterval(_resyncInterval);
    _timerInterval  = null;
    _resyncInterval = null;

    if (window._onBlurUjian) {
        window.removeEventListener('blur', window._onBlurUjian);
        window._onBlurUjian = null;
    }
    if (window._onFocusUjian) {
        window.removeEventListener('focus', window._onFocusUjian);
        window._onFocusUjian = null;
    }
    clearTimeout(window._blurCheatTimeout);

    // Reset visual state dari ujian view
    document.body.style.opacity = '1';
    var _ps = document.getElementById('privacy-screen');
    if (_ps) _ps.style.display = 'none';

    var url = 'ajax/view_' + view + '.php';
    if (params) url += '?' + $.param(params);

    $('#app-content').html(
        '<div class="d-flex justify-content-center align-items-center" style="min-height:60vh;">' +
        '<div class="spinner-border text-primary" role="status"></div></div>'
    );

    $.get(url, function(html) {
        $('#app-content').html(html);

        // Update browser URL (bookmarkable, no reload)
        var stateUrl = 'index.php';
        if (view !== 'dashboard') {
            stateUrl += '?view=' + view + (params ? '&' + $.param(params) : '');
        } else if (params && params.msg) {
            stateUrl += '?msg=' + params.msg;
        }
        history.pushState({ view: view, params: params || null }, 'CBT Online', stateUrl);
    });
}

// Fungsi Refresh Internal agar tetap Fullscreen & tidak muncul Blue Screen
window.internalRefresh = function() {
    var params = new URLSearchParams(window.location.search);
    var view = params.get('view') || 'dashboard';
    var p = {};
    // Ambil semua parameter URL kecuali 'view' dan 'msg'
    params.forEach((value, key) => {
        if (key !== 'view' && key !== 'msg') p[key] = value;
    });
    
    loadView(view, p);
};

// Handle browser back/forward button
window.addEventListener('popstate', function(e) {
    clearInterval(_timerInterval);
    clearInterval(_resyncInterval);
    if (window._onBlurUjian) {
        window.removeEventListener('blur', window._onBlurUjian);
        window._onBlurUjian = null;
    }
    if (window._onFocusUjian) {
        window.removeEventListener('focus', window._onFocusUjian);
        window._onFocusUjian = null;
    }
    clearTimeout(window._blurCheatTimeout);
    var v = (e.state && e.state.view) ? e.state.view : 'dashboard';
    var p = (e.state && e.state.params) ? e.state.params : null;
    var url = 'ajax/view_' + v + '.php';
    if (p) url += '?' + $.param(p);
    $.get(url, function(html) { $('#app-content').html(html); });
});

// Called from ujian view to finalise the exam via AJAX then go to dashboard
function spaSelesai(examId, reason) {
    // Dipanggil hanya saat ujian benar-benar selesai/gagal — bukan saat "too_early",
    // supaya timer & listener keamanan tetap aktif kalau siswa masih di tengah ujian.
    function cleanupUjianState() {
        clearInterval(_timerInterval);
        clearInterval(_resyncInterval);
        _timerInterval  = null;
        _resyncInterval = null;
        if (window._onBlurUjian) {
            window.removeEventListener('blur', window._onBlurUjian);
            window._onBlurUjian = null;
        }
        if (window._onFocusUjian) {
            window.removeEventListener('focus', window._onFocusUjian);
            window._onFocusUjian = null;
        }
        clearTimeout(window._blurCheatTimeout);
    }
    $.get('proses_selesai_ujian.php', { id: examId, reason: reason || '', _spa: 1 }, function(res) {
        if (res && res.status === 'too_early') {
            // Server menolak: belum masuk window 5 menit terakhir. Biarkan siswa
            // tetap di halaman ujian — jangan hentikan timer/listener, jangan pindah dashboard.
            Swal.fire({
                title: 'Belum Waktunya Selesai',
                text: res.message || 'Ujian belum bisa diselesaikan saat ini. Silakan coba lagi sesaat lagi.',
                icon: 'warning',
                confirmButtonColor: '#4e73df'
            });
            return;
        }
        cleanupUjianState();
        loadView('dashboard', { msg: 'ujian_selesai' });
    }, 'json').fail(function() {
        cleanupUjianState();
        loadView('dashboard', { msg: 'ujian_selesai' });
    });
}

// Handle session expiry on any AJAX call
$(document).ajaxComplete(function(event, xhr) {
    try {
        var res = JSON.parse(xhr.responseText);
        if (res && res.status === 'session_expired') {
            clearInterval(_timerInterval);
            clearInterval(_resyncInterval);
            window.location.href = '../index.php?pesan=sesi_berakhir';
        }
    } catch(e) {}
});

$(document).ready(function() {
    var params  = new URLSearchParams(window.location.search);
    var view    = params.get('view') || 'dashboard';
    var viewParams = null;

    if ((view === 'ujian' || view === 'konfirmasi') && params.get('id')) {
        viewParams = { id: params.get('id') };
    } else if (view === 'dashboard' && params.get('msg')) {
        viewParams = { msg: params.get('msg') };
    }

    loadView(view, viewParams);
});
</script>
</body>
</html>
