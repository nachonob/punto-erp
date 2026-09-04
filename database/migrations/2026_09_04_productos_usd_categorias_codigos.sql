-- Ajuste del catálogo importado: moneda USD, categorías administrables y corrección nombre/código.
-- Es compatible tanto si ya se ejecutó como si todavía no se ejecutó la migración de moneda por precio.

SET @currency_column_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_prices' AND COLUMN_NAME='currency'
);
SET @currency_sql := IF(
  @currency_column_exists=0,
  'ALTER TABLE product_prices ADD COLUMN currency ENUM(''ARS'',''USD'') NOT NULL DEFAULT ''USD'' AFTER price',
  'SELECT 1'
);
PREPARE currency_statement FROM @currency_sql;
EXECUTE currency_statement;
DEALLOCATE PREPARE currency_statement;

UPDATE product_prices SET currency='USD';
UPDATE price_lists SET currency='USD';

CREATE TEMPORARY TABLE product_name_code_swap (
  product_id INT UNSIGNED PRIMARY KEY,
  old_name VARCHAR(255) NOT NULL,
  old_sku VARCHAR(190) NOT NULL
);

INSERT INTO product_name_code_swap(product_id,old_name,old_sku)
SELECT id,name,sku FROM products
WHERE source_key IN ('odoo-row-14','odoo-row-23','odoo-row-24','odoo-row-26','odoo-row-27','odoo-row-28','odoo-row-30','odoo-row-31','odoo-row-32','odoo-row-33','odoo-row-34','odoo-row-35','odoo-row-36','odoo-row-79','odoo-row-80','odoo-row-121','odoo-row-137','odoo-row-138','odoo-row-139','odoo-row-140','odoo-row-141','odoo-row-150','odoo-row-151','odoo-row-152','odoo-row-153','odoo-row-154','odoo-row-156','odoo-row-157','odoo-row-158','odoo-row-159','odoo-row-160','odoo-row-161','odoo-row-162','odoo-row-163','odoo-row-164','odoo-row-165','odoo-row-166','odoo-row-167','odoo-row-168','odoo-row-169','odoo-row-170','odoo-row-171','odoo-row-172','odoo-row-173','odoo-row-174','odoo-row-175','odoo-row-176','odoo-row-177','odoo-row-178','odoo-row-179','odoo-row-180','odoo-row-181','odoo-row-182','odoo-row-183','odoo-row-184','odoo-row-185','odoo-row-186','odoo-row-187','odoo-row-188','odoo-row-189','odoo-row-190','odoo-row-202','odoo-row-244','odoo-row-248','odoo-row-249','odoo-row-253','odoo-row-257','odoo-row-258','odoo-row-293','odoo-row-299','odoo-row-305','odoo-row-306','odoo-row-307','odoo-row-308','odoo-row-309')
  AND sku IS NOT NULL AND sku<>'';

UPDATE products p
JOIN product_name_code_swap s ON s.product_id=p.id
SET p.name=s.old_sku,
    p.sku=s.old_name;

DROP TEMPORARY TABLE product_name_code_swap;
