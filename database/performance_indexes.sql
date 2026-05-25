-- Lis'onTech - performance indexes for the admin panel
-- Run this file in the same MySQL database used by the site.
-- Safe to run more than once.

DROP PROCEDURE IF EXISTS lison_add_index_if_missing;
DELIMITER $$
CREATE PROCEDURE lison_add_index_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_alter_sql TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @sql = p_alter_sql;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL lison_add_index_if_missing(
  'automation_runs',
  'idx_automation_runs_created',
  'ALTER TABLE automation_runs ADD INDEX idx_automation_runs_created (created_at)'
);

CALL lison_add_index_if_missing(
  'automation_runs',
  'idx_automation_runs_updated',
  'ALTER TABLE automation_runs ADD INDEX idx_automation_runs_updated (updated_at)'
);

CALL lison_add_index_if_missing(
  'automation_runs',
  'idx_automation_runs_status_created',
  'ALTER TABLE automation_runs ADD INDEX idx_automation_runs_status_created (status, created_at)'
);

CALL lison_add_index_if_missing(
  'automation_runs',
  'idx_automation_runs_bill_id',
  'ALTER TABLE automation_runs ADD INDEX idx_automation_runs_bill_id (bill_id)'
);

DROP PROCEDURE IF EXISTS lison_add_index_if_missing;
