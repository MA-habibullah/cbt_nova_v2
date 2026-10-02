<?php
ob_start();
require_once '../../../config/database.php';
if (ob_get_length()) ob_clean(); 

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id === 0) {
    echo "<tr><td colspan='4' class='p-4 text-center text-danger'>ID Ujian tidak valid.</td></tr>";
    exit;
}

// Ambil Daftar Soal
$stmt_s = $pdo->prepare("
    SELECT q.* FROM cbt_questions q 
    JOIN cbt_exam_questions eq ON q.id = eq.question_id 
    WHERE eq.exam_id = ? ORDER BY eq.order_number ASC
");
$stmt_s->execute([$exam_id]);
$list_soal = $stmt_s->fetchAll();

$tipe_nama = [
    'pg'            => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">PG</span>',
    'pg_kompleks'   => '<span class="badge bg-info-subtle text-info border border-info-subtle">PGK</span>',
    'isian'         => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Isian</span>',
    'benar_salah'   => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">B/S</span>',
    'menjodohkan'   => '<span class="badge bg-dark-subtle text-dark border border-dark-subtle">Jodoh</span>',
    'essay'         => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Essay</span>'
];

if (!$list_soal) {
    echo "<tr><td colspan='4' class='p-5 text-center text-muted'>Tidak ada data soal untuk ujian ini.</td></tr>";
    exit;
}

foreach ($list_soal as $index => $s): 
?>
    <tr>
        <td class="text-center fw-bold"><?= $index + 1 ?></td>
        <td class="p-3">
            <div class="mb-2"><?= $tipe_nama[$s['tipe']] ?></div>
            <div class="small text-dark fw-medium" style="max-height: 50px; overflow: hidden;">
                <?= strip_tags($s['konten_soal']) ?>
            </div>
        </td>
        <td class="p-3">
            <div class="row g-2">
                <?php if ($s['tipe'] == 'pg' || $s['tipe'] == 'benar_salah'): 
                    $stmt_opt = $pdo->prepare("SELECT label, is_correct FROM cbt_question_options WHERE question_id = ? ORDER BY label ASC");
                    $stmt_opt->execute([$s['id']]);
                    $options = $stmt_opt->fetchAll();

                    foreach ($options as $opt):
                        $st_hit = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa 
                                                 JOIN cbt_exam_participants p ON sa.participant_id = p.id 
                                                 WHERE p.exam_id = ? AND sa.question_id = ? AND sa.jawaban_simpan = ?");
                        $st_hit->execute([$exam_id, $s['id'], $opt['label']]);
                        $count = $st_hit->fetchColumn();
                        $label_display = ($s['tipe'] == 'benar_salah') ? ($opt['label'] == 'A' ? 'Benar' : 'Salah') : $opt['label'];
                ?>
                    <div class="col-md-2 col-4">
                        <div class="text-center p-2 rounded border <?= $opt['is_correct'] ? 'bg-success-subtle border-success' : 'bg-white' ?>">
                            <div class="small fw-bold" style="font-size: 10px;"><?= $label_display ?></div>
                            <div class="h6 mb-0"><?= $count ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php else: ?>
                    <div class="col-12"><small class="text-muted italic">Analisis detail hanya tersedia untuk pilihan ganda & benar/salah.</small></div>
                <?php endif; ?>
            </div>
        </td>
        <td class="text-center">
            <?php 
            $st_ksg = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_answers sa 
                                     JOIN cbt_exam_participants p ON sa.participant_id = p.id 
                                     WHERE p.exam_id = ? AND sa.question_id = ? AND (sa.jawaban_simpan IS NULL OR sa.jawaban_simpan = '')");
            $st_ksg->execute([$exam_id, $s['id']]);
            $kosong = $st_ksg->fetchColumn();
            ?>
            <div class="fw-bold <?= $kosong > 0 ? 'text-danger' : 'text-muted' ?>"><?= $kosong ?></div>
            <div style="font-size: 10px;" class="text-uppercase">Siswa</div>
        </td>
    </tr>
<?php 
endforeach; 
ob_end_flush();
?>