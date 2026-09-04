-- Permite definir ARS o USD individualmente para cada precio de producto.
-- Conserva la moneda que tenía la lista al momento de ejecutar esta migración.

ALTER TABLE product_prices
  ADD COLUMN currency ENUM('ARS','USD') NULL AFTER price;

UPDATE product_prices pp
JOIN price_lists pl ON pl.id=pp.price_list_id
SET pp.currency=pl.currency
WHERE pp.currency IS NULL;

ALTER TABLE product_prices
  MODIFY COLUMN currency ENUM('ARS','USD') NOT NULL DEFAULT 'ARS' AFTER price;
