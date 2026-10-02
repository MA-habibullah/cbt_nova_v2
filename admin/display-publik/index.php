<?php
require_once '../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

log_activity('Akses halaman Pengaturan Layar Display Publik', null, null, null, 'akses');
?>

<!DOCTYPE html>
<html lang="id">

    <?php include '../../includes/header.php'; ?>

<body class="bg-gray-50 font-sans">
    <div class="flex min-h-screen flex-col md:flex-row">
        <?php include '../../includes/sidebar.php'; ?>

        <main class="flex-1 w-full overflow-x-hidden p-4 md:p-8 main-content">

            <!-- Page Header / Breadcrumb -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Pengaturan Layar</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small text-muted">
                            <li class="breadcrumb-item">Admin</li>
                            <li class="breadcrumb-item">Tampilan Publik</li>
                            <li class="breadcrumb-item active" aria-current="page">Pengaturan Layar</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Card: Daftar Jadwal Tayangan -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold text-gray-700 mb-0"><i class="fas fa-list me-2 text-primary"></i>Daftar Jadwal Tayangan</h6>
                    <button type="button" id="btn-add-slot" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Tambah Jadwal
                    </button>
                </div>

                <div id="slots-container">
                    <!-- Slot cards rendered by JS -->
                </div>
            </div>

            <!-- Generate Button -->
            <div class="mb-5">
                <button id="btn-generate" class="btn btn-primary btn-lg">
                    <i class="fas fa-tv me-1"></i> Generate URL Tayangan
                </button>
            </div>

            <!-- Result Card (hidden initially) -->
            <div id="result-card" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-5 d-none">
                <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-info-circle fa-lg"></i>
                    <span>URL berhasil dibuat. Bagikan URL ini ke layar TV/proyektor.</span>
                </div>
                <div class="input-group mb-3">
                    <input type="text" id="generated-url" class="form-control" readonly
                           placeholder="URL tayangan akan muncul di sini...">
                    <button id="btn-copy" class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-copy me-1"></i> Salin URL
                    </button>
                </div>
                <a id="btn-preview" href="#" target="_blank" class="btn btn-success">
                    <i class="fas fa-external-link-alt me-1"></i> Buka Layar
                </a>
                <span id="status-badge" class="badge bg-success ms-2 align-middle" style="display:none;font-size:.85rem">
                    <i class="fas fa-circle me-1" style="font-size:.6rem"></i> Aktif
                </span>
                <button id="btn-toggle" class="btn btn-outline-danger btn-sm ms-2" style="display:none">
                    <i class="fas fa-ban me-1"></i> Nonaktifkan
                </button>
            </div>

            <!-- Riwayat URL Tayangan -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-5">
                <div class="mb-4">
                    <h6 class="fw-bold text-gray-700 mb-0"><i class="fas fa-history me-2 text-primary"></i>Riwayat URL Tayangan</h6>
                </div>
                <div id="token-history">
                    <div class="text-center text-muted py-4 small"><i class="fas fa-spinner fa-spin me-1"></i> Memuat...</div>
                </div>
            </div>

        </main>
    </div>

