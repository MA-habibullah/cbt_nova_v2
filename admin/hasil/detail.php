<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'proktor'])) {
    header("Location: ../../index.php");
    exit;
}

// Tangkap Parameter
$p_id = isset($_GET['p_id']) ? (int)$_GET['p_id'] : 0; 
$id_bank = isset($_GET['id']) ? (int)$_GET['id'] : 0; 

// 1. Ambil Data Peserta, Siswa, dan Ujian
$query_p = "SELECT p.*, s.nama_lengkap, s.nisn, k.nama_kelas, e.nama_mapel_ujian, e.durasi_menit
            FROM cbt_exam_participants p
            JOIN cbt_students s ON p.student_id = s.id
            JOIN cbt_exams e ON p.exam_id = e.id
            LEFT JOIN cbt_classes k ON s.class_id = k.id
            WHERE p.id = ?";
$stmt_p = $pdo->prepare($query_p);
$stmt_p->execute([$p_id]);
$data = $stmt_p->fetch();
$skor_status = $data['skor_status'] ?? 'final';

if (!$data) {
    die("Data peserta tidak ditemukan.");
}

// Tentukan daftar ID soal yang resmi dibagikan kepada siswa (soal_ids)
$soal_ids_json = $data['soal_ids'] ?? null;
$allowed_soal_ids = [];
if (!empty($soal_ids_json)) {
    $decoded = json_decode($soal_ids_json, true);
    if (is_array($decoded) && !empty($decoded)) {
        $allowed_soal_ids = array_values(array_filter(array_map('intval', $decoded), fn($id) => $id > 0));
    }
}

// Ambil butir soal ujian siswa beserta jawabannya (LEFT JOIN agar soal tak terjawab ikut tampil)
if (!empty($allowed_soal_ids)) {
    $ph_soal = implode(',', array_fill(0, count($allowed_soal_ids), '?'));
    $query_ans = "SELECT q.id AS question_id, q.konten_soal, q.tipe, q.bobot_skor AS bobot_asli,
                         a.id AS id, a.jawaban_simpan, COALESCE(a.skor_didapat, 0) AS skor_didapat,
                         COALESCE(a.is_graded, 0) AS is_graded
                  FROM cbt_questions q
                  LEFT JOIN cbt_student_answers a ON a.question_id = q.id AND a.participant_id = ?
                  WHERE q.id IN ($ph_soal)
                  ORDER BY FIELD(q.id, $ph_soal)";
    $stmt_ans = $pdo->prepare($query_ans);
    $params = array_merge([$p_id], $allowed_soal_ids, $allowed_soal_ids);
    $stmt_ans->execute($params);
    $answers = $stmt_ans->fetchAll();
} else {
    $query_ans = "SELECT q.id AS question_id, q.konten_soal, q.tipe, q.bobot_skor AS bobot_asli,
                         a.id AS id, a.jawaban_simpan, COALESCE(a.skor_didapat, 0) AS skor_didapat,
                         COALESCE(a.is_graded, 0) AS is_graded
                  FROM cbt_exam_participants p
                  JOIN cbt_exam_questions eq ON eq.exam_id = p.exam_id
                  JOIN cbt_questions q ON eq.question_id = q.id
                  LEFT JOIN cbt_student_answers a ON a.question_id = q.id AND a.participant_id = p.id
                  WHERE p.id = ?
                  ORDER BY eq.id ASC";
    $stmt_ans = $pdo->prepare($query_ans);
    $stmt_ans->execute([$p_id]);
    $answers = $stmt_ans->fetchAll();
}

$total_soal     = count($answers);
$cnt_benar      = 0; $cnt_salah = 0; $cnt_kosong = 0;
$skor_objektif  = 0.0;
$skor_essay_tot = 0.0;
$bobot_objektif = 0.0;
$bobot_essay    = 0.0;
$tipe_objektif  = ['pg', 'pg_kompleks', 'benar_salah', 'menjodohkan', 'isian'];

