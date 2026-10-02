-- Scoring Improvement Migration (2026-06-15)
-- Tambah kolom skor_status dan is_graded (idempotent)

DROP PROCEDURE IF EXISTS migrate_scoring_improvement;
DELIMITER $$
CREATE PROCEDURE migrate_scoring_improvement()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'cbt_exam_participants'
          AND COLUMN_NAME  = 'skor_status'
    ) THEN
        ALTER TABLE cbt_exam_participants
            ADD COLUMN skor_status ENUM('pending','final') NOT NULL DEFAULT 'final'
            AFTER skor_akhir;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'cbt_student_answers'
          AND COLUMN_NAME  = 'is_graded'
    ) THEN
        ALTER TABLE cbt_student_answers
            ADD COLUMN is_graded TINYINT(1) NOT NULL DEFAULT 0
            AFTER skor_didapat;
    END IF;
END$$
DELIMITER ;
CALL migrate_scoring_improvement();
DROP PROCEDURE IF EXISTS migrate_scoring_improvement;