<!-- Edit Token Modal -->
<div class="modal fade" id="modal-edit-token" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Edit Tayangan Publik</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning small mb-3">
          <i class="fas fa-exclamation-triangle me-1"></i>
          Menyimpan perubahan akan menghasilkan URL baru. <strong>URL lama tidak bisa diakses lagi.</strong>
          Label akan diperbarui otomatis dari nama ujian pertama.
        </div>
        <hr class="mt-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Daftar Jadwal</h6>
          <button type="button" id="edit-add-slot" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-plus me-1"></i> Tambah Jadwal
          </button>
        </div>
        <div id="edit-slots-container">
          <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-1"></i> Memuat konfigurasi...</div>
        </div>
        <div id="edit-error" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn-save-edit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i> Simpan Perubahan
        </button>
      </div>
    </div>
  </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {

        var slotCount = 1;

        // SLOT TEMPLATE FUNCTION
        function newSlotHTML(n) {
            return '<div class="slot-card border rounded-3 p-4 mb-3 bg-light position-relative" data-slot="' + n + '">' +
                '<div class="d-flex justify-content-between align-items-center mb-3">' +
                    '<span class="badge bg-primary">Jadwal #' + n + '</span>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-slot">' +
                        '<i class="fas fa-trash-alt me-1"></i> Hapus' +
                    '</button>' +
                '</div>' +
                '<div class="row g-3 mb-3">' +
                    '<div class="col-md-4">' +
                        '<label class="form-label fw-semibold small text-uppercase text-muted">Tanggal Ujian</label>' +
                        '<input type="date" class="form-control slot-tanggal" value="' + new Date().toISOString().substring(0, 10) + '">' +
                    '</div>' +
                    '<div class="col-md-4">' +
                        '<label class="form-label fw-semibold small text-uppercase text-muted">Jadwal Ujian</label>' +
                        '<select class="form-select slot-exam" disabled>' +
                            '<option value="">-- Pilih Ujian --</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="col-md-4">' +
                        '<label class="form-label fw-semibold small text-uppercase text-muted">Kelas</label>' +
                        '<select class="form-select slot-class" disabled>' +
                            '<option value="all">Semua Kelas</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="row g-3">' +
                    '<div class="col-md-3">' +
                        '<label class="form-label fw-semibold small text-uppercase text-muted">Jumlah Peringkat</label>' +
                        '<select class="form-select slot-top">' +
                            '<option value="10" selected>Top 10</option>' +
                            '<option value="25">Top 25</option>' +
                            '<option value="50">Top 50</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="col-md-3 d-flex align-items-end pb-2">' +
                        '<div class="form-check form-switch">' +
                            '<input class="form-check-input slot-mask" type="checkbox" role="switch">' +
                            '<label class="form-check-label small">Samarkan Nama</label>' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<label class="form-label fw-semibold small text-uppercase text-muted">Template Banner</label>' +
                        '<textarea class="form-control slot-banner" rows="2">🔴 LIVE PERINGKAT: [NAMA_KEGIATAN] - JENJANG [JENIS_JENJANG] - KELAS [JENIS_KELAS]</textarea>' +
                        '<div class="form-text">Shortcode: <code>[NAMA_KEGIATAN]</code> <code>[JENIS_JENJANG]</code> <code>[JENIS_KELAS]</code></div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }

        // Render first slot on load
        $('#slots-container').html(newSlotHTML(1));
        updateRemoveButtons();

        // Trigger date load for initial slot
        $('#slots-container .slot-tanggal').trigger('change');

        // ADD SLOT
        $('#btn-add-slot').on('click', function() {
            slotCount++;
            $('#slots-container').append(newSlotHTML(slotCount));
            // Trigger today date load on new slot
            $('#slots-container .slot-card:last .slot-tanggal').trigger('change');
            updateRemoveButtons();
        });

        // REMOVE SLOT (delegated)
        $(document).on('click', '.btn-remove-slot', function() {
            if ($('.slot-card').length <= 1) return;
            $(this).closest('.slot-card').remove();
            // Re-number remaining slots
            $('.slot-card').each(function(i) {
                $(this).find('.badge').text('Jadwal #' + (i + 1));
            });
            updateRemoveButtons();
        });

        function updateRemoveButtons() {
            var count = $('.slot-card').length;
            $('.btn-remove-slot').toggle(count > 1);
        }

        // DATE CHANGE (delegated per slot)
        $(document).on('change', '.slot-tanggal', function() {
            var $card = $(this).closest('.slot-card');
            var tgl   = $(this).val();
            var $exam = $card.find('.slot-exam');
            var $cls  = $card.find('.slot-class');
            $exam.prop('disabled', true).html('<option value="">Memuat...</option>');
            $cls.prop('disabled', true).html('<option value="all">Semua Kelas</option>');
            $.post('ajax/get-exams.php', {tanggal: tgl}, function(res) {
                var opts = '<option value="">-- Pilih Ujian --</option>';
                if (res.status === 'success' && res.data.length) {
                    res.data.forEach(function(e) {
                        opts += '<option value="' + e.id + '">' + e.nama_mapel_ujian + ' (' + e.mulai_pada.substring(0, 16) + ')</option>';
                    });
                } else {
                    opts = '<option value="">Tidak ada ujian pada tanggal ini</option>';
                }
                $exam.html(opts).prop('disabled', false);
            }, 'json');
        });

        // EXAM CHANGE (delegated per slot)
        $(document).on('change', '.slot-exam', function() {
            var $card = $(this).closest('.slot-card');
            var eid   = $(this).val();
            var $cls  = $card.find('.slot-class');
            if (!eid) {
                $cls.prop('disabled', true);
                return;
            }
            $.post('ajax/get-classes.php', {exam_id: eid}, function(res) {
                var opts = '<option value="all">Semua Kelas</option>';
                if (res.status === 'success') {
                    res.data.forEach(function(k) {
                        opts += '<option value="' + k.id + '">' + k.jenjang + ' - ' + k.nama_kelas + '</option>';
                    });
                }
                $cls.html(opts).prop('disabled', false);
            }, 'json');
        });

        // GENERATE URL
        $('#btn-generate').on('click', function() {
            // Collect all slots
            var slots = [];
            $('.slot-card').each(function() {
                var $c = $(this);
                slots.push({
                    exam_id:    $c.find('.slot-exam').val(),
                    class_id:   $c.find('.slot-class').val(),
                    top_limit:  $c.find('.slot-top').val(),
                    mask_names: $c.find('.slot-mask').is(':checked') ? 1 : 0,
                    banner_tpl: $c.find('.slot-banner').val()
                });
            });

            var btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memproses...');

            $.post('ajax/generate-url.php', {
                slots: JSON.stringify(slots)
            }, function(res) {
                if (res.status === 'success') {
                    $('#generated-url').val(res.url);
                    $('#btn-preview').attr('href', res.url);
                    $('#result-card').removeClass('d-none');
                    $('html, body').animate({scrollTop: $('#result-card').offset().top - 20}, 400);
                    // Update toggle button state
                    var tHash = res.token_hash || '';
                    $('#btn-toggle').show().data('hash', tHash).data('active', true)
                        .html('<i class="fas fa-ban me-1"></i> Nonaktifkan')
                        .removeClass('btn-outline-success').addClass('btn-outline-danger');
                    $('#status-badge').show().html('<i class="fas fa-circle me-1" style="font-size:.6rem"></i> Aktif')
                        .removeClass('bg-danger').addClass('bg-success');
                    loadTokenHistory();
                } else {
                    Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                }
            }, 'json').always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-tv me-1"></i> Generate URL Tayangan');
            });
        });

        // COPY URL
        $('#btn-copy').on('click', function() {
            var url = $('#generated-url').val();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    Swal.fire({icon: 'success', title: 'Disalin!', timer: 1200, showConfirmButton: false});
                });
            }
        });

        // TOGGLE STATUS
        $('#btn-toggle').on('click', function() {
            var isActive = $(this).data('active') === true;
            var hash     = $(this).data('hash');
            Swal.fire({
                title: isActive ? 'Nonaktifkan Dashboard?' : 'Aktifkan Dashboard?',
                text:  isActive ? 'URL tidak bisa diakses sampai diaktifkan kembali.' : 'URL tayangan publik akan aktif kembali.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: isActive ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: isActive ? '#e74c3c' : '#28a745'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post('ajax/toggle-status.php', {
                    token_hash: hash,
                    action: isActive ? 'deactivate' : 'activate'
                }, function(res) {
                    if (res.status === 'success') {
                        var nowActive = res.is_active == 1;
                        $('#btn-toggle').data('active', nowActive)
                            .html(nowActive ? '<i class="fas fa-ban me-1"></i> Nonaktifkan' : '<i class="fas fa-check me-1"></i> Aktifkan')
                            .removeClass('btn-outline-danger btn-outline-success')
                            .addClass(nowActive ? 'btn-outline-danger' : 'btn-outline-success');
                        $('#status-badge')
                            .html(nowActive ? '<i class="fas fa-circle me-1" style="font-size:.6rem"></i> Aktif' : '<i class="fas fa-circle me-1" style="font-size:.6rem"></i> Nonaktif')
                            .removeClass('bg-success bg-danger')
                            .addClass(nowActive ? 'bg-success' : 'bg-danger');
                        loadTokenHistory();
                    } else {
                        Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    }
                }, 'json');
            });
        });

        // HISTORY TABLE
        function loadTokenHistory() {
            $.get('ajax/get-tokens.php', function(res) {
                if (res.status !== 'success' || !res.data.length) {
                    $('#token-history').html('<div class="text-center text-muted py-4 small">Belum ada URL yang dibuat.</div>');
                    return;
                }
                var html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">' +
                    '<thead class="table-light"><tr>' +
                    '<th>Label</th><th>Dibuat Oleh</th><th>Dibuat</th><th>Status</th><th class="text-end">Aksi</th>' +
                    '</tr></thead><tbody>';
                res.data.forEach(function(row) {
                    var active = row.is_active == 1;
                    var badge  = active
                        ? '<span class="badge bg-success">Aktif</span>'
                        : '<span class="badge bg-danger">Nonaktif</span>';
                    var creatorBadge = row.created_by_role === 'guru'
                        ? '<span class="badge bg-info-subtle text-info border border-info-subtle">'+$('<span>').text(row.creator_name).html()+'</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Admin</span>';
                    var btn = active
                        ? '<button class="btn btn-outline-danger btn-sm hist-toggle" data-hash="'+row.token_hash+'" data-active="1">Nonaktifkan</button>'
                        : '<button class="btn btn-outline-success btn-sm hist-toggle" data-hash="'+row.token_hash+'" data-active="0">Aktifkan</button>';
                    var editBtn = '<button class="btn btn-outline-primary btn-sm hist-edit ms-1" data-hash="'+row.token_hash+'" title="Edit Jadwal"><i class="fas fa-pencil-alt"></i></button>';
                    var copyBtn = row.url
                        ? '<button class="btn btn-outline-secondary btn-sm hist-copy ms-1" data-url="'+$('<span>').text(row.url).html()+'" title="Salin URL"><i class="fas fa-copy"></i></button>'
                        : '';
                    var delBtn = '<button class="btn btn-outline-danger btn-sm hist-delete ms-1" data-hash="'+row.token_hash+'" title="Hapus"><i class="fas fa-trash-alt"></i></button>';

                    // Collapse toggle
                    var cid = 'sc-'+row.token_hash.substring(0,10);
                    var hasSlots = row.slots_display && row.slots_display.length > 0;
                    var collapseBtn = hasSlots
                        ? '<button class="btn btn-link btn-sm p-0 ms-1 hist-collapse" data-cid="'+cid+'" title="Lihat jadwal"><i class="fas fa-chevron-down"></i></button>'
                        : '';

                    html += '<tr>' +
                        '<td class="small fw-semibold">'+$('<span>').text(row.label).html()+collapseBtn+'</td>' +
                        '<td>'+creatorBadge+'</td>' +
                        '<td class="small text-muted">'+row.created_at+'</td>' +
                        '<td>'+badge+'</td>' +
                        '<td class="text-end text-nowrap">'+btn+editBtn+copyBtn+delBtn+'</td></tr>';

                    // Collapse row
                    if (hasSlots) {
                        var slotList = row.slots_display.map(function(s, i) {
                            var cls = s.class_id === 'all' ? 'Semua Kelas' : 'Kelas '+s.class_id;
                            return '<div class="d-flex align-items-center gap-2 py-1 border-bottom">' +
                                '<span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle">'+(i+1)+'</span>' +
                                '<span class="small fw-semibold flex-grow-1">'+$('<span>').text(s.exam_name).html()+'</span>' +
                                '<span class="badge bg-light text-muted border small">'+$('<span>').text(cls).html()+'</span>' +
                                '<span class="badge bg-light text-muted border small">Top '+s.top_limit+'</span></div>';
                        }).join('');
                        html += '<tr id="'+cid+'" style="display:none"><td colspan="5" class="px-4 py-2 bg-light border-top-0">' +
                            '<div class="small text-muted fw-semibold mb-1"><i class="fas fa-list me-1 text-primary"></i>Daftar Jadwal</div>' +
                            slotList + '</td></tr>';
                    }
                });
                html += '</tbody></table></div>';
                $('#token-history').html(html);
            }, 'json');
        }

        // History table toggle (delegated)
        $(document).on('click', '.hist-toggle', function() {
            var $btn   = $(this);
            var hash   = $btn.data('hash');
            var active = $btn.data('active') == 1;
            var action = active ? 'deactivate' : 'activate';
            Swal.fire({
                title: active ? 'Nonaktifkan?' : 'Aktifkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal',
                confirmButtonColor: active ? '#e74c3c' : '#28a745'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post('ajax/toggle-status.php', { token_hash: hash, action: action }, function(res) {
                    if (res.status === 'success') {
                        loadTokenHistory();
                    } else {
                        Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    }
                }, 'json');
            });
        });

        // Collapse toggle (delegated)
        $(document).on('click', '.hist-collapse', function() {
            var cid = $(this).data('cid');
            var $row = $('#' + cid);
            var visible = $row.is(':visible');
            $row.toggle(!visible);
            $(this).find('i').toggleClass('fa-chevron-down', visible).toggleClass('fa-chevron-up', !visible);
        });

        // History table delete (delegated)
        $(document).on('click', '.hist-delete', function() {
            var hash = $(this).data('hash');
            Swal.fire({
                title: 'Hapus URL ini?',
                text: 'URL yang sudah dibagikan tidak bisa diakses lagi.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e74c3c'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post('ajax/delete-token.php', { token_hash: hash }, function(res) {
                    if (res.status === 'success') {
                        loadTokenHistory();
                    } else {
                        Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    }
                }, 'json');
            });
        });

        // ---- EDIT MODAL ----
        var editModal = new bootstrap.Modal(document.getElementById('modal-edit-token'));
        var currentEditHash = '';

        function editSlotCardHTML(slot, idx) {
            var classOpts = '<option value="all"' + (slot.class_id === 'all' ? ' selected' : '') + '>Semua Kelas</option>';
            (slot.classes || []).forEach(function(c) {
                var sel = (String(slot.class_id) === String(c.id)) ? ' selected' : '';
                classOpts += '<option value="' + c.id + '"' + sel + '>' + c.jenjang + ' - ' + c.nama_kelas + '</option>';
            });
            var topOpts = [10, 25, 50].map(function(v) {
                return '<option value="' + v + '"' + (slot.top_limit == v ? ' selected' : '') + '>Top ' + v + '</option>';
            }).join('');
            return '<div class="edit-slot-card border rounded-3 p-3 mb-3 bg-light" data-exam-id="' + slot.exam_id + '" data-type="existing">' +
                '<div class="d-flex justify-content-between align-items-start mb-2">' +
                    '<strong class="small text-dark">' + $('<span>').text(slot.exam_name).html() + '</strong>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger edit-remove-slot ms-2 flex-shrink-0">Hapus</button>' +
                '</div>' +
                '<div class="row g-2">' +
                    '<div class="col-md-5"><label class="form-label small text-muted mb-1">Kelas</label>' +
                    '<select class="form-select form-select-sm edit-slot-class">' + classOpts + '</select></div>' +
                    '<div class="col-md-3"><label class="form-label small text-muted mb-1">Peringkat</label>' +
                    '<select class="form-select form-select-sm edit-slot-top">' + topOpts + '</select></div>' +
                    '<div class="col-md-4 d-flex align-items-end pb-1"><div class="form-check form-switch">' +
                    '<input class="form-check-input edit-slot-mask" type="checkbox" role="switch"' + (slot.mask_names ? ' checked' : '') + '>' +
                    '<label class="form-check-label small">Samarkan Nama</label></div></div>' +
                '</div>' +
                '<div class="mt-2"><label class="form-label small text-muted mb-1">Template Banner</label>' +
                '<textarea class="form-control form-control-sm edit-slot-banner" rows="2">' + $('<span>').text(slot.banner_tpl).html() + '</textarea></div>' +
            '</div>';
        }

        function editNewSlotHTML() {
            return '<div class="edit-slot-card border rounded-3 p-3 mb-3 bg-white" data-type="new">' +
                '<div class="d-flex justify-content-end mb-2">' +
                    '<button type="button" class="btn btn-sm btn-outline-danger edit-remove-slot">Hapus</button>' +
                '</div>' +
                '<div class="row g-2 mb-2">' +
                    '<div class="col-md-4"><label class="form-label small text-muted mb-1">Tanggal</label>' +
                    '<input type="date" class="form-control form-control-sm edit-slot-tanggal" value="' + new Date().toISOString().substring(0,10) + '"></div>' +
                    '<div class="col-md-8"><label class="form-label small text-muted mb-1">Jadwal Ujian</label>' +
                    '<select class="form-select form-select-sm edit-slot-exam" disabled><option value="">-- Pilih Ujian --</option></select></div>' +
                '</div>' +
                '<div class="row g-2">' +
                    '<div class="col-md-5"><label class="form-label small text-muted mb-1">Kelas</label>' +
                    '<select class="form-select form-select-sm edit-slot-class" disabled><option value="all">Semua Kelas</option></select></div>' +
                    '<div class="col-md-3"><label class="form-label small text-muted mb-1">Peringkat</label>' +
                    '<select class="form-select form-select-sm edit-slot-top">' +
                    '<option value="10" selected>Top 10</option><option value="25">Top 25</option><option value="50">Top 50</option>' +
                    '</select></div>' +
                    '<div class="col-md-4 d-flex align-items-end pb-1"><div class="form-check form-switch">' +
                    '<input class="form-check-input edit-slot-mask" type="checkbox" role="switch">' +
                    '<label class="form-check-label small">Samarkan Nama</label></div></div>' +
                '</div>' +
                '<div class="mt-2"><label class="form-label small text-muted mb-1">Template Banner</label>' +
                '<textarea class="form-control form-control-sm edit-slot-banner" rows="2">🔴 LIVE PERINGKAT: [NAMA_KEGIATAN] - JENJANG [JENIS_JENJANG] - KELAS [JENIS_KELAS]</textarea></div>' +
            '</div>';
        }

        $(document).on('click', '.hist-edit', function() {
            var hash  = $(this).data('hash');
            currentEditHash = hash;
            $('#edit-slots-container').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-1"></i> Memuat konfigurasi...</div>');
            $('#edit-error').addClass('d-none');
            editModal.show();
            $.get('ajax/get-token-config.php', { token_hash: hash }, function(res) {
                if (res.status !== 'success') {
                    $('#edit-slots-container').html('<div class="alert alert-danger">Gagal memuat konfigurasi.</div>');
                    return;
                }
                // label auto-generated by server, no input needed
                var html = '';
                (res.slots || []).forEach(function(slot, i) { html += editSlotCardHTML(slot, i); });
                $('#edit-slots-container').html(html || '<div class="text-muted small text-center py-3">Belum ada jadwal.</div>');
            }, 'json').fail(function() {
                $('#edit-slots-container').html('<div class="alert alert-danger">Gagal memuat konfigurasi.</div>');
            });
        });

        $('#edit-add-slot').on('click', function() {
            $('#edit-slots-container').append(editNewSlotHTML());
            var $card = $('#edit-slots-container .edit-slot-card:last');
            $card.find('.edit-slot-tanggal').trigger('change');
        });

        $(document).on('click', '.edit-remove-slot', function() {
            $(this).closest('.edit-slot-card').remove();
        });

        // Date change for new slots in edit modal
        $(document).on('change', '.edit-slot-tanggal', function() {
            var $card = $(this).closest('.edit-slot-card');
            var tgl   = $(this).val();
            var $exam = $card.find('.edit-slot-exam');
            var $cls  = $card.find('.edit-slot-class');
            $exam.prop('disabled', true).html('<option value="">Memuat...</option>');
            $cls.prop('disabled', true).html('<option value="all">Semua Kelas</option>');
            $.post('ajax/get-exams.php', {tanggal: tgl}, function(res) {
                var opts = '<option value="">-- Pilih Ujian --</option>';
                if (res.status === 'success' && res.data.length) {
                    res.data.forEach(function(e) {
                        opts += '<option value="' + e.id + '">' + e.nama_mapel_ujian + ' (' + e.mulai_pada.substring(0,16) + ')</option>';
                    });
                } else {
                    opts = '<option value="">Tidak ada ujian pada tanggal ini</option>';
                }
                $exam.html(opts).prop('disabled', false);
            }, 'json');
        });

        $(document).on('change', '.edit-slot-exam', function() {
            var $card = $(this).closest('.edit-slot-card');
            var eid   = $(this).val();
            var $cls  = $card.find('.edit-slot-class');
            if (!eid) { $cls.prop('disabled', true); return; }
            $.post('ajax/get-classes.php', {exam_id: eid}, function(res) {
                var opts = '<option value="all">Semua Kelas</option>';
                if (res.status === 'success') {
                    res.data.forEach(function(k) {
                        opts += '<option value="' + k.id + '">' + k.jenjang + ' - ' + k.nama_kelas + '</option>';
                    });
                }
                $cls.html(opts).prop('disabled', false);
            }, 'json');
        });

        $('#btn-save-edit').on('click', function() {
            var slots = [];
            $('.edit-slot-card').each(function() {
                var $c = $(this);
                var type = $c.data('type');
                var exam_id = (type === 'existing') ? $c.data('exam-id') : $c.find('.edit-slot-exam').val();
                if (!exam_id) return;
                slots.push({
                    exam_id:    exam_id,
                    class_id:   $c.find('.edit-slot-class').val(),
                    top_limit:  $c.find('.edit-slot-top').val(),
                    mask_names: $c.find('.edit-slot-mask').is(':checked') ? 1 : 0,
                    banner_tpl: $c.find('.edit-slot-banner').val()
                });
            });
            if (!slots.length) { $('#edit-error').removeClass('d-none').text('Minimal satu jadwal harus ada.'); return; }
            $('#edit-error').addClass('d-none');
            var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...');
            $.post('ajax/edit-token.php', {
                old_token_hash: currentEditHash,
                slots: JSON.stringify(slots)
            }, function(res) {
                if (res.status === 'success') {
                    editModal.hide();
                    loadTokenHistory();
                    Swal.fire({icon:'success', title:'Berhasil!', text:'Konfigurasi diperbarui. URL baru telah dihasilkan.', timer:2000, showConfirmButton:false});
                } else {
                    $('#edit-error').removeClass('d-none').text(res.message || 'Terjadi kesalahan.');
                }
            }, 'json').always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Simpan Perubahan');
            });
        });

        // History table copy URL (delegated)
        $(document).on('click', '.hist-copy', function() {
            var url = $(this).data('url');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    Swal.fire({icon: 'success', title: 'URL Disalin!', timer: 1200, showConfirmButton: false});
                });
            }
        });

        // Load history on page load
        loadTokenHistory();

    });
    </script>
</body>
</html>