foreach ($answers as $_a) {
    $_j = $_a['jawaban_simpan'];
    if ($_j === null || $_j === '' || $_j === '[]' || $_j === '{}') {
        $cnt_kosong++;
    } elseif ($_a['tipe'] === 'essay') {
        // essay: tidak masuk benar/salah, ditampilkan terpisah
    } elseif ((float)$_a['skor_didapat'] > 0) {
        $cnt_benar++;
    } else {
        $cnt_salah++;
    }

    if (in_array($_a['tipe'], $tipe_objektif)) {
        $skor_objektif  += (float)$_a['skor_didapat'];
        $bobot_objektif += (float)$_a['bobot_asli'];
    } elseif ($_a['tipe'] === 'essay') {
        $skor_essay_tot += (float)$_a['skor_didapat'];
        $bobot_essay    += (float)$_a['bobot_asli'];
    }
}

// Hitung nilai per kategori (skala 0-100)
$nilai_obj_display  = ($bobot_objektif > 0)
    ? round($skor_objektif  / $bobot_objektif * 100, 2)
    : 0.0;
$nilai_esai_display = ($bobot_essay > 0)
    ? round($skor_essay_tot / $bobot_essay    * 100, 2)
    : 0.0;

$has_obj_d  = $bobot_objektif > 0;
$has_esai_d = $bobot_essay    > 0;

// Formula 50:50 dengan edge case
if ($has_obj_d && $has_esai_d) {
    $nilai_akhir = round(($nilai_obj_display * 0.5) + ($nilai_esai_display * 0.5), 2);
} elseif ($has_obj_d) {
    $nilai_akhir = $nilai_obj_display;
} elseif ($has_esai_d) {
    $nilai_akhir = $nilai_esai_display;
} else {
    $nilai_akhir = 0.0;
}

// Pre-fetch semua opsi untuk semua soal sekaligus (eliminasi N+1 query dalam loop)
$options_by_question = []; // [question_id][] = option row
$options_by_id       = []; // [option_id]     = option row

