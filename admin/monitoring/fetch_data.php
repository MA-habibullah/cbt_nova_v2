<?php
ob_start(); 
require_once '../../config/database.php';
if (ob_get_length()) ob_clean(); 

$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');
$exam_id  = $_GET['exam_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$sesi     = $_GET['sesi'] ?? '';

// Query peserta ujian aktif
$params = [$tanggal . ' 00:00:00', $tanggal . ' 23:59:59'];
$sql = "SELECT p.id as p_id, s.id as s_id, s.nama_lengkap, s.username, s.sesi, c.nama_kelas,
        e.id as e_id, e.nama_mapel_ujian, e.durasi_menit, e.selesai_pada, p.status, p.tambahan_waktu, p.waktu_mulai,
        NOW() as now_db,
        dl.ip_address, dl.user_agent
        FROM cbt_exam_participants p
        JOIN cbt_students s ON p.student_id = s.id
        JOIN cbt_classes c ON s.class_id = c.id
        JOIN cbt_exams e ON p.exam_id = e.id
        LEFT JOIN cbt_device_locks dl ON s.id = dl.student_id
        WHERE e.mulai_pada BETWEEN ? AND ?";

if($exam_id) { $sql .= " AND e.id = ?"; $params[] = $exam_id; }
if($class_id) { $sql .= " AND c.id = ?"; $params[] = $class_id; }
if($sesi) { $sql .= " AND s.sesi = ?"; $params[] = $sesi; }

$sql .= " ORDER BY p.status DESC, s.nama_lengkap ASC";

$data = query($sql, $params)->fetchAll();

if (!$data) {
    echo "<tr><td colspan='8' class='p-10 text-center text-gray-400'>Tidak ada peserta ujian.</td></tr>";
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
    $bar_color = $pct == 100 ? 'bg-green-500' : 'bg-blue-500';

    $status_color = [
        'working' => 'bg-green-100 text-green-700 animate-pulse',
        'ready' => 'bg-blue-100 text-blue-700',
        'finished' => 'bg-gray-100 text-gray-500',
        'blocked' => 'bg-red-100 text-red-700 font-bold'
    ];
    $color = $status_color[$st] ?? 'bg-gray-100';

    echo "<tr>
        <td class='p-4 text-center'><input type='checkbox' class='check-item' value='{$row['p_id']}'></td>
        <td class='p-4'>
            <div class='font-bold text-gray-800 uppercase text-xs'>".htmlspecialchars($row['nama_lengkap'])."</div>
            <div class='text-[10px] text-blue-500 font-mono'>".htmlspecialchars($row['username'])."</div>
        </td>
        <td class='p-4 text-[10px] text-gray-500 font-bold'>".htmlspecialchars($row['nama_kelas'])."<br>Sesi {$row['sesi']}</td>
        <td class='p-4'>
            <div class='text-[10px] mb-1 font-medium'>".htmlspecialchars($row['nama_mapel_ujian'])."</div>
            <div class='flex items-center gap-2'>
                <div class='w-24 bg-gray-200 rounded-full h-1.5'>
                    <div class='$bar_color h-1.5 rounded-full' style='width: {$pct}%'></div>
                </div>
                <span class='text-[9px] font-bold text-gray-500'>{$row['jml_jawab']}/{$row['total_soal']}</span>
            </div>
        </td>
        <td class='p-4 text-center'>
            <span class='px-3 py-1 rounded-full text-[10px] font-bold uppercase $color'>$st</span>
            <div class='mt-1 text-[10px]'>$display_time</div>
        </td>
        <td class='p-4 text-center text-[10px] text-gray-400'>
            <div class='font-mono text-indigo-600 font-bold'>".($row['ip_address'] ?? '-')."</div>
            <div class='truncate w-24 mx-auto'>".($row['user_agent'] ? substr($row['user_agent'], 0, 15) : '-')."</div>
        </td>
        <td class='p-4 text-center'>
            <button onclick=\"viewLogs({$row['s_id']}, {$row['e_id']})\" class='flex flex-col items-center mx-auto border-0 bg-transparent'>
                <span class='text-sm font-black ".($row['logs'] > 0 ? 'text-red-600' : 'text-gray-200')."'>{$row['logs']}</span>
                <span class='text-[8px] uppercase text-gray-400 font-bold'>Pelanggaran</span>
            </button>
        </td>
    </tr>";
}
ob_end_flush();