-- Evidencia de aceptación electrónica para presupuestos del módulo Proyectos/Quotes.

CREATE TABLE IF NOT EXISTS quote_acceptances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  accepted_by_name VARCHAR(190) NOT NULL,
  accepted_by_document VARCHAR(40) NOT NULL,
  accepted_at DATETIME NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  evidence_hash CHAR(64) NOT NULL,
  evidence_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quote_acceptance (quote_id),
  INDEX idx_quote_acceptance_hash (evidence_hash),
  CONSTRAINT fk_quote_acceptance_quote
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
