-- ==========================================================================
-- CBT NOVA: COMPLETE PRODUCTION DATABASE SYNC SCRIPT (IDEMPOTENT)
-- Target Database: cbt_nova_v2 (Linux Production Server)
-- ==========================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Table: cbt_display_tokens
CREATE TABLE IF NOT EXISTS `cbt_display_tokens` (
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

-- Table: cbt_device_locks
CREATE TABLE IF NOT EXISTS `cbt_device_locks` (
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
) ENGINE=InnoDB AUTO_INCREMENT=12887 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cbt_cheat_logs
CREATE TABLE IF NOT EXISTS `cbt_cheat_logs` (
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
) ENGINE=InnoDB AUTO_INCREMENT=5410 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cbt_online_sessions
CREATE TABLE IF NOT EXISTS `cbt_online_sessions` (
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  KEY `idx_role_last_seen` (`role`,`last_seen`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_user_role` (`user_id`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cbt_activity_logs
CREATE TABLE IF NOT EXISTS `cbt_activity_logs` (
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
) ENGINE=InnoDB AUTO_INCREMENT=48729 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cbt_backup_logs
CREATE TABLE IF NOT EXISTS `cbt_backup_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cbt_migrations
CREATE TABLE IF NOT EXISTS `cbt_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------------------------
-- COLUMN & INDEX PATCHES FOR CORE TABLES
-- --------------------------------------------------------------------------

-- 1. Pastikan kolom status, is_aktif, foto di cbt_students ada
ALTER TABLE `cbt_students` ADD COLUMN IF NOT EXISTS `status` ENUM('aktif','alumni','nonaktif') NOT NULL DEFAULT 'aktif' AFTER `is_aktif`;
ALTER TABLE `cbt_students` ADD COLUMN IF NOT EXISTS `foto` VARCHAR(255) NULL DEFAULT NULL AFTER `nama_lengkap`;

-- 2. Indeks Performa & Monitoring Skalabilitas Tinggi
ALTER TABLE `cbt_exam_participants` ADD INDEX IF NOT EXISTS `idx_exam_status` (`exam_id`, `status`);
ALTER TABLE `cbt_exam_participants` ADD INDEX IF NOT EXISTS `idx_student_exam` (`student_id`, `exam_id`);
ALTER TABLE `cbt_student_answers` ADD INDEX IF NOT EXISTS `idx_participant_question` (`participant_id`, `question_id`);
ALTER TABLE `cbt_device_locks` ADD INDEX IF NOT EXISTS `idx_student_device` (`student_id`, `device_token`);
ALTER TABLE `cbt_cheat_logs` ADD INDEX IF NOT EXISTS `idx_participant_cheat` (`participant_id`, `exam_id`);

SET FOREIGN_KEY_CHECKS = 1;
