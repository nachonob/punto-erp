-- Restauración selectiva de precios desde Producto (product.template)(1).xlsx.
-- Solo actualiza valores distintos de 0 y 1, los interpreta como USD y recalcula Gremio al 82%.

CREATE TABLE IF NOT EXISTS product_price_backups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  backup_batch VARCHAR(80) NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  price_list_id INT UNSIGNED NOT NULL,
  price DECIMAL(15,2) NOT NULL,
  currency ENUM('ARS','USD') NOT NULL,
  backed_up_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_price_backup_batch(backup_batch,product_id,price_list_id),
  INDEX idx_product_price_backup_product(product_id),
  CONSTRAINT fk_product_price_backup_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_price_backup_list FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @general_list_id := (
  SELECT id FROM price_lists
  WHERE LOWER(name) IN ('lista general','general')
  ORDER BY CASE WHEN LOWER(name)='lista general' THEN 0 ELSE 1 END,id
  LIMIT 1
);
SET @gremio_list_id := (
  SELECT id FROM price_lists WHERE LOWER(name)='gremio' ORDER BY id LIMIT 1
);

INSERT INTO product_price_backups(backup_batch,product_id,price_list_id,price,currency)
SELECT 'restauracion_excel_2026_09_04',pp.product_id,pp.price_list_id,pp.price,pp.currency
FROM product_prices pp
WHERE pp.price_list_id IN (@general_list_id,@gremio_list_id)
ON DUPLICATE KEY UPDATE backed_up_at=backed_up_at;

CREATE TEMPORARY TABLE corrected_product_prices (
  source_key VARCHAR(120) PRIMARY KEY,
  price DECIMAL(15,2) NOT NULL
);

INSERT INTO corrected_product_prices(source_key,price) VALUES
('odoo-row-2',126.00),
('odoo-row-4',48.00),
('odoo-row-11',150.00),
('odoo-row-19',190.00),
('odoo-row-23',260.00),
('odoo-row-29',-1455.00),
('odoo-row-30',324.00),
('odoo-row-40',117.00),
('odoo-row-53',450.00),
('odoo-row-65',310.00),
('odoo-row-67',390.00),
('odoo-row-68',350.00),
('odoo-row-69',450.00),
('odoo-row-73',2500.00),
('odoo-row-98',-727.00),
('odoo-row-99',336.00),
('odoo-row-133',38.00),
('odoo-row-134',260.00),
('odoo-row-137',63.00),
('odoo-row-148',220.00),
('odoo-row-150',80.00),
('odoo-row-151',44.00),
('odoo-row-153',48.00),
('odoo-row-154',26.00),
('odoo-row-155',26.00),
('odoo-row-156',118.00),
('odoo-row-157',82.00),
('odoo-row-158',82.00),
('odoo-row-159',86.00),
('odoo-row-160',86.00),
('odoo-row-161',86.00),
('odoo-row-162',43.79),
('odoo-row-163',102.00),
('odoo-row-164',660.00),
('odoo-row-165',62.00),
('odoo-row-168',68.00),
('odoo-row-169',46.00),
('odoo-row-170',56.00),
('odoo-row-171',68.00),
('odoo-row-173',270.00),
('odoo-row-174',244.00),
('odoo-row-175',324.00),
('odoo-row-176',98.00),
('odoo-row-177',134.00),
('odoo-row-178',134.00),
('odoo-row-179',595.00),
('odoo-row-180',48.00),
('odoo-row-181',98.00),
('odoo-row-182',90.00),
('odoo-row-183',68.00),
('odoo-row-184',48.00),
('odoo-row-185',108.00),
('odoo-row-186',118.00),
('odoo-row-189',250.00),
('odoo-row-190',494.00),
('odoo-row-197',350.00),
('odoo-row-224',5590.00),
('odoo-row-232',1480.00),
('odoo-row-233',1198.00),
('odoo-row-250',126.00),
('odoo-row-282',1090.00),
('odoo-row-293',48.00),
('odoo-row-299',180.00),
('odoo-row-300',50.00),
('odoo-row-302',60.00),
('odoo-row-316',150.00),
('odoo-row-317',160.00),
('odoo-row-321',48.00);

INSERT INTO product_prices(product_id,price_list_id,price,currency)
SELECT p.id,@general_list_id,c.price,'USD'
FROM corrected_product_prices c
JOIN products p ON p.source_key=c.source_key
WHERE @general_list_id IS NOT NULL
ON DUPLICATE KEY UPDATE price=VALUES(price),currency='USD',updated_at=CURRENT_TIMESTAMP;

INSERT INTO product_prices(product_id,price_list_id,price,currency)
SELECT p.id,@gremio_list_id,ROUND(c.price*0.82,2),'USD'
FROM corrected_product_prices c
JOIN products p ON p.source_key=c.source_key
WHERE @gremio_list_id IS NOT NULL
ON DUPLICATE KEY UPDATE price=VALUES(price),currency='USD',updated_at=CURRENT_TIMESTAMP;

UPDATE price_lists
SET currency='USD',base_price_list_id=NULL,discount_percentage=NULL
WHERE id=@general_list_id;

UPDATE price_lists
SET currency='USD',base_price_list_id=@general_list_id,discount_percentage=18.00
WHERE id=@gremio_list_id;

DROP TEMPORARY TABLE corrected_product_prices;

