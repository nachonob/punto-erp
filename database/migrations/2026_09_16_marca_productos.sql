-- Marca independiente de la categoría para el catálogo de productos.
-- La aplicación también aplica esta actualización de forma compatible al ingresar al ERP.

SET @brand_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'brand'
);

SET @brand_sql := IF(
  @brand_exists = 0,
  'ALTER TABLE products ADD COLUMN brand VARCHAR(120) NULL AFTER name, ADD INDEX idx_products_brand (brand)',
  'SELECT 1'
);

PREPARE brand_stmt FROM @brand_sql;
EXECUTE brand_stmt;
DEALLOCATE PREPARE brand_stmt;
