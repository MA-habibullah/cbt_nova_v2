<?php
// Mulai buffering untuk memastikan hanya JSON yang dikirim
ob_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
header('Content-Type: application/json');

// Set zona waktu agar sinkron dengan database
date_default_timezone_set('Asia/Jakarta');

// Proteksi: Pastikan hanya siswa yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['html' => 'Unauthorized']);
    exit;
}

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$no = isset($_GET['no']) ? (int)$_GET['no'] : 1;
if ($no < 1) $no = 1;
$student_id = (int)$_SESSION['student_id'];
session_write_close();

try {
    // 1. Ambil Data Partisipasi & Cek Status + soal_ids (untuk distribusi per-siswa)
    $stmtPart = $pdo->prepare("SELECT id, status, soal_ids FROM cbt_exam_participants WHERE exam_id = ? AND student_id = ?");
    $stmtPart->execute([$exam_id, $student_id]);
    $participant = $stmtPart->fetch();

    if (!$participant) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['html' => 'Sesi ujian tidak ditemukan.']);
        exit;
    }

    // --- LOGIKA REKOMENDASI: CEK BLOCKED ---
    if ($participant['status'] === 'blocked') {
        $stmtCheat = $pdo->prepare("SELECT COUNT(*) FROM cbt_cheat_logs WHERE exam_id = ? AND student_id = ?");
        $stmtCheat->execute([$exam_id, $student_id]);
        $jumlah_pelanggaran = $stmtCheat->fetchColumn();

        $html_blocked = "
        <div class='text-center py-5'>
            <i class='fas fa-user-lock fa-5x text-danger mb-4 opacity-50'></i>
            <h3 class='fw-bold text-danger'>AKUN ANDA TERKUNCI</h3>
            <div class='alert alert-danger d-inline-block px-4 mt-3 rounded-4 shadow-sm'>
                <p class='mb-1'>Anda telah melakukan pelanggaran sebanyak <b>$jumlah_pelanggaran kali</b>.</p>
                <p class='mb-0 fw-bold'>Sistem memblokir akses Anda secara otomatis.</p>
            </div>
            <p class='text-muted mt-3'>Silakan hubungi <b>Pengawas Kelas</b> atau <b>Operator</b> untuk membuka blokir.</p>

            <a href='proses_selesai_ujian.php?id=$exam_id&reason=blocked' class='btn btn-danger fw-bold rounded-pill mt-2 px-4 shadow-sm'>
                <i class='fas fa-check-circle me-2'></i> SELESAI
            </a>
        </div>";

        if (ob_get_length()) ob_clean();
        echo json_encode(['html' => $html_blocked, 'is_blocked' => true]);
        exit;
    }
    // --- END LOGIKA BLOCKED ---

    $participant_id = $participant['id'];

    // 2. Ambil Pengaturan Acak dari Ujian
    $stmtExam = $pdo->prepare("SELECT acak_soal, acak_opsi FROM cbt_exams WHERE id = ?");
    $stmtExam->execute([$exam_id]);
    $exam = $stmtExam->fetch();
    $acak_soal = (int)($exam['acak_soal'] ?? 0);
    $acak_opsi_global = (int)($exam['acak_opsi'] ?? 0);

    // 3. Ambil Semua ID Soal dalam Ujian (urutan dasar)
    // Jika soal_ids terisi (distribusi per-siswa aktif), gunakan itu; jika tidak, ambil dari cbt_exam_questions
    $soal_ids_json = $participant['soal_ids'] ?? null;
    if ($soal_ids_json) {
        $all_question_ids = json_decode($soal_ids_json, true) ?: [];
    } else {
        $stmtAllQ = $pdo->prepare("SELECT question_id FROM cbt_exam_questions WHERE exam_id = ? ORDER BY id ASC");
        $stmtAllQ->execute([$exam_id]);
        $all_question_ids = $stmtAllQ->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4. Acak Urutan Soal jika Diaktifkan (deterministik per siswa per ujian)
    if ($acak_soal && count($all_question_ids) > 1) {
        $seed_soal = crc32($student_id . '_exam_' . $exam_id);
        seeded_shuffle($all_question_ids, $seed_soal);
    }

    // 5. Tentukan ID Soal yang Diminta Berdasarkan Nomor
    if (!isset($all_question_ids[$no - 1])) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['html' => 'Soal tidak ditemukan.']);
        exit;
    }
    $target_question_id = (int)$all_question_ids[$no - 1];

    // 6. Ambil Detail Soal + Status Jawaban Siswa
    $stmtQ = $pdo->prepare("
        SELECT q.*, sa.jawaban_simpan, sa.is_ragu
        FROM cbt_questions q
        LEFT JOIN cbt_student_answers sa ON q.id = sa.question_id AND sa.participant_id = ?
        WHERE q.id = ?
    ");
    $stmtQ->execute([$participant_id, $target_question_id]);
    $q = $stmtQ->fetch();

    if (!$q) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['html' => 'Soal tidak ditemukan.']);
        exit;
    }

    // Mulai Bangun HTML
    $html = "";
    $html .= "<input type='hidden' id='q_id' value='{$q['id']}'>";
    $html .= "<div class='mb-3'><span class='badge bg-primary px-3 py-2 rounded-pill shadow-sm'>SOAL NOMOR $no</span></div>";

    // Render Konten Soal (Mendukung Gambar/HTML)
    $html .= "<div class='question-text mb-4'>" . $q['konten_soal'] . "</div>";

    $html .= "<div class='options-container'>";

    // Label urut untuk opsi jawaban
    $option_labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    // 7. Render Jawaban Berdasarkan Tipe (Strict Column Projection: Hindari pengambilan kolom is_correct)
    switch ($q['tipe']) {
        case 'pg': // Pilihan Ganda
            $opts = $pdo->prepare("SELECT id, question_id, label, value_target FROM cbt_question_options WHERE question_id = ? ORDER BY label ASC");
            $opts->execute([$q['id']]);
            $options = $opts->fetchAll();

            // Acak opsi jika diaktifkan (deterministik per siswa per soal)
            if ($acak_opsi_global || $q['acak_opsi']) {
                $seed_opsi = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id']);
                seeded_shuffle($options, $seed_opsi);
            }

            foreach ($options as $idx => $o) {
                $label = $option_labels[$idx] ?? ($idx + 1);
                $isSelected = ($q['jawaban_simpan'] == $o['id']) ? 'selected' : '';
                $isChecked = ($q['jawaban_simpan'] == $o['id']) ? 'checked' : '';
                $html .= "
                <label class='option-item $isSelected'>
                    <input type='radio' name='jawaban' class='answer-input d-none' value='{$o['id']}' $isChecked>
                    <span class='me-3 fw-bold text-primary'>$label.</span>
                    <span class='text-dark'>{$o['value_target']}</span>
                </label>";
            }
            break;

        case 'pg_kompleks': // Pilihan Ganda Kompleks
            $opts = $pdo->prepare("SELECT id, question_id, label, value_target FROM cbt_question_options WHERE question_id = ? ORDER BY label ASC");
            $opts->execute([$q['id']]);
            $options = $opts->fetchAll();
            $jawaban_user = json_decode($q['jawaban_simpan'], true) ?: [];

            // Acak opsi jika diaktifkan
            if ($acak_opsi_global || $q['acak_opsi']) {
                $seed_opsi = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id']);
                seeded_shuffle($options, $seed_opsi);
            }

            foreach ($options as $idx => $o) {
                $label = $option_labels[$idx] ?? ($idx + 1);
                $isChecked = in_array((string)$o['id'], array_map('strval', $jawaban_user), true) ? 'checked' : '';
                $isSelected = in_array((string)$o['id'], array_map('strval', $jawaban_user), true) ? 'selected' : '';
                $html .= "
                <label class='option-item $isSelected'>
                    <input type='checkbox' name='jawaban[]' class='answer-input d-none' value='{$o['id']}' $isChecked>
                    <span class='me-3 fw-bold text-primary'>$label.</span>
                    <span class='text-dark'>{$o['value_target']}</span>
                </label>";
            }
            break;

        case 'isian':
        case 'essay':
            $html .= "
            <div class='form-group'>
                <label class='small fw-bold text-muted mb-2 text-uppercase'>Jawaban Anda:</label>
                <textarea class='form-control answer-input p-3 shadow-sm' rows='6' placeholder='Ketik jawaban di sini...' style='border-radius:15px; border: 2px solid #eaecf4;'>{$q['jawaban_simpan']}</textarea>
            </div>";
            break;

        case 'benar_salah':
            // Benar/Salah tidak diacak karena hanya 2 opsi tetap
            $opts = $pdo->prepare("SELECT id, question_id, label, value_target FROM cbt_question_options WHERE question_id = ? ORDER BY label ASC");
            $opts->execute([$q['id']]);
            foreach ($opts->fetchAll() as $idx => $o) {
                $label = $option_labels[$idx] ?? ($idx + 1);
                $isSelected = ($q['jawaban_simpan'] == $o['id']) ? 'selected' : '';
                $isChecked = ($q['jawaban_simpan'] == $o['id']) ? 'checked' : '';
                $html .= "
                <label class='option-item $isSelected'>
                    <input type='radio' name='jawaban' class='answer-input d-none' value='{$o['id']}' $isChecked>
                    <span class='me-3 fw-bold text-primary'>$label.</span>
                    <span class='text-dark'>{$o['value_target']}</span>
                </label>";
            }
            break;

        case 'menjodohkan':
            $opts = $pdo->prepare("SELECT id, question_id, label, value_target FROM cbt_question_options WHERE question_id = ? ORDER BY id ASC");
            $opts->execute([$q['id']]);
            $all_options  = $opts->fetchAll();
            $jawaban_user = json_decode($q['jawaban_simpan'], true) ?: [];

            $rows_display    = $all_options;
            $choices_display = $all_options;

            // Menjodohkan selalu diacak deterministik per siswa
            $seed_rows    = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id'] . '_rows');
            $seed_choices = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id'] . '_choices');
            seeded_shuffle($rows_display,    $seed_rows);
            seeded_shuffle($choices_display, $seed_choices);

            // Jaminan derangement: jawaban di posisi i tidak boleh menjadi jawaban benar
            // untuk pernyataan di posisi i yang sama (hindari pasangan lurus yang mudah ditebak)
            $n = count($rows_display);
            if ($n >= 2) {
                for ($i = 0; $i < $n; $i++) {
                    if ($choices_display[$i]['value_target'] === $rows_display[$i]['value_target']) {
                        // Tukar dengan posisi berikutnya (circular)
                        $j = ($i + 1) % $n;
                        [$choices_display[$i], $choices_display[$j]] = [$choices_display[$j], $choices_display[$i]];
                    }
                }
            }

            // Siapkan array nilai pilihan untuk diakses via JS (menghindari HTML di data-attribute)
            $choices_values = array_values(array_map(fn($c) => inject_domain_to_html($c['value_target']), $choices_display));

            $html .= "<div class='matching-header d-none d-md-flex mb-2 px-1'>
                        <div class='col-5 fw-semibold text-muted small'>Pertanyaan / Pernyataan</div>
                        <div class='col-7 fw-semibold text-muted small ps-3'>Pilih Pasangan Jawaban</div>
                      </div>
                      <div class='matching-list'>";

            foreach ($rows_display as $o) {
                $row_id         = $o['id'];
                $current_choice = $jawaban_user[$row_id] ?? '';

                // Cari index pilihan yang sudah dipilih
                $selected_idx = -1;
                foreach ($choices_display as $cidx => $ch) {
                    if ($ch['value_target'] === $current_choice) {
                        $selected_idx = $cidx;
                        break;
                    }
                }

                $selected_html = '<span class="text-muted">-- Pilih Jawaban --</span>';
                if ($selected_idx >= 0) {
                    $selected_html = $choices_display[$selected_idx]['value_target'];
                }

                $current_val_attr = htmlspecialchars($current_choice, ENT_QUOTES, 'UTF-8');

                $html .= "<div class='matching-row border rounded-3 mb-3 p-3 bg-white shadow-sm'>
                            <div class='row align-items-center g-2 g-md-3'>
                                <div class='col-12 col-md-5 matching-question-text'>
                                    <span class='d-inline d-md-none badge bg-light text-secondary border mb-2' style='font-size:0.7rem;font-weight:600;'>PERNYATAAN</span>
                                    <div>{$o['label']}</div>
                                </div>
                                <div class='col-12 col-md-7'>
                                    <span class='d-inline d-md-none badge bg-light text-secondary border mb-2' style='font-size:0.7rem;font-weight:600;'>PILIH JAWABAN</span>
                                    <div class='matching-custom-select' data-row-id='{$row_id}'>
                                        <div class='matching-selected-display border rounded-3 px-3 py-2 d-flex justify-content-between align-items-center'
                                             style='cursor:pointer; min-height:46px; background:#f8f9fc;'>
                                            <div class='selected-content flex-grow-1 me-2'>{$selected_html}</div>
                                            <i class='fas fa-chevron-down flex-shrink-0 text-muted' style='font-size:0.8rem;'></i>
                                        </div>
                                        <div class='matching-options-dropdown border rounded-3 shadow-sm bg-white'
                                             style='display:none; position:absolute; z-index:9999; max-height:240px; overflow-y:auto; min-width:200px;'>
                                            <div class='matching-option px-3 py-2 border-bottom' data-idx='-1'>
                                                <span class='text-muted'>-- Pilih Jawaban --</span>
                                            </div>";

                foreach ($choices_display as $cidx => $choice) {
                    $is_sel = ($cidx === $selected_idx) ? 'bg-primary-subtle fw-semibold' : '';
                    $html .= "<div class='matching-option px-3 py-2 border-bottom {$is_sel}' data-idx='{$cidx}'>
                                  {$choice['value_target']}
                              </div>";
                }

                $html .= "          </div>
                                        <input type='hidden' class='matching-input' data-row-id='{$row_id}' value='{$current_val_attr}'>
                                    </div>
                                </div>
                            </div>
                          </div>";
            }

            $html .= "</div>";

            // Injek array nilai pilihan ke JS agar click handler bisa mengambil nilai asli (termasuk HTML)
            $html .= "<script>window._matchChoicesData = " . json_encode($choices_values) . ";</script>";
            break;
    }

    $html .= "</div>"; // End options-container

    // Bersihkan buffer dan kirim JSON
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'html' => inject_domain_to_html($html),
        'is_ragu' => $q['is_ragu']
    ]);

} catch (PDOException $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['html' => 'Error: ' . $e->getMessage()]);
}