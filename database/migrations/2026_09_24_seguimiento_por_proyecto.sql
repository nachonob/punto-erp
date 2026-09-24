-- El seguimiento comercial es único por proyecto. La aplicación completa
-- estas columnas y migra el historial existente de forma idempotente.
ALTER TABLE projects ADD COLUMN followup_sent_at DATETIME NULL;
ALTER TABLE projects ADD COLUMN followup_responsible_user_id INT UNSIGNED NULL;
ALTER TABLE projects ADD COLUMN next_followup_date DATE NULL;
ALTER TABLE projects ADD COLUMN followup_reminder_sent_at DATETIME NULL;
ALTER TABLE projects ADD COLUMN followup_closed_at DATETIME NULL;

CREATE TABLE project_followup_history (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 source_quote_id INT UNSIGNED NULL,
 user_id INT UNSIGNED NULL,
 event_type VARCHAR(40) NOT NULL,
 next_contact_date DATE NULL,
 notes TEXT NULL,
 event_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 legacy_quote_history_id INT UNSIGNED NULL,
 UNIQUE KEY uq_project_followup_legacy (legacy_quote_history_id),
 KEY idx_project_followup_project (project_id,event_date),
 KEY idx_project_followup_quote (source_quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
