<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../../../config/database.php';
require_once '../../../includes/helpers.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
csrf_verify();

$UPLOAD_DIR = dirname(__DIR__, 3) . '/assets/uploads/soal/';

// Pastikan direktori upload ada
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0777, true);
}

/**
 * Ekstrak nama file gambar yang diupload dari konten HTML (tag <img src="...">)
 */
function extractUploadedImages(string $html): array {
    if (empty($html)) return [];
    $files = [];
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
    foreach ($matches[1] as $url) {
        if (strpos($url, 'assets/uploads/soal/') !== false) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            if ($filename) $files[] = $filename;
        }
    }
    return $files;
}

/**
 * Hapus file gambar dari direktori upload
 */
function deleteImages(array $filenames, string $uploadDir): void {
    foreach ($filenames as $filename) {
        $path = $uploadDir . $filename;
        if (file_exists($path)) @unlink($path);
    }
}

/**
 * Kumpulkan semua gambar dari data POST (semua tipe soal)
 */
function collectNewImages(): array {
    $contents = [
        $_POST['konten_soal'] ?? '',
    ];
    foreach ((array)($_POST['kunci_teks'] ?? []) as $k) {
        $contents[] = $k;
    }
    foreach (['A','B','C','D','E'] as $l) {
        $contents[] = $_POST['jawaban_'.$l] ?? '';
    }
    foreach (($_POST['match_kiri'] ?? []) as $v)  $contents[] = $v;
    foreach (($_POST['match_kanan'] ?? []) as $v) $contents[] = $v;

    $images = [];
    foreach ($contents as $html) {
        $images = array_merge($images, extractUploadedImages($html));
    }
    return array_unique($images);
}

/**
 * Ambil semua gambar milik sebuah soal dari database
 */
function getQuestionImages(PDO $pdo, int $soal_id): array {
    $q = $pdo->prepare("SELECT konten_soal FROM cbt_questions WHERE id = ?");
    $q->execute([$soal_id]);
    $row = $q->fetch();
    $images = extractUploadedImages($row['konten_soal'] ?? '');

    $opts = $pdo->prepare("SELECT label, value_target FROM cbt_question_options WHERE question_id = ?");
    $opts->execute([$soal_id]);
    foreach ($opts->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $images = array_merge($images, extractUploadedImages($row['label']));
        $images = array_merge($images, extractUploadedImages($row['value_target']));
    }
    return array_unique($images);
}

