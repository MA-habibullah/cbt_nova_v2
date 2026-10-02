<?php
// Fallback: jika database.php belum di-include, hitung BASE_URL secara dinamis
if (!defined('BASE_URL')) {
    $proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? rtrim($_SERVER['HTTP_X_FORWARDED_PROTO'], '/') . '://'
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://');
    $basepath = defined('APP_BASEPATH') ? APP_BASEPATH : '/';
    define('BASE_URL', $proto . $_SERVER['HTTP_HOST'] . $basepath);
}

// Ambil data logo dan nama sekolah (dengan session cache 5 menit)
$_hdr_setting = get_app_settings();
$namaSekolah  = !empty($_hdr_setting['nama_sekolah']) ? $_hdr_setting['nama_sekolah'] : "CBT NATIVE";
$fileFavicon  = BASE_URL . "assets/img/logo.png";
if (!empty($_hdr_setting['logo'])) {
    $path_fisik = dirname(__DIR__) . "/assets/img/logo/" . $_hdr_setting['logo'];
    if (file_exists($path_fisik)) {
        $fileFavicon = BASE_URL . "assets/img/logo/" . $_hdr_setting['logo'];
    }
}
unset($_hdr_setting);
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?= htmlspecialchars($namaSekolah) ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= esc(BASE_URL) ?>assets/css/style.css">
    <link rel="icon" type="image/png" href="<?= esc($fileFavicon) ?>">
    <style>
        /* Batasi ukuran gambar di konten soal (preview list, opsi jawaban, menjodohkan) */
        .soal-konten img { max-width: 100%; height: auto; border-radius: 6px; display: block; margin: 6px auto; }
        .p-2.border.rounded img { max-height: 100px; width: auto; max-width: 100%; border-radius: 4px; vertical-align: middle; }
        /* Batasi gambar di dalam konten TinyMCE yang sudah dirender */
        .tox-edit-area img, .mce-content-body img { max-width: 100% !important; height: auto !important; }
    </style>

    <script>
    var CSRF_TOKEN = '<?= csrf_token() ?>';

    // 1. Auto-inject CSRF token ke semua form POST (form HTML biasa, bukan AJAX)
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form').forEach(function(form) {
            if ((form.method || '').toUpperCase() === 'POST' && !form.querySelector('input[name="csrf_token"]')) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'csrf_token';
                inp.value = CSRF_TOKEN;
                form.appendChild(inp);
            }
        });
    });

    // 2. Kirim CSRF token + deteksi session expired pada semua AJAX request (admin & guru)
    (function() {
        var _open = XMLHttpRequest.prototype.open;
        var _send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function(method) {
            this._csrfMethod = method;
            return _open.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function() {
            if (this._csrfMethod && this._csrfMethod.toUpperCase() !== 'GET') {
                try { this.setRequestHeader('X-CSRF-Token', CSRF_TOKEN); } catch(e) {}
            }
            this.addEventListener('load', function() {
                try {
                    var res = JSON.parse(this.responseText);
                    if (res && res.status === 'session_expired') {
                        window.location.href = '<?= esc(BASE_URL) ?>index.php?pesan=sesi_berakhir';
                    }
                } catch(e) {}
            });
            return _send.apply(this, arguments);
        };
    })();
    </script>

    <script src="<?= esc(BASE_URL) ?>assets/js/tinymce/tinymce.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
    <script>
    /**
     * renderMath(el)
     * Render semua formula LaTeX di dalam elemen.
     * Gunakan $...$ untuk inline, $$...$$ untuk display/block.
     */
    function renderMath(el) {
        if (!el || typeof renderMathInElement === 'undefined') return;
        renderMathInElement(el, {
            delimiters: [
                { left: '$$', right: '$$', display: true  },
                { left: '$',  right: '$',  display: false }
            ],
            throwOnError: false
        });
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
</head>