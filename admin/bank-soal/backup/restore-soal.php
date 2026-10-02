<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';

if (isset($_POST['restore'])) {
    csrf_verify();
    $zipFile    = $_FILES['backup_file']['tmp_name'];
    $subject_id = (int)$_POST['subject_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $uploadDir  = dirname(__DIR__, 3) . "/assets/uploads/soal/";

    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $data = json_decode($zip->getFromName('data.json'), true);
        
        $pdo->beginTransaction();
        try {
            // 1. Ambil kode bank soal dari data backup atau buat otomatis jika kosong
            $kode_asli = isset($data['bank']['kode_bank_soal']) ? $data['bank']['kode_bank_soal'] : 'RESTORE';
            $kode_baru = $kode_asli . "-" . time(); // Ditambah timestamp agar selalu UNIK dan tidak error duplicate

            // 2. Buat Bank Soal Baru (Sertakan kode_bank_soal agar tidak error duplicate entry '')
            $stmt_b = $pdo->prepare("INSERT INTO cbt_bank_soal (kode_bank_soal, nama_bank_soal, subject_id, teacher_id) VALUES (?, ?, ?, ?)");
            $stmt_b->execute([
                $kode_baru, 
                $data['bank']['nama_bank_soal'] . " (Restored)", 
                $subject_id, 
                $teacher_id
            ]);
            $new_bank_id = $pdo->lastInsertId();

            // Helper: ekstrak filename gambar dari HTML
            $extractImgNames = function($html) {
                if (empty($html)) return [];
                preg_match_all('/assets\/uploads\/soal\/([^\s"\'<>?#]+)/i', $html, $m);
                return array_unique(array_filter($m[1]));
            };

            // 3. Tulis gambar dari ZIP ke disk, tangani collision dengan rename (dengan proteksi whitelist ekstensi & basename)
            $allowedMediaExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'mp4', 'ogg', 'svg'];
            $renameMap = [];
            foreach ($data['soal'] as $q) {
                $imgNames = [];
                if (!empty($q['media_files'])) $imgNames[] = $q['media_files'];
                foreach ($extractImgNames($q['konten_soal'] ?? '') as $f) $imgNames[] = $f;
                foreach ($q['options'] ?? [] as $o) {
                    foreach ($extractImgNames($o['value_target'] ?? '') as $f) $imgNames[] = $f;
                }
                foreach (array_unique(array_filter($imgNames)) as $rawOrigName) {
                    $origName = basename($rawOrigName); // Cegah path traversal
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedMediaExts)) continue; // Cegah upload file berbahaya (.php, .exe, dsb)

                    if (isset($renameMap[$rawOrigName])) continue;
                    $imgContent = $zip->getFromName('images/' . $origName);
                    if ($imgContent === false) {
                        $imgContent = $zip->getFromName('images/' . $rawOrigName);
                    }
                    if ($imgContent === false) continue;

                    $destName = $origName;
                    if (file_exists($uploadDir . $origName)) {
                        $destName = pathinfo($origName, PATHINFO_FILENAME) . '_' . uniqid() . '.' . $ext;
                    }
                    file_put_contents($uploadDir . $destName, $imgContent);
                    $renameMap[$rawOrigName] = $destName;
                }
            }

            // 4. Insert soal dengan referensi gambar yang sudah diperbarui
            foreach ($data['soal'] as $q) {
                if (!empty($q['media_files']) && isset($renameMap[$q['media_files']])) {
                    $q['media_files'] = $renameMap[$q['media_files']];
                }
                foreach ($renameMap as $old => $new) {
                    if (!empty($q['konten_soal'])) {
                        $q['konten_soal'] = str_replace('assets/uploads/soal/' . $old, 'assets/uploads/soal/' . $new, $q['konten_soal']);
                    }
                }

                $stmt_q = $pdo->prepare("INSERT INTO cbt_questions (bank_soal_id, tipe, konten_soal, media_files, tingkat_kesulitan, bobot_skor) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_q->execute([$new_bank_id, $q['tipe'], $q['konten_soal'], $q['media_files'] ?? null, $q['tingkat_kesulitan'], $q['bobot_skor']]);
                $new_q_id = $pdo->lastInsertId();

                foreach ($q['options'] ?? [] as $o) {
                    foreach ($renameMap as $old => $new) {
                        if (!empty($o['value_target'])) {
                            $o['value_target'] = str_replace('assets/uploads/soal/' . $old, 'assets/uploads/soal/' . $new, $o['value_target']);
                        }
                    }
                    $stmt_o = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                    $stmt_o->execute([$new_q_id, $o['label'], $o['value_target'], $o['is_correct']]);
                }
            }

            $pdo->commit();
            $zip->close();
            log_activity("Restore bank soal dari backup, bank baru ID $new_bank_id (" . ($data['bank']['nama_bank_soal'] ?? '') . ")", null, null, null, 'ujian');
            // Redirect ke halaman sebelumnya dengan status sukses (Memicu SweetAlert)
            header("Location: bank-soal-backup.php?status=success");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $zip->close();
            
            // Cek jika errornya adalah duplicate entry (Kode 23000)
            if ($e->getCode() == 23000) {
                header("Location: bank-soal-backup.php?status=error&type=duplicate");
            } else {
                header("Location: bank-soal-backup.php?status=error&msg=" . urlencode($e->getMessage()));
            }
            exit;
        }
    } else {
        header("Location: bank-soal-backup.php?status=error&msg=Gagal membuka file ZIP");
        exit;
    }
}
