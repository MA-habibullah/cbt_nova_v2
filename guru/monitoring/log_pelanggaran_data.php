<?php
ob_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean();

if (!isset($_SESSION['teacher_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo '<div class="p-4 text-center text-danger">Sesi berakhir. Silakan login ulang.</div>';
    exit;
}
session_write_close();
$teacher_id = (int)$_SESSION['teacher_id'];

$tanggal  = $_GET['tanggal'] ?? '';
$exam_id  = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';

$sql = "SELECT cl.*, s.nama_lengkap, s.username, s.nisn, c.nama_kelas, e.nama_mapel_ujian
        FROM cbt_cheat_logs cl
        JOIN cbt_students s ON cl.student_id = s.id
        LEFT JOIN cbt_classes c ON s.class_id = c.id
        JOIN cbt_exams e ON cl.exam_id = e.id
        WHERE e.teacher_id = ?";
$params = [$teacher_id];

if ($tanggal) {
    $sql .= " AND DATE(cl.waktu_kejadian) = ?";
    $params[] = $tanggal;
}
if ($exam_id) {
    $sql .= " AND cl.exam_id = ?";
    $params[] = $exam_id;
}
if ($class_id) {
    $sql .= " AND s.class_id = ?";
    $params[] = $class_id;
}

$sql .= " ORDER BY cl.waktu_kejadian DESC LIMIT 300";
$logs = query($sql, $params)->fetchAll();

if (empty($logs)) {
    echo '<div class="p-5 text-center text-muted">
            <i class="fas fa-shield-alt fa-3x text-success mb-3 opacity-50"></i>
            <h6 class="fw-bold">Tidak Ada Pelanggaran Ditemukan</h6>
            <p class="small mb-0">Seluruh siswa tertib atau belum ada log tercatat pada filter ini.</p>
          </div>';
    exit;
}

// Group by student
$grouped = [];
foreach ($logs as $l) {
    $grouped[$l['student_id']]['info'] = [
        'nama'     => $l['nama_lengkap'],
        'username' => $l['username'],
        'nisn'     => $l['nisn'],
        'kelas'    => $l['nama_kelas'],
        'mapel'    => $l['nama_mapel_ujian']
    ];
    $grouped[$l['student_id']]['logs'][] = $l;
}
?>

<div class="accordion" id="accordionLogs">
<?php $idx = 0; foreach ($grouped as $sId => $g): $idx++; ?>
    <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
        <h2 class="accordion-header" id="heading<?= $idx ?>">
            <button class="accordion-button <?= $idx > 1 ? 'collapsed' : '' ?> bg-white py-3 px-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>">
                <div class="d-flex align-items-center justify-content-between w-100 me-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark"><?= esc($g['info']['nama']) ?></h6>
                            <small class="text-muted"><?= esc($g['info']['username']) ?> &bull; <?= esc($g['info']['kelas'] ?? '-') ?> &bull; <?= esc($g['info']['mapel']) ?></small>
                        </div>
                    </div>
                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-semibold">
                        <?= count($g['logs']) ?> Kali Pelanggaran
                    </span>
                </div>
            </button>
        </h2>
        <div id="collapse<?= $idx ?>" class="accordion-collapse collapse <?= $idx === 1 ? 'show' : '' ?>" data-bs-parent="#accordionLogs">
            <div class="accordion-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 180px;">Waktu</th>
                                <th style="width: 200px;">Jenis Pelanggaran</th>
                                <th>Keterangan / Detail Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($g['logs'] as $log): 
                                $jenis = strtolower($log['jenis_pelanggaran'] ?? '');
                                $badgeClass = 'bg-secondary';
                                if (strpos($jenis, 'tab') !== false || strpos($jenis, 'blur') !== false) $badgeClass = 'bg-warning text-dark';
                                elseif (strpos($jenis, 'fullscreen') !== false) $badgeClass = 'bg-danger text-white';
                                elseif (strpos($jenis, 'screenshot') !== false || strpos($jenis, 'key') !== false) $badgeClass = 'bg-dark text-white';
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td class="font-monospace small text-muted">
                                    <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i:s', strtotime($log['waktu_kejadian'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> px-2.5 py-1.5 rounded-pill fw-semibold">
                                        <?= esc($log['jenis_pelanggaran']) ?>
                                    </span>
                                </td>
                                <td class="small text-secondary">
                                    <?= esc($log['keterangan'] ?? '-') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
