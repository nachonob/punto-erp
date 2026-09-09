CREATE TABLE IF NOT EXISTS quote_public_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id INT NOT NULL,
  token CHAR(64) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quote_public_links_token (token),
  UNIQUE KEY uq_quote_public_links_quote (quote_id),
  KEY idx_quote_public_links_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;