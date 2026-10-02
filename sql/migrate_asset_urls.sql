-- Migrasi URL aset: hapus domain hardcoded dari src= di konten HTML.
-- Jalankan sekali setelah deploy fitur domain-agnostic asset URLs.
--
-- Sebelum : src="https://domain.com/basepath/assets/uploads/soal/soal_xxx.png"
-- Sesudah : src="assets/uploads/soal/soal_xxx.png"
--
-- Aman dijalankan berulang (idempotent) — baris tanpa domain tidak tersentuh.

UPDATE cbt_questions
SET konten_soal = REGEXP_REPLACE(
    konten_soal,
    'src="https?://[^"]*/(assets/[^"]+)"',
    'src="$1"'
)
WHERE konten_soal REGEXP 'src="https?://';

UPDATE cbt_question_options
SET label = REGEXP_REPLACE(
    label,
    'src="https?://[^"]*/(assets/[^"]+)"',
    'src="$1"'
)
WHERE label REGEXP 'src="https?://';

UPDATE cbt_question_options
SET value_target = REGEXP_REPLACE(
    value_target,
    'src="https?://[^"]*/(assets/[^"]+)"',
    'src="$1"'
)
WHERE value_target REGEXP 'src="https?://';
