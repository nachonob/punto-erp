CREATE TABLE IF NOT EXISTS product_price_overrides (
  product_id INT UNSIGNED NOT NULL,
  price_list_id INT UNSIGNED NOT NULL,
  markup_percentage DECIMAL(8,3) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id, price_list_id),
  CONSTRAINT fk_product_price_overrides_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_price_overrides_list
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;