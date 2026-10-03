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
$class_id = $_GET['class_id'] ?? '';
$jenjang  = $_GET['jenjang'] ?? '';
$search   = $_GET['search'] ?? '';

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

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

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

    <div class="print-toolbar no-print">
        <span><strong><?= count($students) ?></strong> Kartu Siap Cetak</span>
        <button class="btn-print-action" onclick="window.print()">
            &#128438; Cetak / Print A4
        </button>
        <a href="cetak-kartu.php" class="btn-print-back">
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

</body>
</html>