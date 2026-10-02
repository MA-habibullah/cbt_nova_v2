-- ================================================================
-- CBT Nova — Performance Improvements SQL
-- Target: MySQL 8.0+
-- Script ini IDEMPOTENT: aman dijalankan berulang kali
--
-- Cara eksekusi:
--   mysql -u root -p cbt_nova < sql/performance_improvements.sql
--
-- URUTAN EKSEKUSI:
--   1. Jalankan BAGIAN 1 (diagnostic) — catat baseline
--   2. Jalankan BAGIAN 2 (apply index dari backup)
--   3. Jalankan BAGIAN 3 (index baru)
--   4. Jalankan BAGIAN 4 (opsional)
--   5. Jalankan BAGIAN 5 (hapus index duplikat)
--   6. Jalankan BAGIAN 6 (verify)
-- ================================================================

-- ================================================================
-- BAGIAN 1: DIAGNOSTIC
-- Jalankan dulu untuk tahu kondisi index di production saat ini
-- ================================================================

SELECT
    s.table_name,
    s.index_name,
    GROUP_CONCAT(s.column_name ORDER BY s.seq_in_index SEPARATOR ', ') AS columns,
    s.non_unique,
    CASE s.non_unique WHEN 0 THEN 'UNIQUE' ELSE 'INDEX' END AS type
FROM information_schema.statistics s
WHERE s.table_schema = DATABASE()
  AND s.table_name IN (
      'cbt_exam_participants',
      'cbt_student_answers',
      'cbt_cheat_logs',
      'cbt_question_options',
      'cbt_exam_questions',
      'cbt_exams',
      'cbt_questions',
      'cbt_students',
      'cbt_activity_logs',
      'cbt_device_locks'
  )
GROUP BY s.table_name, s.index_name, s.non_unique
ORDER BY s.table_name, s.index_name;


-- ================================================================
-- BAGIAN 2: APPLY INDEX DARI BACKUP (IDEMPOTENT)
-- Index-index ini ada di database_backup.sql bagian ALTER TABLE,
-- tapi mungkin belum dijalankan di server production.
-- Script menggunakan stored procedure agar aman dijalankan berulang.
-- ================================================================

DROP PROCEDURE IF EXISTS _cbt_add_idx;

DELIMITER ;;
CREATE PROCEDURE _cbt_add_idx(
    IN p_table   VARCHAR(64),
    IN p_index   VARCHAR(64),
    IN p_columns TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.statistics
        WHERE  table_schema = DATABASE()
          AND  table_name   = p_table
          AND  index_name   = p_index
        LIMIT  1
    ) THEN
        SET @_sql = CONCAT(
            'ALTER TABLE `', p_table, '` ',
            'ADD INDEX `', p_index, '` (', p_columns, ')'
        );
        PREPARE _stmt FROM @_sql;
        EXECUTE _stmt;
        DEALLOCATE PREPARE _stmt;
        SELECT CONCAT('[ADDED]  ', p_table, '.', p_index) AS log;
    ELSE
        SELECT CONCAT('[EXISTS] ', p_table, '.', p_index) AS log;
    END IF;
END;;
DELIMITER ;

-- --------------------------------------------------------------
-- 1. cbt_exams
-- --------------------------------------------------------------
-- Untuk query: WHERE status=? AND mulai_pada BETWEEN ? AND ?
CALL _cbt_add_idx('cbt_exams', 'idx_exams_status_waktu',
    '`status`, `mulai_pada`, `selesai_pada`');

-- Untuk query monitoring guru: WHERE teacher_id=?
CALL _cbt_add_idx('cbt_exams', 'idx_exams_teacher',
    '`teacher_id`');

-- Untuk JOIN/filter bank soal
CALL _cbt_add_idx('cbt_exams', 'idx_exams_bank_soal',
    '`bank_soal_id`');

-- Untuk JOIN/filter mata pelajaran
CALL _cbt_add_idx('cbt_exams', 'idx_exams_subject',
    '`subject_id`');

-- Untuk range scan mulai_pada (BETWEEN tanpa DATE())
CALL _cbt_add_idx('cbt_exams', 'idx_exams_mulai_pada',
    '`mulai_pada`');

-- --------------------------------------------------------------
-- 2. cbt_exam_participants  (tabel paling sering di-query saat ujian)
-- --------------------------------------------------------------
-- KRITIS: untuk WHERE exam_id=? AND student_id=? (ajax_get_waktu, save_jawaban)
CALL _cbt_add_idx('cbt_exam_participants', 'idx_part_exam_student',
    '`exam_id`, `student_id`');

-- Untuk WHERE student_id=? (lookup per siswa)
CALL _cbt_add_idx('cbt_exam_participants', 'idx_part_student',
    '`student_id`');

