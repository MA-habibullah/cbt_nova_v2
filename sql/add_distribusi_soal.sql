-- Migration: Distribusi dan Limitasi Soal Ujian
-- Tambah 3 kolom ke cbt_exams (idempotent)

DROP PROCEDURE IF EXISTS migrate_add_distribusi_soal;
DELIMITER $$
CREATE PROCEDURE migrate_add_distribusi_soal()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'cbt_exams'
          AND COLUMN_NAME  = 'jumlah_soal_limit'
    ) THEN
        ALTER TABLE cbt_exams
            ADD COLUMN jumlah_soal_limit    INT UNSIGNED NULL DEFAULT NULL
                COMMENT 'Total soal yang dipilih otomatis (NULL = semua soal manual)',
            ADD COLUMN distribusi_tipe      JSON NULL DEFAULT NULL
                COMMENT 'Distribusi per jenis soal, contoh: {"pg":30,"isian":5,"essay":5}',
            ADD COLUMN distribusi_kesulitan JSON NULL DEFAULT NULL
                COMMENT 'Distribusi per tingkat kesulitan, contoh: {"mudah":10,"sedang":20,"sulit":10}';
    END IF;
END$$
DELIMITER ;
CALL migrate_add_distribusi_soal();
DROP PROCEDURE IF EXISTS migrate_add_distribusi_soal;
