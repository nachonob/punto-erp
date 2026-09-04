-- Punto ERP - Constructor de presupuestos desde catalogo
-- Ejecutar UNA sola vez en phpMyAdmin sobre la base de DEV.
-- No elimina presupuestos, proyectos, clientes, pagos ni cargos existentes.

ALTER TABLE quotes
  ADD COLUMN quote_template_family ENUM('lifesmart','control4','shelly') NULL AFTER quote_families,
  ADD COLUMN price_list_id INT UNSIGNED NULL AFTER currency,
  ADD INDEX idx_quotes_price_list (price_list_id),
  ADD CONSTRAINT fk_quotes_price_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE SET NULL;

ALTER TABLE quote_items
  ADD COLUMN product_id INT UNSIGNED NULL AFTER quote_id,
  ADD COLUMN sku VARCHAR(190) NULL AFTER brand,
  ADD COLUMN unit VARCHAR(60) NOT NULL DEFAULT 'Unidad' AFTER description,
  ADD COLUMN price_list_id INT UNSIGNED NULL AFTER unit_price,
  ADD INDEX idx_quote_items_product (product_id),
  ADD INDEX idx_quote_items_price_list (price_list_id),
  ADD CONSTRAINT fk_quote_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_quote_items_price_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE SET NULL;
