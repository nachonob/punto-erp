-- Punto ERP DEV · Items manuales en presupuestos
-- Hace nullable product_id conservando exactamente su tipo actual y agrega marca de item manual.

SET @db := DATABASE();
SET @col_type := (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND COLUMN_NAME='product_id'
  LIMIT 1
);
SET @sql := IF(
  @col_type IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE quote_items MODIFY product_id ', @col_type, ' NULL')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND COLUMN_NAME='is_manual'
);
SET @sql := IF(
  @exists=0,
  'ALTER TABLE quote_items ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0 AFTER product_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
