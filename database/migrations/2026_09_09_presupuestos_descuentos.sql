CREATE TABLE IF NOT EXISTS quote_discounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id INT NOT NULL,
  scope ENUM('general','materials','labor','item') NOT NULL DEFAULT 'general',
  item_type ENUM('material','labor') NULL,
  item_id INT NULL,
  discount_type ENUM('amount','percentage') NOT NULL DEFAULT 'percentage',
  value DECIMAL(12,2) NOT NULL DEFAULT 0,
  description VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quote_discounts_quote (quote_id),
  KEY idx_quote_discounts_item (item_type,item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;