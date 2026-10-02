<?php
ob_start(); 
require_once dirname(__DIR__, 2) . '/config/database.php';
if (ob_get_length()) ob_clean(); 

if (!isset($_SESSION['admin_id'])) {
    echo "<tr><td colspan='7' class='p-10 text-center text-red-500'>Sesi berakhir. Silakan login ulang.</td></tr>";
    exit;
}

$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');
$exam_id  = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$sesi     = $_GET['sesi'] ?? '';

// Query peserta ujian aktif
$params = [];
$sql = "SELECT p.id as p_id, s.id as s_id, s.nama_lengkap, s.username, s.sesi, c.nama_kelas,
        e.id as e_id, e.nama_mapel_ujian, e.durasi_menit, e.selesai_pada, p.status, p.tambahan_waktu, p.waktu_mulai,
        NOW() as now_db,
        dl.ip_address, dl.user_agent
        FROM cbt_exam_participants p
        JOIN cbt_students s ON p.student_id = s.id
        LEFT JOIN cbt_classes c ON COALESCE(p.class_id, s.class_id) = c.id
        JOIN cbt_exams e ON p.exam_id = e.id
        LEFT JOIN cbt_device_locks dl ON s.id = dl.student_id
        WHERE 1=1";

if ($tanggal) {
    $sql .= " AND e.mulai_pada BETWEEN ? AND ?";
    $params[] = $tanggal . ' 00:00:00';
    $params[] = $tanggal . ' 23:59:59';
}
if ($exam_id) { $sql .= " AND e.id = ?"; $params[] = $exam_id; }
if ($class_id) { $sql .= " AND COALESCE(p.class_id, s.class_id) = ?"; $params[] = $class_id; }
if ($sesi) { $sql .= " AND s.sesi = ?"; $params[] = $sesi; }

$sql .= " ORDER BY p.status DESC, s.nama_lengkap ASC";

$data = query($sql, $params)->fetchAll();

if (!$data) {
    echo "<tr><td colspan='7' class='p-10 text-center text-gray-400'>Tidak ada peserta ujian pada filter ini.</td></tr>";
    exit;
}

// Scoped batch aggregation (menghilangkan Full Table Scan pada ratusan ribu baris)
$p_ids = array_column($data, 'p_id');
$e_ids = array_values(array_unique(array_column($data, 'e_id')));

