<?php
session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

use PhpOffice\PhpWord\IOFactory;

$is_guru = ($_SESSION['role'] ?? '') === 'guru' && isset($_SESSION['teacher_id']);
if (!isset($_SESSION['admin_id']) && !$is_guru) {
    header("Location: " . BASE_URL . "index.php"); exit;
}
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metode tidak diizinkan");
}

// 1. Ambil data dari POST dan FILES
$bank_soal_id = $_POST['bank_soal_id'] ?? null;
// Disesuaikan menjadi 'file_soal' sesuai hasil print_r Anda
$file_word = $_FILES['file_soal'] ?? null; 

// 2. Validasi input awal & Magic Bytes MIME Type
if (!$bank_soal_id || !$file_word || ($file_word['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file_word['tmp_name'] ?? '')) {
    $r = $is_guru ? BASE_URL."guru/bank-soal/upload.php" : '../upload.php'; header("Location: $r?id=$bank_soal_id&msg=error_data");
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file_word['tmp_name']);
finfo_close($finfo);

$allowed_mimes = [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
    'application/octet-stream',
    'application/zip'
];
$ext = strtolower(pathinfo($file_word['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, ['docx', 'doc']) || !in_array($mime, $allowed_mimes)) {
    $r = $is_guru ? BASE_URL."guru/bank-soal/upload.php" : '../upload.php';
    header("Location: $r?id=$bank_soal_id&msg=invalid_format");
    exit;
}

try {
    // 3. Load Dokumen Word
    $phpWord = IOFactory::load($file_word['tmp_name']);
    $pdo->beginTransaction();

    $count = 0;

    // 4. Iterasi melalui setiap Section dan Element di Word
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            
            // Kita hanya mencari elemen berupa Tabel (karena struktur template kita adalah Tabel)
            if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                $rows = $element->getRows();
                $dataSoal = [];

                // Kolom konten yang perlu dipertahankan formatnya sebagai HTML
                static $HTML_FIELDS = [
                    'SOAL',
                    'OPSI_A', 'OPSI_B', 'OPSI_C', 'OPSI_D', 'OPSI_E',
                    'MATCH_L1', 'MATCH_R1', 'MATCH_L2', 'MATCH_R2',
                    'MATCH_L3', 'MATCH_R3', 'MATCH_L4', 'MATCH_R4',
                    'MATCH_L5', 'MATCH_R5',
                ];

                foreach ($rows as $row) {
                    $cells = $row->getCells();
                    if (count($cells) >= 2) {
                        $field = strtoupper(trim(getWordText($cells[0])));
                        if ($field !== 'FIELD' && $field !== '') {
                            $isi = in_array($field, $HTML_FIELDS)
                                 ? trim(getWordHtml($cells[1]))
                                 : trim(getWordText($cells[1]));
                            $dataSoal[$field] = $isi;
                        }
                    }
                }

                // Cek apakah tabel minimal berisi TIPE dan SOAL
                if (isset($dataSoal['TIPE']) && isset($dataSoal['SOAL'])) {
                    $tipe = normalize_tipe_soal($dataSoal['TIPE']);
                    $konten = $dataSoal['SOAL'];
                    $bobot = isset($dataSoal['BOBOT']) ? (float)$dataSoal['BOBOT'] : 1.00;
                    $kesulitan = 'sedang'; 
                    $acak_opsi = 1;

                    // A. Simpan ke tabel cbt_questions
                    $stmtQ = $pdo->prepare("INSERT INTO cbt_questions (bank_soal_id, tipe, konten_soal, tingkat_kesulitan, acak_opsi, bobot_skor) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtQ->execute([$bank_soal_id, $tipe, $konten, $kesulitan, $acak_opsi, $bobot]);
                    $question_id = $pdo->lastInsertId();

                    // B. Simpan Jawaban/Opsi berdasarkan Tipe
                    if (in_array($tipe, ['pg', 'pg_kompleks'])) {
                        $labels = ['A', 'B', 'C', 'D', 'E'];
                        $kunci_raw = $dataSoal['KUNCI'] ?? '';
                        $kunci_array = explode(',', strtoupper(str_replace(' ', '', $kunci_raw)));

                        foreach ($labels as $l) {
                            $key_opsi = "OPSI_" . $l;
                            if (isset($dataSoal[$key_opsi]) && $dataSoal[$key_opsi] !== '') {
                                $is_correct = in_array($l, $kunci_array) ? 1 : 0;
                                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                                $stmtOpt->execute([$question_id, $l, $dataSoal[$key_opsi], $is_correct]);
                            }
                        }

                    } elseif ($tipe === 'benar_salah') {
                        // KUNCI di Word template adalah 'B' (Benar) atau 'S' (Salah)
                        $kunci_bs = strtoupper(trim($dataSoal['KUNCI'] ?? 'B'));
                        $opsi_bs = [
                            ['label' => 'B', 'teks' => 'Benar'],
                            ['label' => 'S', 'teks' => 'Salah'],
                        ];
                        foreach ($opsi_bs as $opsi) {
                            $is_correct = ($kunci_bs === $opsi['label']) ? 1 : 0;
                            $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                            $stmtOpt->execute([$question_id, $opsi['label'], $opsi['teks'], $is_correct]);
                        }

                    } elseif ($tipe === 'menjodohkan') {
                        // Iterasi MATCH_L1/R1 sampai MATCH_L5/R5 sesuai template
                        for ($i = 1; $i <= 5; $i++) {
                            $l_key = "MATCH_L" . $i;
                            $r_key = "MATCH_R" . $i;
                            if (!empty($dataSoal[$l_key]) && !empty($dataSoal[$r_key])) {
                                $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                                $stmtOpt->execute([$question_id, $dataSoal[$l_key], $dataSoal[$r_key], 1]);
                            }
                        }

                    } elseif ($tipe === 'isian') {
                        // Kunci jawaban isian singkat (mendukung multi-kunci dengan delimiter ; atau |)
                        if (!empty($dataSoal['KUNCI'])) {
                            $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                            $stmtOpt->execute([$question_id, 'KUNCI', $dataSoal['KUNCI'], 1]);
                        }

                    } elseif ($tipe === 'essay') {
                        // Rubrik/kunci esai (opsional)
                        if (!empty($dataSoal['KUNCI'])) {
                            $stmtOpt = $pdo->prepare("INSERT INTO cbt_question_options (question_id, label, value_target, is_correct) VALUES (?, ?, ?, ?)");
                            $stmtOpt->execute([$question_id, 'RUBRIK', $dataSoal['KUNCI'], 1]);
                        }
                    }

                    $count++;
                }
            }
        }
    }

    $pdo->commit();
    $redir = $is_guru ? BASE_URL."guru/bank-soal/upload.php" : '../upload.php';
    header("Location: $redir?id=$bank_soal_id&msg=success&count=$count");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = urlencode($e->getMessage());
    $redir = $is_guru ? BASE_URL."guru/bank-soal/upload.php" : '../upload.php';
    header("Location: $redir?id=$bank_soal_id&msg=error&error=$error");
    exit;
}

/** Normalize teks dari PhpWord: decode HTML entities terlebih dahulu lalu escape ulang */
function encodeWordText($text) {
    return htmlspecialchars(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ENT_NOQUOTES, 'UTF-8');
}

/** Ambil teks murni dari sel — dipakai untuk field metadata (TIPE, KUNCI, BOBOT, dll.) */
function getWordText($cell) {
    $text = '';
    foreach ($cell->getElements() as $el) {
        if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
            foreach ($el->getElements() as $te) {
                if (method_exists($te, 'getText')) $text .= $te->getText();
            }
        } elseif (method_exists($el, 'getText')) {
            $text .= $el->getText();
        } elseif ($el instanceof \PhpOffice\PhpWord\Element\ListItem) {
            $text .= $el->getTextObject()->getText();
        }
    }
    return $text;
}

/** Konversi sel Word ke HTML, mempertahankan bold/italic/underline/paragraf/list */
function getWordHtml($cell) {
    $parts     = [];
    $listItems = [];
    $isOrdered = false;

    $flushList = function () use (&$parts, &$listItems, &$isOrdered) {
        if (!empty($listItems)) {
            $tag     = $isOrdered ? 'ol' : 'ul';
            $parts[] = ['block', "<$tag><li>" . implode('</li><li>', $listItems) . "</li></$tag>"];
            $listItems = [];
        }
    };

    $detectOrdered = function ($el) {
        $style = method_exists($el, 'getStyle') ? $el->getStyle() : null;
        if (!$style) return false;

        // Modern path (PhpWord >= 0.10): look up actual w:numFmt from numbering.xml
        $numStyleName = method_exists($style, 'getNumStyle') ? $style->getNumStyle() : null;
        if ($numStyleName) {
            $numbering = \PhpOffice\PhpWord\Style::getStyle($numStyleName);
            if ($numbering instanceof \PhpOffice\PhpWord\Style\Numbering) {
                $levels  = $numbering->getLevels();
                $levelId = method_exists($el, 'getDepth') ? (int) $el->getDepth() : 0;
                if (isset($levels[$levelId])) {
                    $fmt = $levels[$levelId]->getFormat();
                    return $fmt !== null && $fmt !== 'bullet' && $fmt !== 'none';
                }
            }
        }

        // Legacy fallback: use TYPE_NUMBER / TYPE_NUMBER_NESTED / TYPE_ALPHANUM constants
        if (method_exists($style, 'getListType')) {
            return in_array($style->getListType(), [
                \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER,
                \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER_NESTED,
                \PhpOffice\PhpWord\Style\ListItem::TYPE_ALPHANUM,
            ]);
        }

        return false;
    };

    foreach ($cell->getElements() as $el) {
        $isList = $el instanceof \PhpOffice\PhpWord\Element\ListItem
               || $el instanceof \PhpOffice\PhpWord\Element\ListItemRun;

        if ($isList) {
            $ordered = $detectOrdered($el);
            if (!empty($listItems) && $ordered !== $isOrdered) { $flushList(); }
            $isOrdered   = $ordered;
            $listItems[] = ($el instanceof \PhpOffice\PhpWord\Element\ListItemRun)
                ? wordRunToHtml($el)
                : encodeWordText($el->getTextObject()->getText());
        } else {
            $flushList();
            if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
                $h = wordRunToHtml($el);
                if ($h !== '') $parts[] = ['inline', $h];
            } elseif ($el instanceof \PhpOffice\PhpWord\Element\Text) {
                $h = wordApplyFont(encodeWordText($el->getText()), $el->getFontStyle());
                if ($h !== '') $parts[] = ['inline', $h];
            }
        }
    }
    $flushList();

    // <br><br> between consecutive text paragraphs; block elements (lists) stand alone
    $html = '';
    foreach ($parts as $i => [$type, $content]) {
        if ($i > 0 && $type === 'inline' && $parts[$i - 1][0] === 'inline') {
            $html .= '<br><br>';
        }
        $html .= $content;
    }
    return $html;
}

/** Konversi TextRun / ListItemRun ke HTML inline */
function wordRunToHtml($run) {
    $html = '';
    foreach ($run->getElements() as $el) {
        if ($el instanceof \PhpOffice\PhpWord\Element\TextBreak) {
            $html .= '<br>';
        } elseif ($el instanceof \PhpOffice\PhpWord\Element\Text) {
            $html .= wordApplyFont(encodeWordText($el->getText()), $el->getFontStyle());
        } elseif (method_exists($el, 'getText')) {
            $html .= encodeWordText($el->getText());
        }
    }
    return $html;
}

/** Balut teks dengan tag HTML sesuai format font */
function wordApplyFont($text, $font) {
    if (!$text || !($font instanceof \PhpOffice\PhpWord\Style\Font)) return $text;
    $ul = $font->getUnderline();
    if ($ul && $ul !== 'none')  $text = '<u>' . $text . '</u>';
    if ($font->isItalic())      $text = '<em>' . $text . '</em>';
    if ($font->isBold())        $text = '<strong>' . $text . '</strong>';
    return $text;
}