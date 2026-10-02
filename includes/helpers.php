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

/**
 * Menyisipkan BASE_URL ke relative src= di HTML saat ditampilkan ke browser.
 * Mengubah: src="assets/..." → src="BASE_URL/assets/..."
 */
function inject_domain_to_html(string $html): string {
    return str_replace('src="assets/', 'src="' . BASE_URL . 'assets/', $html);
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
