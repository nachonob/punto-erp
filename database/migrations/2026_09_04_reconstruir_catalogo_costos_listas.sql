-- Punto ERP DEV - Reconstrucción completa del catálogo y listas de precios
-- Compatible con MySQL 5.7 / GTID (sin tablas temporales ni DDL dentro de transacciones)
-- Fuente inicial: Lista de precios LifeSmart.xlsx
-- TODO el catálogo trabaja en USD.
--
-- IMPORTANTE:
-- - Conserva clientes, proyectos, presupuestos, pagos y recibos.
-- - Los renglones históricos de presupuestos conservan SKU, descripción, cantidad y precio.
-- - Sus vínculos product_id/price_list_id se ponen en NULL antes de reconstruir el catálogo.

SET @db := DATABASE();

-- 1) Desvincular referencias históricas antes de eliminar tablas del catálogo.
UPDATE quote_items SET product_id = NULL WHERE product_id IS NOT NULL;
UPDATE quote_items SET price_list_id = NULL WHERE price_list_id IS NOT NULL;
UPDATE quotes SET price_list_id = NULL WHERE price_list_id IS NOT NULL;

-- 2) Quitar FKs desde quote_items -> products.
SET @fk := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items'
    AND COLUMN_NAME='product_id' AND REFERENCED_TABLE_NAME='products'
  LIMIT 1
);
SET @sql := IF(@fk IS NULL,'SELECT 1',CONCAT('ALTER TABLE quote_items DROP FOREIGN KEY `',@fk,'`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Quitar FKs desde quote_items -> price_lists.
SET @fk := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items'
    AND COLUMN_NAME='price_list_id' AND REFERENCED_TABLE_NAME='price_lists'
  LIMIT 1
);
SET @sql := IF(@fk IS NULL,'SELECT 1',CONCAT('ALTER TABLE quote_items DROP FOREIGN KEY `',@fk,'`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Quitar FK desde quotes -> price_lists.
SET @fk := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quotes'
    AND COLUMN_NAME='price_list_id' AND REFERENCED_TABLE_NAME='price_lists'
  LIMIT 1
);
SET @sql := IF(@fk IS NULL,'SELECT 1',CONCAT('ALTER TABLE quotes DROP FOREIGN KEY `',@fk,'`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Eliminar por completo la estructura vieja de catálogo/precios/stock.
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS product_prices;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS price_lists;

-- 6) Mantener categorías existentes, o crear la tabla si no existiera.
CREATE TABLE IF NOT EXISTS product_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO product_categories(name,active)
VALUES ('LifeSmart / Domótica',1)
ON DUPLICATE KEY UPDATE active=1;

SET @lifesmart_category_id := (
  SELECT id FROM product_categories
  WHERE name='LifeSmart / Domótica'
  LIMIT 1
);

-- 7) Nueva tabla de productos: SKU + descripción + costo USD.
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(190) NOT NULL,
  description VARCHAR(255) NOT NULL,
  category_id INT UNSIGNED NULL,
  unit VARCHAR(60) NOT NULL DEFAULT 'Unidad',
  cost_usd DECIMAL(15,2) NOT NULL DEFAULT 0,
  stock_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  track_stock TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  image_data MEDIUMBLOB NULL,
  image_mime VARCHAR(60) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_sku (sku),
  INDEX idx_products_description (description),
  INDEX idx_products_category (category_id),
  CONSTRAINT fk_products_category
    FOREIGN KEY(category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8) Nueva política de listas: porcentaje de margen sobre costo.
CREATE TABLE price_lists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  markup_percentage DECIMAL(8,3) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_price_lists_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO price_lists(name,markup_percentage,active) VALUES
('Público',0,1),
('Gremio',0,1);

-- 9) Stock.
CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  movement_date DATE NOT NULL,
  movement_type ENUM('inicial','entrada','salida','ajuste') NOT NULL,
  quantity DECIMAL(15,3) NOT NULL,
  reference VARCHAR(190) NULL,
  notes TEXT NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stock_product_date(product_id,movement_date),
  CONSTRAINT fk_stock_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10) Asegurar columnas del constructor de presupuestos.
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quotes' AND COLUMN_NAME='quote_template_family'
);
SET @sql := IF(@exists=0,
  "ALTER TABLE quotes ADD COLUMN quote_template_family ENUM('lifesmart','control4','shelly') NULL AFTER quote_families",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quotes' AND COLUMN_NAME='price_list_id'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quotes ADD COLUMN price_list_id INT UNSIGNED NULL AFTER currency',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND COLUMN_NAME='product_id'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quote_items ADD COLUMN product_id INT UNSIGNED NULL AFTER quote_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND COLUMN_NAME='sku'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quote_items ADD COLUMN sku VARCHAR(190) NULL AFTER brand',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND COLUMN_NAME='unit'
);
SET @sql := IF(@exists=0,
  "ALTER TABLE quote_items ADD COLUMN unit VARCHAR(60) NOT NULL DEFAULT 'Unidad' AFTER description",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND COLUMN_NAME='price_list_id'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quote_items ADD COLUMN price_list_id INT UNSIGNED NULL AFTER unit_price',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Si alguna instalación anterior creó primary_family, migrar su valor.
SET @has_primary := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quotes' AND COLUMN_NAME='primary_family'
);
SET @sql := IF(@has_primary>0,
  "UPDATE quotes SET quote_template_family=CASE primary_family WHEN 'LifeSmart' THEN 'lifesmart' WHEN 'Control4' THEN 'control4' WHEN 'Shelly' THEN 'shelly' ELSE quote_template_family END WHERE quote_template_family IS NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 11) Volver a crear índices/FKs hacia el catálogo nuevo.
SET @exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quotes' AND INDEX_NAME='idx_quotes_price_list'
);
SET @sql := IF(@exists=0,'ALTER TABLE quotes ADD INDEX idx_quotes_price_list (price_list_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND INDEX_NAME='idx_quote_items_product'
);
SET @sql := IF(@exists=0,'ALTER TABLE quote_items ADD INDEX idx_quote_items_product (product_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quote_items' AND INDEX_NAME='idx_quote_items_price_list'
);
SET @sql := IF(@exists=0,'ALTER TABLE quote_items ADD INDEX idx_quote_items_price_list (price_list_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='quotes' AND CONSTRAINT_NAME='fk_quotes_price_list'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quotes ADD CONSTRAINT fk_quotes_price_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='quote_items' AND CONSTRAINT_NAME='fk_quote_items_product'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quote_items ADD CONSTRAINT fk_quote_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='quote_items' AND CONSTRAINT_NAME='fk_quote_items_price_list'
);
SET @sql := IF(@exists=0,
  'ALTER TABLE quote_items ADD CONSTRAINT fk_quote_items_price_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 12) Importación inicial LifeSmart (38 productos).
INSERT INTO products(sku,description,cost_usd,category_id,unit,track_stock,stock_quantity,active) VALUES
('LS082WH','Smart Station（HomekiT, ZigbeE, Coss）',59.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS227','Nature 7 PRO',290.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS280-AQ','Nature X PRO（US Adapter）',247.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS177','CUBE Switch Module (2 way)',31.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS193','CUBE Switch Module PRO (3 way )',34.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS124WH','Smart Light Switch (118/120 －Type， 2 Lanes) White',45.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS125WH','Smart Light Switch (118/120 －Type， 3 Lanes) White',48.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS124BL','Smart Light Switch (118/120 －Type， 2 Lanes) Black',45.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS125BL','Smart Light Switch (118/120 －Type， 3 Lanes) Black',48.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS218-WH3','Nature Mini L (White 3 way)',162.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS174','Dimmer&Motion Sensor Switch(Coss)',51.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS069WH','Cube Clicker',13.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS069WG','CUBE Clicker (Wood grain)',13.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS136','SPOT Universal Remote Controller (CoSS)',34.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS251WH','SPOT Mini',24.00,@lifesmart_category_id,'Unidad',1,0,1),
('C200','Smart door lock C200',162.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS063WH','Cube Environmental Sensor',24.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS258','Indoor Camera',54.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS259','Outdoor Camera',59.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS240-WCN','BLEND Curtain Controller PRO(White)',45.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS220-WT1','Nature Thermostat (White Frame - White Glass)',67.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS220-GT1','Nature Thermostat (Gray Frame - Black Glass)',67.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS215','Smart Home Starter Set',122.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS202WH','DEFED Door/Window Sensor',23.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS203WH','DEFED Motion Sensor',28.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS204WH','DEFED Indoor Siren',34.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS205WH','DEFED Smart Station ',135.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS219-WF3','Nature Switch L (White 3 way - White AG Glass)',49.00,@lifesmart_category_id,'Unidad',1,0,1),
('QS-Zigbee-D02-TRIAC-2C-LN','2 gang zigbee dimmer module',38.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS143','General Controller',43.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS012','Light Strip Controller',29.00,@lifesmart_category_id,'Unidad',1,0,1),
('81.00016','Light Strip (2meters)',20.00,@lifesmart_category_id,'Unidad',1,0,1),
('70.00013','Light Strip Connect Line',1.00,@lifesmart_category_id,'Unidad',1,0,1),
('24.90069','Light strip connection pin',1.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS240-GCN','BLEND Curtain Controller PRO(Gray)',49.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS268-GR3','Nature Mini PRO (Gray 3 way)',125.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS235WH','motion sensor pro',24.00,@lifesmart_category_id,'Unidad',1,0,1),
('LS058WH','Cube Door/Window Sensor',22.00,@lifesmart_category_id,'Unidad',1,0,1);

-- Verificación final.
SELECT COUNT(*) AS productos_importados FROM products;
SELECT id,name,markup_percentage,active FROM price_lists ORDER BY id;
SELECT sku,description,cost_usd FROM products ORDER BY id;
