-- ================================================================
-- CBT Nova — Tambah Kolom Jenjang di cbt_bank_soal
-- Target: MySQL 8.0+
-- Script ini IDEMPOTENT: aman dijalankan berulang kali
--
-- Cara eksekusi:
--   mysql -h 127.0.0.1 -P 3307 -u cbt_user -pcbt_pass cbt_nova < sql/add_jenjang_bank_soal.sql
-- ================================================================

-- Tambah kolom jenjang jika belum ada
-- (MySQL 8.0 tidak mendukung ADD COLUMN IF NOT EXISTS, gunakan stored procedure)
DROP PROCEDURE IF EXISTS add_jenjang_column;
DELIMITER $$
CREATE PROCEDURE add_jenjang_column()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'cbt_bank_soal'
      AND COLUMN_NAME  = 'jenjang'
  ) THEN
    ALTER TABLE cbt_bank_soal
      ADD COLUMN jenjang VARCHAR(10) NULL AFTER subject_id;
  END IF;
END$$
DELIMITER ;
CALL add_jenjang_column();
DROP PROCEDURE IF EXISTS add_jenjang_column;

-- Tambah index untuk performa filter jika belum ada
DROP PROCEDURE IF EXISTS add_jenjang_index;
DELIMITER $$
CREATE PROCEDURE add_jenjang_index()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'cbt_bank_soal'
      AND INDEX_NAME   = 'idx_bs_jenjang'
  ) THEN
    ALTER TABLE cbt_bank_soal
      ADD INDEX idx_bs_jenjang (jenjang);
  END IF;
END$$
DELIMITER ;
CALL add_jenjang_index();
DROP PROCEDURE IF EXISTS add_jenjang_index;
