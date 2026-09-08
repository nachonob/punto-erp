-- Punto ERP · Presupuestos por secciones
-- Ejecutar una sola vez en la base DEV antes de probar el nuevo constructor.

ALTER TABLE quote_items
  ADD COLUMN section_title VARCHAR(190) NULL AFTER brand,
  ADD COLUMN block_order INT NOT NULL DEFAULT 0 AFTER section_title;

CREATE TABLE IF NOT EXISTS quote_labor_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  tax_mode ENUM('sin_iva','mas_iva','iva_incluido') NOT NULL DEFAULT 'sin_iva',
  vat_rate DECIMAL(6,2) NOT NULL DEFAULT 21.00,
  block_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_quote_labor_items_quote (quote_id),
  CONSTRAINT fk_quote_labor_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;