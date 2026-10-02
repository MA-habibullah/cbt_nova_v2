<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/database.php';
date_default_timezone_set('Asia/Jakarta');

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    if ($is_ajax) { echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid.']); exit; }
    header("Location: ../index.php"); exit;
}
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) { echo json_encode(['status' => 'error', 'message' => 'Method tidak valid.']); exit; }
    header("Location: ../index.php"); exit;
}

// 2. Variabel Dasar
$sekarang = date('Y-m-d H:i:s'); 
$exam_id = (int)$_POST['exam_id'];
$student_id = $_SESSION['student_id'];
$token_input = isset($_POST['token']) ? trim($_POST['token']) : '';

try {
    // 1. Ambil Detail Ujian
    $stmtExam = $pdo->prepare("SELECT * FROM cbt_exams WHERE id = ? AND status = 'aktif'");
    $stmtExam->execute([$exam_id]);
    $exam = $stmtExam->fetch();

    if (!$exam) {
        if ($is_ajax) { echo json_encode(['status' => 'error', 'message' => 'Ujian tidak ditemukan atau sudah dinonaktifkan.']); exit; }
        die("Ujian tidak ditemukan atau sudah dinonaktifkan.");
    }

    // 2. Validasi Waktu (Strict Checking)
    if ($sekarang < $exam['mulai_pada'] || $sekarang > $exam['selesai_pada']) {
        $msg = "Maaf, waktu ujian belum dimulai atau sudah berakhir.";
        if ($is_ajax) { echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
        die($msg);
    }

    // 3. Validasi Token
    if ($exam['token'] && $exam['is_token_aktif']) {
        if ($token_input !== $exam['token']) {
            if ($is_ajax) { echo json_encode(['status' => 'error', 'message' => 'Token salah! Periksa kembali.']); exit; }
            echo "<script>alert('Token salah!'); window.location.href='konfirmasi_ujian.php?id=$exam_id';</script>";
            exit;
        }
    }

    // 4. Cek Partisipasi
    $stmtPart = $pdo->prepare("SELECT * FROM cbt_exam_participants WHERE exam_id = ? AND student_id = ?");
    $stmtPart->execute([$exam_id, $student_id]);
    $participant = $stmtPart->fetch();

    if ($participant && in_array($participant['status'], ['finished', 'blocked'])) {
        $msg = $participant['status'] === 'blocked' ? 'akun_diblokir' : 'sudah_selesai';
        if ($is_ajax) { echo json_encode(['status' => 'redirect', 'redirect' => $msg]); exit; }
        header("Location: index.php?msg=$msg"); exit;
    }

    // 5. VERIFIKASI DEVICE LOCK
    $device_id = md5($_SERVER['HTTP_USER_AGENT']);

    $stmtLock = $pdo->prepare("SELECT device_id FROM cbt_device_locks WHERE student_id = ?");
    $stmtLock->execute([$student_id]);
    $lock = $stmtLock->fetch();

    if ($lock && $lock['device_id'] !== $device_id) {
        if ($is_ajax) { echo json_encode(['status' => 'redirect', 'redirect' => 'device_locked']); exit; }
        header("Location: index.php?msg=device_locked"); exit;
    }

    // 6. UPDATE ATAU INSERT DATA (LOGIKA DIPERBAIKI)
    if (!$participant) {
        // Siswa benar-benar baru pertama kali klik
        $stmtInsert = $pdo->prepare("INSERT INTO cbt_exam_participants
            (exam_id, student_id, waktu_mulai, status, created_at)
            VALUES (?, ?, CURRENT_TIMESTAMP, 'working', CURRENT_TIMESTAMP)");
        $stmtInsert->execute([$exam_id, $student_id]);
        $participant_id = (int)$pdo->lastInsertId();
        // Re-fetch participant agar soal_ids bisa dicek
        $participant = $pdo->prepare("SELECT * FROM cbt_exam_participants WHERE id = ?");
        $participant->execute([$participant_id]);
        $participant = $participant->fetch();
    } else {
        // Jika data sudah ada (tapi status bukan finished)
        if ($participant['status'] !== 'finished') {
            // Set status working; waktu_mulai HANYA diisi jika belum ada (resume tidak reset timer)
            $sqlUp = "UPDATE cbt_exam_participants SET status = 'working'";
            if (empty($participant['waktu_mulai'])) {
                $sqlUp .= ", waktu_mulai = CURRENT_TIMESTAMP";
            }
            $sqlUp .= " WHERE id = ?";

            $stmtUpdate = $pdo->prepare($sqlUp);
            $stmtUpdate->execute([$participant['id']]);
            $participant_id = (int)$participant['id'];
        } else {
            // Jika sudah finished, lempar ke index
            if ($is_ajax) { echo json_encode(['status' => 'redirect', 'redirect' => 'sudah_selesai']); exit; }
            header("Location: index.php?msg=sudah_selesai"); exit;
        }
    }

    // 7. Generate soal_ids per-siswa jika distribusi aktif dan belum di-set
    if (empty($participant['soal_ids'])) {
        $dist_tipe      = $exam['distribusi_tipe']      ? json_decode($exam['distribusi_tipe'], true)      : null;
        $dist_kesulitan = $exam['distribusi_kesulitan']  ? json_decode($exam['distribusi_kesulitan'], true)  : null;
        $limit          = (int)($exam['jumlah_soal_limit'] ?? 0);

        if ($dist_tipe || $dist_kesulitan || $limit > 0) {
            $valid_tipes  = ['pg', 'pg_kompleks', 'isian', 'benar_salah', 'menjodohkan', 'essay'];
            $valid_levels = ['mudah', 'sedang', 'sulit'];
            $picked = [];

            // SRE Optimization: Tarik seluruh pool soal ujian dalam 1 query terindeks,
            // lalu lakukan filtering & pengacakan in-memory untuk mengeliminasi ORDER BY RAND()
            $stPool = $pdo->prepare("SELECT eq.question_id, q.tipe, q.tingkat_kesulitan 
                                     FROM cbt_exam_questions eq 
                                     JOIN cbt_questions q ON eq.question_id = q.id 
                                     WHERE eq.exam_id = ?");
            $stPool->execute([$exam_id]);
            $poolRows = $stPool->fetchAll(PDO::FETCH_ASSOC);

            if ($dist_tipe) {
                foreach ($dist_tipe as $tipe => $jumlah) {
                    if (!in_array($tipe, $valid_tipes, true) || $jumlah <= 0) continue;

                    // Group pool by level for this type
                    $byLevel = ['mudah' => [], 'sedang' => [], 'sulit' => []];
                    foreach ($poolRows as $pr) {
                        if ($pr['tipe'] === $tipe && in_array($pr['tingkat_kesulitan'], $valid_levels, true)) {
                            $byLevel[$pr['tingkat_kesulitan']][] = (int)$pr['question_id'];
                        }
                    }

                    $active = array_values(array_filter($valid_levels, fn($l) => !empty($byLevel[$l])));
                    if (empty($active)) continue;

                    $tipe_ids  = [];
                    $remaining = $jumlah;
                    $n = count($active);
                    $per = intdiv($jumlah, $n);
                    $rem = $jumlah % $n;

                    foreach ($active as $i => $level) {
                        $take = min($per + ($i < $rem ? 1 : 0), count($byLevel[$level]));
                        if ($take <= 0) continue;
                        $shuffled = $byLevel[$level];
                        shuffle($shuffled);
                        $chosen = array_slice($shuffled, 0, $take);
                        $tipe_ids = array_merge($tipe_ids, $chosen);
                        $remaining -= count($chosen);
                    }

                    // Fallback jika masih kurang
                    if ($remaining > 0) {
                        $allTipeIds = [];
                        foreach ($poolRows as $pr) {
                            if ($pr['tipe'] === $tipe && !in_array((int)$pr['question_id'], $tipe_ids, true)) {
                                $allTipeIds[] = (int)$pr['question_id'];
                            }
                        }
                        shuffle($allTipeIds);
                        $tipe_ids = array_merge($tipe_ids, array_slice($allTipeIds, 0, $remaining));
                    }
                    $picked = array_merge($picked, $tipe_ids);
                }

            } elseif ($dist_kesulitan) {
                foreach ($dist_kesulitan as $level => $jumlah) {
                    if (!in_array($level, $valid_levels, true) || $jumlah <= 0) continue;

                    $byTipe = [];
                    foreach ($valid_tipes as $vt) $byTipe[$vt] = [];
                    foreach ($poolRows as $pr) {
                        if ($pr['tingkat_kesulitan'] === $level && in_array($pr['tipe'], $valid_tipes, true)) {
                            $byTipe[$pr['tipe']][] = (int)$pr['question_id'];
                        }
                    }

                    $active = array_values(array_filter($valid_tipes, fn($t) => !empty($byTipe[$t])));
                    if (empty($active)) continue;

                    $level_ids = [];
                    $remaining = $jumlah;
                    $n = count($active);
                    $per = intdiv($jumlah, $n);
                    $rem = $jumlah % $n;

                    foreach ($active as $i => $tipe) {
                        $take = min($per + ($i < $rem ? 1 : 0), count($byTipe[$tipe]));
                        if ($take <= 0) continue;
                        $shuffled = $byTipe[$tipe];
                        shuffle($shuffled);
                        $chosen = array_slice($shuffled, 0, $take);
                        $level_ids = array_merge($level_ids, $chosen);
                        $remaining -= count($chosen);
                    }

                    if ($remaining > 0) {
                        $allLevelIds = [];
                        foreach ($poolRows as $pr) {
                            if ($pr['tingkat_kesulitan'] === $level && !in_array((int)$pr['question_id'], $level_ids, true)) {
                                $allLevelIds[] = (int)$pr['question_id'];
                            }
                        }
                        shuffle($allLevelIds);
                        $level_ids = array_merge($level_ids, array_slice($allLevelIds, 0, $remaining));
                    }
                    $picked = array_merge($picked, $level_ids);
                }

            } else {
                // Mode total limit: shuffle seluruh pool in-memory
                $allIds = array_column($poolRows, 'question_id');
                shuffle($allIds);
                $picked = array_slice($allIds, 0, $limit);
            }

            if (!empty($picked)) {
                $picked = array_unique(array_map('intval', $picked));
                $pdo->prepare("UPDATE cbt_exam_participants SET soal_ids = ? WHERE id = ?")
                    ->execute([json_encode(array_values($picked)), $participant_id]);
            }
        }
    }

    // Setelah insert/update berhasil
    log_activity("Mulai ujian ID $exam_id", (int)$student_id, null, null, 'ujian');
    if ($is_ajax) { echo json_encode(['status' => 'ok', 'exam_id' => $exam_id]); exit; }
    header("Location: ujian.php?id=" . $exam_id);
    exit;

} catch (PDOException $e) {
    if ($is_ajax) { echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.']); exit; }
    header("Location: index.php?msg=error_sistem"); exit;
}