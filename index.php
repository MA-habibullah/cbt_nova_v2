<?php
require_once 'config/database.php';

// Jika sudah login, langsung alihkan ke dashboard masing-masing peran
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . BASE_URL . "admin/index.php");
    } elseif ($_SESSION['role'] === 'guru') {
        header("Location: " . BASE_URL . "guru/index.php");
    } elseif ($_SESSION['role'] === 'siswa') {
        header("Location: " . BASE_URL . "siswa/index.php");
    }
    exit;
}

// Ambil identitas sekolah & logo dinamis
$app_settings = get_app_settings();
$namaSekolah  = !empty($app_settings['nama_sekolah']) ? $app_settings['nama_sekolah'] : "AXON CBT";
$fileFavicon  = BASE_URL . "assets/img/logo.png";
$logoImg      = BASE_URL . "assets/img/logo.png";
if (!empty($app_settings['logo'])) {
    $path_fisik = __DIR__ . "/assets/img/logo/" . $app_settings['logo'];
    if (file_exists($path_fisik)) {
        $logoImg = BASE_URL . "assets/img/logo/" . $app_settings['logo'];
        $fileFavicon = $logoImg;
    }
}

// Pre-generate captcha untuk pemuatan halaman awal
if (empty($_SESSION['captcha_code'])) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $cap = '';
    for ($i = 0; $i < 5; $i++) { 
        $cap .= $chars[random_int(0, strlen($chars) - 1)]; 
    }
    $_SESSION['captcha_code'] = $cap;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Terpadu — <?= htmlspecialchars($namaSekolah) ?></title>

    <!-- PWA Settings -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS Framework & Custom Theme -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= esc(BASE_URL) ?>assets/css/nova-modern-ui.css">
    <link rel="icon" type="image/png" href="<?= esc($fileFavicon) ?>">
    
    <style>
        :root {
            --auth-bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
            --auth-brand-blue: #2563eb;
            --auth-brand-hover: #1d4ed8;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--auth-bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            max-width: 960px;
            width: 100%;
            display: flex;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .auth-sidebar {
            background: linear-gradient(160deg, #1e3a8a 0%, #1e293b 100%);
            padding: 44px 36px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 42%;
            position: relative;
            overflow: hidden;
        }

        .auth-sidebar::after {
            content: '';
            position: absolute;
            right: -40px;
            bottom: -40px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .auth-form-section {
            padding: 40px 44px;
            width: 58%;
            background: #ffffff;
        }

        .nav-pills .nav-link {
            border-radius: 12px;
            color: #475569;
            font-weight: 600;
            font-size: 0.88rem;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            background: #f8fafc;
        }

        .nav-pills .nav-link:hover {
            background: #eff6ff;
            color: var(--auth-brand-blue);
            border-color: #bfdbfe;
        }

        .nav-pills .nav-link.active {
            background-color: var(--auth-brand-blue);
            color: #ffffff;
            border-color: var(--auth-brand-blue);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .input-group-text {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            color: #64748b;
        }

        .form-control {
            border-radius: 10px;
            border-color: #cbd5e1;
            padding: 11px 14px;
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--auth-brand-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn-auth-submit {
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.2px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.2);
        }

        .btn-auth-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }

        .captcha-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 12px;
        }

        .trust-badge {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 0.78rem;
        }

        /* Responsivitas Mobile */
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            .auth-sidebar {
                display: none;
            }
            .auth-form-section {
                width: 100%;
                padding: 28px 20px;
            }
            .auth-card {
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>

<div class="auth-card">
    
    <!-- Sidebar / Hero Left Panel -->
    <div class="auth-sidebar">
        <div>
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="<?= esc($logoImg) ?>" alt="Logo Sekolah" style="height: 46px; width: auto; object-fit: contain;" class="rounded bg-white p-1 shadow-sm">
                <div>
                    <h5 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px;"><?= htmlspecialchars($namaSekolah) ?></h5>
                    <small style="color: rgba(255, 255, 255, 0.75); font-size: 0.78rem;">Computer Based Testing Portal</small>
                </div>
            </div>
            
            <h4 class="fw-bold mb-2 text-white" style="line-height: 1.3;">Sistem Evaluasi Belajar Modern &amp; Berintegritas</h4>
            <p class="small mb-4" style="color: rgba(255, 255, 255, 0.8); line-height: 1.6;">
                Silakan pilih peran login yang sesuai untuk mengakses lembar ujian siswa, bank soal guru, atau manajemen ujian proktor.
            </p>

            <div class="trust-badge mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-shield-check text-warning fs-6"></i>
                    <span><strong>Keamanan Terpadu:</strong> Proteksi Single Device Lock &amp; Anti-Cheat Guard aktif.</span>
                </div>
            </div>
        </div>

        <div class="pt-4 border-top border-white border-opacity-10">
            <small style="color: rgba(255, 255, 255, 0.65); font-size: 0.75rem;">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($namaSekolah) ?>. Hak Cipta Dilindungi.
            </small>
        </div>
    </div>

    <!-- Form Section Right Panel -->
    <div class="auth-form-section">
        
        <div class="d-md-none text-center mb-4 pb-2 border-bottom">
            <img src="<?= esc($logoImg) ?>" alt="Logo Sekolah" style="height: 42px; width: auto;" class="mb-2">
            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($namaSekolah) ?></h5>
            <small class="text-muted">AXON CBT Portal</small>
        </div>

        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">Masuk ke Akun Anda</h4>
            <p class="text-muted small mb-0">Silakan tentukan peran dan masukkan kredensial akun</p>
        </div>

        <!-- Alert Notifikasi Status Login -->
        <?php if(isset($_GET['pesan'])): ?>
            <?php if($_GET['pesan'] === 'logout'): ?>
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-2 text-primary fs-5"></i>
                        <div class="small">Anda telah berhasil <strong>keluar</strong> dari sistem.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] === 'device_locked'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-lock me-2 mt-1 text-warning fs-5"></i>
                        <div class="small">
                            <strong>Akses Terkunci!</strong> Akun Anda sedang aktif di perangkat lain. Silakan hubungi proktor/pengawas untuk mereset kunci perangkat.
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] === 'gagal'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2 text-danger fs-5"></i>
                        <div class="small"><strong>Login Gagal!</strong> Username atau password tidak sesuai.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] === 'captcha_salah'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt me-2 text-warning fs-5"></i>
                        <div class="small"><strong>Kode Verifikasi Salah!</strong> Harap masukkan 5 karakter captcha yang benar.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] === 'sesi_berakhir'): ?>
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock me-2 text-info fs-5"></i>
                        <div class="small"><strong>Sesi Berakhir!</strong> Anda otomatis keluar karena tidak aktif selama 2 jam.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] === 'csrf_expired'): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt me-2 text-warning fs-5"></i>
                        <div class="small"><strong>Sesi Halaman Kedaluwarsa!</strong> Token keamanan diperbarui. Silakan coba masuk kembali.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

            <?php elseif($_GET['pesan'] === 'role_tidak_valid'): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2 text-danger fs-5"></i>
                        <div class="small"><strong>Akses Ditolak!</strong> Peran (role) pengguna tidak dikenali.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Role Tabs Selector -->
        <ul class="nav nav-pills nav-justified mb-4 gap-2" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center justify-content-center gap-2" id="pills-siswa-tab" data-bs-toggle="pill" data-bs-target="#pills-siswa" type="button" role="tab">
                    <i class="fas fa-user-graduate"></i> <span>Siswa</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center justify-content-center gap-2" id="pills-guru-tab" data-bs-toggle="pill" data-bs-target="#pills-guru" type="button" role="tab">
                    <i class="fas fa-chalkboard-teacher"></i> <span>Guru</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center justify-content-center gap-2" id="pills-admin-tab" data-bs-toggle="pill" data-bs-target="#pills-admin" type="button" role="tab">
                    <i class="fas fa-user-shield"></i> <span>Admin</span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            
            <!-- TAB SISWA -->
            <div class="tab-pane fade show active" id="pills-siswa" role="tabpanel">
                <form action="auth/proses-login.php?role=siswa" method="POST" id="form-login-siswa">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">NISN / USERNAME SISWA</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan NISN atau username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PASSWORD</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="pass-siswa" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('pass-siswa', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">KODE VERIFIKASI (CAPTCHA)</label>
                        <div class="d-flex align-items-center gap-2 mb-2 captcha-box">
                            <img src="auth/captcha.php" class="captcha-img rounded border bg-white" style="height:44px; width: 140px; object-fit: contain; cursor:pointer;" onclick="refreshCaptcha()" title="Klik untuk refresh kode">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i> <span class="small">Ganti</span>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase font-monospace fw-bold" style="letter-spacing:4px; font-size: 1.05rem;" placeholder="MASUKKAN KODE" required maxlength="5" autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-auth-submit">
                        <i class="fas fa-sign-in-alt me-1"></i> Masuk Ujian Siswa
                    </button>
                </form>
            </div>

            <!-- TAB GURU -->
            <div class="tab-pane fade" id="pills-guru" role="tabpanel">
                <form action="auth/proses-login.php?role=guru" method="POST" id="form-login-guru">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">NIP / USERNAME GURU</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan NIP atau username" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PASSWORD</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="pass-guru" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('pass-guru', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">KODE VERIFIKASI (CAPTCHA)</label>
                        <div class="d-flex align-items-center gap-2 mb-2 captcha-box">
                            <img src="auth/captcha.php" class="captcha-img rounded border bg-white" style="height:44px; width: 140px; object-fit: contain; cursor:pointer;" onclick="refreshCaptcha()" title="Klik untuk refresh kode">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i> <span class="small">Ganti</span>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase font-monospace fw-bold" style="letter-spacing:4px; font-size: 1.05rem;" placeholder="MASUKKAN KODE" required maxlength="5" autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-success w-100 btn-auth-submit" style="background-color: #16a34a; border-color: #16a34a;">
                        <i class="fas fa-sign-in-alt me-1"></i> Masuk Portal Guru
                    </button>
                </form>
            </div>

            <!-- TAB ADMIN -->
            <div class="tab-pane fade" id="pills-admin" role="tabpanel">
                <form action="auth/proses-login.php?role=admin" method="POST" id="form-login-admin">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">USERNAME ADMINISTRATOR</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username admin" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PASSWORD</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="pass-admin" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('pass-admin', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">KODE VERIFIKASI (CAPTCHA)</label>
                        <div class="d-flex align-items-center gap-2 mb-2 captcha-box">
                            <img src="auth/captcha.php" class="captcha-img rounded border bg-white" style="height:44px; width: 140px; object-fit: contain; cursor:pointer;" onclick="refreshCaptcha()" title="Klik untuk refresh kode">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1" onclick="refreshCaptcha()">
                                <i class="fas fa-sync-alt"></i> <span class="small">Ganti</span>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase font-monospace fw-bold" style="letter-spacing:4px; font-size: 1.05rem;" placeholder="MASUKKAN KODE" required maxlength="5" autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-dark w-100 btn-auth-submit" style="background-color: #0f172a; border-color: #0f172a;">
                        <i class="fas fa-shield-alt me-1"></i> Masuk Panel Proktor
                    </button>
                </form>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Refresh captcha
function refreshCaptcha() {
    var t = Date.now();
    document.querySelectorAll('.captcha-img').forEach(function(img) {
        img.src = 'auth/captcha.php?refresh=1&t=' + t;
    });
}

// Toggle password visibility
function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Tab activation from URL param (?tab=...)
var urlParams = new URLSearchParams(window.location.search);
var activeTab = urlParams.get('tab');
if (activeTab) {
    var tabEl = document.getElementById('pills-' + activeTab + '-tab');
    if (tabEl) {
        var tab = new bootstrap.Tab(tabEl);
        tab.show();
    }
}

// Clean URL history
if (window.history.replaceState) {
    var url = new URL(window.location.href);
    url.searchParams.delete('pesan');
    url.searchParams.delete('tab');
    window.history.replaceState({path: url.href}, '', url.href);
}
</script>
</body>
</html>