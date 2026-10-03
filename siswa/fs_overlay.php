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
    cursor: pointer; user-select: none; -webkit-user-select: none;
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

    // Cross-Platform OS & Device Detection Helper
    window.isIOSDevice = function() {
        return (/iPad|iPhone|iPod/.test(navigator.userAgent) || 
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) && 
               !window.MSStream;
    };

    window.isNativeFullscreenSupported = function() {
        var el = document.documentElement;
        return !!(el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen);
    };

    window.isFullscreenActive = function() {
        return !!(document.fullscreenElement || 
                  document.webkitFullscreenElement || 
                  document.mozFullScreenElement || 
                  document.msFullscreenElement || 
                  window.navigator.standalone || 
                  (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches));
    };

    window.requestUniversalFullscreen = function(element) {
        element = element || document.documentElement;
        try {
            if (element.requestFullscreen) {
                var p = element.requestFullscreen();
                if (p && typeof p.catch === 'function') {
                    return p.catch(function(err) { console.warn('Fullscreen request failed:', err); });
                }
                return Promise.resolve();
            } else if (element.webkitRequestFullscreen) {
                var pWebkit = element.webkitRequestFullscreen();
                if (pWebkit && typeof pWebkit.catch === 'function') {
                    return pWebkit.catch(function(err) { console.warn('Webkit fullscreen failed:', err); });
                }
                return Promise.resolve();
            } else if (element.mozRequestFullScreen) {
                return element.mozRequestFullScreen();
            } else if (element.msRequestFullscreen) {
                return element.msRequestFullscreen();
            }
        } catch (e) {
            console.warn('Fullscreen invocation error:', e);
        }

        // Fallback untuk iOS Safari (iPhone/iPad) atau browser tanpa dukungan Element Fullscreen
        if (window.isIOSDevice()) {
            document.body.classList.add('ios-simulated-fullscreen');
            document.documentElement.classList.add('ios-simulated-fullscreen');
            try { window.scrollTo(0, 1); } catch (e) {}
        }
        return Promise.resolve();
    };

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

    // Kunci fullscreen – re-show overlay jika siswa keluar (Esc/F11) pada browser yang mendukung native fullscreen
    function attachLock() {
        var handler = function () {
            if (!window.isFullscreenActive()) {
                // Pada perangkat iOS reguler tanpa native element fullscreen, jangan loop overlay jika sudah consent
                if (window.isIOSDevice() && !window.isNativeFullscreenSupported() && sessionStorage.getItem(FS_KEY)) {
                    return;
                }
                showOverlay(true);
            }
        };
        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange']
            .forEach(function (e) { document.addEventListener(e, handler); });
    }

    var enterBtn = document.getElementById('fs-enter-btn');
    if (enterBtn) {
        enterBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (window.CBT_WakeLock) window.CBT_WakeLock.request();
            window.requestUniversalFullscreen(document.documentElement).then(function () {
                sessionStorage.setItem(FS_KEY, '1');
                hideOverlay();
            });
        });
    }

    // Sudah fullscreen (PWA / sudah masuk sebelumnya di tab yang sama)
    if (window.isFullscreenActive()) {
        attachLock();
        return;
    }

    // Sudah pernah consent di sesi ini
    if (sessionStorage.getItem(FS_KEY)) {
        if (window.isIOSDevice() && !window.isNativeFullscreenSupported()) {
            document.body.classList.add('ios-simulated-fullscreen');
            document.documentElement.classList.add('ios-simulated-fullscreen');
            attachLock();
            return;
        }
        showOverlay(true);
    } else {
        // Pertama kali → overlay penuh
        showOverlay(false);
    }

    attachLock();
})();
</script>
