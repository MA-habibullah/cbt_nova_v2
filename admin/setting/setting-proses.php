<?php
session_start();
require_once '../../config/database.php';

// 1. Proteksi Keamanan
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    try {
        $nama_sekolah   = $_POST['nama_sekolah'];
        $kepala_sekolah = $_POST['kepala_sekolah'];
        $nip_kepala     = $_POST['nip_kepala'];
        $alamat_sekolah = $_POST['alamat_sekolah'];
        $kota           = $_POST['kota'];
        $email_sekolah  = $_POST['email_sekolah'];
        $judul_kartu    = $_POST['judul_kartu'];
        $tgl_cetak      = !empty($_POST['tgl_cetak']) ? $_POST['tgl_cetak'] : null;

        $check = $pdo->query("SELECT id FROM cbt_settings LIMIT 1")->fetch();

        if ($check) {
            $query = "UPDATE cbt_settings SET
                        nama_sekolah = ?, kepala_sekolah = ?, nip_kepala = ?,
                        alamat_sekolah = ?, kota = ?, email_sekolah = ?,
                        judul_kartu = ?, tgl_cetak = ?, updated_at = NOW()
                      WHERE id = ?";
            $pdo->prepare($query)->execute([$nama_sekolah, $kepala_sekolah, $nip_kepala, $alamat_sekolah, $kota, $email_sekolah, $judul_kartu, $tgl_cetak, $check['id']]);
            $setting_id = $check['id'];
        } else {
            $query = "INSERT INTO cbt_settings (nama_sekolah, kepala_sekolah, nip_kepala, alamat_sekolah, kota, email_sekolah, judul_kartu, tgl_cetak)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($query)->execute([$nama_sekolah, $kepala_sekolah, $nip_kepala, $alamat_sekolah, $kota, $email_sekolah, $judul_kartu, $tgl_cetak]);
            $setting_id = $pdo->lastInsertId();
        }

        if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png'];
            $file_name = $_FILES['logo']['name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed   = ['jpg', 'jpeg', 'png'];

            if (in_array($file_ext, $allowed) && in_array($mimeType, $allowedMimes)) {
                $new_filename = "logo_" . time() . "_" . uniqid() . "." . $file_ext;
                $target_dir   = "../../assets/img/logo/";

                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                // finfo_file and getimagesize verified image upload
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $new_filename)) {
                    $stmtOld = $pdo->prepare("SELECT logo FROM cbt_settings WHERE id = ?");
                    $stmtOld->execute([$setting_id]);
                    $old_logo = $stmtOld->fetchColumn();
                    if ($old_logo && file_exists($target_dir . $old_logo)) {
                        @unlink($target_dir . $old_logo);
                    }
                    $pdo->prepare("UPDATE cbt_settings SET logo = ? WHERE id = ?")->execute([$new_filename, $setting_id]);
                }
            }
        }

        log_activity("Update settings sekolah: $nama_sekolah", null, null, null, 'sistem');
        header("Location: index.php?msg=success");
    } catch (\PDOException $e) {
        header("Location: index.php?msg=error");
    }
    exit;
} else {
    header("Location: index.php");
    exit;
}