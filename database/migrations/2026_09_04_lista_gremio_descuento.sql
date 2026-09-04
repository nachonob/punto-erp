-- Lista Gremio: 18% menos que Lista general y soporte para cálculos porcentuales.
-- Puede ejecutarse una sola vez sobre la base existente.

SET @base_column_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='price_lists' AND COLUMN_NAME='base_price_list_id'
);
SET @base_column_sql := IF(
  @base_column_exists=0,
  'ALTER TABLE price_lists ADD COLUMN base_price_list_id INT UNSIGNED NULL AFTER currency',
  'SELECT 1'
);
PREPARE base_column_statement FROM @base_column_sql;
EXECUTE base_column_statement;
DEALLOCATE PREPARE base_column_statement;

SET @discount_column_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='price_lists' AND COLUMN_NAME='discount_percentage'
);
SET @discount_column_sql := IF(
  @discount_column_exists=0,
  'ALTER TABLE price_lists ADD COLUMN discount_percentage DECIMAL(5,2) NULL AFTER base_price_list_id',
  'SELECT 1'
);
PREPARE discount_column_statement FROM @discount_column_sql;
EXECUTE discount_column_statement;
DEALLOCATE PREPARE discount_column_statement;

SET @general_list_id := (
  SELECT id FROM price_lists
  WHERE LOWER(name) IN ('lista general','general')
  ORDER BY CASE WHEN LOWER(name)='lista general' THEN 0 ELSE 1 END,id
  LIMIT 1
);

INSERT INTO price_lists(name,currency,base_price_list_id,discount_percentage,active)
SELECT 'Gremio','USD',@general_list_id,18.00,1
WHERE @general_list_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM price_lists WHERE LOWER(name)='gremio');

SET @gremio_list_id := (
  SELECT id FROM price_lists WHERE LOWER(name)='gremio' ORDER BY id LIMIT 1
);

UPDATE price_lists
SET currency='USD',base_price_list_id=@general_list_id,discount_percentage=18.00,active=1
WHERE id=@gremio_list_id AND @general_list_id IS NOT NULL;

INSERT INTO product_prices(product_id,price_list_id,price,currency)
SELECT product_id,@gremio_list_id,ROUND(price*0.82,2),currency
FROM product_prices
WHERE price_list_id=@general_list_id
  AND @general_list_id IS NOT NULL
  AND @gremio_list_id IS NOT NULL
ON DUPLICATE KEY UPDATE price=VALUES(price),currency=VALUES(currency),updated_at=CURRENT_TIMESTAMP;
