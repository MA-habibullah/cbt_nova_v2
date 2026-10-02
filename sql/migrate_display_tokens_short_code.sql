-- Tambah kolom short_code & config_json ke tabel cbt_display_tokens (idempotent)
-- Jalankan jika tabel sudah ada sebelum fitur short URL ditambahkan

SET @db = DATABASE();

-- short_code
SET @exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cbt_display_tokens' AND COLUMN_NAME = 'short_code'
);
SET @sql = IF(@exists = 0,
    'ALTER TABLE `cbt_display_tokens` ADD COLUMN `short_code` varchar(12) NULL UNIQUE COMMENT ''Short alphanumeric code for public URL (?c=)'' AFTER `token_hash`',
    'SELECT ''short_code already exists'' AS _msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- config_json
SET @exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cbt_display_tokens' AND COLUMN_NAME = 'config_json'
);
SET @sql = IF(@exists = 0,
    'ALTER TABLE `cbt_display_tokens` ADD COLUMN `config_json` JSON NULL COMMENT ''Array of slots: [{exam_id, class_id, top_limit, mask_names, banner_tpl}]'' AFTER `url`',
    'SELECT ''config_json already exists'' AS _msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
