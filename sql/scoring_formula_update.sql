-- Scoring Formula Update (2026-06-17)
-- Tambah kolom nilai_objektif dan nilai_esai ke cbt_exam_participants (idempotent)

DROP PROCEDURE IF EXISTS migrate_scoring_formula_update;
DELIMITER $$
CREATE PROCEDURE migrate_scoring_formula_update()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'cbt_exam_participants'
          AND COLUMN_NAME  = 'nilai_objektif'
    ) THEN
        ALTER TABLE cbt_exam_participants
            ADD COLUMN nilai_objektif DECIMAL(5,2) DEFAULT NULL AFTER skor_akhir,
            ADD COLUMN nilai_esai     DECIMAL(5,2) DEFAULT NULL AFTER nilai_objektif;
    END IF;
END$$
DELIMITER ;
CALL migrate_scoring_formula_update();
DROP PROCEDURE IF EXISTS migrate_scoring_formula_update;
