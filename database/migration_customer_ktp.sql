  -- Migration: Add ktp column to tbl_customers (KTP / ID Card Number)
-- Idempotent: checks column existence before adding so re-running does not fail.

SET @dbname = DATABASE();
SET @tablename = 'tbl_customers';
SET @columnname = 'ktp';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT "Column ktp already exists in tbl_customers" AS status',
  'ALTER TABLE `tbl_customers` ADD COLUMN `ktp` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT "" COMMENT "KTP / ID Card Number" AFTER `coordinates`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