-- Untuk WHERE status=? AND exam_id=? (monitoring count)
CALL _cbt_add_idx('cbt_exam_participants', 'idx_part_status_exam',
    '`status`, `exam_id`');

-- --------------------------------------------------------------
-- 3. cbt_exam_questions
-- --------------------------------------------------------------
-- Untuk WHERE exam_id=? ORDER BY id (ambil daftar soal)
CALL _cbt_add_idx('cbt_exam_questions', 'idx_eq_exam_id',
    '`exam_id`');

-- Untuk WHERE question_id=?
CALL _cbt_add_idx('cbt_exam_questions', 'idx_eq_question_id',
    '`question_id`');

-- --------------------------------------------------------------
-- 4. cbt_student_answers  (KRITIS — INSERT/UPDATE tiap jawaban)
-- --------------------------------------------------------------
-- Untuk WHERE participant_id=?
CALL _cbt_add_idx('cbt_student_answers', 'idx_ans_participant',
    '`participant_id`');

-- KRITIS: untuk WHERE participant_id=? AND question_id=? (cek existing jawaban)
CALL _cbt_add_idx('cbt_student_answers', 'idx_ans_part_question',
    '`participant_id`, `question_id`');

-- Untuk WHERE question_id=?
CALL _cbt_add_idx('cbt_student_answers', 'idx_ans_question',
    '`question_id`');

-- --------------------------------------------------------------
-- 5. cbt_question_options
-- --------------------------------------------------------------
-- Untuk WHERE question_id=?
CALL _cbt_add_idx('cbt_question_options', 'idx_opt_question_id',
    '`question_id`');

-- KRITIS: untuk WHERE question_id=? AND is_correct=1 (cek jawaban benar)
CALL _cbt_add_idx('cbt_question_options', 'idx_opt_question_correct',
    '`question_id`, `is_correct`');

-- --------------------------------------------------------------
-- 6. cbt_questions
-- --------------------------------------------------------------
CALL _cbt_add_idx('cbt_questions', 'idx_q_bank_soal_id',
    '`bank_soal_id`');

CALL _cbt_add_idx('cbt_questions', 'idx_q_bank_tipe',
    '`bank_soal_id`, `tipe`');

-- --------------------------------------------------------------
-- 7. cbt_cheat_logs
-- --------------------------------------------------------------
-- Untuk WHERE exam_id=? AND student_id=? (count pelanggaran)
CALL _cbt_add_idx('cbt_cheat_logs', 'idx_cheat_exam_student',
    '`exam_id`, `student_id`');

-- Untuk WHERE student_id=? AND exam_id=? (UPDATE blocked)
CALL _cbt_add_idx('cbt_cheat_logs', 'idx_cheat_student_exam',
    '`student_id`, `exam_id`');

-- --------------------------------------------------------------
-- 8. cbt_students
-- --------------------------------------------------------------
CALL _cbt_add_idx('cbt_students', 'idx_student_class',
    '`class_id`');

CALL _cbt_add_idx('cbt_students', 'idx_student_aktif',
    '`is_aktif`');

-- --------------------------------------------------------------
-- 9. cbt_device_locks
-- --------------------------------------------------------------
CALL _cbt_add_idx('cbt_device_locks', 'idx_device_created',
    '`student_id`, `created_at`');

-- --------------------------------------------------------------
-- 10. cbt_activity_logs
-- --------------------------------------------------------------
-- Untuk query filter di halaman Activity Log admin
CALL _cbt_add_idx('cbt_activity_logs', 'idx_log_type_created',
    '`type`, `created_at`');

-- Untuk ORDER BY / filter created_at
CALL _cbt_add_idx('cbt_activity_logs', 'idx_log_created_at',
    '`created_at`');

-- --------------------------------------------------------------
-- 11. cbt_teachers & cbt_admins (login query)
-- --------------------------------------------------------------
CALL _cbt_add_idx('cbt_teachers', 'idx_teacher_username', '`username`');
CALL _cbt_add_idx('cbt_admins',   'idx_admin_username',   '`username`');


-- ================================================================
-- BAGIAN 3: INDEX BARU (belum ada di backup asli)
-- ================================================================

-- Composite untuk monitoring guru:
-- WHERE e.mulai_pada BETWEEN ? AND ? AND e.teacher_id = ?
-- MySQL hanya bisa pakai 1 index per tabel — composite lebih efisien
-- daripada idx_exams_teacher dan idx_exams_mulai_pada yang terpisah
CALL _cbt_add_idx('cbt_exams', 'idx_exams_teacher_mulai',
    '`teacher_id`, `mulai_pada`');

-- Cleanup procedure
DROP PROCEDURE IF EXISTS _cbt_add_idx;