// --- 1. LOGIKA HAPUS SOAL ---
if (($_POST['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    $id      = (int)($_POST['id'] ?? 0);
    $bank_id = (int)($_POST['bank_id'] ?? 0);

    try {
        $oldImages = getQuestionImages($pdo, $id);

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM cbt_question_options WHERE question_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cbt_questions WHERE id = ?")->execute([$id]);
        $pdo->commit();

        deleteImages($oldImages, $UPLOAD_DIR);
        log_activity("Hapus soal ID $id dari bank soal ID $bank_id", null, null, null, 'ujian');

        echo json_encode(['status' => 'ok']); exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
    }
}

// --- 2. LOGIKA SIMPAN (TAMBAH) & UPDATE SOAL ---
if (isset($_POST['simpan_soal']) || isset($_POST['update_soal'])) {
    $is_update  = isset($_POST['update_soal']);
    $bank_id    = $_POST['bank_soal_id'];
    $tipe       = $_POST['tipe'];
    $konten     = strip_domain_from_html($_POST['konten_soal']);
    $bobot      = $_POST['bobot_skor'];
    $kesulitan  = $_POST['tingkat_kesulitan'];
    $soal_id    = $is_update ? $_POST['soal_id'] : null;

    try {
        $pdo->beginTransaction();

        if ($is_update) {
            // Ambil gambar lama sebelum dihapus dari DB
            $oldImages = getQuestionImages($pdo, $soal_id);
            $newImages = collectNewImages();
            $imagesToDelete = array_diff($oldImages, $newImages);

            $sql = "UPDATE cbt_questions SET tipe = ?, konten_soal = ?, tingkat_kesulitan = ?, bobot_skor = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$tipe, $konten, $kesulitan, $bobot, $soal_id]);
            $pdo->prepare("DELETE FROM cbt_question_options WHERE question_id = ?")->execute([$soal_id]);
            $current_q_id = $soal_id;
        } else {
            // Insert Soal Baru
            $sql = "INSERT INTO cbt_questions (bank_soal_id, tipe, konten_soal, tingkat_kesulitan, bobot_skor) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$bank_id, $tipe, $konten, $kesulitan, $bobot]);
            $current_q_id = $pdo->lastInsertId();
        }

        // --- PROSES OPSI JAWABAN BERDASARKAN TIPE ---
        if ($tipe == 'pg' || $tipe == 'pg_kompleks') {
            $kunci_pg = $_POST['kunci_pg'] ?? ''; // Untuk PG Biasa (Radio)
            $kunci_complex = $_POST['kunci_complex'] ?? []; // Untuk PG Kompleks (Checkbox)

            foreach (['A', 'B', 'C', 'D', 'E'] as $l) {
                $val = strip_domain_from_html($_POST['jawaban_' . $l] ?? '');
                // Cek apakah label ini adalah kunci
                $is_correct = 0;
                if ($tipe == 'pg' && $kunci_pg === $l) $is_correct = 1;
                if ($tipe == 'pg_kompleks' && in_array($l, $kunci_complex)) $is_correct = 1;

                $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)")
                    ->execute([$current_q_id, $l, $val, $is_correct]);
            }
        } 
        elseif ($tipe == 'benar_salah') {
            // 1. Ambil kunci jawaban dari form (B atau S)
            $kunci_dipilih = $_POST['kunci_bs'] ?? 'B';

            // 2. Daftar opsi yang harus ada di database
            $opsi_bs = [
                ['label' => 'B', 'teks' => 'Benar'],
                ['label' => 'S', 'teks' => 'Salah']
            ];

            // 3. Looping untuk simpan KEDUA opsi (Benar dan Salah)
            foreach ($opsi_bs as $opsi) {
                // Tentukan apakah baris ini adalah jawaban yang benar
                $is_correct = ($kunci_dipilih == $opsi['label']) ? 1 : 0;

                $stmt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $current_q_id, 
                    $opsi['label'],   // Menyimpan 'B' atau 'S'
                    $opsi['teks'],    // Menyimpan 'Benar' atau 'Salah'
                    $is_correct       // 1 jika dipilih sebagai kunci, 0 jika tidak
                ]);
            }
        }
        elseif ($tipe == 'menjodohkan') {
            $kiri = $_POST['match_kiri'] ?? [];
            $kanan = $_POST['match_kanan'] ?? [];
            foreach ($kiri as $index => $val_kiri) {
                $val_kiri  = strip_domain_from_html($val_kiri);
                $val_kanan = strip_domain_from_html($kanan[$index] ?? '');
                $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)")
                    ->execute([$current_q_id, $val_kiri, $val_kanan, 1]);
            }
        } 
        elseif ($tipe === 'isian') {
            $kunci_list = array_values(array_filter(
                array_map('trim', (array)($_POST['kunci_teks'] ?? [])),
                fn($k) => $k !== ''
            ));
            foreach ($kunci_list as $i => $kunci_val) {
                $kunci_val = strip_domain_from_html($kunci_val);
                $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)")
                    ->execute([$current_q_id, 'KUNCI_' . ($i + 1), $kunci_val, 1]);
            }
        } elseif ($tipe === 'essay') {
            $kunci_list = (array)($_POST['kunci_teks'] ?? []);
            $kunci = strip_domain_from_html(trim($kunci_list[0] ?? ''));
            $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)")
                ->execute([$current_q_id, 'KUNCI', $kunci, 1]);
        }

        $pdo->commit();

        if ($is_update && !empty($imagesToDelete)) {
            // Hapus gambar lama yang tidak dipakai lagi
            deleteImages($imagesToDelete, $UPLOAD_DIR);
        } else {
            // Saat INSERT: hapus orphan — file yang diupload via TinyMCE tapi tidak jadi dipakai
            $usedImages  = getQuestionImages($pdo, (int)$current_q_id);
            $pendingUploads = $_SESSION['pending_uploads'] ?? [];
            $orphans = array_diff($pendingUploads, $usedImages);
            if (!empty($orphans)) {
                deleteImages(array_values($orphans), $UPLOAD_DIR);
            }
        }
        unset($_SESSION['pending_uploads']);

        $label = $is_update ? "Update soal ID $soal_id" : "Tambah soal baru ID $current_q_id";
        log_activity("$label di bank soal ID $bank_id (tipe: $tipe)", null, null, null, 'ujian');
        $msg = $is_update ? 'updated' : 'success';
        header("Location: ../soal.php?id=$bank_id&msg=$msg"); exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}