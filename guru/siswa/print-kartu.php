<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

// Proteksi Guru
if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    exit("Akses Ditolak: Khusus Guru");
}

// 1. Ambil Setting Sekolah & Tahun Ajaran Aktif
$sch = $pdo->query("SELECT * FROM cbt_settings LIMIT 1")->fetch();
$ta_aktif = $pdo->query("SELECT * FROM cbt_tahun_ajaran WHERE is_aktif = 1 LIMIT 1")->fetch();

// 2. Filter Data (Meneruskan dari formulir pratinjau)
$class_id = isset($_GET['class_id']) ? trim($_GET['class_id']) : '';
$jenjang  = isset($_GET['jenjang']) ? trim($_GET['jenjang']) : '';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

$has_filter = ($class_id !== '' || $jenjang !== '' || $search !== '');
$students   = [];

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

$logo_path    = (!empty($sch['logo'])) ? BASE_URL . "assets/img/logo/" . $sch['logo'] : BASE_URL . "assets/img/logo/logo.png";
$nama_sekolah = $sch['nama_sekolah'] ?? "SMA NEGERI 11 SURABAYA";
$judul_kartu  = (!empty($sch['judul_kartu'])) ? $sch['judul_kartu'] : "KARTU PESERTA UJIAN";
$thn_ajaran   = ($ta_aktif) ? $ta_aktif['tahun'] : "2025/2026";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Kartu Peserta - Guru</title>
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
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.4);
            cursor: pointer;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: system-ui, sans-serif;
            border: none;
        }
        .print-toolbar:hover { background: #1d4ed8; }
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: system-ui, sans-serif;
        }
        .back-btn:hover { background: #e2e8f0; color: #0f172a; }
        
        .empty-filter-guard {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 600px;
            margin: 80px auto;
            text-align: center;
            padding: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <a href="cetak-kartu.php?<?= esc(http_build_query($_GET)) ?>" class="back-btn no-print">&larr; Kembali ke Filter</a>
    <button class="print-toolbar no-print" onclick="window.print();">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
            <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
        </svg>
        Cetak Sekarang (Print)
    </button>

    <?php if (!$has_filter): ?>
        <div class="empty-filter-guard no-print">
            <h3 style="color:#0f172a; margin-top:0;">Pilih Filter Terlebih Dahulu</h3>
            <p style="color:#64748b; font-size:14px; line-height:1.6;">
                Anda belum memilih Jenjang atau Kelas Target. Silakan kembali ke halaman pratinjau dan pilih kelas terlebih dahulu sebelum mencetak kartu ujian.
            </p>
            <a href="cetak-kartu.php" style="display:inline-block; margin-top:10px; background:#2563eb; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600;">
                Buka Halaman Filter Kartu
            </a>
        </div>
    <?php elseif (empty($students)): ?>
        <div class="empty-filter-guard no-print">
            <h3 style="color:#0f172a; margin-top:0;">Tidak Ada Siswa Ditemukan</h3>
            <p style="color:#64748b; font-size:14px; line-height:1.6;">
                Tidak ada data siswa yang cocok dengan filter yang dipilih.
            </p>
            <a href="cetak-kartu.php" style="display:inline-block; margin-top:10px; background:#2563eb; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600;">
                Kembali &amp; Ubah Filter
            </a>
        </div>
    <?php else: ?>
        <div class="wrapper">
            <?php foreach ($students as $s): ?>
            <div class="card-box">
                <!-- Header Kartu -->
                <div class="header">
                    <img src="<?= esc($logo_path) ?>" class="logo" alt="Logo">
                    <div class="header-text">
                        <h1><?= strtoupper($judul_kartu) ?></h1>
                        <h2><?= strtoupper($nama_sekolah) ?></h2>
                        <p>TAHUN PELAJARAN <?= $thn_ajaran ?></p>
                    </div>
                </div>

                <!-- Konten Body Kartu -->
                <div class="body-row">
                    <table class="info-table">
                        <tr>
                            <td>Username</td>
                            <td>:</td>
                            <td><strong style="font-family:monospace; font-size:10pt;"><?= $s['username'] ?></strong></td>
                        </tr>
                        <tr>
                            <td>Password</td>
                            <td>:</td>
                            <td><strong style="font-family:monospace; font-size:10pt;"><?= $s['kartu'] ?? $s['password'] ?></strong></td>
                        </tr>
                        <tr>
                            <td>Nama Peserta</td>
                            <td>:</td>
                            <td><strong><?= strtoupper($s['nama_lengkap']) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Agama</td>
                            <td>:</td>
                            <td><?= strtoupper($s['agama'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td>Kelas</td>
                            <td>:</td>
                            <td><?= $s['jenjang'] . ' - ' . $s['nama_kelas'] ?></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <div class="room-box">
                                    <span class="room-label">Ruang &bull; Sesi</span>
                                    <span class="room-val"><?= $s['jenjang'] . ' - ' . $s['nama_kelas'] ?> &bull; Sesi <?= $s['sesi'] ?></span>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <!-- Kotak Foto Siswa -->
                    <div class="photo-box">
                        <?php if (!empty($s['foto']) && file_exists("../../assets/uploads/foto_siswa/" . $s['foto'])): ?>
                            <img src="<?= esc(BASE_URL) ?>assets/uploads/foto_siswa/<?= htmlspecialchars($s['foto']) ?>" alt="Foto">
                        <?php else: ?>
                            <span>FOTO 3x4</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</body>
</html>
