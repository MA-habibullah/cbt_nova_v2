<?php
/**
 * Fullscreen Overlay – include di semua halaman siswa.
 * Mode ditentukan oleh $fs_mode yang di-set sebelum include:
 *   'first'    → overlay penuh (halaman pertama, belum ada consent)
 *   'resume'   → overlay ringkas "Ketuk untuk Lanjutkan" (pindah halaman)
 * Jika $fs_mode tidak di-set, JS akan menentukan sendiri via sessionStorage.
 */
?>
<style>
#fs-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: white; text-align: center; padding: 20px;
    cursor: pointer; user-select: none;
    transition: opacity 0.35s;
}
#fs-overlay.fs-resume { background: rgba(34, 74, 190, 0.97); }
#fs-overlay .fs-icon  { font-size: 4rem; margin-bottom: 1.5rem; animation: fsPulse 2s infinite; }
#fs-overlay .fs-icon.fs-icon-sm { font-size: 2.5rem; margin-bottom: 1rem; }
#fs-overlay .fs-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.5rem; }
#fs-overlay .fs-title.fs-title-sm { font-size: 1.2rem; }
#fs-overlay .fs-subtitle { font-size: 0.9rem; opacity: 0.8; margin-bottom: 2rem; }
#fs-overlay .fs-btn {
    background: white; color: #4e73df; border: none;
    padding: 14px 40px; border-radius: 50px; font-weight: 700;
    font-size: 1rem; box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    cursor: pointer;
}
#fs-overlay .fs-btn.fs-btn-sm { padding: 10px 30px; font-size: 0.9rem; }
#fs-overlay .fs-hint { font-size: 0.75rem; opacity: 0.6; margin-top: 1rem; }
@keyframes fsPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
</style>

<div id="fs-overlay" style="display:none;" aria-hidden="true">
    <div class="fs-icon"><i class="fas fa-graduation-cap"></i></div>
    <div class="fs-title">CBT Online</div>
    <div class="fs-subtitle" id="fs-subtitle-text">Sistem Ujian Berbasis Komputer</div>
    <button class="fs-btn" id="fs-enter-btn" type="button">
        <i class="fas fa-expand me-2"></i> <span id="fs-btn-label">Masuk ke Layar Penuh</span>
    </button>
    <div class="fs-hint" id="fs-hint-text">Tap / klik tombol di atas untuk memulai</div>
</div>

<script>
(function () {
    var FS_KEY = 'cbt_fs_consent';

    var _hideOverlayTimer = null;

    function enterFS() {
        var el = document.documentElement;
        var req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (req && !document.fullscreenElement && !document.webkitFullscreenElement && !window.navigator.standalone) {
            try {
                var result = req.call(el);
                if (result && typeof result.then === 'function') {
                    return result.catch(function () {});
                }
            } catch (e) {}
        }
        return Promise.resolve();
    }

    function hideOverlay() {
        var o = document.getElementById('fs-overlay');
        if (!o) return;
        clearTimeout(_hideOverlayTimer);
        o.style.opacity = '0';
        _hideOverlayTimer = setTimeout(function () { o.style.display = 'none'; }, 350);
    }

    function showOverlay(resume) {
        clearTimeout(_hideOverlayTimer);
        _hideOverlayTimer = null;
        var o = document.getElementById('fs-overlay');
        if (!o) return;
        if (resume) {
            o.classList.add('fs-resume');
            o.querySelector('.fs-icon').classList.add('fs-icon-sm');
            o.querySelector('.fs-title').classList.add('fs-title-sm');
            o.querySelector('.fs-title').textContent = 'Layar Penuh Diperlukan';
            document.getElementById('fs-subtitle-text').textContent = 'Klik tombol di bawah untuk melanjutkan';
            document.getElementById('fs-btn-label').textContent = 'Lanjutkan';
            o.querySelector('.fs-btn').classList.add('fs-btn-sm');
            document.getElementById('fs-hint-text').textContent = 'Layar penuh wajib selama sesi ujian';
        }
        o.style.display = 'flex';
        o.style.opacity = '0';
        // force reflow then fade in
        o.offsetHeight;
        o.style.opacity = '1';
    }

    // Kunci fullscreen – re-show overlay jika siswa keluar (Esc/F11)
    function attachLock() {
        var handler = function () {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !window.navigator.standalone) {
                showOverlay(true);
            }
        };
        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange']
            .forEach(function (e) { document.addEventListener(e, handler); });
    }

    document.getElementById('fs-enter-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        enterFS().then(function () {
            sessionStorage.setItem(FS_KEY, '1');
            hideOverlay();
        });
    });

    // Sudah fullscreen (PWA / sudah masuk sebelumnya di tab yang sama)
    if (document.fullscreenElement || document.webkitFullscreenElement || window.navigator.standalone) {
        attachLock();
        return;
    }

    // Sudah pernah consent di sesi ini → overlay ringkas
    if (sessionStorage.getItem(FS_KEY)) {
        showOverlay(true);
    } else {
        // Pertama kali → overlay penuh
        showOverlay(false);
    }

    attachLock();
})();
</script>
