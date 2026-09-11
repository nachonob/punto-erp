-- Estados, aceptación pública y control de versiones para presupuestos comerciales.

ALTER TABLE sales_quotes
  MODIFY status ENUM('borrador','enviado','visto','aprobado','aprobado_inicial','aprobado_final','rechazado','vencido','reemplazado') NOT NULL DEFAULT 'borrador',
  ADD COLUMN parent_quote_id INT UNSIGNED NULL AFTER id,
  ADD COLUMN version_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER quote_number,
  ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 1 AFTER version_no,
  ADD COLUMN approved_initial_at DATETIME NULL AFTER status,
  ADD COLUMN approved_initial_by VARCHAR(190) NULL AFTER approved_initial_at,
  ADD COLUMN approved_final_at DATETIME NULL AFTER approved_initial_by,
  ADD COLUMN approved_final_by INT UNSIGNED NULL AFTER approved_final_at,
  ADD COLUMN superseded_at DATETIME NULL AFTER approved_final_by,
  ADD COLUMN project_quote_id INT UNSIGNED NULL AFTER superseded_at,
  ADD INDEX idx_sales_quote_versions(parent_quote_id,version_no),
  ADD INDEX idx_sales_quote_current(project_id,is_current,status),
  ADD CONSTRAINT fk_sales_quote_parent FOREIGN KEY(parent_quote_id) REFERENCES sales_quotes(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sales_quote_final_user FOREIGN KEY(approved_final_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sales_quote_project_quote FOREIGN KEY(project_quote_id) REFERENCES quotes(id) ON DELETE SET NULL;

UPDATE sales_quotes SET status='aprobado_inicial' WHERE status='aprobado';

ALTER TABLE sales_quotes
  MODIFY status ENUM('borrador','enviado','visto','aprobado_inicial','aprobado_final','rechazado','vencido','reemplazado') NOT NULL DEFAULT 'borrador';

CREATE TABLE sales_quote_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_quote_id INT UNSIGNED NOT NULL,
  event_type ENUM('enviado_email','enviado_whatsapp','visto','aprobado_inicial','aprobado_final','rechazado','version_creada') NOT NULL,
  actor_name VARCHAR(190) NULL,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  details VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_quote_events_quote(sales_quote_id,created_at),
  CONSTRAINT fk_sales_quote_event_quote FOREIGN KEY(sales_quote_id) REFERENCES sales_quotes(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_quote_event_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
