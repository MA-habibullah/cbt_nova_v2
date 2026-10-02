<?php
require_once 'config/database.php';
// Jika sudah login, jangan kasih akses ke halaman login lagi, lempar ke dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: " . BASE_URL . "admin/index.php");
    } elseif ($_SESSION['role'] == 'siswa') {
        header("Location: " . BASE_URL . "siswa/index.php");
    }
    exit;
}

// Pre-generate captcha for this page load
if (empty($_SESSION['captcha_code'])) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $cap = '';
    for ($i = 0; $i < 5; $i++) { $cap .= $chars[random_int(0, strlen($chars) - 1)]; }
    $_SESSION['captcha_code'] = $cap;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem CBT Sekolah</title>

    <!-- PWA: Android home screen fullscreen -->
    <link rel="manifest" href="manifest.json">
    <!-- PWA: iOS home screen fullscreen -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
        }
        .login-sidebar {
            background: #4e73df;
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            padding: 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 40%;
        }
        .login-form-section {
            padding: 40px;
            width: 60%;
        }
        .nav-pills .nav-link {
            border-radius: 10px;
            color: #4e73df;
            font-weight: 600;
            margin-bottom: 10px;
            border: 1px solid #e3e6f0;
        }
        .nav-pills .nav-link.active {
            background-color: #4e73df;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
        }
        .btn-login {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            background: #4e73df;
            border: none;
            transition: 0.3s;
        }
        .btn-login:hover {
            background: #224abe;
            transform: translateY(-2px);
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .login-sidebar {
                display: none; /* Sembunyikan sidebar di HP/Tablet kecil */
            }
            .login-form-section {
                width: 100%;
                padding: 30px 20px;
            }
        }

    </style>
</head>
<body>

<div class="login-container shadow-lg">
    
    <div class="login-sidebar text-center">
        <i class="fas fa-laptop-code fa-5x mb-4"></i>
        <h3>CBT System</h3>
        <p>Silahkan pilih akses login sesuai dengan peran Anda di sekolah.</p>
        <small class="mt-5 opacity-75">&copy; 2026 SMAN 11 CBT Online</small>
    </div>

    <div class="login-form-section">
        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark">Selamat Datang</h4>
            <p class="text-muted">Masukkan username dan password Anda</p>
        </div>

        <?php if(isset($_GET['pesan'])): ?>
            
            <?php if($_GET['pesan'] == 'logout'): ?>
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>Anda telah berhasil <strong>keluar</strong> dari sistem.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] == 'device_locked'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
                    <div class="d-flex">
                        <i class="fas fa-lock me-2 mt-1"></i>
                        <div>
                            <strong>Akses Terkunci!</strong><br>
                            Akun Anda sedang aktif di perangkat lain. Silakan hubungi pengawas untuk reset perangkat.
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] == 'gagal'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div><strong>Login Gagal!</strong> Username atau password salah.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] == 'captcha_salah'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt me-2"></i>
                        <div><strong>Kode Verifikasi Salah!</strong> Masukkan kode yang tertera pada gambar.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($_GET['pesan'] == 'sesi_berakhir'): ?>
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock me-2"></i>
                        <div><strong>Sesi Berakhir!</strong> Anda otomatis keluar karena tidak aktif selama 2 jam.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php endif; ?>

        <?php endif; ?>
        <ul class="nav nav-pills nav-justified mb-4" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-siswa-tab" data-bs-toggle="pill" data-bs-target="#pills-siswa" type="button" role="tab">Siswa</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-guru-tab" data-bs-toggle="pill" data-bs-target="#pills-guru" type="button" role="tab">Guru</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-admin-tab" data-bs-toggle="pill" data-bs-target="#pills-admin" type="button" role="tab">Admin</button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            <div class="tab-pane fade show active" id="pills-siswa" role="tabpanel">
                <form action="auth/proses-login.php?role=siswa" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NISN / Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" name="username" class="form-control border-start-0" placeholder="NISN / Username" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode Verifikasi</label>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="auth/captcha.php" class="captcha-img rounded border" style="height:45px; cursor:pointer;" onclick="refreshCaptcha()" title="Klik untuk refresh">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase border-start-0" style="letter-spacing:4px;" placeholder="Masukkan Captcha" required maxlength="5" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-login">Login Siswa</button>
                </form>
            </div>

            <div class="tab-pane fade" id="pills-guru" role="tabpanel">
                <form action="auth/proses-login.php?role=guru" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NIP / Username Guru</label>
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode Verifikasi</label>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="auth/captcha.php" class="captcha-img rounded border" style="height:45px; cursor:pointer;" onclick="refreshCaptcha()" title="Klik untuk refresh">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase border-start-0" style="letter-spacing:4px;" placeholder="Masukkan Captcha" required maxlength="5" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-login text-white" style="background: #1cc88a;">Login Guru</button>
                </form>
            </div>

            <div class="tab-pane fade" id="pills-admin" role="tabpanel">
                <form action="auth/proses-login.php?role=admin" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Admin Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode Verifikasi</label>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="auth/captcha.php" class="captcha-img rounded border" style="height:45px; cursor:pointer;" onclick="refreshCaptcha()" title="Klik untuk refresh">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase border-start-0" style="letter-spacing:4px;" placeholder="Masukkan Captcha" required maxlength="5" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-dark w-100 btn-login">Login Admin / Proktor</button>
                </form>

            </div>
        </div>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Refresh all captcha images on page
function refreshCaptcha() {
    var t = Date.now();
    document.querySelectorAll('.captcha-img').forEach(function(img) {
        img.src = 'auth/captcha.php?refresh=1&t=' + t;
    });
}

// Activate correct tab from URL ?tab= param (after failed login)
var urlParams = new URLSearchParams(window.location.search);
var activeTab = urlParams.get('tab');
if (activeTab) {
    var tabEl = document.getElementById('pills-' + activeTab + '-tab');
    if (tabEl) new bootstrap.Tab(tabEl).show();
}

// Remove query params from URL cleanly
if (window.history.replaceState) {
    var url = new URL(window.location.href);
    url.searchParams.delete('pesan');
    url.searchParams.delete('tab');
    window.history.replaceState({path: url.href}, '', url.href);
}
</script>
</body>
</html>