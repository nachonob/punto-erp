-- Evidencia de aceptación electrónica de presupuestos comerciales.
-- La tabla conserva una instantánea inmutable de la propuesta y los datos técnicos de la confirmación.

CREATE TABLE IF NOT EXISTS sales_quote_acceptances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_quote_id INT UNSIGNED NOT NULL,
  accepted_by_name VARCHAR(190) NOT NULL,
  accepted_by_document VARCHAR(40) NOT NULL,
  accepted_at DATETIME NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  evidence_hash CHAR(64) NOT NULL,
  evidence_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_quote_acceptance (sales_quote_id),
  INDEX idx_sales_quote_acceptance_hash (evidence_hash),
  CONSTRAINT fk_sales_quote_acceptance_quote
    FOREIGN KEY (sales_quote_id) REFERENCES sales_quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
