-- AXON CBT — MASTER DATABASE SCHEMA (10K CONCURRENCY & 100 PARALLEL EXAMS)
-- Exported on: 2026-10-01 07:46:49
-- Database: db_axon
-- ===================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- -----------------------------------------------------
-- Table structure for table `cache`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cache_locks`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_activity_logs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_activity_logs`;
CREATE TABLE `cbt_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'System',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `activity` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'umum',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_role` (`role`),
  KEY `idx_log_type_created` (`type`,`created_at`),
  KEY `idx_log_user_id` (`user_id`),
  KEY `idx_log_created_at` (`created_at`),
  KEY `idx_log_role_type` (`role`,`type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=48715 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_admins`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_admins`;
CREATE TABLE `cbt_admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('superadmin','admin','proktor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_admin_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_backup_logs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_backup_logs`;
CREATE TABLE `cbt_backup_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_bank_soal`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_bank_soal`;
CREATE TABLE `cbt_bank_soal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_bank_soal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_bank_soal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `jenjang` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_bank_soal` (`kode_bank_soal`),
  KEY `idx_bs_jenjang` (`jenjang`)
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_cheat_logs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_cheat_logs`;
CREATE TABLE `cbt_cheat_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `exam_id` bigint unsigned NOT NULL,
  `tipe_pelanggaran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_kejadian` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cheat_exam_student` (`exam_id`,`student_id`),
  KEY `idx_cheat_student_exam` (`student_id`,`exam_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5404 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_classes`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_classes`;
CREATE TABLE `cbt_classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_kelas` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenjang` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_device_locks`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_device_locks`;
CREATE TABLE `cbt_device_locks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_device` (`student_id`,`device_id`),
  KEY `idx_device_student` (`student_id`),
  KEY `idx_device_created` (`student_id`,`created_at`),
  KEY `idx_device_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12884 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_display_tokens`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_display_tokens`;
CREATE TABLE `cbt_display_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 hex of the encrypted token',
  `short_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short alphanumeric code for public URL (?c=)',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Auto-generated from first exam name + date',
  `url` text COLLATE utf8mb4_unicode_ci COMMENT 'Full public dashboard URL',
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Array of slots: [{exam_id, class_id, top_limit, mask_names, banner_tpl}]',
  `created_by_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin atau guru',
  `created_by_id` bigint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  UNIQUE KEY `short_code` (`short_code`),
  KEY `idx_by_creator` (`created_by_role`,`created_by_id`,`is_active`),
  CONSTRAINT `cbt_display_tokens_chk_1` CHECK (json_valid(`config_json`))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_exam_participants`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_exam_participants`;
CREATE TABLE `cbt_exam_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `waktu_mulai` timestamp NULL DEFAULT NULL,
  `waktu_selesai` timestamp NULL DEFAULT NULL,
  `skor_akhir` decimal(5,2) DEFAULT NULL,
  `nilai_objektif` decimal(5,2) DEFAULT NULL,
  `nilai_esai` decimal(5,2) DEFAULT NULL,
  `skor_status` enum('pending','final') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'final',
  `status` enum('ready','working','finished','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `tambahan_waktu` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `soal_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Per-student question IDs when distribution is active',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `idx_part_exam_student` (`exam_id`,`student_id`),
  KEY `idx_part_student` (`student_id`),
  KEY `idx_part_status_exam` (`status`,`exam_id`),
  KEY `idx_exam_student_status` (`exam_id`,`student_id`,`status`),
  KEY `idx_student_status` (`student_id`,`status`),
  KEY `idx_exam_status` (`exam_id`,`status`),
  KEY `idx_part_waktu_mulai` (`waktu_mulai`),
  CONSTRAINT `cbt_exam_participants_chk_1` CHECK (json_valid(`soal_ids`))
) ENGINE=InnoDB AUTO_INCREMENT=52993 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_exam_questions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_exam_questions`;
CREATE TABLE `cbt_exam_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned NOT NULL,
  `order_number` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_question` (`exam_id`,`question_id`),
  KEY `idx_eq_exam_id` (`exam_id`),
  KEY `idx_eq_question_id` (`question_id`),
  KEY `idx_eq_order` (`exam_id`,`order_number`)
) ENGINE=InnoDB AUTO_INCREMENT=5593 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_exams`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_exams`;
CREATE TABLE `cbt_exams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_mapel_ujian` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenjang` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_soal_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `durasi_menit` smallint unsigned NOT NULL,
  `mulai_pada` timestamp NULL DEFAULT NULL,
  `selesai_pada` timestamp NULL DEFAULT NULL,
  `status` enum('draft','aktif','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `token` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_token_aktif` tinyint(1) DEFAULT '1',
  `acak_soal` tinyint(1) NOT NULL DEFAULT '1',
  `acak_opsi` tinyint(1) NOT NULL DEFAULT '1',
  `tampilkan_nilai` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `jumlah_soal_limit` int unsigned DEFAULT NULL COMMENT 'Total soal yang dipilih otomatis (NULL = semua soal manual)',
  `distribusi_tipe` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Distribusi per jenis soal, contoh: {"pg":30,"isian":5,"essay":5}',
  `distribusi_kesulitan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Distribusi per tingkat kesulitan, contoh: {"mudah":10,"sedang":20,"sulit":10}',
  PRIMARY KEY (`id`),
  KEY `idx_exams_status_waktu` (`status`,`mulai_pada`,`selesai_pada`),
  KEY `idx_exams_jenjang` (`jenjang`),
  KEY `idx_exams_teacher` (`teacher_id`),
  KEY `idx_exams_bank_soal` (`bank_soal_id`),
  KEY `idx_exams_subject` (`subject_id`),
  KEY `idx_exams_mulai_pada` (`mulai_pada`),
  KEY `idx_exams_teacher_mulai` (`teacher_id`,`mulai_pada`),
  CONSTRAINT `cbt_exams_chk_1` CHECK (json_valid(`distribusi_tipe`)),
  CONSTRAINT `cbt_exams_chk_2` CHECK (json_valid(`distribusi_kesulitan`))
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_migrations`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_migrations`;
CREATE TABLE `cbt_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_online_sessions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_online_sessions`;
CREATE TABLE `cbt_online_sessions` (
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  KEY `idx_role_last_seen` (`role`,`last_seen`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_user_role` (`user_id`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_question_options`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_question_options`;
CREATE TABLE `cbt_question_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint unsigned NOT NULL,
  `label` text COLLATE utf8mb4_unicode_ci,
  `value_target` text COLLATE utf8mb4_unicode_ci,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_opt_question_id` (`question_id`),
  KEY `idx_opt_question_correct` (`question_id`,`is_correct`),
  KEY `idx_opt_q_correct` (`question_id`,`is_correct`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12396 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_questions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_questions`;
CREATE TABLE `cbt_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bank_soal_id` bigint unsigned NOT NULL,
  `tipe` enum('pg','pg_kompleks','isian','benar_salah','menjodohkan','essay') COLLATE utf8mb4_unicode_ci NOT NULL,
  `konten_soal` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_files` longtext COLLATE utf8mb4_unicode_ci,
  `tingkat_kesulitan` enum('mudah','sedang','sulit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sedang',
  `acak_opsi` tinyint(1) NOT NULL DEFAULT '1',
  `bobot_skor` decimal(5,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_q_bank_soal_id` (`bank_soal_id`),
  KEY `idx_q_bank_tipe` (`bank_soal_id`,`tipe`),
  KEY `idx_q_bank_diff` (`bank_soal_id`,`tingkat_kesulitan`),
  KEY `idx_q_bank_bobot` (`bank_soal_id`,`bobot_skor`)
) ENGINE=InnoDB AUTO_INCREMENT=2701 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_riwayat_kelas`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_riwayat_kelas`;
CREATE TABLE `cbt_riwayat_kelas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `class_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_riwayat_student` (`student_id`),
  KEY `fk_riwayat_class` (`class_id`),
  KEY `fk_riwayat_ta` (`tahun_ajaran_id`),
  CONSTRAINT `fk_riwayat_class` FOREIGN KEY (`class_id`) REFERENCES `cbt_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_riwayat_student` FOREIGN KEY (`student_id`) REFERENCES `cbt_students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_riwayat_ta` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `cbt_tahun_ajaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=636 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_sesi`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_sesi`;
CREATE TABLE `cbt_sesi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_sesi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `is_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_settings`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_settings`;
CREATE TABLE `cbt_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_sekolah` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kepala_sekolah` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nip_kepala` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat_sekolah` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_sekolah` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `judul_kartu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kota` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl_cetak` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_student_answers`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_student_answers`;
CREATE TABLE `cbt_student_answers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned NOT NULL,
  `jawaban_simpan` longtext COLLATE utf8mb4_unicode_ci,
  `is_ragu` tinyint(1) NOT NULL DEFAULT '0',
  `skor_didapat` decimal(5,2) NOT NULL DEFAULT '0.00',
  `is_graded` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_part_question` (`participant_id`,`question_id`),
  KEY `idx_ans_participant` (`participant_id`),
  KEY `idx_ans_part_question` (`participant_id`,`question_id`),
  KEY `idx_ans_question` (`question_id`),
  KEY `idx_question_ans` (`question_id`,`jawaban_simpan`(10)),
  KEY `idx_ans_part_ragu` (`participant_id`,`is_ragu`),
  KEY `idx_ans_part_graded` (`participant_id`,`is_graded`)
) ENGINE=InnoDB AUTO_INCREMENT=331275 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_students`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_students`;
CREATE TABLE `cbt_students` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nisn` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_lengkap` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kartu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned DEFAULT NULL,
  `sesi` tinyint unsigned NOT NULL DEFAULT '1',
  `agama` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nisn` (`nisn`),
  UNIQUE KEY `username` (`username`),
  KEY `class_id` (`class_id`,`sesi`),
  KEY `idx_student_username` (`username`),
  KEY `idx_student_class` (`class_id`),
  KEY `idx_student_aktif` (`is_aktif`),
  KEY `idx_student_class_ta` (`class_id`,`tahun_ajaran_id`,`is_aktif`)
) ENGINE=InnoDB AUTO_INCREMENT=1074 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_subjects`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_subjects`;
CREATE TABLE `cbt_subjects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_mapel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_mapel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_mapel` (`kode_mapel`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_tahun_ajaran`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_tahun_ajaran`;
CREATE TABLE `cbt_tahun_ajaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tahun` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `semester` enum('ganjil','genap') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `cbt_teachers`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cbt_teachers`;
CREATE TABLE `cbt_teachers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nip` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_lengkap` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_teacher_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `sessions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
