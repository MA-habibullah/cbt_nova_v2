<?php
if (!defined('BASE_URL')) {
    $proto = !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? rtrim($_SERVER['HTTP_X_FORWARDED_PROTO'], '/') . '://'
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://');
    $basepath = defined('APP_BASEPATH') ? APP_BASEPATH : '/';
    define('BASE_URL', $proto . $_SERVER['HTTP_HOST'] . $basepath);
}

try {
    // Ambil data logo dan nama sekolah dari database
    $querySetting = $pdo->query("SELECT nama_sekolah, logo FROM cbt_settings LIMIT 1");
    $dataSetting = $querySetting->fetch();

    // 1. Tentukan Nama Sekolah
    $namaSekolah = ($dataSetting && !empty($dataSetting['nama_sekolah'])) ? $dataSetting['nama_sekolah'] : "CBT NATIVE";
    
    // 2. Tentukan Path Logo/Favicon
    $fileFavicon = BASE_URL . "assets/img/logo.png"; // Default awal

    if ($dataSetting && !empty($dataSetting['logo'])) {
        // Samakan folder dengan sidebar.php: assets/img/logo/
        $nama_file = $dataSetting['logo'];
        
        // Cek secara fisik apakah filenya ada di folder tersebut
        // Kita gunakan dirname(__DIR__) untuk naik dari folder 'includes' ke root project
        $path_fisik = dirname(__DIR__) . "/assets/img/logo/" . $nama_file;

        if (file_exists($path_fisik)) {
            $fileFavicon = BASE_URL . "assets/img/logo/" . $nama_file;
        }
    }
} catch (PDOException $e) {
    $namaSekolah = "CBT NATIVE";
    $fileFavicon = BASE_URL . "assets/img/logo.png";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($namaSekolah) ?></title>
    <style>
        /* SETTING KERTAS A4 STANDAR & PRESISI */
        @page { 
            size: A4; 
            margin: 10mm 15mm 10mm 15mm; 
        }
        body { 
            font-family: 'Arial', sans-serif; 
            font-size: 11px; 
            margin: 0; 
            padding: 0; 
            color: #000;
        }
        .text-center { text-align: center; }
        
        /* HEADER STYLE */
        .header-title { font-size: 14px; font-weight: bold; margin-bottom: 20px; line-height: 1.2; }
        
        /* INFO TABLE (SEKOLAH, RUANG, DLL) */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { padding: 2px 0; vertical-align: top; border: none; }
        .underline { border-bottom: 1px solid #000; display: inline-block; width: 100%; }

        /* DATA TABLE */
        .table-data { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed;
        }
        .table-data th, .table-data td { 
            border: 1px solid #000; 
            padding: 0 5px; 
            height: 26px; /* Tinggi baris dikunci agar pas 20 baris per halaman */
            vertical-align: middle;
        }
        .table-data th { background-color: #f2f2f2; font-size: 10px; font-weight: bold; }

        .table-data tbody tr:nth-child(even) {
        background-color: #f9f9f9 !important;
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
        }
    
        /* TANDA TANGAN ZIGZAG */
        .sign-cell { padding: 0 !important; width: 160px; position: relative; }
        .sign-wrapper { display: flex; align-items: center; height: 100%; width: 100%; font-size: 10px; }
        .sign-ganjil { justify-content: flex-start; padding-left: 10px; }
        .sign-genap { justify-content: center; }

        /* FOOTER / PENGAWAS */
        .footer-section { margin-top: 15px; width: 100%; }
        .stats-table { width: 100%; margin-bottom: 20px; }
        .supervisor-table { width: 100%; margin-top: 20px; }
        
        .line-blue { border-top: 2px solid #0000FF; margin-top: 40px; }
        .page-break { page-break-after: always; }

        @page { 
            size: A4; 
            margin: 15mm 20mm 15mm 20mm; 
        }
        body { 
            font-family: 'Arial', sans-serif; 
            font-size: 11px; 
            color: #000; 
            line-height: 1.5;
        }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        
        /* Layout Berita Acara */
        .ba-title { font-size: 14px; margin-bottom: 30px; }
        .ba-opening { margin-bottom: 20px; text-align: justify; }
        
        /* List & Form Styles */
        .ba-list { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .ba-list td { padding: 3px 0; vertical-align: top; }
        .dots-line { 
            border-bottom: 1px solid #000; 
            display: inline-block; 
            flex-grow: 1;
            margin-left: 5px;
            height: 14px;
        }
        .flex-row { display: flex; align-items: flex-end; width: 100%; }

        /* Signature Styles */
        .sig-section { margin-top: 30px; width: 100%; }
        .sig-item { margin-bottom: 25px; }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-table td { padding: 2px 0; }

        .divider-blue { border-top: 2px solid #0000FF; margin-top: 50px; width: 100%; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body onload="window.print()">

<?php
function tanggal_id(string $format, int $timestamp): string {
    static $hari  = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    static $bulan = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
    return str_replace(array_keys($bulan), array_values($bulan),
           str_replace(array_keys($hari),  array_values($hari),  date($format, $timestamp)));
}
?>

<?php foreach ($chunks as $page_num => $students): ?>
<div class="page-break">
    <div class="text-center header-title">
        DAFTAR HADIR PESERTA<br>
        UJIAN <?= strtoupper($exam['nama_mapel']) ?><br>
        TAHUN PELAJARAN <?= $thn_ajaran ?>
    </div>

    <table class="info-table">
        <tr>
            <td>SEKOLAH/MADRASAH</td>
            <td>: </td>
            <td width="300">
                <span class="underline fw-bold"><?= isset($sch['nama_sekolah']) ? strtoupper($sch['nama_sekolah']) : 'NAMA SEKOLAH BELUM DISET' ?></span>
            </td>
            <td width="80"></td>
        </tr>

        <tr>
            <td>RUANG</td>
            <td>: </td>
            <td width="300">
                <span class="underline"><?= $nama_kelas['jenjang'].' - '.$nama_kelas['nama_kelas'] ?></span>
            </td>
            <td width="80" style="padding-left: 15px;">KELOMPOK</td>
            <td width="10">:</td>
            <td>
                <span class="underline"><?= $nama_kelas['jenjang'].' - '.$nama_kelas['nama_kelas'] ?></span>
            </td>
        </tr>

        <tr>
            <td>HARI/TANGGAL</td>
            <td>: </td>
            <td>
                <span class="underline"><?= tanggal_id('l, d F Y', strtotime($exam['mulai_pada'])) ?></span>
            </td>
            <td style="padding-left: 15px;">PUKUL</td>
            <td>: </td>
            <td>
                <span class="underline"><?= date('H:i:s', strtotime($exam['mulai_pada'])) ?></span>
            </td>
        </tr>

        <tr>
            <td>MATA PELAJARAN</td>
            <td>: </td>
            <td width="300">
                <span class="underline"><?= strtoupper($exam['nama_mapel']) ?></span>
            </td>
            <td width="80"></td>
        </tr>
    </table>

    <table class="table-data">
        <thead>
            <tr>
                <th width="35">NO</th>
                <th width="100">NISN</th>
                <th>NAMA PESERTA</th>
                <th width="90">KELOMPOK</th>
                <th width="160">TANDA TANGAN</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $i => $s): 
                $global_no = ($page_num * 20) + ($i + 1);
            ?>
            <tr>
                <td align="center"><?= $global_no ?></td>
                <td align="center"><?= $s['username'] ?></td>
                <td style="font-size: 10px;"><?= strtoupper($s['nama_lengkap']) ?></td>
                <td align="center"><?= $nama_kelas['jenjang'].' - '.$nama_kelas['nama_kelas'] ?></td>
                <td class="sign-cell">
                    <?php if ($global_no % 2 != 0): ?>
                        <div class="sign-wrapper sign-ganjil"><?= $global_no ?></div>
                    <?php else: ?>
                        <div class="sign-wrapper sign-genap"><?= $global_no ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-section">
        <table class="stats-table">
            <tr>
                <td width="230">Jumlah Peserta yang Seharusnya Hadir</td><td>: _______ orang</td>
            </tr>
            <tr>
                <td>Jumlah Peserta yang Tidak Hadir</td><td>: _______ orang</td>
            </tr>
            <tr>
                <td>Jumlah Peserta Hadir</td><td>: _______ orang</td>
            </tr>
        </table>

        <table class="supervisor-table">
            <tr>
                <td width="50%" align="center">Pengawas I</td>
                <td width="50%" align="center">Pengawas II</td>
            </tr>
            <tr><td colspan="2" height="60"></td></tr>
            <tr>
                <td align="center">( ____________________ )<br>NIP.</td>
                <td align="center">( ____________________ )<br>NIP.</td>
            </tr>
        </table>
    </div>

    <div class="line-blue"></div>
</div>
<?php endforeach; ?>

<div class="container-ba">
    <div class="text-center ba-title">
        <h3 style="margin: 0;">BERITA ACARA PELAKSANAAN</h3>
        <h3 style="margin: 0;">UJIAN <?= strtoupper($exam['nama_mapel']) ?></h3>
        <h3 style="margin: 0;">TAHUN PELAJARAN <?= $thn_ajaran ?></h3>
    </div>

    <div class="ba-opening">
        Pada hari ini <?= tanggal_id('l', strtotime($exam['mulai_pada'])) ?>, <?= tanggal_id('d F Y', strtotime($exam['mulai_pada'])) ?>, di <span class="fw-bold"><?= isset($sch['nama_sekolah']) ? strtoupper($sch['nama_sekolah']) : 'NAMA SEKOLAH BELUM DISET' ?> telah diselenggarakan UJIAN <?= strtoupper($exam['nama_mapel']) ?>, untuk Mata Pelajaran <?= strtoupper($exam['nama_mapel']) ?> dari pukul <?= date('H:i', strtotime($exam['mulai_pada'])) ?> sampai dengan pukul <?= date('H:i', strtotime($exam['selesai_pada'])) ?>
    </div>

    <table class="ba-list">
        <tr>
            <td width="25">1.</td>
            <td width="160">Sekolah / Madrasah</td>
            <td>
                <div class="flex-row">: <span class="underline fw-bold">&nbsp;<?= isset($sch['nama_sekolah']) ? strtoupper($sch['nama_sekolah']) : 'NAMA SEKOLAH BELUM DISET' ?></span></div>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>Ruang</td>
            <td>
                <div class="flex-row">: <span class="underline fw-bold">&nbsp;<?= $nama_kelas['jenjang'].' - '.$nama_kelas['nama_kelas'] ?></span></div>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>Jumlah Peserta seharusnya</td>
            <td>
                <div class="flex-row">: <span class="underline fw-bold">&nbsp;<?= count($list_students) ?></span></div>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>Jumlah yang Hadir</td>
            <td><div class="flex-row">: <span class="dots-line"></span></div></td>
        </tr>
        <tr>
            <td></td>
            <td>Jumlah yang Tidak Hadir</td>
            <td><div class="flex-row">: <span class="dots-line"></span></div></td>
        </tr>
        <tr>
            <td></td>
            <td>Yakni Nomor</td>
            <td><div class="flex-row">: <span class="dots-line"></span></div></td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td><div class="flex-row"><span class="dots-line" style="margin-left: 10px;"></span></div></td>
        </tr>
    </table>

    <div style="margin-top: 20px;">
        <table class="ba-list">
            <tr>
                <td width="25">2.</td>
                <td colspan="2">Catatan selama Ujian :</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="2">
                    <div class="dots-line" style="display:block; margin-top: 15px;"></div>
                    <div class="dots-line" style="display:block; margin-top: 15px;"></div>
                    <div class="dots-line" style="display:block; margin-top: 15px;"></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="sig-section">
        <p style="margin-bottom: 15px;">Yang membuat berita acara :</p>
        
        <div class="sig-item">
            <table class="sig-table">
                <tr>
                    <td width="25">1.</td>
                    <td width="120">Pengawas I</td>
                    <td><div class="flex-row">: <span class="dots-line"></span></div></td>
                </tr>
                <tr>
                    <td></td>
                    <td>NIP</td>
                    <td><div class="flex-row">: <span class="dots-line"></span></div></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Tanda Tangan</td>
                    <td><div class="flex-row">: <span class="dots-line"></span></div></td>
                </tr>
            </table>
        </div>

        <div class="sig-item">
            <table class="sig-table">
                <tr>
                    <td width="25">2.</td>
                    <td width="120">Pengawas II</td>
                    <td><div class="flex-row">: <span class="dots-line"></span></div></td>
                </tr>
                <tr>
                    <td></td>
                    <td>NIP</td>
                    <td><div class="flex-row">: <span class="dots-line"></span></div></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Tanda Tangan</td>
                    <td><div class="flex-row">: <span class="dots-line"></span></div></td>
                </tr>
            </table>
        </div>
    </div>

    <div style="margin-top: 40px; font-size: 10px;">
        Catatan:<br>
        - Dibuat rangkap 2 (dua), masing-masing untuk Sekolah.<br>
        - Mohon berita acara diisi dengan sebenar-benarnya.
    </div>

    <div class="divider-blue"></div>
</div>

</body>
</html>