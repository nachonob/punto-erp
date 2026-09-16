-- Catálogo administrable de marcas y vínculo por nombre con products.brand.
-- Compatible con instalaciones existentes y seguro para ejecutar más de una vez.

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

CREATE TABLE IF NOT EXISTS product_brands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO product_brands(name,active)
SELECT DISTINCT TRIM(brand),1
FROM products
WHERE brand IS NOT NULL AND TRIM(brand)<>'';

INSERT IGNORE INTO product_brands(name,active) VALUES('LifeSmart',1);

UPDATE products p
JOIN product_categories pc ON pc.id=p.category_id
SET p.brand='LifeSmart'
WHERE (p.brand IS NULL OR TRIM(p.brand)='')
  AND LOWER(pc.name) LIKE '%lifesmart%';
