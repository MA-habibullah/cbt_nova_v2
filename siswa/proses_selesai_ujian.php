<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once '../includes/helpers.php';
date_default_timezone_set('Asia/Jakarta');

// Proteksi: Hanya siswa yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit;
}

$exam_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = $_SESSION['student_id'];
$waktu_selesai = date('Y-m-d H:i:s');

try {
    // 1. Ambil Data Partisipasi + soal_ids (untuk distribusi per-siswa)
    $stmtPart = $pdo->prepare("
        SELECT p.id, p.soal_ids, e.bank_soal_id
        FROM cbt_exam_participants p
        JOIN cbt_exams e ON p.exam_id = e.id
        WHERE p.exam_id = ? AND p.student_id = ? AND p.status NOT IN ('finished', 'blocked')
    ");
    $stmtPart->execute([$exam_id, $student_id]);
    $participant = $stmtPart->fetch();

    if (!$participant) {
        header("Location: index.php");
        exit;
    }

    $participant_id = $participant['id'];

    // Validasi server-side: tombol Selesai hanya boleh dipakai 5 menit terakhir.
    // reason=timeout (auto-submit saat waktu benar-benar habis) selalu lolos.
    $reason = $_GET['reason'] ?? '';
    if ($reason !== 'timeout') {
        $waktu = hitung_sisa_waktu($pdo, $participant_id);
        if ($waktu['sisa_detik'] > 300) {
            $too_early_msg = 'Ujian belum bisa diselesaikan. Tombol Selesai aktif 5 menit sebelum waktu habis.';
            if (isset($_GET['_spa'])) {
                echo json_encode(['status' => 'too_early', 'message' => $too_early_msg]);
                exit;
            }
            header("Location: index.php?view=ujian&id=" . $exam_id . "&msg=too_early");
            exit;
        }
    }

    // Ambil bobot per kategori dari exam
    $tipe_objektif_list = ['pg', 'pg_kompleks', 'benar_salah', 'menjodohkan', 'isian'];
    $ph_tipe = implode(',', array_fill(0, count($tipe_objektif_list), '?'));
    $stmtBobot = $pdo->prepare("
        SELECT
            SUM(CASE WHEN q.tipe IN ($ph_tipe) THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
            SUM(CASE WHEN q.tipe = 'essay'     THEN q.bobot_skor ELSE 0 END) AS bobot_essay
        FROM cbt_exam_questions eq
        JOIN cbt_questions q ON eq.question_id = q.id
        WHERE eq.exam_id = ?
    ");
    $stmtBobot->execute([...$tipe_objektif_list, $exam_id]);
    $bobotRow    = $stmtBobot->fetch();
    $bobot_obj   = (float)($bobotRow['bobot_obj']   ?? 0);
    $bobot_essay = (float)($bobotRow['bobot_essay'] ?? 0);
    $has_obj     = $bobot_obj   > 0;
    $has_essay   = $bobot_essay > 0;
    $skor_status = $has_essay ? 'pending' : 'final';

    // 2. Ambil semua jawaban siswa
    // Jika soal_ids terisi (distribusi per-siswa aktif), filter hanya soal dalam distribusi tersebut
    $soal_ids_json = $participant['soal_ids'] ?? null;
    if ($soal_ids_json) {
        $allowed_soal_ids = json_decode($soal_ids_json, true) ?: [];
    } else {
        $allowed_soal_ids = [];
    }

    $stmtAns = $pdo->prepare("SELECT id, question_id, jawaban_simpan FROM cbt_student_answers WHERE participant_id = ?");
    $stmtAns->execute([$participant_id]);
    $jawaban_siswa_raw = $stmtAns->fetchAll();

    // Filter jawaban: jika distribusi aktif, hanya nilai soal dalam soal_ids
    if (!empty($allowed_soal_ids)) {
        $allowed_set = array_map('intval', $allowed_soal_ids);
        $jawaban_siswa = array_values(array_filter($jawaban_siswa_raw, function($js) use ($allowed_set) {
            return in_array((int)$js['question_id'], $allowed_set, true);
        }));
    } else {
        $jawaban_siswa = $jawaban_siswa_raw;
    }

    $total_skor_obj = 0;

    if (!empty($jawaban_siswa)) {
        // --- BATCH FETCH: Ambil semua soal & opsi sekaligus (hindari N+1 query) ---
        $q_ids        = array_column($jawaban_siswa, 'question_id');
        $placeholders = implode(',', array_fill(0, count($q_ids), '?'));

        // Batch fetch soal
        $stmtQuestions = $pdo->prepare(
            "SELECT id, tipe, bobot_skor FROM cbt_questions WHERE id IN ($placeholders)"
        );
        $stmtQuestions->execute($q_ids);
        $questions_map = [];
        foreach ($stmtQuestions->fetchAll() as $q) {
            $questions_map[(int)$q['id']] = $q;
        }

        // Batch fetch semua opsi untuk soal-soal tersebut
        $stmtOptions = $pdo->prepare(
            "SELECT question_id, id, is_correct, value_target FROM cbt_question_options WHERE question_id IN ($placeholders)"
        );
        $stmtOptions->execute($q_ids);
        $options_map = [];
        foreach ($stmtOptions->fetchAll() as $opt) {
            $options_map[(int)$opt['question_id']][] = $opt;
        }
        // --- END BATCH FETCH ---

        // Kumpulkan skor per jawaban untuk batch update
        $score_updates = []; // [answer_id => skor]

        foreach ($jawaban_siswa as $js) {
            $q_id    = (int)$js['question_id'];
            $j_siswa = $js['jawaban_simpan'];
            $soal    = $questions_map[$q_id] ?? null;

            // Lewati jika soal sudah dihapus dari database
            if (!$soal) {
                $score_updates[(int)$js['id']] = 0;
                continue;
            }

            $opts        = $options_map[$q_id] ?? [];
            $skor_didapat = 0;

            // --- LOGIKA PENILAIAN OTOMATIS ---

            // A. Pilihan Ganda & Benar Salah
            if ($soal['tipe'] == 'pg' || $soal['tipe'] == 'benar_salah') {
                foreach ($opts as $opt) {
                    if ($opt['is_correct'] && (string)$js['jawaban_simpan'] === (string)$opt['id']) {
                        $skor_didapat = $soal['bobot_skor'];
                        break;
                    }
                }
            }

            // B. Pilihan Ganda Kompleks
            elseif ($soal['tipe'] == 'pg_kompleks') {
                $kunci_raw = array_filter($opts, fn($o) => $o['is_correct']);
                $kunci_arr = array_values(array_unique(array_filter(
                    array_map('intval', array_column($kunci_raw, 'id')),
                    fn($x) => $x > 0
                )));

                $j_decoded = json_decode($j_siswa, true);
                $j_ids = [];

                if (is_array($j_decoded)) {
                    if (array_values($j_decoded) === $j_decoded) {
                        $j_ids = $j_decoded;
                    } else {
                        foreach ($j_decoded as $k => $v) {
                            if (is_numeric($k) && ($v === true || $v === 1 || $v === '1' || (string)$v === (string)$k)) {
                                $j_ids[] = $k;
                            } elseif (is_numeric($v)) {
                                $j_ids[] = $v;
                            }
                        }
                    }
                }

                $j_siswa_arr = array_values(array_unique(array_filter(
                    array_map('intval', $j_ids),
                    fn($x) => $x > 0
                )));

                sort($kunci_arr);
                sort($j_siswa_arr);

                $total_kunci      = count($kunci_arr);
                $total_salah_opts = count(array_filter($opts, fn($o) => !(bool)$o['is_correct']));

                $kunci_set      = array_flip($kunci_arr);
                $benar_terpilih = count(array_filter($j_siswa_arr, fn($id) => isset($kunci_set[$id])));
                $salah_terpilih = count(array_filter($j_siswa_arr, fn($id) => !isset($kunci_set[$id])));

                $ratio_benar  = ($total_kunci > 0)      ? ($benar_terpilih / $total_kunci)      : 0;
                $ratio_salah  = ($total_salah_opts > 0) ? ($salah_terpilih / $total_salah_opts) : 0;
                $skor_didapat = max(0.0, ($ratio_benar - $ratio_salah) * (float)$soal['bobot_skor']);
            }

            // C. Menjodohkan (Matching)
            elseif ($soal['tipe'] == 'menjodohkan') {
                $kunci_by_id = [];
                foreach ($opts as $opt) {
                    $kunci_by_id[(string)$opt['id']] = trim((string)$opt['value_target']);
                }
                ksort($kunci_by_id);

                $j_siswa_map = json_decode($j_siswa, true);
                $jawaban_norm = [];

                if (is_array($j_siswa_map)) {
                    foreach ($j_siswa_map as $k => $v) {
                        $jawaban_norm[(string)$k] = trim((string)$v);
                    }
                    ksort($jawaban_norm);
                }

                $total_baris = count($kunci_by_id);
                $benar_baris = 0;
                foreach ($kunci_by_id as $k => $v) {
                    if (isset($jawaban_norm[$k]) && $jawaban_norm[$k] === $v) {
                        $benar_baris++;
                    }
                }
                $skor_didapat = ($total_baris > 0)
                    ? ($benar_baris / $total_baris) * (float)$soal['bobot_skor']
                    : 0.0;
            }

            // D. Isian (Jawaban Singkat) — exact match case-insensitive, multi-kunci
            elseif ($soal['tipe'] === 'isian') {
                $j_text = strtolower(trim((string)$j_siswa));
                if ($j_text !== '') {
                    foreach ($opts as $opt) {
                        if ((int)$opt['is_correct'] === 1) {
                            $kunci = strtolower(trim((string)$opt['value_target']));
                            if ($kunci !== '' && $j_text === $kunci) {
                                $skor_didapat = (float)$soal['bobot_skor'];
                                break;
                            }
                        }
                    }
                }
            }
            // E. Essay — dinilai manual oleh guru, tidak dihitung di sini

            $score_updates[(int)$js['id']] = $skor_didapat;

            // Hanya tipe pg, pg_kompleks, benar_salah, menjodohkan, isian yang masuk total objektif
            $tipe_dinilai = ['pg', 'pg_kompleks', 'benar_salah', 'menjodohkan', 'isian'];
            if (in_array($soal['tipe'], $tipe_dinilai)) {
                $total_skor_obj += $skor_didapat;
            }
        }

        // Hitung nilai per kategori (skala 0-100)
        $nilai_objektif = ($bobot_obj > 0)
            ? round($total_skor_obj / $bobot_obj * 100, 2)
            : 0.0;
        $nilai_esai = 0.0; // Essay belum dikoreksi saat siswa selesai

        // Formula 50:50 dengan edge case
        if ($has_obj && $has_essay) {
            $nilai_akhir = round(($nilai_objektif * 0.5) + ($nilai_esai * 0.5), 2);
        } elseif ($has_obj) {
            $nilai_akhir = $nilai_objektif;
        } elseif ($has_essay) {
            $nilai_akhir = $nilai_esai;
        } else {
            $nilai_akhir = 0.0;
        }

        // --- BATCH UPDATE SKOR dalam satu transaction ---
        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE cbt_student_answers SET skor_didapat = ? WHERE id = ?");
        foreach ($score_updates as $answer_id => $skor) {
            $upd->execute([$skor, $answer_id]);
        }

        // Update status partisipasi final
        $stmtFinish = $pdo->prepare("
            UPDATE cbt_exam_participants
            SET status = 'finished',
                waktu_selesai = ?,
                skor_akhir = ?,
                nilai_objektif = ?,
                nilai_esai = ?,
                skor_status = ?,
                tambahan_waktu = 0
            WHERE id = ?
        ");
        $stmtFinish->execute([$waktu_selesai, $nilai_akhir, $nilai_objektif, $nilai_esai, $skor_status, $participant_id]);

        $pdo->commit();

    } else {
        // Tidak ada jawaban — langsung selesaikan dengan skor 0
        $nilai_akhir    = 0.0;
        $nilai_objektif = 0.0;
        $nilai_esai     = 0.0;

        $pdo->beginTransaction();

        $stmtFinish = $pdo->prepare("
            UPDATE cbt_exam_participants
            SET status = 'finished',
                waktu_selesai = ?,
                skor_akhir = 0,
                nilai_objektif = 0,
                nilai_esai = 0,
                skor_status = ?,
                tambahan_waktu = 0
            WHERE id = ?
        ");
        $stmtFinish->execute([$waktu_selesai, $skor_status, $participant_id]);

        $pdo->commit();
    }

    log_activity("Selesai ujian ID $exam_id, nilai akhir: $nilai_akhir (obj: $nilai_objektif, esai: $nilai_esai)", (int)$student_id, null, null, 'ujian');
    if (isset($_GET['_spa'])) { echo json_encode(['status' => 'ok']); exit; }
    header("Location: index.php?msg=ujian_selesai");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Gagal memproses nilai: " . $e->getMessage());
}
