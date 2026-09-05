SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND COLUMN_NAME='labor_description');
SET @sql := IF(@col_exists=0,'ALTER TABLE quotes ADD COLUMN labor_description VARCHAR(255) NULL AFTER labor_amount','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
