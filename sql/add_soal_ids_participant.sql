-- Migration: tambah kolom soal_ids ke cbt_exam_participants (idempotent)

DROP PROCEDURE IF EXISTS migrate_add_soal_ids;
DELIMITER $$
CREATE PROCEDURE migrate_add_soal_ids()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'cbt_exam_participants'
          AND COLUMN_NAME  = 'soal_ids'
    ) THEN
        ALTER TABLE cbt_exam_participants
            ADD COLUMN soal_ids JSON NULL DEFAULT NULL
                COMMENT 'Per-student question IDs when distribution is active';
    END IF;
END$$
DELIMITER ;
CALL migrate_add_soal_ids();
DROP PROCEDURE IF EXISTS migrate_add_soal_ids;
