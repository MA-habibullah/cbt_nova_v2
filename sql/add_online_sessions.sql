-- Migration: tambah tabel cbt_online_sessions untuk tracking pengguna login aktif
-- Menggantikan pendekatan cbt_activity_logs yang tidak bisa membedakan
-- pengguna aktif vs pengguna yang sudah logout.

CREATE TABLE IF NOT EXISTS `cbt_online_sessions` (
    `session_id`  VARCHAR(128)  NOT NULL,
    `user_id`     INT(11)       NOT NULL,
    `role`        VARCHAR(20)   NOT NULL,
    `last_seen`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`session_id`),
    KEY `idx_role_last_seen` (`role`, `last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
