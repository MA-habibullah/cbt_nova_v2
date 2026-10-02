CREATE TABLE IF NOT EXISTS `cbt_display_tokens` (
  `id`               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash`       char(64)            NOT NULL COMMENT 'SHA-256 hex of the encrypted token',
  `label`            varchar(255)        NOT NULL COMMENT 'Auto-generated from first exam name + date',
  `short_code`       varchar(12)         NULL     COMMENT 'Short alphanumeric code for public URL (?c=)',
  `url`              text                NULL     COMMENT 'Full public dashboard URL',
  `config_json`      JSON                NULL     COMMENT 'Array of slots: [{exam_id, class_id, top_limit, mask_names, banner_tpl}]',
  `created_by_role`  varchar(20)         NOT NULL COMMENT 'admin atau guru',
  `created_by_id`    bigint(20) unsigned NOT NULL,
  `is_active`        tinyint(1)          NOT NULL DEFAULT 1,
  `created_at`       timestamp           NULL     DEFAULT current_timestamp(),
  `updated_at`       timestamp           NULL     DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  UNIQUE KEY `uk_short_code` (`short_code`),
  KEY `idx_by_creator` (`created_by_role`, `created_by_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
