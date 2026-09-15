-- Porcentajes específicos por categoría para cada lista de precios.
-- En listas base el porcentaje es recargo sobre costo.
-- En listas derivadas (por ejemplo Gremio) es descuento sobre la lista base.
CREATE TABLE IF NOT EXISTS product_category_price_rules (
  price_list_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  percentage DECIMAL(8,3) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(price_list_id,category_id),
  CONSTRAINT fk_pcpr_list FOREIGN KEY(price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
  CONSTRAINT fk_pcpr_category FOREIGN KEY(category_id) REFERENCES product_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
