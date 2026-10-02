<?php
require_once '../../config/database.php';
require_once '../../includes/helpers.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo '<div class="alert alert-danger m-4">Sesi tidak valid.</div>';
    exit;
}

if (!isset($_GET['id'])) {
    echo '<script>loadView("dashboard");</script>';
    exit;
}

$exam_id    = (int)$_GET['id'];
$student_id = (int)$_SESSION['student_id'];
session_write_close(); // Rilis session lock segera agar request lain tidak terblokir

// 1. Ambil Data Partisipasi & Pengaturan Ujian
$stmt = $pdo->prepare("
    SELECT p.*, e.nama_mapel_ujian, e.durasi_menit, e.selesai_pada as batas_waktu_server,
           e.acak_soal, e.acak_opsi, NOW() as waktu_sekarang_db
    FROM cbt_exam_participants p
    JOIN cbt_exams e ON p.exam_id = e.id
    WHERE p.exam_id = ? AND p.student_id = ?
    AND p.status IN ('working', 'ready')
");
$stmt->execute([$exam_id, $student_id]);
$data = $stmt->fetch();

if (!$data) {
    echo '<script>loadView("dashboard", { msg: "sesi_berakhir" });</script>';
    exit;
}

$waktu      = hitung_sisa_waktu($pdo, (int)$data['id']);
$sisa_detik = $waktu['sisa_detik'];

// 2. Tentukan Daftar Urutan Soal Siswa
$soal_ids_json = $data['soal_ids'] ?? null;
if ($soal_ids_json) {
    $all_question_ids = json_decode($soal_ids_json, true) ?: [];
} else {
    $stmtAllQ = $pdo->prepare("SELECT question_id FROM cbt_exam_questions WHERE exam_id = ? ORDER BY id ASC");
    $stmtAllQ->execute([$exam_id]);
    $all_question_ids = $stmtAllQ->fetchAll(PDO::FETCH_COLUMN);
}

$acak_soal = (int)($data['acak_soal'] ?? 0);
$acak_opsi_global = (int)($data['acak_opsi'] ?? 0);

if ($acak_soal && count($all_question_ids) > 1) {
    $seed_soal = crc32($student_id . '_exam_' . $exam_id);
    seeded_shuffle($all_question_ids, $seed_soal);
}

// 3. High-Concurrency Single-Payload: Ambil Seluruh Data Soal, Opsi, & Jawaban Tersimpan dalam 3 Query Cepat
$questions_map = [];
$nav_items = [];

if (!empty($all_question_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($all_question_ids), '?'));
    
    // Query 1: Data Soal
    $stQ = $pdo->prepare("SELECT id, tipe, konten_soal, media_files, acak_opsi FROM cbt_questions WHERE id IN ($in_placeholders)");
    $stQ->execute($all_question_ids);
    $raw_questions = [];
    foreach ($stQ->fetchAll() as $rq) {
        $raw_questions[$rq['id']] = $rq;
    }

    // Query 2: Data Opsi
    $stOpt = $pdo->prepare("SELECT id, question_id, label, value_target FROM cbt_question_options WHERE question_id IN ($in_placeholders) ORDER BY label ASC");
    $stOpt->execute($all_question_ids);
    $raw_options = [];
    foreach ($stOpt->fetchAll() as $ro) {
        $raw_options[$ro['question_id']][] = $ro;
    }

    // Query 3: Jawaban Tersimpan Siswa
    $stAns = $pdo->prepare("SELECT question_id, jawaban_simpan, is_ragu FROM cbt_student_answers WHERE participant_id = ? AND question_id IN ($in_placeholders)");
    $stAns->execute(array_merge([$data['id']], $all_question_ids));
    $raw_answers = [];
    foreach ($stAns->fetchAll() as $ra) {
        $raw_answers[$ra['question_id']] = $ra;
    }

    // 4. Pre-render HTML Soal ke dalam Payload Klien (0 ms Navigasi di Browser)
    $option_labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    foreach ($all_question_ids as $index => $qid) {
        $no = $index + 1;
        $q  = $raw_questions[$qid] ?? null;
        if (!$q) continue;

        $ans_row        = $raw_answers[$qid] ?? null;
        $jawaban_simpan = $ans_row['jawaban_simpan'] ?? '';
        $is_ragu        = (int)($ans_row['is_ragu'] ?? 0);
        $is_answered    = false;

        $html = "<input type='hidden' id='q_id' value='{$q['id']}'>";
        $html .= "<div class='mb-3'><span class='badge bg-primary px-3 py-2 rounded-pill shadow-sm'>SOAL NOMOR $no</span></div>";

        if (!empty($q['media_files'])) {
            $mf = trim((string)$q['media_files']);
            if ($mf !== '' && !str_contains($q['konten_soal'], $mf)) {
                $html .= "<div class='mb-3 text-center'><img src='assets/uploads/soal/" . htmlspecialchars($mf, ENT_QUOTES, 'UTF-8') . "' class='img-fluid rounded shadow-sm' style='max-height:350px;' alt='Gambar Soal'></div>";
            }
        }
        $html .= "<div class='question-text mb-4'>" . $q['konten_soal'] . "</div>";
        $html .= "<div class='options-container'>";

        $opts = $raw_options[$qid] ?? [];

        switch ($q['tipe']) {
            case 'pg':
                if ($acak_opsi_global || $q['acak_opsi']) {
                    $seed_opsi = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id']);
                    seeded_shuffle($opts, $seed_opsi);
                }
                foreach ($opts as $idx => $o) {
                    $label = $option_labels[$idx] ?? ($idx + 1);
                    $isSelected = ((string)$jawaban_simpan === (string)$o['id']) ? 'selected' : '';
                    $isChecked  = ((string)$jawaban_simpan === (string)$o['id']) ? 'checked' : '';
                    if ($isChecked) $is_answered = true;
                    $html .= "
                    <label class='option-item $isSelected'>
                        <input type='radio' name='jawaban' class='answer-input d-none' value='{$o['id']}' $isChecked>
                        <span class='me-3 fw-bold text-primary'>$label.</span>
                        <span class='text-dark'>{$o['value_target']}</span>
                    </label>";
                }
                break;

            case 'pg_kompleks':
                if ($acak_opsi_global || $q['acak_opsi']) {
                    $seed_opsi = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id']);
                    seeded_shuffle($opts, $seed_opsi);
                }
                $jawaban_user = json_decode($jawaban_simpan, true) ?: [];
                if (!empty($jawaban_user)) $is_answered = true;

                foreach ($opts as $idx => $o) {
                    $label = $option_labels[$idx] ?? ($idx + 1);
                    $checked = in_array((string)$o['id'], array_map('strval', $jawaban_user), true);
                    $isChecked = $checked ? 'checked' : '';
                    $isSelected = $checked ? 'selected' : '';
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
                if (trim((string)$jawaban_simpan) !== '') $is_answered = true;
                $html .= "
                <div class='form-group'>
                    <label class='small fw-bold text-muted mb-2 text-uppercase'>Jawaban Anda:</label>
                    <textarea class='form-control answer-input p-3 shadow-sm' rows='6' placeholder='Ketik jawaban di sini...' style='border-radius:15px; border: 2px solid #eaecf4;'>{$jawaban_simpan}</textarea>
                </div>";
                break;

            case 'benar_salah':
                foreach ($opts as $idx => $o) {
                    $label = $option_labels[$idx] ?? ($idx + 1);
                    $isSelected = ((string)$jawaban_simpan === (string)$o['id']) ? 'selected' : '';
                    $isChecked  = ((string)$jawaban_simpan === (string)$o['id']) ? 'checked' : '';
                    if ($isChecked) $is_answered = true;
                    $html .= "
                    <label class='option-item $isSelected'>
                        <input type='radio' name='jawaban' class='answer-input d-none' value='{$o['id']}' $isChecked>
                        <span class='me-3 fw-bold text-primary'>$label.</span>
                        <span class='text-dark'>{$o['value_target']}</span>
                    </label>";
                }
                break;

            case 'menjodohkan':
                $jawaban_user = json_decode($jawaban_simpan, true) ?: [];
                if (!empty($jawaban_user)) $is_answered = true;

                $rows_display    = $opts;
                $choices_display = $opts;

                $seed_rows    = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id'] . '_rows');
                $seed_choices = crc32($student_id . '_exam_' . $exam_id . '_q_' . $q['id'] . '_choices');
                seeded_shuffle($rows_display,    $seed_rows);
                seeded_shuffle($choices_display, $seed_choices);

                $n = count($rows_display);
                if ($n >= 2) {
                    for ($i = 0; $i < $n; $i++) {
                        if ($choices_display[$i]['value_target'] === $rows_display[$i]['value_target']) {
                            $j = ($i + 1) % $n;
                            [$choices_display[$i], $choices_display[$j]] = [$choices_display[$j], $choices_display[$i]];
                        }
                    }
                }

                $choices_values = array_values(array_map(fn($c) => inject_domain_to_html($c['value_target']), $choices_display));

                $html .= "<div class='matching-header d-none d-md-flex mb-2 px-1'>
                            <div class='col-5 fw-semibold text-muted small'>Pertanyaan / Pernyataan</div>
                            <div class='col-7 fw-semibold text-muted small ps-3'>Pilih Pasangan Jawaban</div>
                          </div>
                          <div class='matching-list'>";

                foreach ($rows_display as $o) {
                    $row_id         = $o['id'];
                    $current_choice = $jawaban_user[$row_id] ?? '';

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
                                                <div class='matching-option px-3 py-2 border-bottom' data-idx='-1' data-val=''>
                                                    <span class='text-muted'>-- Pilih Jawaban --</span>
                                                </div>";

                    foreach ($choices_display as $cidx => $choice) {
                        $is_sel = ($cidx === $selected_idx) ? 'bg-primary-subtle fw-semibold' : '';
                        $choice_val_escaped = htmlspecialchars($choice['value_target'], ENT_QUOTES, 'UTF-8');
                        $html .= "<div class='matching-option px-3 py-2 border-bottom {$is_sel}' data-idx='{$cidx}' data-val='{$choice_val_escaped}'>
                                      {$choice['value_target']}
                                  </div>";
                    }

                    $html .= "              </div>
                                            <input type='hidden' class='matching-input' data-row-id='{$row_id}' value='{$current_val_attr}'>
                                        </div>
                                    </div>
                                </div>
                              </div>";
                }

                $html .= "</div>";
                $html .= "<script>window._matchChoicesData = " . json_encode($choices_values) . ";</script>";
                break;
        }

        $html .= "</div>";

        $questions_map[$no] = [
            'id'          => (int)$qid,
            'no'          => $no,
            'html'        => inject_domain_to_html($html),
            'is_ragu'     => $is_ragu,
            'is_answered' => $is_answered
        ];

        $nav_items[$no] = [
            'no'          => $no,
            'is_ragu'     => $is_ragu,
            'is_answered' => $is_answered
        ];
    }
}

$exam_package_json = json_encode([
    'total_soal' => count($all_question_ids),
    'questions'  => $questions_map,
    'nav_items'  => $nav_items
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>

<nav class="exam-header sticky-top py-2">
    <div class="container-fluid px-3 px-md-4 d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex align-items-center gap-2 exam-title-group">
            <button type="button" onclick="internalRefresh()" class="btn btn-light btn-sm border-0 shadow-sm flex-shrink-0" title="Muat Ulang Soal" aria-label="Muat Ulang">
                <i class="fas fa-sync-alt text-primary"></i>
            </button>
            <h6 class="mb-0 fw-bold text-dark text-truncate"><?= htmlspecialchars($data['nama_mapel_ujian']) ?></h6>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-2 exam-meta-group">
            <div class="timer-pill" id="timerPill">
                <i class="far fa-clock"></i>
                <span class="timer-box" id="timer">00:00:00</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-primary rounded-pill d-lg-none" id="btn-toggle-nav" aria-label="Nomor Soal" title="Nomor Soal">
                    <i class="fas fa-th"></i>
                </button>
                <button class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm d-none" id="btn-finish-exam">SELESAI</button>
            </div>
        </div>
    </div>
</nav>
<div class="nav-drawer-backdrop" id="navDrawerBackdrop"></div>

<!-- Anti-Screenshot & Privacy Screen Layer -->
<style>
    @media print { body { display: none !important; } }
    #privacy-screen { position: fixed; inset: 0; background: #ffffff; z-index: 2147483647; display: none; }
</style>
<div id="privacy-screen"></div>

<div class="exam-page-wrap container-fluid px-3 px-md-4 mt-3 mt-md-4">
    <div class="row exam-layout-row">
        <div class="col-12 col-lg-9 exam-main-col mb-5">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 p-lg-5 exam-question-card">
                <div class="exam-progress mb-3" id="examProgress">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-muted" id="progressText">Soal 1 dari <?= count($all_question_ids) ?></span>
                    </div>
                    <div class="progress exam-progress-bar" style="height:6px;">
                        <div class="progress-bar" id="progressBarFill" role="progressbar" style="width:0%"></div>
                    </div>
                </div>

                <div id="soal-container">
                    <div class="d-flex justify-content-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>

                <hr class="my-3 my-md-4 my-lg-5">

                <div class="exam-action-bar">
                    <button class="btn btn-light border exam-btn-prev" id="btn-prev" aria-label="Soal Sebelumnya" title="Soal Sebelumnya">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button type="button" class="btn exam-btn-ragu" id="btnRagu" aria-pressed="false">
                        <i class="fas fa-flag me-2"></i>RAGU-RAGU
                    </button>
                    <button class="btn btn-primary exam-btn-next" id="btn-next" aria-label="Soal Berikutnya" title="Soal Berikutnya">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-3 exam-nav-col" id="navContainer">
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 sticky-lg-top exam-nav-card">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Nomor Soal</h6>
                <div class="d-flex flex-wrap gap-2 justify-content-start" id="nav-numbers"></div>
                <div class="nav-summary small text-muted text-center mt-3 pt-2 border-top" id="navSummary">0 dari 0 terjawab</div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="examPackageJson">
<?= $exam_package_json ?>
</script>

<script>
(function() {
    var examId        = <?= $exam_id ?>;
    var sisaWaktu     = <?= $sisa_detik ?>;
    var currentNumber = 1;
    var _finishCalled = false;

    // Load Single-Payload Exam Package ke Memori Klien
    var _pkg = JSON.parse(document.getElementById('examPackageJson').textContent || '{}');
    var _questionsMap = _pkg.questions || {};
    var totalSoal     = _pkg.total_soal || Object.keys(_questionsMap).length;

    function showTimer(s) {
        var h = Math.floor(s / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;
        $('#timer').text(
            (h < 10 ? '0' + h : h) + ':' +
            (m < 10 ? '0' + m : m) + ':' +
            (sec < 10 ? '0' + sec : sec)
        );

        var $pill   = $('#timerPill');
        var $finish = $('#btn-finish-exam');
        $pill.removeClass('timer-warning timer-danger');
        if (s <= 60) {
            $pill.addClass('timer-danger');
            $finish.removeClass('btn-outline-danger').addClass('btn-danger');
        } else if (s <= 300) {
            $pill.addClass('timer-warning');
            $finish.removeClass('btn-outline-danger').addClass('btn-danger');
        } else {
            $finish.removeClass('btn-danger').addClass('btn-outline-danger');
        }

        if (s <= 300) {
            $finish.removeClass('d-none');
        } else {
            $finish.addClass('d-none');
        }

        if (totalSoal > 0 && currentNumber === totalSoal) {
            if (s <= 300) {
                $('#btn-next').html('SELESAI UJIAN').addClass('btn-success is-finish').removeClass('btn-primary');
            } else {
                $('#btn-next').html('<i class="fas fa-arrow-right"></i>').addClass('btn-primary').removeClass('btn-success is-finish');
            }
        }
    }

    function updateProgress() {
        if (totalSoal > 0) {
            $('#progressText').text('Soal ' + currentNumber + ' dari ' + totalSoal);
            $('#progressBarFill').css('width', Math.round((currentNumber / totalSoal) * 100) + '%');
        }
    }

    function showBlockedAlert() {
        if (_finishCalled) return;
        _finishCalled = true;
        clearInterval(_timerInterval);
        clearInterval(_resyncInterval);
        Swal.fire({
            title: 'AKUN TERBLOKIR!',
            text: 'Terlalu banyak pelanggaran terdeteksi. Ujian Anda dihentikan oleh sistem.',
            icon: 'error',
            allowOutsideClick: false,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Keluar'
        }).then(function() { loadView('dashboard'); });
    }

    function autoFinishUjian() {
        if (_finishCalled) return;
        _finishCalled = true;
        clearInterval(_timerInterval);
        clearInterval(_resyncInterval);
        $('#timer').text('00:00:00');
        $('#timerPill').addClass('timer-danger');
        Swal.fire({
            title: 'Waktu Habis!',
            text: 'Sistem akan mengirimkan jawaban Anda secara otomatis.',
            icon: 'warning', timer: 3000, showConfirmButton: false, allowOutsideClick: false
        }).then(function() { spaSelesai(examId, 'timeout'); });
    }

    function logSecurityViolation(type) {
        if (_finishCalled) return;
        $.post('ajax_cheat_log.php', { 
            exam_id: examId, 
            type: type,
            waktu: new Date().toISOString()
        }, function(res) {
            if (res && res.status === 'blocked') {
                showBlockedAlert();
            } else {
                Swal.fire({
                    title: 'Keamanan AXON CBT',
                    text: 'Tindakan mencurigakan (' + type + ') terdeteksi dan telah dicatat oleh sistem!',
                    icon: 'warning',
                    confirmButtonColor: '#4e73df'
                });
            }
        }, 'json');
    }

    if (sisaWaktu > 0) {
        showTimer(sisaWaktu);
    } else {
        autoFinishUjian();
    }

    _timerInterval = setInterval(function() {
        sisaWaktu--;
        if (sisaWaktu <= 0) {
            autoFinishUjian();
            return;
        }
        showTimer(sisaWaktu);
    }, 1000);

    var jitter = Math.floor(Math.random() * 30000);
    _resyncInterval = setInterval(function() {
        $.get('ajax_get_waktu.php', { exam_id: examId }, function(res) {
            if (res.status === 'ok') {
                sisaWaktu = res.sisa_detik;
            } else if (res.status === 'blocked') {
                showBlockedAlert();
            } else if (res.status === 'finished') {
                autoFinishUjian();
            }
        }, 'json');
    }, 120000 + jitter);

    function finishExamConfirm() {
        Swal.fire({
            title: 'Selesai Ujian?',
            text: 'Periksa kembali jawaban Anda. Ujian tidak bisa diulang!',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Selesai',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) spaSelesai(examId);
        });
    }

    function refreshNavSummary() {
        var answered = $('#nav-numbers .no-box.answered').length;
        var ragu     = $('#nav-numbers .no-box.ragu').length;
        $('#navSummary').text(answered + ' dari ' + totalSoal + ' terjawab' + (ragu > 0 ? ' · ' + ragu + ' ragu-ragu' : ''));
        updateProgress();
    }

    // Inisialisasi Grid Navigasi Soal Langsung di Klien (0 ms)
    function initNavGrid() {
        var htmlNav = '';
        var navData = _pkg.nav_items || {};
        for (var i = 1; i <= totalSoal; i++) {
            var item = navData[i] || {};
            var classes = ['no-box'];
            if (i === currentNumber) classes.push('active');
            if (item.is_ragu) classes.push('ragu');
            if (item.is_answered) classes.push('answered');
            htmlNav += "<div class='" + classes.join(' ') + "' data-no='" + i + "' onclick='loadSoal(" + i + ")'>" + i + "</div>";
        }
        $('#nav-numbers').html(htmlNav);
        refreshNavSummary();
    }

    var _saveTimers = {};
    function saveJawabanToServer(qId, val, isAnswered) {
        // 1. Instant DOM & In-Memory State Update
        var $targetBox = $('#nav-numbers .no-box[data-no="' + currentNumber + '"]');
        if (isAnswered) {
            $targetBox.addClass('answered');
        } else {
            $targetBox.removeClass('answered');
        }
        if (_pkg.nav_items && _pkg.nav_items[currentNumber]) {
            _pkg.nav_items[currentNumber].is_answered = isAnswered;
        }
        if (_questionsMap[currentNumber]) {
            _questionsMap[currentNumber].is_answered = isAnswered;
            _questionsMap[currentNumber].jawaban = val;
        }
        refreshNavSummary();

        // 2. Buffer LocalStorage untuk redundansi koneksi offline & rehidrasi instan
        try {
            var storageKey = 'cbt_ans_' + examId;
            var stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
            stored[qId] = { jawaban: val, waktu: Date.now() };
            localStorage.setItem(storageKey, JSON.stringify(stored));
        } catch (e) {}

        // 3. Silent Asynchronous Background Autosave (Debounced 300ms)
        if (_saveTimers[qId]) {
            clearTimeout(_saveTimers[qId]);
        }
        var delay = 300 + Math.floor(Math.random() * 150);
        _saveTimers[qId] = setTimeout(function() {
            delete _saveTimers[qId];
            $.post('ajax_save_jawaban.php', {
                exam_id: examId,
                question_id: qId,
                jawaban: val
            }, function(res) {
                if (res && res.status === 'blocked') {
                    showBlockedAlert();
                }
            }, 'json');
        }, delay);
    }

    function flushActiveInputs() {
        var $ta = $('#soal-container textarea.answer-input');
        if ($ta.length) {
            var val = $ta.val();
            var qId = $('#q_id').val();
            if (qId) {
                saveJawabanToServer(qId, val, $.trim(val) !== '');
            }
        }
    }

    // High-Concurrency Single-Payload Renderer: Pindah Soal 100% INSTAN di Klien (0 ms Latensi)
    window.loadSoal = function loadSoal(num) {
        flushActiveInputs();
        currentNumber = num;
        var qData = _questionsMap[num];

        if (!qData) {
            $('#soal-container').html('<div class="alert alert-warning">Soal tidak ditemukan.</div>');
            return;
        }

        // Render HTML langsung dari memori tanpa HTTP round-trip
        $('#soal-container').html(qData.html);
        renderMath(document.getElementById('soal-container'));

        // Sinkronisasi jawaban terkini dari LocalStorage buffer atau In-Memory State
        try {
            var storageKey = 'cbt_ans_' + examId;
            var stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
            var qId = qData.id;
            var ans = undefined;
            if (stored && stored[qId] && stored[qId].jawaban !== undefined) {
                ans = stored[qId].jawaban;
            } else if (qData.jawaban !== undefined) {
                ans = qData.jawaban;
            }

            if (ans !== undefined && ans !== null) {
                // A. Pilihan Ganda Tunggal / Benar Salah / Textarea (Isian/Essay)
                if (typeof ans === 'string' || typeof ans === 'number') {
                    var $radio = $('#soal-container .answer-input[type="radio"][value="' + ans + '"]');
                    if ($radio.length) {
                        $('#soal-container .answer-input[type="radio"]').prop('checked', false).closest('.option-item').removeClass('selected');
                        $radio.prop('checked', true).closest('.option-item').addClass('selected');
                    } else {
                        $('#soal-container textarea.answer-input').val(ans);
                    }
                }
                // B. Pilihan Ganda Kompleks (Multi-Jawaban / Checkbox)
                else if (Array.isArray(ans)) {
                    $('#soal-container .answer-input[type="checkbox"]').each(function() {
                        var valStr = String($(this).val());
                        var checked = ans.some(function(item) { return String(item) === valStr; });
                        $(this).prop('checked', checked).closest('.option-item').toggleClass('selected', checked);
                    });
                }
                // C. Menjodohkan (Matching Pairs - Rehidrasi Pasangan Jawaban)
                else if (typeof ans === 'object' && ans !== null) {
                    $.each(ans, function(rowId, matchVal) {
                        var $select = $('#soal-container .matching-custom-select[data-row-id="' + rowId + '"]');
                        if ($select.length) {
                            $select.find('.matching-input').val(matchVal);
                            var $matchedOpt = null;
                            $select.find('.matching-option').each(function() {
                                if ($(this).data('idx') >= 0) {
                                    var optVal = $(this).attr('data-val') !== undefined ? $(this).attr('data-val') : $(this).text().trim();
                                    if (String(optVal).trim() === String(matchVal).trim()) {
                                        $matchedOpt = $(this);
                                        return false;
                                    }
                                }
                            });

                            if ($matchedOpt && $matchedOpt.length) {
                                $select.find('.selected-content').html($matchedOpt.html());
                                $select.find('.matching-option').removeClass('bg-primary-subtle fw-semibold');
                                $matchedOpt.addClass('bg-primary-subtle fw-semibold');
                            } else if (matchVal !== '') {
                                $select.find('.selected-content').html(matchVal);
                            } else {
                                $select.find('.selected-content').html('<span class="text-muted">-- Pilih Jawaban --</span>');
                                $select.find('.matching-option').removeClass('bg-primary-subtle fw-semibold');
                            }
                            renderMath($select.find('.selected-content')[0]);
                        }
                    });
                }
            }
        } catch(e) {}

        // Update tombol dan indikator aktif
        $('#nav-numbers .no-box').removeClass('active');
        $('#nav-numbers .no-box[data-no="' + num + '"]').addClass('active');
        updateProgress();

        $('#btn-prev').prop('disabled', num === 1);
        $('#btn-next').prop('disabled', false);
        if (num === totalSoal && sisaWaktu <= 300) {
            $('#btn-next').html('SELESAI UJIAN').addClass('btn-success is-finish').removeClass('btn-primary');
        } else {
            $('#btn-next').html('<i class="fas fa-arrow-right"></i>').addClass('btn-primary').removeClass('btn-success is-finish');
        }

        var isRagu = qData.is_ragu || ($('#nav-numbers .no-box[data-no="' + num + '"]').hasClass('ragu') ? 1 : 0);
        $('#btnRagu').toggleClass('is-active', isRagu == 1).attr('aria-pressed', isRagu == 1 ? 'true' : 'false');
    };

    // Event Handler Input Jawaban (Radio & Checkbox)
    $(document).off('change.ujian').on('change.ujian', '.answer-input', function() {
        var input = $(this);
        if (input.is('textarea')) return; // Ditangani oleh debounced input.ujian-textarea
        var val   = input.val();
        var hasAnswer = false;
        if (input.attr('type') === 'checkbox') {
            val = $('#soal-container .answer-input[type="checkbox"]:checked').map(function() { return $(this).val(); }).get();
            hasAnswer = (val.length > 0);
            input.closest('.option-item').toggleClass('selected', input.is(':checked'));
        } else if (input.attr('type') === 'radio') {
            hasAnswer = true;
            $('#soal-container .option-item').removeClass('selected');
            input.closest('.option-item').addClass('selected');
        } else {
            hasAnswer = ($.trim(val) !== '');
        }
        saveJawabanToServer($('#q_id').val(), val, hasAnswer);
    });

    // Event Handler Input Textarea (Isian Singkat & Essay - Debounced Autosave)
    var _textareaTimer = null;
    $(document).off('input.ujian-textarea').on('input.ujian-textarea', 'textarea.answer-input', function() {
        var $ta = $(this);
        var val = $ta.val();
        var qId = $('#q_id').val();
        var hasAnswer = ($.trim(val) !== '');

        if (_textareaTimer) clearTimeout(_textareaTimer);
        _textareaTimer = setTimeout(function() {
            saveJawabanToServer(qId, val, hasAnswer);
        }, 400);
    });

    // Toggle dropdown menjodohkan
    $(document).off('click.ujian-match-toggle').on('click.ujian-match-toggle', '.matching-selected-display', function(e) {
        e.stopPropagation();
        var $wrapper = $(this).closest('.matching-custom-select');
        var $dd = $wrapper.find('.matching-options-dropdown');
        $('.matching-options-dropdown').not($dd).hide();
        if ($dd.is(':visible')) {
            $dd.hide();
        } else {
            $dd.css({ top: $(this).outerHeight() + 'px', left: '0', width: $(this).outerWidth() + 'px' }).show();
        }
    });

    // Pilih opsi menjodohkan
    $(document).off('click.ujian-match-pick').on('click.ujian-match-pick', '.matching-option', function(e) {
        e.stopPropagation();
        var $opt = $(this);
        var $wrapper = $opt.closest('.matching-custom-select');
        var idx = parseInt($opt.data('idx'));
        var val = ($opt.attr('data-val') !== undefined) ? $opt.attr('data-val') : (idx >= 0 ? $opt.text().trim() : '');
        var displayHtml = (idx >= 0)
                    ? $opt.html()
                    : '<span class="text-muted">-- Pilih Jawaban --</span>';

        $wrapper.find('.selected-content').html(displayHtml);
        $wrapper.find('.matching-input').val(val);
        $wrapper.find('.matching-options-dropdown').hide();
        $wrapper.find('.matching-option').removeClass('bg-primary-subtle fw-semibold');
        if (idx >= 0) $opt.addClass('bg-primary-subtle fw-semibold');

        renderMath($wrapper.find('.selected-content')[0]);

        var mapping = {};
        var hasAnswer = false;
        $('#soal-container .matching-input').each(function() {
            var rowId = $(this).data('row-id');
            var v     = $(this).val();
            if (v !== '' && v !== null && v !== undefined) {
                mapping[rowId] = v;
                hasAnswer = true;
            }
        });
        saveJawabanToServer($('#q_id').val(), mapping, hasAnswer);
    });

    $(document).off('click.ujian-match-outside').on('click.ujian-match-outside', function() {
        $('.matching-options-dropdown').hide();
    });

    $(document).off('click.ujian-ragu').on('click.ujian-ragu', '#btnRagu', function() {
        var $btn = $(this);
        var newState = !$btn.hasClass('is-active');
        $btn.toggleClass('is-active', newState).attr('aria-pressed', newState ? 'true' : 'false');
        
        var $targetBox = $('#nav-numbers .no-box[data-no="' + currentNumber + '"]');
        $targetBox.toggleClass('ragu', newState);
        if (_questionsMap[currentNumber]) {
            _questionsMap[currentNumber].is_ragu = newState ? 1 : 0;
        }
        if (_pkg.nav_items && _pkg.nav_items[currentNumber]) {
            _pkg.nav_items[currentNumber].is_ragu = newState ? 1 : 0;
        }
        refreshNavSummary();

        $.post('ajax_toggle_ragu.php', {
            exam_id: examId, question_id: $('#q_id').val(), ragu: newState ? 1 : 0
        }, function(res) {
            if (res && res.status === 'blocked') {
                showBlockedAlert();
            }
        }, 'json');
    });

    $('#btn-next').off('click.ujian').on('click.ujian', function() {
        if (currentNumber < totalSoal) {
            loadSoal(currentNumber + 1);
        } else if (sisaWaktu <= 300) {
            finishExamConfirm();
        } else {
            Swal.fire({
                title: 'Belum Bisa Diselesaikan',
                text: 'Tombol Selesai aktif 5 menit sebelum waktu ujian habis',
                icon: 'info',
                timer: 2500,
                showConfirmButton: false
            });
        }
    });

    $('#btn-prev').off('click.ujian').on('click.ujian', function() {
        if (currentNumber > 1) loadSoal(currentNumber - 1);
    });

    $('#btn-toggle-nav').off('click.ujian').on('click.ujian', function() {
        $('#navContainer').addClass('show');
        $('#navDrawerBackdrop').addClass('show');
    });

    function closeNavDrawer() {
        $('#navContainer').removeClass('show');
        $('#navDrawerBackdrop').removeClass('show');
    }

    $('#navDrawerBackdrop').off('click.ujian').on('click.ujian', closeNavDrawer);

    $(document).off('click.ujian-navclose').on('click.ujian-navclose', '.no-box', function() {
        if (window.innerWidth < 992) closeNavDrawer();
    });

    $('#btn-finish-exam').off('click.ujian').on('click.ujian', function() {
        finishExamConfirm();
    });

    // Keamanan: Touch & Visibility Listeners
    $(document).on('touchstart.security', function(e) {
        if (e.originalEvent.touches.length >= 3) {
            document.body.style.opacity = "0";
            logSecurityViolation("Screenshot");
        }
    });

    $(document).on('touchend.security', function() {
        setTimeout(function() {
            document.body.style.opacity = "1";
        }, 1500);
    });

    window._onBlurUjian = function() {
        document.body.style.opacity = "0";
        $('#privacy-screen').show();
        
        window._blurCheatTimeout = setTimeout(function() {
            logSecurityViolation("Pindah Aplikasi/Blur");
        }, 300);
    };

    window._onFocusUjian = function() {
        clearTimeout(window._blurCheatTimeout);
        document.body.style.opacity = "1";
        $('#privacy-screen').hide();
    };

    window.addEventListener('blur', window._onBlurUjian);
    window.addEventListener('focus', window._onFocusUjian);

    $(document).on('copy.security cut.security paste.security', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Aksi Dilarang',
            text: 'Fungsi Copy, Cut, dan Paste dinonaktifkan demi keamanan ujian.',
            icon: 'error',
            timer: 2000,
            showConfirmButton: false
        });
        return false;
    });

    $(window).on('keyup.security', function(e) {
        if (e.key === 'PrintScreen' || (e.ctrlKey && e.key === 'p')) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(""); 
            }
            $('#privacy-screen').show().fadeOut(1000);
            logSecurityViolation("Shortcut Screenshot/Print");
            return false;
        }
    });

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            $('#privacy-screen').show();
            document.body.style.opacity = "0";
        } else {
            $('#privacy-screen').hide();
            document.body.style.opacity = "1";
        }
    });

    // Inisialisasi Navigasi & Muat Soal #1 secara Instan (0 ms)
    initNavGrid();
    loadSoal(1);
})();
</script>
