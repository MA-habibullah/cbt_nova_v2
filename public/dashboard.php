<?php
require_once '../config/database.php';

$short_code = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['c'] ?? '');

$registry = ($short_code !== '')
    ? query("SELECT is_active, config_json FROM cbt_display_tokens WHERE short_code = ?", [$short_code])->fetch()
    : null;

if (!$registry) {
    http_response_code(403);
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Layar Publik — Link Tidak Valid</title>
<style>
  body{margin:0;background:#111;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center}
  .icon{font-size:5rem;margin-bottom:1rem}
  h2{font-size:1.8rem;margin-bottom:.5rem}
  p{color:#aaa}
</style>
</head>
<body>
  <div>
    <div class="icon">⚠️</div>
    <h2>Link Tidak Valid</h2>
    <p>URL leaderboard publik tidak valid atau tidak ditemukan.<br>
       Harap buat ulang URL dari halaman Pengaturan Tampilan Publik.</p>
  </div>
</body>
</html>
    <?php exit;
}

if (!$registry['is_active']) {
    http_response_code(403);
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Layar Publik — Dashboard Dinonaktifkan</title>
<style>
  body{margin:0;background:#f4f7fe;color:#333;font-family:'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center}
  .icon{font-size:5rem;margin-bottom:1rem}
  h2{font-size:1.8rem;margin-bottom:.5rem;color:#e74c3c}
  p{color:#858796}
</style>
</head>
<body>
  <div>
    <div class="icon">🚫</div>
    <h2>Dashboard Dinonaktifkan</h2>
    <p>Tayangan publik ini telah dinonaktifkan oleh pengelola.<br>
       Hubungi admin/guru untuk mengaktifkan kembali.</p>
  </div>
</body>
</html>
    <?php exit;
}

$config    = json_decode($registry['config_json'] ?? '[]', true) ?: [];
$raw_slots = $config;

// Pre-query metadata for each slot (banner substitution + class_name for list view)
$slots_data = [];
$first_valid_exam = null;
foreach ($raw_slots as $slot) {
    $exam_id = (int)($slot['exam_id'] ?? 0);
    if (!$exam_id) continue;

    $exam = query("SELECT nama_mapel_ujian, jenjang FROM cbt_exams WHERE id = ?", [$exam_id])->fetch();
    if (!$exam) continue;

    if (!$first_valid_exam) $first_valid_exam = $exam;

    $class_name = 'Semua Kelas';
    if (($slot['class_id'] ?? 'all') !== 'all') {
        $kelas = query(
            "SELECT CONCAT(jenjang, ' - ', nama_kelas) as label FROM cbt_classes WHERE id = ?",
            [(int)$slot['class_id']]
        )->fetch();
        if ($kelas) $class_name = $kelas['label'];
    }

    $banner = str_replace(
        ['[NAMA_KEGIATAN]', '[JENIS_JENJANG]', '[JENIS_KELAS]'],
        [
            htmlspecialchars($exam['nama_mapel_ujian']),
            htmlspecialchars($exam['jenjang'] ?? ''),
            htmlspecialchars($class_name),
        ],
        $slot['banner_tpl'] ?? ''
    );

    $slots_data[] = [
        'banner'     => $banner,
        'top_limit'  => (int)($slot['top_limit'] ?? 10),
        'exam_name'  => $exam['nama_mapel_ujian'],
        'class_name' => $class_name,
        'jenjang'    => $exam['jenjang'] ?? '',
    ];
}

if (empty($slots_data)) {
    http_response_code(404);
    echo '<p style="background:#f4f7fe;padding:2rem;font-family:sans-serif;">Ujian tidak ditemukan.</p>';
    exit;
}

$encoded_c = htmlspecialchars($short_code);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Layar Publik</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  /* Admin palette: --primary #4e73df, --bg-light #f4f7fe */
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body {
    height: 100%;
    background: #f4f7fe;
    color: #333;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    overflow: hidden;
  }

  /* HEADER BANNER */
  #banner {
    background: linear-gradient(135deg, #4e73df 0%, #2e59d9 100%);
    padding: 1rem 2rem;
    text-align: center;
    box-shadow: 0 4px 16px rgba(78,115,223,.3);
    position: relative;
    color: #fff;
  }
  #banner h1 {
    font-size: clamp(1.2rem, 3vw, 2rem);
    font-weight: 800;
    letter-spacing: 0.04em;
    text-shadow: 0 1px 4px rgba(0,0,0,0.2);
    line-height: 1.2;
  }
  #banner .meta-bar {
    margin-top: .4rem;
    font-size: .85rem;
    color: rgba(255,255,255,0.85);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
  }
  .pulse-dot {
    display: inline-block;
    width: 10px; height: 10px;
    background: #fff;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
    margin-right: 5px;
    opacity: .9;
  }
  @keyframes pulse {
    0%, 100% { opacity: .9; transform: scale(1); }
    50%       { opacity: .4; transform: scale(1.4); }
  }

  /* SLOT INDICATOR */
  .slot-indicator {
    position: absolute;
    top: 10px;
    right: 15px;
    background: rgba(255,255,255,0.2);
    color: #fff;
    font-size: .75rem;
    padding: .2rem .65rem;
    border-radius: 50px;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.35);
  }

  /* LEADERBOARD VIEW — flex column so banner height is dynamic */
  #view-leaderboard.active {
    display: flex !important;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
  }
  #banner    { flex-shrink: 0; }
  #lb-topbar { flex-shrink: 0; }

  /* LEADERBOARD TABLE */
  #leaderboard-wrap {
    flex: 1;
    min-height: 0;
    padding: 1rem 1.5rem 3rem;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
  }
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.45rem;
    table-layout: fixed;
  }
  thead th {
    color: #858796;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: .5rem 1rem;
    border-bottom: 2px solid #e3e6f0;
  }
  tbody tr {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(78,115,223,.08);
    transition: box-shadow 0.3s ease;
  }
  tbody tr:nth-child(1) { background: linear-gradient(135deg,#fffbea,#fff9e0); box-shadow: 0 2px 12px rgba(255,193,7,.18); }
  tbody tr:nth-child(2) { background: linear-gradient(135deg,#f8f9fa,#efefef); }
  tbody tr:nth-child(3) { background: linear-gradient(135deg,#fff5ee,#fdeee3); }
  tbody td {
    padding: .8rem 1rem;
    font-size: clamp(.95rem, 2vw, 1.3rem);
    vertical-align: middle;
    color: #333;
  }
  tbody td:first-child { border-radius: 12px 0 0 12px; }
  tbody td:last-child  { border-radius: 0 12px 12px 0; }

  /* Kelas as subtitle under nama (hidden on desktop, shown on mobile) */
  .nama-kelas-sub { display: none; font-size: .72rem; color: #858796; margin-top: .1rem; }

  /* MOBILE RESPONSIVE */
  @media (max-width: 640px) {
    #banner {
      padding: .5rem .75rem;
    }
    #banner h1 {
      font-size: clamp(.78rem, 3.8vw, 1.1rem);
      letter-spacing: .01em;
    }
    #banner .meta-bar {
      gap: .35rem .7rem;
      font-size: .7rem;
      flex-wrap: wrap;
    }
    #leaderboard-wrap { padding: .5rem .4rem 3rem; }
    thead th {
      padding: .3rem .4rem;
      font-size: .6rem;
      letter-spacing: .04em;
    }
    tbody td { padding: .45rem .4rem; font-size: .88rem; }
    .rank-badge { width: 1.75rem; height: 1.75rem; font-size: .82rem; }
    .score-value { font-size: .95rem; }
    .status-badge { font-size: .6rem; padding: .15rem .35rem; }
    /* Hide kelas column, show as subtitle under name */
    .th-kelas, .td-kelas { display: none; }
    .nama-kelas-sub { display: block; }
    /* Tighten footer */
    #footer-bar { padding: .35rem .75rem; font-size: .72rem; }
    /* List view */
    .slot-grid { padding: 1rem; }
    .slot-card { padding: 1rem; }
    .list-banner { padding: .75rem 1rem; }
    .list-banner h1 { font-size: clamp(.9rem, 4vw, 1.4rem); }
  }

  /* RANK BADGES */
  .rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem; height: 2.2rem;
    border-radius: 50%;
    font-weight: 800;
    font-size: 1rem;
    background: #e3e6f0;
    color: #858796;
  }
  .rank-1 { background: linear-gradient(135deg,#ffd700,#ffaa00); color: #000; box-shadow: 0 0 14px rgba(255,193,7,.45); }
  .rank-2 { background: linear-gradient(135deg,#c0c0c0,#999); color: #333; }
  .rank-3 { background: linear-gradient(135deg,#cd7f32,#a0522d); color: #fff; }

  /* SCORE */
  .score-value {
    font-size: clamp(1.1rem, 2.2vw, 1.6rem);
    font-weight: 800;
    color: #4e73df;
  }
  .status-badge {
    display: inline-block;
    padding: .25rem .6rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .status-final   { background: #d1f5e3; color: #0e7c4a; border: 1px solid #a8e6c5; }
  .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffd966; }

  /* EMPTY STATE */
  #empty-state {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #b7bdd6;
    text-align: center;
  }
  #empty-state .icon { font-size: 4rem; margin-bottom: 1rem; }

  /* FOOTER */
  #footer-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: #fff;
    padding: .4rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .8rem;
    color: #b7bdd6;
    border-top: 1px solid #e3e6f0;
    z-index: 100;
    box-shadow: 0 -2px 8px rgba(78,115,223,.06);
  }
  #footer-bar .highlight { color: #5a5c69; }

  /* CARD LIST VIEW */
  #view-list, #view-leaderboard { display: none; }
  #view-list.active { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
  #view-list.active .list-banner { flex-shrink: 0; }
  #view-list.active .slot-grid { flex: 1; min-height: 0; }

  .list-banner {
    background: linear-gradient(135deg, #4e73df 0%, #2e59d9 100%);
    padding: 1.2rem 2rem;
    text-align: center;
    color: #fff;
    box-shadow: 0 4px 16px rgba(78,115,223,.3);
  }
  .list-banner h1 {
    font-size: clamp(1.1rem, 2.5vw, 1.7rem);
    font-weight: 700;
    margin-bottom: .25rem;
  }
  .list-banner .sub { font-size: .88rem; color: rgba(255,255,255,.82); }

  .slot-grid {
    padding: 2rem;
    overflow-y: auto;
  }
  .slot-card {
    border: 1px solid #e3e6f0;
    border-radius: 16px;
    background: #fff;
    padding: 1.5rem;
    height: 100%;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s, border-color .2s;
  }
  .slot-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(78,115,223,.18);
    border-color: #4e73df;
  }
  .slot-num {
    width: 2.5rem; height: 2.5rem;
    border-radius: 50%;
    background: linear-gradient(135deg,#4e73df,#2e59d9);
    color: #fff;
    font-weight: 800;
    font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }

  /* MINI LEADERBOARD INSIDE CARD */
  .mini-lb {
    min-height: 90px;
    border-top: 1px solid #f0f1f7;
    padding-top: .65rem;
    margin-top: .5rem;
  }
  .mini-row {
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .3rem .45rem;
    border-radius: 8px;
    margin-bottom: .2rem;
  }
  .mini-row:nth-child(1) { background: linear-gradient(135deg,rgba(255,215,0,.15),rgba(255,165,0,.06)); }
  .mini-row:nth-child(2) { background: rgba(0,0,0,.025); }
  .mini-row:nth-child(3) { background: linear-gradient(135deg,rgba(205,127,50,.1),rgba(139,90,43,.04)); }
  .mini-rank { font-size: 1rem; width: 1.5rem; text-align: center; flex-shrink: 0; }
  .mini-name { flex: 1; font-size: .82rem; font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mini-score { font-size: .88rem; font-weight: 800; color: #4e73df; flex-shrink: 0; }
  .mini-empty, .mini-loading { font-size: .78rem; color: #b7bdd6; text-align: center; padding: 1rem 0; }
  .card-footer-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: .65rem;
    padding-top: .55rem;
    border-top: 1px solid #f0f1f7;
  }
  .mini-upd { font-size: .7rem; color: #b7bdd6; }

  /* LEADERBOARD VIEW */
  .lb-topbar {
    padding: .5rem 2rem;
    background: #f4f7fe;
    border-bottom: 1px solid #e3e6f0;
  }
</style>
</head>
<body>

<!-- ===== VIEW: LIST ===== -->
<div id="view-list">
  <div class="list-banner">
    <h1><i class="fas fa-tv me-2" style="opacity:.85"></i>Pilih Live Leaderboard</h1>
    <div class="sub" id="list-subtitle"></div>
  </div>
  <div class="slot-grid">
    <div class="row g-4" id="slot-cards"></div>
  </div>
</div>

<!-- ===== VIEW: LEADERBOARD ===== -->
<div id="view-leaderboard">
  <!-- Banner -->
  <div id="banner">
    <h1 id="banner-text"></h1>
    <div class="meta-bar">
      <span><span class="pulse-dot"></span>LIVE</span>
      <span id="stat-peserta"><i class="fas fa-users me-1"></i>— peserta</span>
      <span id="stat-selesai"><i class="fas fa-check-circle me-1"></i>— selesai</span>
      <span><i class="fas fa-clock me-1"></i>Update: <span id="last-update">—</span></span>
    </div>
  </div>
  <!-- Back button (shown only when multi-slot) -->
  <div class="lb-topbar" id="lb-topbar" style="display:none">
    <button id="btn-back" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> Daftar Live
    </button>
  </div>
  <!-- Table -->
  <div id="leaderboard-wrap">
    <table id="lb-table">
      <thead>
        <tr>
          <th style="width:60px">Rank</th>
          <th>Nama Peserta</th>
          <th class="th-kelas">Kelas</th>
          <th style="width:100px;text-align:right">Nilai</th>
          <th style="width:110px;text-align:center">Status</th>
        </tr>
      </thead>
      <tbody id="lb-body">
        <tr><td colspan="5" class="text-center py-5" style="color:#aaa">Memuat data...</td></tr>
      </tbody>
    </table>
    <div id="empty-state">
      <div class="icon">🏆</div>
      <h4 style="color:#b7bdd6">Belum Ada Peserta Selesai</h4>
      <p style="color:#b7bdd6">Data akan muncul otomatis saat peserta menyelesaikan ujian.</p>
    </div>
  </div>
  <!-- Footer -->
  <div id="footer-bar">
    <span>Refresh tiap <span class="highlight" id="countdown">10</span>s</span>
    <span class="highlight" id="footer-exam-name"></span>
    <span><i class="fas fa-sync-alt me-1" style="color:#b7bdd6"></i><span id="last-update-footer" style="color:#b7bdd6">—</span></span>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php
$publicSlotsJsonPayload = json_encode([
    'slots' => array_values($slots_data),
    'short_code' => $encoded_c
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="publicSlotsDataJson">
<?= $publicSlotsJsonPayload ?>
</script>
<script>
var _pubConfig   = JSON.parse(document.getElementById('publicSlotsDataJson').textContent || '{}');
var SLOTS_DATA   = _pubConfig.slots || [];
var SHORT_CODE   = _pubConfig.short_code || '';
var AJAX_URL     = 'ajax/leaderboard-data.php';
var MULTI_SLOT   = SLOTS_DATA.length > 1;
var CURRENT_SLOT = 0;

// Leaderboard polling
var pollTimer = 0;
var pollTick  = null;

// List preview polling (15s interval)
var listPollTick = null;
var LIST_POLL_MS = 15000;

/* ---- HELPERS ---- */
function rankIcon(r) {
    if (r === 1) return '<span class="rank-badge rank-1">🥇</span>';
    if (r === 2) return '<span class="rank-badge rank-2">🥈</span>';
    if (r === 3) return '<span class="rank-badge rank-3">🥉</span>';
    return '<span class="rank-badge">' + r + '</span>';
}

function esc(str) { return $('<span>').text(str).html(); }

/* ---- BUILD CARD LIST ---- */
function buildList() {
    var html = '';
    SLOTS_DATA.forEach(function(slot, i) {
        html +=
            '<div class="col-xl-3 col-lg-4 col-md-6">' +
              '<div class="slot-card" data-slot="' + i + '">' +
                '<div class="d-flex align-items-center gap-3 mb-1">' +
                  '<div class="slot-num">' + (i + 1) + '</div>' +
                  '<h6 class="mb-0 fw-bold text-dark lh-sm">' + esc(slot.exam_name) + '</h6>' +
                '</div>' +
                '<div class="d-flex gap-3 small text-muted mb-0">' +
                  '<span><i class="fas fa-users me-1 text-primary"></i>' + esc(slot.class_name) + '</span>' +
                  '<span><i class="fas fa-trophy me-1 text-warning"></i>Top ' + slot.top_limit + '</span>' +
                '</div>' +
                // Mini top-3 leaderboard
                '<div class="mini-lb" id="mini-lb-' + i + '">' +
                  '<div class="mini-loading"><i class="fas fa-spinner fa-spin me-1"></i>Memuat...</div>' +
                '</div>' +
                // Card footer
                '<div class="card-footer-bar">' +
                  '<span class="mini-upd"><i class="fas fa-sync-alt me-1"></i><span id="mini-upd-' + i + '">—</span></span>' +
                  '<span class="btn btn-primary btn-sm px-3"><i class="fas fa-expand-alt me-1"></i>Lihat Semua</span>' +
                '</div>' +
              '</div>' +
            '</div>';
    });
    $('#slot-cards').html(html);
    $('#list-subtitle').text(SLOTS_DATA.length + ' live tersedia · klik untuk leaderboard lengkap');
}

/* ---- LOAD MINI PREVIEWS (all slots) ---- */
function loadListPreviews() {
    SLOTS_DATA.forEach(function(slot, i) {
        $.getJSON(AJAX_URL, { c: SHORT_CODE, slot: i }, function(res) {
            if (res.status !== 'success') return;
            $('#mini-upd-' + i).text(res.updated_at);
            var $lb = $('#mini-lb-' + i);
            if (!res.data || res.data.length === 0) {
                $lb.html('<div class="mini-empty">Belum ada peserta selesai</div>');
                return;
            }
            var html = '';
            res.data.slice(0, 3).forEach(function(row) {
                var icon = row.rank === 1 ? '🥇' : (row.rank === 2 ? '🥈' : '🥉');
                html += '<div class="mini-row">' +
                    '<span class="mini-rank">' + icon + '</span>' +
                    '<span class="mini-name">' + esc(row.nama_display) + '</span>' +
                    '<span class="mini-score">' + row.skor.toFixed(2) + '</span>' +
                    '</div>';
            });
            $lb.html(html);
        });
    });
}

function startListPolling() {
    clearInterval(listPollTick);
    loadListPreviews();
    listPollTick = setInterval(loadListPreviews, LIST_POLL_MS);
}

function stopListPolling() {
    clearInterval(listPollTick);
    listPollTick = null;
}

/* ---- SHOW LIST VIEW ---- */
function showList() {
    clearInterval(pollTick);
    $('#view-leaderboard').removeClass('active');
    $('#view-list').addClass('active');
    history.replaceState(null, '', '?c=' + SHORT_CODE);
    document.title = 'Layar Publik — Daftar Leaderboard';
    startListPolling();
}

/* ---- SHOW LEADERBOARD VIEW ---- */
function showLeaderboard(idx) {
    stopListPolling();
    CURRENT_SLOT = idx;
    var slot = SLOTS_DATA[idx];
    $('#banner-text').html(slot.banner);
    $('#footer-exam-name').text(slot.exam_name + ' · Top ' + slot.top_limit);
    $('#lb-body').html('<tr><td colspan="5" class="text-center py-5" style="color:#aaa">Memuat data...</td></tr>');
    $('#lb-table').show();
    $('#empty-state').hide();
    if (MULTI_SLOT) {
        $('#lb-topbar').show();
        $('#leaderboard-wrap').css('height', 'calc(100vh - 172px)');
    }
    $('#view-list').removeClass('active');
    $('#view-leaderboard').addClass('active');
    history.replaceState(null, '', '?c=' + SHORT_CODE + '&slot=' + idx);
    document.title = 'Layar Publik — ' + slot.exam_name;
    clearInterval(pollTick);
    pollTimer = 0;
    pollTick = setInterval(function() {
        pollTimer--;
        if (pollTimer <= 0) {
            loadLeaderboard();
        } else {
            $('#countdown').text(pollTimer);
        }
    }, 1000);
}

/* ---- LOAD LEADERBOARD DATA ---- */
function loadLeaderboard() {
    pollTimer = 10;
    $('#countdown').text(pollTimer);
    $.getJSON(AJAX_URL, { c: SHORT_CODE, slot: CURRENT_SLOT }, function(res) {
        if (res.status !== 'success') return;
        $('#stat-peserta').html('<i class="fas fa-users me-1"></i>' + res.total_peserta + ' peserta');
        $('#stat-selesai').html('<i class="fas fa-check-circle me-1"></i>' + res.selesai + ' selesai');
        $('#last-update').text(res.updated_at);
        $('#last-update-footer').text(res.updated_at);
        if (!res.data || res.data.length === 0) {
            $('#lb-table').hide();
            $('#empty-state').css('display', 'flex');
        } else {
            $('#empty-state').hide();
            $('#lb-table').show();
            var html = '';
            res.data.forEach(function(row) {
                var statusBadge = row.skor_status === 'final'
                    ? '<span class="status-badge status-final">✔ Final</span>'
                    : '<span class="status-badge status-pending">⏳ Belum Final</span>';
                html += '<tr>' +
                    '<td>' + rankIcon(row.rank) + '</td>' +
                    '<td><strong>' + esc(row.nama_display) + '</strong>' +
                         '<div class="nama-kelas-sub">' + esc(row.kelas) + '</div></td>' +
                    '<td class="td-kelas" style="color:#858796;font-size:.9em">' + esc(row.kelas) + '</td>' +
                    '<td style="text-align:right"><span class="score-value">' + row.skor.toFixed(2) + '</span></td>' +
                    '<td style="text-align:center">' + statusBadge + '</td>' +
                    '</tr>';
            });
            $('#lb-body').html(html);
        }
    });
}

/* ---- EVENT HANDLERS ---- */
$(document).on('click', '.slot-card', function() {
    showLeaderboard(parseInt($(this).data('slot')));
});

$('#btn-back').on('click', function() {
    showList();
});

window.addEventListener('popstate', function() {
    var p = new URLSearchParams(window.location.search);
    var s = p.get('slot');
    if (s !== null && SLOTS_DATA[parseInt(s)]) {
        showLeaderboard(parseInt(s));
    } else {
        showList();
    }
});

/* ---- INIT ---- */
buildList();
var initParams = new URLSearchParams(window.location.search);
var initSlot   = initParams.get('slot');
if (!MULTI_SLOT || (initSlot !== null && SLOTS_DATA[parseInt(initSlot)])) {
    showLeaderboard(initSlot !== null ? parseInt(initSlot) : 0);
} else {
    showList();
}
</script>
</body>
</html>
}, 1000);

// --- Start ---
initUI();
loadLeaderboard();
</script>
</body>
</html>