$ans_map = [];
if (!empty($p_ids)) {
    $in_p = implode(',', array_fill(0, count($p_ids), '?'));
    $stmtAns = $pdo->prepare("
        SELECT participant_id, COUNT(*) AS jml_jawab
        FROM cbt_student_answers
        WHERE participant_id IN ($in_p) AND jawaban_simpan IS NOT NULL AND jawaban_simpan != ''
        GROUP BY participant_id
    ");
    $stmtAns->execute($p_ids);
    foreach ($stmtAns->fetchAll() as $r) {
        $ans_map[$r['participant_id']] = (int)$r['jml_jawab'];
    }
}

$eq_map = [];
if (!empty($e_ids)) {
    $in_e = implode(',', array_fill(0, count($e_ids), '?'));
    $stmtEq = $pdo->prepare("
        SELECT exam_id, COUNT(*) AS total_soal
        FROM cbt_exam_questions
        WHERE exam_id IN ($in_e)
        GROUP BY exam_id
    ");
    $stmtEq->execute($e_ids);
    foreach ($stmtEq->fetchAll() as $r) {
        $eq_map[$r['exam_id']] = (int)$r['total_soal'];
    }
}

$cl_map = [];
if (!empty($e_ids)) {
    $in_e = implode(',', array_fill(0, count($e_ids), '?'));
    $stmtCl = $pdo->prepare("
        SELECT student_id, exam_id, COUNT(*) AS logs
        FROM cbt_cheat_logs
        WHERE exam_id IN ($in_e)
        GROUP BY student_id, exam_id
    ");
    $stmtCl->execute($e_ids);
    foreach ($stmtCl->fetchAll() as $r) {
        $cl_map[$r['student_id'] . '_' . $r['exam_id']] = (int)$r['logs'];
    }
}

foreach ($data as $row) {
    $row['jml_jawab'] = $ans_map[$row['p_id']] ?? 0;
    $row['total_soal'] = $eq_map[$row['e_id']] ?? 0;
    $row['logs']       = $cl_map[$row['s_id'] . '_' . $row['e_id']] ?? 0;
    $st = $row['status'] ?? 'ready';
    
    // --- LOGIKA HITUNG WAKTU ---
    $display_time = "-";
    if ($st == 'working' && $row['waktu_mulai']) {
        $waktu_mulai    = strtotime($row['waktu_mulai']);
        $waktu_sekarang = strtotime($row['now_db']);

        if ((int)$row['tambahan_waktu'] > 0) {
            $final_deadline = $waktu_mulai + (int)$row['tambahan_waktu'] * 60;
        } else {
            $waktu_habis_durasi = $waktu_mulai + (int)$row['durasi_menit'] * 60;
            $batas_jadwal       = strtotime($row['selesai_pada']);
            $final_deadline     = min($waktu_habis_durasi, $batas_jadwal);
        }
        $sisa_detik = $final_deadline - $waktu_sekarang;

        // $mulai = strtotime($row['waktu_mulai']);
        // $durasi_total = ($row['durasi_menit'] + $row['tambahan_waktu']) * 60;
        // $seharusnya_selesai = $mulai + $durasi_total;
        // $sisa_detik = $seharusnya_selesai - time();

        if ($sisa_detik > 0) {
            $jam   = floor($sisa_detik / 3600);
            $menit = floor(($sisa_detik % 3600) / 60);
            $detik = $sisa_detik % 60;
            $fmt   = ($jam > 0 ? $jam . 'j ' : '') . $menit . 'm ' . str_pad($detik, 2, '0', STR_PAD_LEFT) . 'd';
            $display_time = "<span class='countdown text-blue-600 font-bold' data-sisa='{$sisa_detik}'>{$fmt}</span>";
        } else {
            $display_time = "<span class='countdown text-red-500 font-bold' data-sisa='0'>Waktu Habis</span>";
        }
    } elseif ($st == 'finished') {
        $display_time = "<span class='text-gray-400'>Selesai</span>";
    }

    // --- LOGIKA PROGRESS BAR ---
    $pct = 0;
    if($row['total_soal'] > 0) {
        $pct = round(($row['jml_jawab'] / $row['total_soal']) * 100);
    }
    $bar_color = $pct == 100 ? 'bg-success' : 'bg-primary';

    $status_badge = match($st) {
        'working'  => '<span class="badge badge-soft-success rounded-pill px-2.5 py-1 text-uppercase fw-bold"><i class="fas fa-spinner fa-spin me-1"></i>Mengerjakan</span>',
        'ready'    => '<span class="badge badge-soft-info rounded-pill px-2.5 py-1 text-uppercase fw-bold">Siap</span>',
        'finished' => '<span class="badge badge-soft-secondary rounded-pill px-2.5 py-1 text-uppercase fw-bold">Selesai</span>',
        'blocked'  => '<span class="badge badge-soft-danger rounded-pill px-2.5 py-1 text-uppercase fw-bold"><i class="fas fa-lock me-1"></i>Terkunci</span>',
        default    => '<span class="badge badge-soft-secondary rounded-pill px-2.5 py-1 text-uppercase fw-bold">'.htmlspecialchars($st).'</span>',
    };

    $nama_peserta = htmlspecialchars($row['nama_lengkap']);
    $username     = htmlspecialchars($row['username']);
    $nama_kelas   = htmlspecialchars($row['nama_kelas'] ?? '-');
    $mapel_ujian  = htmlspecialchars($row['nama_mapel_ujian']);
    $ip_addr      = htmlspecialchars($row['ip_address'] ?? '-');
    $ua           = htmlspecialchars($row['user_agent'] ? substr($row['user_agent'], 0, 18) : '-');

    echo "<tr>
        <td class='py-3 px-3 text-center'><input type='checkbox' class='form-check-input check-item' value='{$row['p_id']}'></td>
        <td class='py-3 px-3'>
            <div class='fw-bold text-dark fs-6'>{$nama_peserta}</div>
            <div class='text-muted font-monospace small'>{$username}</div>
            <div class='d-sm-none text-muted small mt-1'>
                <span class='badge bg-light text-dark border me-1'>{$nama_kelas}</span> Sesi {$row['sesi']}
            </div>
        </td>
        <td class='py-3 px-3 d-none d-sm-table-cell'>
            <span class='badge bg-light text-dark border px-2 py-1 fw-semibold'>{$nama_kelas}</span>
            <div class='text-muted small mt-1'>Sesi {$row['sesi']}</div>
        </td>
        <td class='py-3 px-3'>
            <div class='fw-medium text-dark small mb-1'>{$mapel_ujian}</div>
            <div class='d-flex align-items-center gap-2'>
                <div class='progress flex-grow-1' style='height: 6px; width: 80px; max-width: 120px;'>
                    <div class='progress-bar {$bar_color}' role='progressbar' style='width: {$pct}%'></div>
                </div>
                <span class='small text-muted fw-bold font-monospace' style='font-size:0.75rem;'>{$row['jml_jawab']}/{$row['total_soal']}</span>
            </div>
        </td>
        <td class='py-3 px-3 text-center'>
            <div>{$status_badge}</div>
            <div class='mt-1 small'>{$display_time}</div>
        </td>
        <td class='py-3 px-3 text-center d-none d-md-table-cell'>
            <div class='font-monospace text-primary fw-bold small'>{$ip_addr}</div>
            <div class='text-muted' style='font-size:0.72rem;' title='{$ua}'>{$ua}</div>
        </td>
        <td class='py-3 px-3 text-center'>
            <button onclick=\"viewLogs({$row['s_id']}, {$row['e_id']})\" class='btn btn-sm btn-light border p-1 px-2 shadow-none'>
                <span class='fw-bold ".($row['logs'] > 0 ? 'text-danger' : 'text-muted')."'>{$row['logs']}</span>
                <span class='d-block text-muted' style='font-size: 0.65rem;'>Log</span>
            </button>
        </td>
    </tr>";
}
ob_end_flush();