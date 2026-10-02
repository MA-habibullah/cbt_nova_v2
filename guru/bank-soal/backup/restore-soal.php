<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__, 3) . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: " . BASE_URL . "index.php"); exit;
}
csrf_verify();
$teacher_id = $_SESSION['teacher_id'];

if (!isset($_POST['restore'])) {
    header("Location: ../backup.php"); exit;
}

// Lock guard: verify the originating bank soal is not locked
$id_bank_back = (int)($_POST['bank_id_back'] ?? 0);
if ($id_bank_back) {
    $chk = $pdo->prepare("SELECT status FROM cbt_bank_soal WHERE id = ? AND teacher_id = ?");
    $chk->execute([$id_bank_back, $teacher_id]);
    $bk = $chk->fetch();
    if ($bk && $bk['status'] === 'nonaktif') {
        header("Location: ../backup.php?id=$id_bank_back&msg=locked"); exit;
    }
}

$subject_id = (int)($_POST['subject_id'] ?? 0);
$zipFile      = $_FILES['backup_file']['tmp_name'] ?? '';
$uploadDir    = dirname(__DIR__, 3) . "/assets/uploads/soal/";

if (!$subject_id || !$zipFile) {
    header("Location: ../backup.php?id=$id_bank_back&status=error&msg=Data+tidak+lengkap"); exit;
}

$zip = new ZipArchive;
if ($zip->open($zipFile) !== TRUE) {
    header("Location: ../backup.php?id=$id_bank_back&status=error&msg=Gagal+membuka+ZIP"); exit;
}

$data = json_decode($zip->getFromName('data.json'), true);
if (!$data) {
    $zip->close();
    header("Location: ../backup.php?id=$id_bank_back&status=error&msg=Format+file+tidak+valid"); exit;
}

$pdo->beginTransaction();
try {
    $kode_asli = $data['bank']['kode_bank_soal'] ?? 'RESTORE';
    $kode_baru = $kode_asli . '-' . time();

    $stmt_b = $pdo->prepare("INSERT INTO cbt_bank_soal (kode_bank_soal, nama_bank_soal, subject_id, teacher_id) VALUES (?, ?, ?, ?)");
    $stmt_b->execute([
        $kode_baru,
        ($data['bank']['nama_bank_soal'] ?? 'Restored') . ' (Restored)',
        $subject_id,
        $teacher_id
    ]);
    $new_bank_id = $pdo->lastInsertId();

    // Ekstrak filename gambar dari HTML (konten TinyMCE / value_target)
    $extractImgNames = function($html) {
        if (empty($html)) return [];
        preg_match_all('/assets\/uploads\/soal\/([^\s"\'<>?#]+)/i', $html, $m);
        return array_unique(array_filter($m[1]));
    };

    // Tulis gambar dari ZIP ke disk, tangani collision dengan rename (dengan proteksi whitelist ekstensi & basename)
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

    foreach ($data['soal'] as $q) {
        // Terapkan rename pada media_files dan konten HTML
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
            $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)")
                ->execute([$new_q_id, $o['label'], $o['value_target'], $o['is_correct']]);
        }
    }

    $pdo->commit();
    $zip->close();
    log_activity("Restore bank soal dari backup, bank baru ID $new_bank_id (" . ($data['bank']['nama_bank_soal'] ?? '') . ")", null, null, null, 'ujian');
    header("Location: ../backup.php?id=$id_bank_back&status=success"); exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    $zip->close();
    $type = ($e->getCode() == 23000) ? 'duplicate' : 'error';
    header("Location: ../backup.php?id=$id_bank_back&status=error&type=$type"); exit;
}
