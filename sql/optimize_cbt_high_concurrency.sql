-- ====================================================================
-- CBT NOVA HIGH-CONCURRENCY DATABASE OPTIMIZATION (10.000+ SISWA)
-- Engine: MariaDB 10.4+ / MySQL 8.0+
-- Deskripsi: Menambahkan composite indexes kritis untuk eliminasi
--            Full Table Scan & Disk I/O Bottleneck pada transaksi autosave
-- ====================================================================

-- 1. Optimasi Tabel Jawaban Siswa (cbt_student_answers)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cbt_student_answers' AND INDEX_NAME = 'idx_ans_part_quest_ragu');
SET @sql := IF(@exist = 0, 'ALTER TABLE cbt_student_answers ADD INDEX idx_ans_part_quest_ragu (participant_id, question_id, is_ragu)', 'SELECT "Index idx_ans_part_quest_ragu already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Optimasi Tabel Partisipasi Ujian (cbt_exam_participants)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cbt_exam_participants' AND INDEX_NAME = 'idx_ep_exam_student_status');
SET @sql := IF(@exist = 0, 'ALTER TABLE cbt_exam_participants ADD INDEX idx_ep_exam_student_status (exam_id, student_id, status)', 'SELECT "Index idx_ep_exam_student_status already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cbt_exam_participants' AND INDEX_NAME = 'idx_ep_exam_status_waktu');
SET @sql := IF(@exist = 0, 'ALTER TABLE cbt_exam_participants ADD INDEX idx_ep_exam_status_waktu (exam_id, status, waktu_mulai)', 'SELECT "Index idx_ep_exam_status_waktu already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Optimasi Tabel Soal & Opsi (cbt_questions & cbt_question_options)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cbt_question_options' AND INDEX_NAME = 'idx_opt_qid_label');
SET @sql := IF(@exist = 0, 'ALTER TABLE cbt_question_options ADD INDEX idx_opt_qid_label (question_id, label(50))', 'SELECT "Index idx_opt_qid_label already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Optimasi Tabel Log Kecurangan (cbt_cheat_logs)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cbt_cheat_logs' AND INDEX_NAME = 'idx_cheat_exam_student_waktu');
SET @sql := IF(@exist = 0, 'ALTER TABLE cbt_cheat_logs ADD INDEX idx_cheat_exam_student_waktu (exam_id, student_id, waktu_kejadian)', 'SELECT "Index idx_cheat_exam_student_waktu already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Optimasi Tabel Sesi Online (cbt_online_sessions)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cbt_online_sessions' AND INDEX_NAME = 'idx_sess_role_seen');
SET @sql := IF(@exist = 0, 'ALTER TABLE cbt_online_sessions ADD INDEX idx_sess_role_seen (role, last_seen)', 'SELECT "Index idx_sess_role_seen already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'High-Concurrency Index Optimization completed successfully' AS status;
