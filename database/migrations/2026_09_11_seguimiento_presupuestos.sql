ALTER TABLE quotes
  ADD COLUMN sent_at DATE NULL AFTER quote_date,
  ADD COLUMN responsible_user_id INT UNSIGNED NULL AFTER sent_at,
  ADD COLUMN next_followup_date DATE NULL AFTER responsible_user_id,
  ADD COLUMN reminder_sent_at DATETIME NULL AFTER next_followup_date,
  ADD COLUMN followup_closed_at DATETIME NULL AFTER reminder_sent_at,
  ADD INDEX idx_quotes_followup (responsible_user_id, next_followup_date),
  ADD CONSTRAINT fk_quotes_responsible FOREIGN KEY (responsible_user_id) REFERENCES users(id);

-- Las filas históricas no tienen un creador confiable; se asignan al primer administrador.
UPDATE quotes SET responsible_user_id=(SELECT id FROM users ORDER BY id LIMIT 1) WHERE responsible_user_id IS NULL;
ALTER TABLE quotes MODIFY responsible_user_id INT UNSIGNED NOT NULL;

CREATE TABLE quote_followup_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  event_type ENUM('enviado','cliente_contactado','reprogramado','aprobado','rechazado','nota') NOT NULL,
  event_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  next_contact_date DATE NULL,
  notes TEXT NULL,
  INDEX idx_quote_history (quote_id,event_date),
  CONSTRAINT fk_quote_history_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
  CONSTRAINT fk_quote_history_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
