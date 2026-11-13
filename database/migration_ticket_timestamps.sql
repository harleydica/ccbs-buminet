-- Migration: Add opened_at and closed_at to tbl_tickets with backfill
-- Idempotent: checks column existence before adding.

SET @dbname = DATABASE();
SET @tablename = 'tbl_tickets';
SET @columnname = 'opened_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT "Columns opened_at/closed_at already exist in tbl_tickets" AS status',
  'ALTER TABLE `tbl_tickets` ADD COLUMN `opened_at` DATETIME NULL DEFAULT NULL AFTER `resolved_at`, ADD COLUMN `closed_at` DATETIME NULL DEFAULT NULL AFTER `opened_at`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

UPDATE `tbl_tickets` SET `opened_at` = `created_at` WHERE `opened_at` IS NULL;
UPDATE `tbl_tickets` SET `closed_at` = CASE WHEN `resolved_at` IS NOT NULL THEN `resolved_at` ELSE `created_at` END WHERE `closed_at` IS NULL AND `status` IN ('resolved', 'closed');