if (!empty($answers)) {
    $q_ids        = array_column($answers, 'question_id');
    $placeholders = implode(',', array_fill(0, count($q_ids), '?'));

    $stmtOpts = $pdo->prepare(
        "SELECT id, question_id, label, value_target, is_correct
         FROM cbt_question_options
         WHERE question_id IN ($placeholders)
         ORDER BY question_id, label ASC"
    );
    $stmtOpts->execute($q_ids);

    foreach ($stmtOpts->fetchAll() as $opt) {
        $options_by_question[(int)$opt['question_id']][] = $opt;
        $options_by_id[(int)$opt['id']]                  = $opt;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php include '../../includes/header.php'; ?>
    <style>
        .card-soal        { border-left: 5px solid #dee2e6; transition: all 0.2s; }
        .card-soal.benar  { border-left-color: #198754; }
        .card-soal.salah  { border-left-color: #dc3545; }
        .card-soal.kosong { border-left-color: #ffc107; }
        .card-soal.essay  { border-left-color: #6c757d; }
        .jawaban-box { background-color: #ffffff; border: 1px solid #e9ecef; padding: 15px; border-radius: 8px; }
        .kunci-box        { background-color: #e9ecef; border-left: 3px solid #198754; padding: 10px; margin-top: 10px; font-size: 0.9rem; }
        .kunci-box.manual { border-left-color: #6c757d; }
        @media print {
            #sidebar, .navbar, .btn-back, .btn-print, .no-print, .koreksi-btn { display: none !important; }
            #wrapper { display: block !important; }
            #content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .sticky-top { position: static !important; }
            .card-soal { border: 1px solid #ddd !important; break-inside: avoid; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content" class="w-100">
        <nav class="navbar navbar-expand bg-white px-4 py-3 sticky-top shadow-sm">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <a href="index.php?id=<?= esc($id_bank) ?>" class="btn btn-light border me-3 btn-back">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="mb-0 fw-bold text-primary">Lembar Jawaban Siswa</h5>
                        <small class="text-muted"><?= htmlspecialchars($data['nama_mapel_ujian']) ?></small>
                    </div>
                </div>
                <button type="button" id="btnCetak" class="btn btn-dark shadow-sm btn-print no-print">
                    <i class="fas fa-print me-2"></i> Cetak Hasil
                </button>
            </div>
        </nav>

        <div class="container-fluid px-4 pt-4 pb-5">
            <div class="row">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4 sticky-top" style="top: 100px;">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                    <i class="fas fa-user-graduate fa-2x"></i>
                                </div>
                                <h5 class="fw-bold mb-0"><?= htmlspecialchars($data['nama_lengkap']) ?></h5>
                                <p class="text-muted small"><?= $data['nisn'] ?> | <?= $data['nama_kelas'] ?></p>
                            </div>
                            <div class="text-center p-3 bg-primary-subtle rounded border border-primary-subtle">
                                <small class="text-primary fw-bold d-block mb-1">NILAI AKHIR</small>
                                <?php if ($data['skor_akhir'] === null): ?>
                                    <h1 class="display-5 fw-bold text-primary mb-0">
                                        <span class="text-muted fs-5">Belum Mengerjakan</span>
                                    </h1>
                                <?php else: ?>
                                    <h1 class="display-5 fw-bold text-primary mb-0">
                                        <?= number_format($nilai_akhir, 2) ?>
                                    </h1>
                                    <small class="text-muted">Nilai Akhir (Skala 100)</small>

                                    <?php if ($has_obj_d || $has_esai_d): ?>
                                    <div class="row mt-3 g-2">
                                        <?php if ($has_obj_d): ?>
                                        <div class="col-6">
                                            <div class="bg-light rounded p-2 text-center">
                                                <div class="fw-bold text-success"><?= number_format($nilai_obj_display, 2) ?></div>
                                                <small class="text-muted">Nilai Objektif</small>
                                                <div class="text-muted" style="font-size:0.75rem">
                                                    <?= number_format($skor_objektif, 2) ?> / <?= number_format($bobot_objektif, 2) ?> poin
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($has_esai_d): ?>
                                        <div class="col-6">
                                            <div class="bg-light rounded p-2 text-center">
                                                <div class="fw-bold <?= $skor_status === 'pending' ? 'text-warning' : 'text-info' ?>">
                                                    <?= number_format($nilai_esai_display, 2) ?>
                                                </div>
                                                <small class="text-muted">Nilai Esai</small>
                                                <div class="text-muted" style="font-size:0.75rem">
                                                    <?= number_format($skor_essay_tot, 2) ?> / <?= number_format($bobot_essay, 2) ?> poin
                                                    <?php if ($skor_status === 'pending'): ?><span class="text-warning">*</span><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <?php if ($skor_status === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-clock me-1"></i>Koreksi Esai Belum Final
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Nilai Final
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar jawaban -->
                <div class="col-lg-8">
                    <?php if (empty($answers)): ?>
                        <div class="alert alert-warning">Siswa belum mengerjakan ujian ini.</div>
                    <?php endif; ?>

                    <?php foreach ($answers as $idx => $ans):
                        $_j = $ans['jawaban_simpan'];
                        $jawaban_kosong = ($_j === null || $_j === '' || $_j === '[]' || $_j === '{}');
                        $is_correct   = ($ans['skor_didapat'] > 0);
                        if ($ans['tipe'] === 'essay') {
                            $border_class = 'essay';
                        } elseif ($jawaban_kosong) {
                            $border_class = 'kosong';
                        } elseif ($is_correct) {
                            $border_class = 'benar';
                        } else {
                            $border_class = 'salah';
                        }
                    ?>
                    <div class="card border-0 shadow-sm mb-4 card-soal <?= $border_class ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge bg-dark">SOAL <?= $idx + 1 ?></span>
                                    <span class="badge bg-info"><?= strtoupper(str_replace('_', ' ', $ans['tipe'])) ?></span>
                                    <?php if ($ans['tipe'] === 'essay' && !(bool)$ans['is_graded'] && !empty($ans['jawaban_simpan'])): ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Belum Dinilai
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <?php if ($ans['tipe'] === 'essay'): ?>
                                        <span class="fw-bold <?= (float)$ans['skor_didapat'] > 0 ? 'text-success' : 'text-muted' ?>">
                                            <?= $ans['skor_didapat'] ?> / <?= $ans['bobot_asli'] ?>
                                        </span>
                                    <?php elseif ($ans['tipe'] === 'isian'): ?>
                                        <span class="fw-bold <?= (float)$ans['skor_didapat'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $ans['skor_didapat'] ?> / <?= $ans['bobot_asli'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="fw-bold <?= $is_correct ? 'text-success' : 'text-danger' ?>">
                                            <?= $ans['skor_didapat'] ?> / <?= $ans['bobot_asli'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3 soal-konten"><?= inject_domain_to_html($ans['konten_soal']) ?></div>

                            <div class="jawaban-box shadow-sm">
                                <small class="text-muted fw-bold d-block mb-2">JAWABAN SISWA:</small>
                                <div class="fw-bold text-primary">
                                    <?php
                                    $q_id   = (int)$ans['question_id'];
                                    $q_opts = $options_by_question[$q_id] ?? [];

                                    if (empty($ans['jawaban_simpan'])) {
                                        echo '<em class="text-danger">Tidak Menjawab</em>';
                                    } elseif (in_array($ans['tipe'], ['pg', 'benar_salah'])) {
                                        $opt = $options_by_id[(int)$ans['jawaban_simpan']] ?? null;
                                        if ($opt) {
                                            echo "<strong>".htmlspecialchars((string)$opt['label'])."</strong>. ".inject_domain_to_html((string)$opt['value_target']);
                                        } else {
                                            echo htmlspecialchars($ans['jawaban_simpan']);
                                        }
                                    } elseif ($ans['tipe'] == 'pg_kompleks') {
                                        $decoded = json_decode($ans['jawaban_simpan'], true);
                                        $ids = [];
                                        if (is_array($decoded)) {
                                            if (array_values($decoded) === $decoded) {
                                                $ids = $decoded;
                                            } else {
                                                foreach ($decoded as $k => $v) {
                                                    if (is_numeric($k) && ($v === true || $v === 1 || $v === '1' || (string)$v === (string)$k)) { $ids[] = $k; }
                                                    elseif (is_numeric($v)) { $ids[] = $v; }
                                                }
                                            }
                                        }
                                        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
                                        if (!empty($ids)) {
                                            $output = [];
                                            foreach ($ids as $idOpt) {
                                                $opt = $options_by_id[$idOpt] ?? null;
                                                $output[] = $opt
                                                    ? "<strong>".htmlspecialchars((string)$opt['label'])."</strong>. ".inject_domain_to_html((string)$opt['value_target'])
                                                    : htmlspecialchars((string)$idOpt);
                                            }
                                            echo "<ul class='mb-0'><li>".implode("</li><li>", $output)."</li></ul>";
                                        } else {
                                            echo htmlspecialchars($ans['jawaban_simpan']);
                                        }
                                    } elseif ($ans['tipe'] == 'menjodohkan') {
                                        $pairs = json_decode($ans['jawaban_simpan'], true) ?: [];
                                        $mapIdToLabel = [];
                                        foreach ($q_opts as $op) { $mapIdToLabel[(string)$op['id']] = (string)$op['label']; }
                                        echo "<table class='table table-sm table-bordered mb-0'>";
                                        foreach ($pairs as $k => $v) {
                                            $leftShow = $mapIdToLabel[(string)$k] ?? htmlspecialchars((string)$k);
                                            $leftShow = inject_domain_to_html($leftShow);
                                            $rightShow = inject_domain_to_html((string)$v);
                                            echo "<tr><td class='bg-light'>{$leftShow}</td><td><i class='fas fa-arrow-right mx-2 text-muted'></i>{$rightShow}</td></tr>";
                                        }
                                        echo "</table>";
                                    } else {
                                        echo nl2br(htmlspecialchars($ans['jawaban_simpan']));
                                    }
                                    ?>
                                </div>
                            </div>

                            <?php if ($ans['tipe'] === 'essay'): ?>
                                <div class="kunci-box manual mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-secondary small">
                                             Koreksi Esai
                                        </strong>
                                        <span class="small text-muted">
                                            Skor saat ini: <strong><?= $ans['skor_didapat'] ?></strong> / <?= $ans['bobot_asli'] ?>
                                            <?php if ((bool)$ans['is_graded']): ?>
                                                <span class="badge bg-success ms-1">Sudah Dinilai</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark ms-1">Belum Dinilai</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($ans['id'])): ?>
                                    <button class="btn btn-primary btn-sm w-100 koreksi-btn"
                                            onclick="koreksiSkor(<?= esc((int)$ans['id']) ?>, <?= esc((float)$ans['bobot_asli']) ?>, <?= esc((float)$ans['skor_didapat']) ?>)">
                                        <i class="fas fa-pen me-1"></i><?= (bool)$ans['is_graded'] ? 'Ubah Nilai' : 'Beri Nilai' ?>
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small fst-italic">Siswa belum mengisi jawaban — tidak perlu dinilai.</span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($ans['tipe'] === 'isian'): ?>
                                <div class="kunci-box">
                                    <strong class="text-success small"><i class="fas fa-key me-1"></i> Kunci Jawaban (Auto-Check):</strong><br>
                                    <?php
                                    $kunci_isian = array_filter($options_by_question[$q_id] ?? [], fn($o) => (bool)$o['is_correct']);
                                    if (!empty($kunci_isian)):
                                        $variasi = array_map(fn($o) => '<code>' . htmlspecialchars($o['value_target']) . '</code>', array_values($kunci_isian));
                                        echo implode(' <span class="text-muted">|</span> ', $variasi);
                                    else:
                                        echo '<span class="text-muted small fst-italic">Belum ada kunci jawaban.</span>';
                                    endif;
                                    ?>
                                </div>
                            <?php else: ?>
                                <div class="kunci-box">
                                    <strong class="text-success small"><i class="fas fa-key me-1"></i> Kunci Jawaban:</strong><br>
                                    <?php
                                    $output_kunci = [];
                                    foreach ($q_opts as $k) {
                                        if ($ans['tipe'] == 'menjodohkan') {
                                            $output_kunci[] = inject_domain_to_html((string)$k['label'])." <i class='fas fa-link mx-1 text-muted'></i> ".inject_domain_to_html((string)$k['value_target']);
                                        } elseif ($k['is_correct']) {
                                            $output_kunci[] = "<strong>".htmlspecialchars((string)$k['label'])."</strong> ".inject_domain_to_html((string)$k['value_target']);
                                        }
                                    }
                                    echo !empty($output_kunci) ? implode(", ", $output_kunci) : "<span class='text-muted small fst-italic'>Belum diset / Manual</span>";
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof renderMath === 'function') renderMath(document.getElementById('content'));

    document.getElementById('btnCetak').addEventListener('click', function() {
        Swal.fire({
            title: 'Cetak Lembar Jawaban?',
            text: 'Halaman cetak browser akan dibuka.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#343a40',
            confirmButtonText: '<i class="fas fa-print me-1"></i> Cetak',
            cancelButtonText: 'Batal',
            customClass: {
                container: 'no-print',
            },
            preConfirm: function() { window.print(); }
        });
    });
});
</script>
<script>
function koreksiSkor(answerId, bobotMaks, skorSekarang) {
    Swal.fire({
        title: 'Sesuaikan Skor',
        html: `Masukkan nilai untuk soal ini.<br><small class="text-muted">Maksimal bobot: <b>${bobotMaks}</b></small>`,
        input: 'number',
        inputValue: skorSekarang,
        inputAttributes: {
            min: 0,
            max: bobotMaks,
            step: 0.01
        },
        showCancelButton: true,
        confirmButtonText: 'Simpan Skor',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#4e73df',
        preConfirm: (value) => {
            if (value === '' || value === null || parseFloat(value) < 0 || parseFloat(value) > bobotMaks) {
                Swal.showValidationMessage(`Skor harus antara 0 sampai ${bobotMaks}`);
            }
            return value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'update_skor_manual.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: answerId,
                    skor: result.value
                },
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Skor akhir peserta diperbarui menjadi: ' + response.total_akhir,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', response.message || 'Terjadi kesalahan', 'error');
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', 'Request gagal: ' + xhr.status + ' ' + xhr.statusText, 'error');
                }
            });
        }
    });
}

</script>
</body>
</html>