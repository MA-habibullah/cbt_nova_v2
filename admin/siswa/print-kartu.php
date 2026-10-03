<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    exit("Akses Ditolak");
}

// 1. Ambil Setting Sekolah & Tahun Ajaran Aktif
$sch = $pdo->query("SELECT * FROM cbt_settings LIMIT 1")->fetch();
$ta_aktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();

// 2. Filter Data (Meneruskan dari halaman sebelumnya)
$class_id = isset($_GET['class_id']) ? trim($_GET['class_id']) : '';
$jenjang  = isset($_GET['jenjang']) ? trim($_GET['jenjang']) : '';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

$has_filter = ($class_id !== '' || $jenjang !== '' || $search !== '');
$students = [];

if ($has_filter) {
    $query = "SELECT s.*, c.nama_kelas, c.jenjang 
              FROM cbt_students s 
              JOIN cbt_classes c ON s.class_id = c.id 
              WHERE 1=1";
    $params = [];

    if ($class_id != '') { $query .= " AND s.class_id = ?"; $params[] = $class_id; }
    if ($jenjang != '')  { $query .= " AND c.jenjang = ?"; $params[] = $jenjang; }
    if ($search != '') { 
        $query .= " AND (s.nama_lengkap LIKE ? OR s.username LIKE ? OR s.nisn LIKE ?)"; 
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; 
    }

    $query .= " ORDER BY c.jenjang, c.nama_kelas, s.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

$logo_path = (!empty($sch['logo'])) ? BASE_URL . "assets/img/logo/" . $sch['logo'] : BASE_URL . "assets/img/logo/logo.png";
$nama_sekolah = $sch['nama_sekolah'] ?? "SMA NEGERI 11 SURABAYA";
$judul_kartu = (!empty($sch['judul_kartu'])) ? $sch['judul_kartu'] : "KARTU PESERTA UJIAN";
$thn_ajaran = ($ta_aktif) ? $ta_aktif['tahun'] : "2025/2026";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Kartu Peserta</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 8mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }

        .wrapper {
            width: 194mm;
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4mm;
        }

        .card-box {
            border: 1.5px solid #333;
            border-radius: 8px;
            padding: 10px 12px;
            break-inside: avoid;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .logo {
            width: 14mm;
            height: auto;
            max-height: 16mm;
            margin-right: 10px;
            object-fit: contain;
        }
        .header-text {
            flex-grow: 1;
            text-align: center;
        }
        .header-text h1 { font-size: 10pt; margin: 0; font-weight: bold; }
        .header-text h2 { font-size: 9pt; margin: 2px 0; text-transform: uppercase; font-weight: bold; }
        .header-text p  { font-size: 8pt; margin: 0; font-weight: bold; }

        /* Body */
        .body-row {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 8px;
            flex: 1;
        }

        /* Info tabel */
        .info-table {
            flex: 1;
            font-size: 9pt;
            border-collapse: collapse;
        }
        .info-table td { padding: 2.5px 0; vertical-align: top; }
        .info-table td:first-child { width: 28mm; white-space: nowrap; }
        .info-table td:nth-child(2) { width: 6px; padding-right: 4px; }

        /* Foto */
        .photo-box {
            width: 20mm;
            height: 25mm;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7pt;
            color: #aaa;
            background: #fafafa;
            flex-shrink: 0;
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }

        /* RUANG / SESI */
        .room-box {
            display: inline-block;
            border: 1px solid #000;
            min-width: 40mm;
            text-align: center;
            margin-top: 8px;
        }
        .room-label {
            font-size: 7pt;
            font-weight: bold;
            border-bottom: 1px solid #000;
            display: block;
            padding: 2px 4px;
            background: #f0f0f0;
            text-transform: uppercase;
        }
        .room-val {
            font-size: 11pt;
            font-weight: bold;
            padding: 3px 8px;
            display: block;
        }

        @media print {
            .no-print { display: none !important; }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: #ffffff !important;
            }
            .card-box {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }

        .print-toolbar {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
            padding: 8px 14px;
            border-radius: 10px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
        }
        .btn-print-action {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-print-action:hover { background: #1d4ed8; }
        .btn-print-back {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        .btn-print-back:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <?php if (!$has_filter || count($students) === 0): ?>
    <div style="text-align: center; padding: 60px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="display: inline-block; padding: 16px; background: #e0f2fe; color: #0284c7; border-radius: 50%; margin-bottom: 16px;">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
        </div>
        <h2 style="color: #0f172a; margin-bottom: 8px; font-size: 20px;">Filter Kelas Belum Dipilih</h2>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 24px; max-width: 480px; margin-left: auto; margin-right: auto;">
            Silakan tentukan <strong>Jenjang</strong> atau <strong>Kelas Target</strong> pada halaman Pratinjau Kartu terlebih dahulu sebelum mencetak kartu ujian.
        </p>
        <a href="cetak-kartu.php" style="display: inline-block; padding: 10px 24px; background: #0284c7; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
            &larr; Kembali ke Pratinjau Kartu
        </a>
    </div>
    <?php else: ?>
    <div class="print-toolbar no-print">
        <span><strong><?= count($students) ?></strong> Kartu Siap Cetak</span>
        <button class="btn-print-action" onclick="window.print()">
            &#128438; Cetak / Print A4
        </button>
        <a href="cetak-kartu.php?<?= esc(http_build_query($_GET)) ?>" class="btn-print-back">
            &larr; Kembali
        </a>
    </div>

    <div class="wrapper">
        <?php foreach ($students as $s): ?>
        <div class="card-box">
            <div class="header">
                <img src="<?= esc($logo_path) ?>" class="logo">
                <div class="header-text">
                    <h1><?= strtoupper($judul_kartu) ?></h1>
                    <h2><?= strtoupper($nama_sekolah) ?></h2>
                    <p>TAHUN PELAJARAN <?= $thn_ajaran ?></p>
                </div>
            </div>

            <div class="body-row">
                <div>
                    <table class="info-table">
                        <tr>
                            <td>Username</td>
                            <td>:</td>
                            <td><strong><?= $s['username'] ?></strong></td>
                        </tr>
                        <tr>
                            <td>Password</td>
                            <td>:</td>
                            <td><strong><?= $s['kartu'] ?? $s['password'] ?></strong></td>
                        </tr>
                        <tr>
                            <td>Nama Lengkap</td>
                            <td>:</td>
                            <td style="text-transform: uppercase;"><?= $s['nama_lengkap'] ?></td>
                        </tr>
                        <tr>
                            <td>Agama</td>
                            <td>:</td>
                            <td style="text-transform: uppercase;"><?= $s['agama'] ?></td>
                        </tr>
                        <tr>
                            <td>Kelas</td>
                            <td>:</td>
                            <td><?= $s['jenjang'].' - '.$s['nama_kelas'] ?></td>
                        </tr>
                    </table>
                    <div class="room-box">
                        <span class="room-label">RUANG / SESI</span>
                        <span class="room-val"><?= $s['jenjang'].' - '.$s['nama_kelas'] ?> / <?= $s['sesi'] ?></span>
                    </div>
                </div>

                <div class="photo-box">
                    <?php if(!empty($s['foto']) && file_exists("../../assets/uploads/foto_siswa/".$s['foto'])): ?>
                        <img src="<?= esc(BASE_URL) ?>assets/uploads/foto_siswa/<?= htmlspecialchars($s['foto']) ?>">
                    <?php else: ?>
                        FOTO 3x4
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</body>
</html>