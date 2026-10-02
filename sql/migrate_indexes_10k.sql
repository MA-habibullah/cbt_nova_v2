-- ===================================================================
-- CBT NOVA — 10.000 CONCURRENCY & 100 PARALLEL EXAMS INDEXING MIGRATION
-- Database: MariaDB / MySQL (db_axon / db_cbt)
-- ===================================================================

-- 1. TABEL JAWABAN SISWA (cbt_student_answers) — HOTTEST TABLE (Millions of Rows)
-- Point-lookup unique constraint untuk atomic UPSERT & zero row-lock contention
ALTER TABLE `cbt_student_answers`
    ADD UNIQUE KEY `uq_part_question` (`participant_id`, `question_id`),
    ADD INDEX `idx_question_ans` (`question_id`, `jawaban_simpan`(10)),
    ADD INDEX `idx_ans_part_ragu` (`participant_id`, `is_ragu`),
    ADD INDEX `idx_ans_part_graded` (`participant_id`, `is_graded`);

-- 2. TABEL PARTISIPASI UJIAN (cbt_exam_participants) — 50.000+ Rows
-- Composite filtering per jadwal ujian & status pengerjaan siswa
ALTER TABLE `cbt_exam_participants`
    ADD INDEX `idx_exam_student_status` (`exam_id`, `student_id`, `status`),
    ADD INDEX `idx_student_status` (`student_id`, `status`),
    ADD INDEX `idx_exam_status` (`exam_id`, `status`),
    ADD INDEX `idx_part_waktu_mulai` (`waktu_mulai`);

-- 3. TABEL RELASI SOAL UJIAN (cbt_exam_questions)
-- Menjamin relasi satu soal per ujian bersifat unik & lookup instan
ALTER TABLE `cbt_exam_questions`
    ADD UNIQUE KEY `uq_exam_question` (`exam_id`, `question_id`),
    ADD INDEX `idx_eq_order` (`exam_id`, `order_number`);

-- 4. TABEL SESI ONLINE & LOG AKTIVITAS (cbt_online_sessions, cbt_activity_logs)
-- Menghilangkan full table scan saat pembersihan sesi kadaluwarsa & tracking
ALTER TABLE `cbt_online_sessions`
    ADD INDEX `idx_last_seen` (`last_seen`),
    ADD INDEX `idx_user_role` (`user_id`, `role`);

ALTER TABLE `cbt_activity_logs`
    ADD INDEX `idx_log_role_type` (`role`, `type`, `created_at`);

-- 5. TABEL BANK SOAL & OPSI (cbt_questions, cbt_question_options)
-- Fast retrieval saat randomisasi & pembagian tipe soal
ALTER TABLE `cbt_questions`
    ADD INDEX `idx_q_bank_diff` (`bank_soal_id`, `tingkat_kesulitan`),
    ADD INDEX `idx_q_bank_bobot` (`bank_soal_id`, `bobot_skor`);

ALTER TABLE `cbt_question_options`
    ADD INDEX `idx_opt_q_correct` (`question_id`, `is_correct`, `id`);

-- 6. TABEL DEVICE LOCKS & SISWA (cbt_device_locks, cbt_students)
-- Mempercepat verifikasi token login dan single device session
ALTER TABLE `cbt_device_locks`
    ADD INDEX `idx_device_session` (`session_id`),
    ADD UNIQUE KEY `uq_student_device` (`student_id`, `device_id`);

ALTER TABLE `cbt_students`
    ADD INDEX `idx_student_class_ta` (`class_id`, `tahun_ajaran_id`, `is_aktif`);
