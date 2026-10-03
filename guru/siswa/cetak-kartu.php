<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Ambil data untuk filter kelas aktif
$classes = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas")->fetchAll();

// Ambil Setting Sekolah (Logo, Nama Sekolah, Judul Kartu)
$sch = $pdo->query("SELECT * FROM cbt_settings LIMIT 1")->fetch();

// Ambil Tahun Ajaran Aktif
$ta_aktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();

// Logic Filter
$class_id = isset($_GET['class_id']) ? trim($_GET['class_id']) : '';
$jenjang  = isset($_GET['jenjang']) ? trim($_GET['jenjang']) : '';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

$has_filter = ($class_id !== '' || $jenjang !== '' || $search !== '');
$students   = [];

if ($has_filter) {
    $query = "SELECT s.*, c.nama_kelas, c.jenjang FROM cbt_students s 
              JOIN cbt_classes c ON s.class_id = c.id WHERE 1=1";
    $params = [];

    if ($class_id !== '') { 
        $query .= " AND s.class_id = ?"; 
        $params[] = $class_id; 
    }
    if ($jenjang !== '') { 
        $query .= " AND c.jenjang = ?"; 
        $params[] = $jenjang; 
    }
    if ($search !== '') { 
        $query .= " AND (s.nama_lengkap LIKE ? OR s.username LIKE ? OR s.nisn LIKE ?)"; 
        $params[] = "%$search%"; 
        $params[] = "%$search%"; 
        $params[] = "%$search%"; 
    }

    $query .= " ORDER BY c.jenjang, c.nama_kelas, s.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

// Penanganan Logo Default jika di database kosong
$logo_path   = (!empty($sch['logo'])) ? BASE_URL . "assets/img/logo/" . $sch['logo'] : BASE_URL . "assets/img/logo/logo.png";
$judul_kartu = (!empty($sch['judul_kartu'])) ? $sch['judul_kartu'] : "KARTU PESERTA UJIAN";
$thn_ajaran  = ($ta_aktif) ? $ta_aktif['tahun'] : "2025/2026";
?>
<!DOCTYPE html>
<html lang="id">
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

<style>
    .card-preview-modern {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background: #ffffff;
        padding: 16px;
        position: relative;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-preview-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -3px rgba(15, 23, 42, 0.08);
    }
    .card-header-inner {
        display: flex;
        align-items: center;
        border-bottom: 2px solid #0f172a;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .card-logo { width: 48px; height: 48px; margin-right: 12px; object-fit: contain; }
    .card-title-text { flex-grow: 1; text-align: center; }
    .card-title-text h6 { margin: 0; font-size: 11px; font-weight: 700; line-height: 1.3; color: #1e293b; }
    .card-title-text .main-title { font-size: 13px; margin-bottom: 2px; color: #0f172a; letter-spacing: 0.5px; }
    
    .info-row { display: flex; font-size: 12.5px; margin-bottom: 5px; }
    .info-label { width: 95px; font-weight: 600; color: #475569; }
    .info-val { color: #0f172a; font-weight: 500; }
    
    .box-room {
        border: 1px dashed #94a3b8;
        border-radius: 8px;
        padding: 6px 12px;
        margin-top: 10px;
        font-size: 12px;
        font-weight: 700;
        background: #f8fafc;
        display: inline-block;
        color: #0f172a;
    }
    .box-room-label { font-size: 9px; color: #64748b; display: block; margin-bottom: 2px; text-transform: uppercase; font-weight: 600; }
    .student-photo {
        width: 78px;
        height: 98px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        background: #f8fafc;
        overflow: hidden;
    }
</style>
<body class="bg-light">

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/guru/includes/sidebar.php'; ?>
    
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex justify-content-between w-100 align-items-center">
                <div class="d-flex align-items-center">
                    <button class="btn btn-light border rounded-3 me-2 shadow-sm" id="menu-toggle" title="Buka/Tutup Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="<?= esc(BASE_URL) ?>guru/jadwal/index.php" class="btn btn-light border rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:40px; height:40px;" title="Kembali ke Jadwal Ujian">
                        <i class="fas fa-arrow-left text-secondary"></i>
                    </a>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-id-card text-primary me-2"></i> Pratinjau Kartu Peserta Ujian (Guru)</h5>
                        <small class="text-muted">Cetak kartu login siswa dalam format lembar A4</small>
                    </div>
                </div>
                <?php if ($has_filter && count($students) > 0): ?>
                    <a href="print-kartu.php?<?= esc(http_build_query($_GET)) ?>" target="_blank" class="btn btn-success rounded-3 px-3 py-2 fw-semibold shadow-sm d-flex align-items-center gap-2">
                        <i class="fas fa-print"></i> Cetak Kartu A4 (Print Preview)
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary rounded-3 px-3 py-2 fw-semibold shadow-sm d-flex align-items-center gap-2 opacity-75" onclick="Swal.fire({icon: 'info', title: 'Pilih Filter Terlebih Dahulu', text: 'Silakan pilih Jenjang atau Kelas Target pada formulir filter di bawah untuk mencetak kartu ujian.', confirmButtonColor: '#3b82f6'});">
                        <i class="fas fa-print"></i> Cetak Kartu A4 (Print Preview)
                    </button>
                <?php endif; ?>
            </div>
        </nav>

        <div class="container-fluid px-4 py-4">
            <!-- Filter Toolbar Card -->
            <div class="card card-dashboard p-3 mb-4 shadow-sm border-0 rounded-4 bg-white">
                <div class="card-body p-2">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">Pencarian Siswa</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-secondary-subtle text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control rounded-end-3 py-2 border-secondary-subtle" placeholder="Nama / NISN / Username" value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">Jenjang</label>
                            <select name="jenjang" class="form-select rounded-3 py-2 border-secondary-subtle" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <?php 
                                $j_list = $pdo->query("SELECT DISTINCT jenjang FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang")->fetchAll(PDO::FETCH_COLUMN);
                                foreach($j_list as $jl): ?>
                                    <option value="<?= esc($jl) ?>" <?= esc($jenjang == $jl ? 'selected' : '') ?>>Kelas <?= esc($jl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <label class="small fw-semibold text-secondary text-uppercase mb-1">Kelas Target <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select rounded-3 py-2 border-secondary-subtle" onchange="this.form.submit()">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>" <?= esc($class_id == $c['id'] ? 'selected' : '') ?>>
                                        Kelas <?= esc($c['jenjang']) ?> - <?= esc($c['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-3 py-2 w-100 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-1">
                                <i class="fas fa-filter"></i> Terapkan
                            </button>
                            <a href="cetak-kartu.php" class="btn btn-outline-secondary rounded-3 py-2 px-3 fw-semibold d-flex align-items-center justify-content-center gap-1">
                                <i class="fas fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$has_filter): ?>
                <!-- State Belum Memilih Filter (Wajib Pilih Filter) -->
                <div class="card card-dashboard border-0 shadow-sm rounded-4 p-5 text-center bg-white my-2">
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle d-inline-flex mb-3 mx-auto" style="width: 72px; height: 72px; align-items: center; justify-content: center;">
                        <i class="fas fa-filter fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Pilih Filter Terlebih Dahulu</h5>
                    <p class="text-secondary small mb-4 mx-auto" style="max-width: 540px;">
                        Untuk menjaga performa sistem dan kerapian pratinjau lembar cetak, silakan pilih <strong>Kelas Target</strong>, <strong>Jenjang</strong>, atau lakukan <strong>Pencarian</strong> pada formulir filter di atas sebelum menampilkan kartu peserta ujian siswa.
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill"><i class="fas fa-users-class text-primary me-1"></i> Pilih Kelas Target</span>
                        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill"><i class="fas fa-print text-success me-1"></i> Standar Format Lembar Cetak A4</span>
                    </div>
                </div>
            <?php else: ?>
                <!-- Cards Container Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
                        Total: <?= count($students) ?> Kartu Peserta Ditemukan
                    </span>
                </div>

                <div class="row g-4">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $s): ?>
                        <div class="col-xl-6">
                            <div class="card-preview-modern shadow-sm">
                                <div class="card-header-inner">
                                    <img src="<?= esc($logo_path) ?>" class="card-logo" alt="Logo">
                                    <div class="card-title-text">
                                        <h6 class="main-title"><?= esc(strtoupper($judul_kartu)) ?></h6>
                                        <h6><?= strtoupper($sch['nama_sekolah'] ?? 'NAMA SEKOLAH BELUM DIATUR') ?></h6>
                                        <h6>TAHUN PELAJARAN <?= $thn_ajaran ?></h6>
                                    </div>
                                </div>
                                
                                <div class="row g-0 align-items-center">
                                    <div class="col-8">
                                        <div class="info-body">
                                            <div class="info-row">
                                                <div class="info-label">Username</div>
                                                <div class="info-val">: <strong class="font-monospace text-primary"><?= $s['username'] ?></strong></div>
                                            </div>
                                            <div class="info-row">
                                                <div class="info-label">Password</div>
                                                <div class="info-val">: <strong class="font-monospace text-dark"><?= $s['kartu'] ?? $s['password'] ?></strong></div>
                                            </div>
                                            <div class="info-row">
                                                <div class="info-label">Nama Siswa</div>
                                                <div class="info-val">: <?= strtoupper($s['nama_lengkap']) ?></div>
                                            </div>
                                            <div class="info-row">
                                                <div class="info-label">Agama</div>
                                                <div class="info-val">: <?= strtoupper($s['agama'] ?? '-') ?></div>
                                            </div>
                                            <div class="info-row">
                                                <div class="info-label">Kelas</div>
                                                <div class="info-val">: <?= $s['jenjang'].' - '.$s['nama_kelas'] ?></div>
                                            </div>

                                            <div class="box-room">
                                                <span class="box-room-label">Ruang &bull; Sesi</span>
                                                <?= $s['jenjang'].' - '.$s['nama_kelas'] ?> &bull; Sesi <?= $s['sesi'] ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4 d-flex flex-column align-items-center justify-content-center">
                                        <div class="student-photo shadow-sm">
                                            <?php if(!empty($s['foto']) && file_exists("../../assets/uploads/foto_siswa/".$s['foto'])): ?>
                                                <img src="<?= esc(BASE_URL) ?>assets/uploads/foto_siswa/<?= htmlspecialchars($s['foto']) ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
                                            <?php else: ?>
                                                <span>FOTO 3x4</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm border p-5">
                            <div class="p-3 bg-primary-subtle text-primary rounded-circle d-inline-flex mb-3">
                                <i class="fas fa-id-card-clip fa-3x"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Data Siswa Tidak Ditemukan</h5>
                            <p class="text-muted small mb-0">Tidak ada kartu peserta siswa yang sesuai dengan parameter filter yang Anda pilih.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>
