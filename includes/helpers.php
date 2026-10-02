<?php
/**
 * Fungsi-fungsi helper global untuk CBT Nova.
 * Include file ini di setiap file yang membutuhkan fungsi bersama.
 */

/**
 * Fisher-Yates shuffle dengan seed deterministik.
 * Seed yang sama selalu menghasilkan urutan yang sama,
 * sehingga setiap siswa mendapat urutan unik namun konsisten
 * saat navigasi bolak-balik soal.
 *
 * @param array &$array  Array yang akan diacak (dimodifikasi langsung)
 * @param int   $seed    Seed untuk random number generator
 */
function seeded_shuffle(array &$array, int $seed): void {
    mt_srand($seed);
    $n = count($array);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
    }
}

/**
 * Render input field hidden untuk proteksi CSRF.
 */
function csrf_field(): string {
    $token = function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? '');
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Escape karakter wildcard LIKE MySQL (%, _, \).
 * Gunakan sebelum menyisipkan input user ke parameter LIKE.
 *
 * @param string $value  Nilai mentah dari user
 * @return string        Nilai yang sudah di-escape
 */
function like_escape(string $value): string {
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
}

/**
 * Menghapus domain prefix dari semua src= di HTML sebelum disimpan ke DB.
 * Mengubah: src="https://domain.com/basepath/assets/..." → src="assets/..."
 */
function strip_domain_from_html(string $html): string {
    return preg_replace('/src="https?:\/\/[^"\/][^"]*?\/(assets\/[^"]+)"/i', 'src="$1"', $html);
}

function inject_domain_to_html(string $html): string {
    if ($html === '') return '';
    return (string)preg_replace_callback('/src=["\']([^"\']+)["\']/i', function($matches) {
        $src = $matches[1];
        if (preg_match('/^data:/i', $src)) {
            return 'src="' . $src . '"';
        }
        // Normalisasi URL absolut dari domain lama / localhost yang mengarah ke assets/uploads/ atau uploads/
        if (preg_match('/https?:\/\/[^\/]+(?:\/[^\/]+)*?\/(assets\/uploads\/[^"\']+)/i', $src, $m)) {
            return 'src="' . BASE_URL . $m[1] . '"';
        }
        if (preg_match('/https?:\/\/[^\/]+(?:\/[^\/]+)*?\/(uploads\/[^"\']+)/i', $src, $m)) {
            return 'src="' . BASE_URL . 'assets/' . $m[1] . '"';
        }
        if (preg_match('/^(?:https?:|\/\/)/i', $src)) {
            return 'src="' . $src . '"';
        }
        $src_clean = ltrim($src, './');
        if (str_starts_with($src_clean, 'assets/')) {
            return 'src="' . BASE_URL . $src_clean . '"';
        }
        if (str_starts_with($src_clean, 'uploads/')) {
            return 'src="' . BASE_URL . 'assets/' . $src_clean . '"';
        }
        return 'src="' . $src . '"';
    }, $html);
}

/**
 * Menghitung deadline efektif & sisa waktu ujian untuk seorang peserta.
 *
 * Deadline efektif:
 * - Jika ada tambahan_waktu (admin memberi waktu tambahan): waktu_mulai + tambahan_waktu.
 * - Jika tidak: MIN(waktu_mulai + durasi_menit, selesai_pada milik jadwal ujian).
 *
 * Formula ini di-copy persis dari duplikasi yang sebelumnya ada di
 * siswa/ajax_get_waktu.php, siswa/ajax/view_ujian.php, dan siswa/ajax_save_jawaban.php
 * — JANGAN diubah logikanya di sini tanpa mengubah juga plan terkait.
 *
 * @param PDO $pdo
 * @param int $participant_id  ID baris cbt_exam_participants
 * @return array{final_deadline:int, sisa_detik:int, waktu_sekarang:int}
 */
