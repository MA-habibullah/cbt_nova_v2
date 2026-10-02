<?php
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Ambil data untuk filter
$classes = $pdo->query("SELECT * FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang, nama_kelas")->fetchAll();

// Ambil Setting Sekolah (Logo, Nama Sekolah, Judul Kartu)
$sch = $pdo->query("SELECT * FROM cbt_settings LIMIT 1")->fetch();

// Ambil Tahun Ajaran Aktif
$ta_aktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();

// Logic Filter
$class_id = $_GET['class_id'] ?? '';
$jenjang = $_GET['jenjang'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT s.*, c.nama_kelas, c.jenjang FROM cbt_students s 
          JOIN cbt_classes c ON s.class_id = c.id WHERE 1=1";
$params = [];

if ($class_id != '') { $query .= " AND s.class_id = ?"; $params[] = $class_id; }
if ($jenjang != '') { $query .= " AND c.jenjang = ?"; $params[] = $jenjang; }
if ($search != '') { 
    $query .= " AND (s.nama_lengkap LIKE ? OR s.username LIKE ? OR s.nisn LIKE ?)"; 
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; 
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Penanganan Logo Default jika di database kosong
$logo_path = (!empty($sch['logo'])) ? BASE_URL . "assets/img/logo/" . $sch['logo'] : BASE_URL . "assets/img/logo/logo.png";
$judul_kartu = (!empty($sch['judul_kartu'])) ? $sch['judul_kartu'] : "KARTU PESERTA UJIAN";
$thn_ajaran = ($ta_aktif) ? $ta_aktif['tahun'] : "2025/2026";
?>

<!DOCTYPE html>
<html lang="id">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

    <style>
        .card-preview {
            border: 2px solid #333;
            border-radius: 8px;
            background: #fff;
            padding: 15px;
            margin-bottom: 25px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .card-header-inner {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .card-logo { width: 50px; height: auto; max-height: 60px; margin-right: 15px; object-fit: contain; }
        .card-title-text { flex-grow: 1; text-align: center; }
        .card-title-text h6 { margin: 0; font-size: 10px; font-weight: bold; line-height: 1.2; }
        .card-title-text .main-title { font-size: 12px; margin-bottom: 2px; }
        
        .info-row { display: flex; font-size: 13px; margin-bottom: 4px; }
        .info-label { width: 100px; font-weight: 500; }
        
        .box-room {
            border: 1px solid #000;
            display: inline-block;
            padding: 5px 15px;
            margin-top: 10px;
            font-size: 14px;
            font-weight: bold;
            background: #f9f9f9;
        }
        .box-room-label { font-size: 9px; border-bottom: 1px solid #000; display: block; margin-bottom: 2px; text-transform: uppercase; }
        .student-photo {
            width: 80px;
            height: 100px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #ccc;
            background: #fafafa;
        }
    </style>
<body>

<div class="d-flex" id="wrapper">
    <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
    
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex justify-content-between w-100 align-items-center">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-id-card me-2"></i> Preview Kartu Peserta</h5>
                <a href="print-kartu.php?<?= esc(http_build_query($_GET)) ?>" target="_blank" class="btn btn-success shadow-sm">
                    <i class="fas fa-print me-2"></i> Cetak Halaman Ini
                </a>
            </div>
        </nav>

        <div class="container-fluid px-4 py-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label class="small fw-bold">PENCARIAN</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama / NISN / Username" value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="small fw-bold">JENJANG</label>
                            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <?php 
                                $j_list = $pdo->query("SELECT DISTINCT jenjang FROM cbt_classes WHERE is_aktif = 1 ORDER BY jenjang")->fetchAll(PDO::FETCH_COLUMN);
                                foreach($j_list as $jl): ?>
                                    <option value="<?= esc($jl) ?>" <?= esc($jenjang == $jl ? 'selected' : '') ?>><?= esc($jl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <label class="small fw-bold">KELAS</label>
                            <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Kelas</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= esc($c['id']) ?>" <?= esc($class_id == $c['id'] ? 'selected' : '') ?>>
                                        <?= $c['jenjang'] ?> - <?= $c['nama_kelas'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold shadow-sm"><i class="fas fa-filter me-1"></i> Filter</button>
                            <a href="cetak-kartu.php" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt me-1"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $s): ?>
                    <div class="col-xl-6 col-lg-6">
                        <div class="card-preview shadow-sm">
                            <div class="card-header-inner">
                                <img src="<?= esc($logo_path) ?>" class="card-logo">
                                <div class="card-title-text">
                                    <h6 class="main-title"><?= esc(strtoupper($judul_kartu)) ?></h6>
                                    <h6><?= strtoupper($sch['nama_sekolah'] ?? 'NAMA SEKOLAH BELUM DIATUR') ?></h6>
                                    <h6>TAHUN PELAJARAN <?= $thn_ajaran ?></h6>
                                </div>
                            </div>
                            
                            <div class="row g-0">
                                <div class="col-9">
                                    <div class="info-body">
                                        <div class="info-row">
                                            <div class="info-label">Username</div>
                                            <div class="info-val">: <strong><?= $s['username'] ?></strong></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">Password</div>
                                            <div class="info-val">: <strong><?= $s['kartu'] ?? $s['password'] ?></strong></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">Nama Lengkap</div>
                                            <div class="info-val">: <?= strtoupper($s['nama_lengkap']) ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">Agama</div>
                                            <div class="info-val">: <?= strtoupper($s['agama']) ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">Kelas</div>
                                            <div class="info-val">: <?= $s['jenjang'].' - '.$s['nama_kelas'] ?></div>
                                        </div>

                                        <div class="box-room">
                                            <span class="box-room-label text-center">Ruang / Sesi</span>
                                            <?= $s['jenjang'].' - '.$s['nama_kelas'] ?> / <?= $s['sesi'] ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-3 d-flex flex-column align-items-center justify-content-center">
                                    <div class="student-photo">
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
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <p class="text-muted">Data siswa tidak ditemukan untuk filter ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
</script>
</body>
</html>