-- ================================================================
-- BAGIAN 4: OPSIONAL — HAPUS INDEX YANG TIDAK EFISIEN
-- Uncomment dan jalankan manual setelah konfirmasi tidak ada query
-- yang menggunakan index ini
-- ================================================================

-- idx_log_user_id: tidak ada query WHERE user_id=? di hot-path
-- (hanya ada di halaman activity log yang jarang diakses)
-- Setiap INSERT ke cbt_activity_logs harus update index ini → overhead
-- Uncomment baris berikut jika yakin tidak diperlukan:
-- ALTER TABLE cbt_activity_logs DROP INDEX IF EXISTS idx_log_user_id;

-- idx_exams_jenjang: jarang difilter langsung berdasarkan jenjang
-- (biasanya filter via kelas yang sudah punya index di cbt_students)
-- Uncomment jika diperlukan:
-- ALTER TABLE cbt_exams DROP INDEX IF EXISTS idx_exams_jenjang;


-- ================================================================
-- BAGIAN 5: HAPUS INDEX DUPLIKAT (IDEMPOTENT)
-- Ditemukan dari hasil diagnostic: index-index ini redundan karena
-- sudah di-cover oleh index lain dengan kolom yang sama.
-- Menghapusnya mengurangi overhead INSERT/UPDATE tanpa kehilangan
-- kecepatan SELECT apapun.
-- ================================================================

DROP PROCEDURE IF EXISTS _cbt_drop_idx;

DELIMITER ;;
CREATE PROCEDURE _cbt_drop_idx(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM   information_schema.statistics
        WHERE  table_schema = DATABASE()
          AND  table_name   = p_table
          AND  index_name   = p_index
        LIMIT  1
    ) THEN
        SET @_sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE _stmt FROM @_sql;
        EXECUTE _stmt;
        DEALLOCATE PREPARE _stmt;
        SELECT CONCAT('[DROPPED] ', p_table, '.', p_index) AS log;
    ELSE
        SELECT CONCAT('[SKIP]    ', p_table, '.', p_index, ' (sudah tidak ada)') AS log;
    END IF;
END;;
DELIMITER ;

-- --------------------------------------------------------------
-- cbt_exams
-- `mulai_pada` (single) → duplikat dari `idx_exams_mulai_pada`
-- --------------------------------------------------------------
CALL _cbt_drop_idx('cbt_exams', 'mulai_pada');

-- --------------------------------------------------------------
-- cbt_exam_participants
-- `status_2` (status, exam_id) → identik dengan `idx_part_status_exam`
-- --------------------------------------------------------------
CALL _cbt_drop_idx('cbt_exam_participants', 'status_2');

-- --------------------------------------------------------------
-- cbt_activity_logs
-- `idx_created_at` (created_at) → duplikat dari `idx_log_created_at`
-- `idx_type` (type)             → ter-cover oleh `idx_log_type_created` (type, created_at)
-- --------------------------------------------------------------
CALL _cbt_drop_idx('cbt_activity_logs', 'idx_created_at');
CALL _cbt_drop_idx('cbt_activity_logs', 'idx_type');

DROP PROCEDURE IF EXISTS _cbt_drop_idx;


-- ================================================================
-- BAGIAN 6: VERIFY HASIL AKHIR
-- ================================================================

SELECT
    s.table_name,
    s.index_name,
    GROUP_CONCAT(s.column_name ORDER BY s.seq_in_index SEPARATOR ', ') AS columns
FROM information_schema.statistics s
WHERE s.table_schema = DATABASE()
  AND s.table_name IN (
      'cbt_exam_participants',
      'cbt_student_answers',
      'cbt_cheat_logs',
      'cbt_question_options',
      'cbt_exam_questions',
      'cbt_exams',
      'cbt_activity_logs'
  )
GROUP BY s.table_name, s.index_name
ORDER BY s.table_name, s.index_name;

-- Expected minimum result:
-- cbt_activity_logs     : idx_log_created_at      (created_at)       ← idx_created_at sudah dihapus
-- cbt_activity_logs     : idx_log_type_created    (type, created_at) ← idx_type sudah dihapus
-- cbt_exam_participants : idx_part_exam_student   (exam_id, student_id)
-- cbt_exam_participants : idx_part_status_exam    (status, exam_id)  ← status_2 sudah dihapus
-- cbt_exam_questions    : idx_eq_exam_id          (exam_id)
-- cbt_exams             : idx_exams_mulai_pada    (mulai_pada)       ← duplikat mulai_pada dihapus
-- cbt_exams             : idx_exams_teacher_mulai (teacher_id, mulai_pada)
-- cbt_question_options  : idx_opt_question_correct (question_id, is_correct)
-- cbt_student_answers   : idx_ans_part_question   (participant_id, question_id)