function hitung_sisa_waktu(PDO $pdo, int $participant_id): array {
    $stmt = $pdo->prepare("
        SELECT p.waktu_mulai, p.tambahan_waktu,
               e.durasi_menit, e.selesai_pada,
               NOW() as waktu_sekarang_db
        FROM cbt_exam_participants p
        JOIN cbt_exams e ON p.exam_id = e.id
        WHERE p.id = ?
    ");
    $stmt->execute([$participant_id]);
    $row = $stmt->fetch();

    $waktu_mulai    = strtotime($row['waktu_mulai']);
    $waktu_sekarang = strtotime($row['waktu_sekarang_db']);

    if ((int)$row['tambahan_waktu'] > 0) {
        // Mode tambahan waktu: abaikan selesai_pada & durasi_menit asli
        $final_deadline = $waktu_mulai + (int)$row['tambahan_waktu'] * 60;
    } else {
        // Mode normal: MIN(durasi_menit, selesai_pada)
        $waktu_habis_durasi = $waktu_mulai + (int)$row['durasi_menit'] * 60;
        $batas_jadwal       = strtotime($row['selesai_pada']);
        $final_deadline     = min($waktu_habis_durasi, $batas_jadwal);
    }

    $sisa_detik = max(0, $final_deadline - $waktu_sekarang);

    return [
        'final_deadline' => $final_deadline,
        'sisa_detik'     => $sisa_detik,
        'waktu_sekarang' => $waktu_sekarang,
    ];
}

/**
 * Context-aware HTML escaping untuk output teks dan atribut HTML.
 *
 * @param mixed $value
 * @return string
 */
if (!function_exists('esc')) {
    function esc($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * ==========================================================================
 *  CENTRALIZED SCORING ENGINE (CBT NOVA)
 *  Satu-satunya sumber kebenaran (Single Source of Truth) untuk kalkulasi
 *  dan penyimpanan nilai peserta ujian.
 * ==========================================================================
 */

/**
 * Menghitung dan menyimpan nilai peserta ujian ke tabel cbt_student_answers
 * dan cbt_exam_participants.
 *
 * Mendukung seluruh tipe soal:
 * - pg (Pilihan Ganda Biasa)
 * - benar_salah (Benar / Salah)
 * - pg_kompleks (Multi-Jawaban dengan bobot parsial)
 * - menjodohkan (Matching dengan normalisasi anti-HTML)
 * - isian (Short Answer dengan case-insensitive & multi-kunci)
 * - essay (Skor manual guru)
 *
 * @param PDO         $pdo
 * @param int         $participant_id      ID di cbt_exam_participants
 * @param string|null $waktu_selesai       Timestamp selesai (opsional)
 * @return array{success:bool, participant_id?:int, nilai_akhir?:float, nilai_objektif?:float, nilai_esai?:float, skor_status?:string, waktu_selesai?:string, error?:string}
 */
function hitung_dan_simpan_nilai_peserta(PDO $pdo, int $participant_id, ?string $waktu_selesai = null): array {
    // 1. Ambil Data Partisipasi & Exam
    $stmtPart = $pdo->prepare("
        SELECT p.id, p.exam_id, p.student_id, p.soal_ids, p.waktu_selesai,
               e.bank_soal_id
        FROM cbt_exam_participants p
        JOIN cbt_exams e ON p.exam_id = e.id
        WHERE p.id = ?
    ");
    $stmtPart->execute([$participant_id]);
    $participant = $stmtPart->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        return ['success' => false, 'error' => 'Data partisipasi tidak ditemukan'];
    }

    $exam_id      = (int)$participant['exam_id'];
    $bank_soal_id = (int)$participant['bank_soal_id'];
    $final_waktu_selesai = $waktu_selesai ?: ($participant['waktu_selesai'] ?: date('Y-m-d H:i:s'));

    // 2. Tentukan daftar ID soal yang resmi dibagikan kepada siswa (soal_ids)
    $soal_ids_json = $participant['soal_ids'] ?? null;
    $allowed_soal_ids = [];
    if (!empty($soal_ids_json)) {
        $decoded = json_decode($soal_ids_json, true);
        if (is_array($decoded)) {
            $allowed_soal_ids = array_values(array_filter(array_map('intval', $decoded), fn($id) => $id > 0));
        }
    }

    $tipe_objektif_list = ['pg', 'pg_kompleks', 'benar_salah', 'menjodohkan', 'isian'];
    $ph_tipe = implode(',', array_fill(0, count($tipe_objektif_list), '?'));

    // Jika siswa memiliki paket soal subset / acak, hitung bobot maksimal HANYA dari soal miliknya
    if (!empty($allowed_soal_ids)) {
        $ph_soal = implode(',', array_fill(0, count($allowed_soal_ids), '?'));
        $stmtBobot = $pdo->prepare("
            SELECT
                SUM(CASE WHEN q.tipe IN ($ph_tipe) THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
                SUM(CASE WHEN q.tipe = 'essay'     THEN q.bobot_skor ELSE 0 END) AS bobot_essay,
                COUNT(CASE WHEN q.tipe = 'essay' THEN 1 END) AS count_essay
            FROM cbt_questions q
            WHERE q.id IN ($ph_soal)
        ");
        $stmtBobot->execute([...$tipe_objektif_list, ...$allowed_soal_ids]);
        $bobotRow = $stmtBobot->fetch(PDO::FETCH_ASSOC);

        $bobot_obj   = (float)($bobotRow['bobot_obj']   ?? 0);
        $bobot_essay = (float)($bobotRow['bobot_essay'] ?? 0);
        $count_essay = (int)($bobotRow['count_essay']   ?? 0);
    } else {
        // Fallback untuk ujian tanpa subset dinamis: Ambil bobot per kategori dari exam_questions
        $stmtBobot = $pdo->prepare("
            SELECT
                SUM(CASE WHEN q.tipe IN ($ph_tipe) THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
                SUM(CASE WHEN q.tipe = 'essay'     THEN q.bobot_skor ELSE 0 END) AS bobot_essay,
                COUNT(CASE WHEN q.tipe = 'essay' THEN 1 END) AS count_essay
            FROM cbt_exam_questions eq
            JOIN cbt_questions q ON eq.question_id = q.id
            WHERE eq.exam_id = ?
        ");
        $stmtBobot->execute([...$tipe_objektif_list, $exam_id]);
        $bobotRow = $stmtBobot->fetch(PDO::FETCH_ASSOC);

        $bobot_obj   = (float)($bobotRow['bobot_obj']   ?? 0);
        $bobot_essay = (float)($bobotRow['bobot_essay'] ?? 0);
        $count_essay = (int)($bobotRow['count_essay']   ?? 0);

        // Fallback ke cbt_questions jika cbt_exam_questions belum terisi
        if ($bobot_obj <= 0 && $bobot_essay <= 0) {
            $stmtBobotBank = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN q.tipe IN ($ph_tipe) THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
                    SUM(CASE WHEN q.tipe = 'essay'     THEN q.bobot_skor ELSE 0 END) AS bobot_essay,
                    COUNT(CASE WHEN q.tipe = 'essay' THEN 1 END) AS count_essay
                FROM cbt_questions q
                WHERE q.bank_soal_id = ?
            ");
            $stmtBobotBank->execute([...$tipe_objektif_list, $bank_soal_id]);
            $bobotRowBank = $stmtBobotBank->fetch(PDO::FETCH_ASSOC);

            $bobot_obj   = (float)($bobotRowBank['bobot_obj']   ?? 0);
            $bobot_essay = (float)($bobotRowBank['bobot_essay'] ?? 0);
            $count_essay = (int)($bobotRowBank['count_essay']   ?? 0);
        }
    }

    $has_obj   = $bobot_obj   > 0;
    $has_essay = $bobot_essay > 0;

    // 3. Ambil jawaban siswa

    $stmtAns = $pdo->prepare("SELECT id, question_id, jawaban_simpan, skor_didapat, is_graded FROM cbt_student_answers WHERE participant_id = ?");
    $stmtAns->execute([$participant_id]);
    $jawaban_siswa_raw = $stmtAns->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allowed_soal_ids)) {
        $jawaban_siswa = array_values(array_filter($jawaban_siswa_raw, function($js) use ($allowed_soal_ids) {
            return in_array((int)$js['question_id'], $allowed_soal_ids, true);
        }));
    } else {
        $jawaban_siswa = $jawaban_siswa_raw;
    }

    $total_skor_obj = 0.0;
    $total_skor_essay = 0.0;
    $graded_essay_count = 0;
    $score_updates = [];

    if (!empty($jawaban_siswa)) {
        $q_ids = array_unique(array_filter(array_map('intval', array_column($jawaban_siswa, 'question_id'))));
        $placeholders = implode(',', array_fill(0, count($q_ids), '?'));

        $stmtQuestions = $pdo->prepare("SELECT id, tipe, bobot_skor FROM cbt_questions WHERE id IN ($placeholders)");
        $stmtQuestions->execute(array_values($q_ids));
        $questions_map = [];
        foreach ($stmtQuestions->fetchAll(PDO::FETCH_ASSOC) as $q) {
            $questions_map[(int)$q['id']] = $q;
        }

        $stmtOptions = $pdo->prepare("SELECT * FROM cbt_question_options WHERE question_id IN ($placeholders)");
        $stmtOptions->execute(array_values($q_ids));
        $options_map = [];
        foreach ($stmtOptions->fetchAll(PDO::FETCH_ASSOC) as $opt) {
            $options_map[(int)$opt['question_id']][] = $opt;
        }

        foreach ($jawaban_siswa as $js) {
            $ans_id  = (int)$js['id'];
            $q_id    = (int)$js['question_id'];
            $j_siswa = $js['jawaban_simpan'];
            $soal    = $questions_map[$q_id] ?? null;

            if (!$soal) {
                $score_updates[$ans_id] = 0.0;
                continue;
            }

            $opts        = $options_map[$q_id] ?? [];
            $skor_didapat = 0.0;
            $j_trimmed   = trim((string)$j_siswa);

            // A. Pilihan Ganda & Benar Salah
            if ($soal['tipe'] === 'pg' || $soal['tipe'] === 'benar_salah') {
                foreach ($opts as $opt) {
                    if ((int)$opt['is_correct'] === 1) {
                        $is_match = false;
                        // 1. Cocokkan ID Opsi
                        if ((string)$j_trimmed === (string)$opt['id']) {
                            $is_match = true;
                        }
                        // 2. Cocokkan Label Opsi (A, B, C, D, E)
                        elseif (!empty($opt['label']) && strtoupper($j_trimmed) === strtoupper(trim((string)$opt['label']))) {
                            $is_match = true;
                        }
                        // 3. Khusus Benar Salah: Cocokkan 'B'/'S' atau teks target
                        elseif ($soal['tipe'] === 'benar_salah') {
                            $target_clean = strtoupper(trim(strip_tags((string)($opt['value_target'] ?? ''))));
                            $label_clean  = strtoupper(trim((string)($opt['label'] ?? '')));
                            $ans_clean    = strtoupper($j_trimmed);
                            if ($ans_clean === 'B' && ($label_clean === 'B' || $target_clean === 'BENAR' || $target_clean === 'TRUE')) {
                                $is_match = true;
                            } elseif ($ans_clean === 'S' && ($label_clean === 'S' || $target_clean === 'SALAH' || $target_clean === 'FALSE')) {
                                $is_match = true;
                            }
                        }

                        if ($is_match) {
                            $skor_didapat = (float)$soal['bobot_skor'];
                            break;
                        }
                    }
                }
            }

            // B. Pilihan Ganda Kompleks (Multi-Jawaban / Checkbox)
            elseif ($soal['tipe'] === 'pg_kompleks') {
                $kunci_raw = array_filter($opts, fn($o) => (int)$o['is_correct'] === 1);
                $kunci_ids = array_values(array_unique(array_filter(
                    array_map('intval', array_column($kunci_raw, 'id')),
                    fn($x) => $x > 0
                )));

                // Map label ke ID (e.g. 'A' => 101)
                $label_to_id = [];
                foreach ($opts as $o) {
                    if (!empty($o['label'])) {
                        $label_to_id[strtoupper(trim((string)$o['label']))] = (int)$o['id'];
                    }
                    $label_to_id[(string)$o['id']] = (int)$o['id'];
                }

                $j_decoded = json_decode($j_trimmed, true);
                $j_items = [];

                if (is_array($j_decoded)) {
                    if (array_values($j_decoded) === $j_decoded) {
                        $j_items = $j_decoded;
                    } else {
                        foreach ($j_decoded as $k => $v) {
                            if ($v === true || $v === 1 || $v === '1' || (string)$v === (string)$k) {
                                $j_items[] = $k;
                            } elseif (is_numeric($v)) {
                                $j_items[] = $v;
                            }
                        }
                    }
                } elseif (str_contains($j_trimmed, ',')) {
                    $j_items = explode(',', $j_trimmed);
                } elseif ($j_trimmed !== '') {
                    $j_items = [$j_trimmed];
                }

                $resolved_ids = [];
                foreach ($j_items as $item) {
                    $item_str = trim((string)$item);
                    $item_upper = strtoupper($item_str);
                    if (isset($label_to_id[$item_upper])) {
                        $resolved_ids[] = $label_to_id[$item_upper];
                    } elseif (isset($label_to_id[$item_str])) {
                        $resolved_ids[] = $label_to_id[$item_str];
                    }
                }

                $resolved_ids = array_values(array_unique(array_filter($resolved_ids, fn($x) => $x > 0)));
                sort($kunci_ids);
                sort($resolved_ids);

                $total_kunci      = count($kunci_ids);
                $total_salah_opts = count(array_filter($opts, fn($o) => !(bool)$o['is_correct']));

                $kunci_set      = array_flip($kunci_ids);
                $benar_terpilih = count(array_filter($resolved_ids, fn($id) => isset($kunci_set[$id])));
                $salah_terpilih = count(array_filter($resolved_ids, fn($id) => !isset($kunci_set[$id])));

                $ratio_benar  = ($total_kunci > 0)      ? ($benar_terpilih / $total_kunci)      : 0;
                $ratio_salah  = ($total_salah_opts > 0) ? ($salah_terpilih / $total_salah_opts) : 0;
                $skor_didapat = max(0.0, ($ratio_benar - $ratio_salah) * (float)$soal['bobot_skor']);
            }

            // C. Menjodohkan (Matching) — Sanitized Anti-HTML Mismatch
            elseif ($soal['tipe'] === 'menjodohkan') {
                $kunci_by_id = [];
                $kunci_by_label = [];
                foreach ($opts as $opt) {
                    $val_target = strtolower(trim(strip_tags(html_entity_decode((string)$opt['value_target'], ENT_QUOTES, 'UTF-8'))));
                    $kunci_by_id[(string)$opt['id']] = $val_target;
                    if (!empty($opt['label'])) {
                        $kunci_by_label[strtolower(trim(strip_tags((string)$opt['label'])))] = $val_target;
                    }
                }

                $j_siswa_map = json_decode($j_trimmed, true);
                $jawaban_norm = [];

                if (is_array($j_siswa_map)) {
                    foreach ($j_siswa_map as $k => $v) {
                        $k_clean = strtolower(trim(strip_tags(html_entity_decode((string)$k, ENT_QUOTES, 'UTF-8'))));
                        $v_clean = strtolower(trim(strip_tags(html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8'))));
                        $jawaban_norm[$k_clean] = $v_clean;
                    }
                }

                $total_baris = count($kunci_by_id);
                $benar_baris = 0;
                foreach ($kunci_by_id as $k => $v) {
                    if (isset($jawaban_norm[$k]) && $jawaban_norm[$k] !== '' && $jawaban_norm[$k] === $v) {
                        $benar_baris++;
                    }
                }
                if ($benar_baris === 0 && !empty($kunci_by_label)) {
                    foreach ($kunci_by_label as $k => $v) {
                        if (isset($jawaban_norm[$k]) && $jawaban_norm[$k] !== '' && $jawaban_norm[$k] === $v) {
                            $benar_baris++;
                        }
                    }
                }

                $skor_didapat = ($total_baris > 0)
                    ? ($benar_baris / $total_baris) * (float)$soal['bobot_skor']
                    : 0.0;
            }

            // D. Isian (Jawaban Singkat) — Sanitized & Case-Insensitive Multi-Kunci
            elseif ($soal['tipe'] === 'isian') {
                $j_text = strtolower(trim(strip_tags(html_entity_decode($j_trimmed, ENT_QUOTES, 'UTF-8'))));
                if ($j_text !== '') {
                    foreach ($opts as $opt) {
                        if ((int)$opt['is_correct'] === 1) {
                            $kunci_raw = html_entity_decode((string)$opt['value_target'], ENT_QUOTES, 'UTF-8');
                            $kunci_list = preg_split('/[;|]/', $kunci_raw);
                            foreach ($kunci_list as $k_item) {
                                $kunci_clean = strtolower(trim(strip_tags((string)$k_item)));
                                if ($kunci_clean !== '' && $j_text === $kunci_clean) {
                                    $skor_didapat = (float)$soal['bobot_skor'];
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // E. Essay — Pertahankan nilai guru jika sudah dikoreksi manual
            elseif ($soal['tipe'] === 'essay') {
                $skor_didapat = (float)($js['skor_didapat'] ?? 0);
                $total_skor_essay += $skor_didapat;
                if (!empty($js['is_graded'])) {
                    $graded_essay_count++;
                }
            }

            $score_updates[$ans_id] = $skor_didapat;

            if (in_array($soal['tipe'], $tipe_objektif_list, true)) {
                $total_skor_obj += $skor_didapat;
            }
        }
    }

    // Hitung persentase nilai (skala 0-100)
    $nilai_objektif = ($bobot_obj > 0)
        ? round(($total_skor_obj / $bobot_obj) * 100, 2)
        : 0.0;

    $nilai_esai = ($bobot_essay > 0)
        ? round(($total_skor_essay / $bobot_essay) * 100, 2)
        : 0.0;

    if ($has_obj && $has_essay) {
        $nilai_akhir = round(($nilai_objektif * 0.5) + ($nilai_esai * 0.5), 2);
    } elseif ($has_obj) {
        $nilai_akhir = $nilai_objektif;
    } elseif ($has_essay) {
        $nilai_akhir = $nilai_esai;
    } else {
        $nilai_akhir = 0.0;
    }

    // Tentukan status skor: jika ada essay dan belum semua dinilai guru -> pending
    if ($has_essay && $count_essay > 0 && $graded_essay_count < $count_essay) {
        $skor_status = 'pending';
    } else {
        $skor_status = 'final';
    }

    // Eksekusi Update ke Database dalam Transaction (support nested transaction)
    $is_nested_trans = $pdo->inTransaction();
    if (!$is_nested_trans) {
        $pdo->beginTransaction();
    }
    try {
        if (!empty($score_updates)) {
            $updAns = $pdo->prepare("UPDATE cbt_student_answers SET skor_didapat = ? WHERE id = ?");
            foreach ($score_updates as $ans_id => $skor) {
                $updAns->execute([$skor, $ans_id]);
            }
        }

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
        $stmtFinish->execute([$final_waktu_selesai, $nilai_akhir, $nilai_objektif, $nilai_esai, $skor_status, $participant_id]);

        if (!$is_nested_trans) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if (!$is_nested_trans && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }

    return [
        'success'        => true,
        'participant_id' => $participant_id,
        'nilai_akhir'    => $nilai_akhir,
        'nilai_objektif' => $nilai_objektif,
        'nilai_esai'     => $nilai_esai,
        'skor_status'    => $skor_status,
        'waktu_selesai'  => $final_waktu_selesai
    ];
}

/**
 * Menghitung ulang seluruh nilai peserta dalam 1 jadwal ujian atau 1 bank soal (Batch Recalculate).
 *
 * @param PDO $pdo
 * @param int $exam_id       ID Ujian (cbt_exams.id)
 * @param int $bank_soal_id  ID Bank Soal (cbt_bank_soal.id) jika exam_id tidak spesifik
 * @return array{total_peserta:int, berhasil:int, gagal:int, rata_rata:float}
 */
function hitung_ulang_nilai_ujian(PDO $pdo, int $exam_id = 0, int $bank_soal_id = 0): array {
    $p_ids = [];

    if ($exam_id > 0) {
        // Ambil semua peserta di ujian ini (baik yang finished, working, maupun yang memiliki jawaban tersimpan)
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.id 
            FROM cbt_exam_participants p 
            LEFT JOIN cbt_student_answers sa ON sa.participant_id = p.id
            WHERE p.exam_id = ? AND (p.status IN ('finished', 'working') OR sa.id IS NOT NULL)
        ");
        $stmt->execute([$exam_id]);
        $p_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Fallback: Jika query di atas kosong, ambil seluruh partisipasi di exam_id tersebut
        if (empty($p_ids)) {
            $stmtFallback = $pdo->prepare("SELECT id FROM cbt_exam_participants WHERE exam_id = ?");
            $stmtFallback->execute([$exam_id]);
            $p_ids = $stmtFallback->fetchAll(PDO::FETCH_COLUMN);
        }
    } elseif ($bank_soal_id > 0) {
        // Ambil semua peserta di semua ujian di bawah bank soal ini
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.id 
            FROM cbt_exam_participants p 
            JOIN cbt_exams e ON p.exam_id = e.id
            LEFT JOIN cbt_student_answers sa ON sa.participant_id = p.id
            WHERE e.bank_soal_id = ? AND (p.status IN ('finished', 'working') OR sa.id IS NOT NULL)
        ");
        $stmt->execute([$bank_soal_id]);
        $p_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($p_ids)) {
            $stmtFallback = $pdo->prepare("SELECT p.id FROM cbt_exam_participants p JOIN cbt_exams e ON p.exam_id = e.id WHERE e.bank_soal_id = ?");
            $stmtFallback->execute([$bank_soal_id]);
            $p_ids = $stmtFallback->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    $total   = count($p_ids);
    $berhasil = 0;
    $gagal    = 0;
    $total_skor = 0.0;

    foreach ($p_ids as $pid) {
        $res = hitung_dan_simpan_nilai_peserta($pdo, (int)$pid);
        if (!empty($res['success'])) {
            $berhasil++;
            $total_skor += (float)($res['nilai_akhir'] ?? 0);
        } else {
            $gagal++;
        }
    }

    $avg = $berhasil > 0 ? round($total_skor / $berhasil, 2) : 0.0;

    return [
        'total_peserta' => $total,
        'berhasil'      => $berhasil,
        'gagal'         => $gagal,
        'rata_rata'     => $avg
    ];
}


