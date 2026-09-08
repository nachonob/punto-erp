-- Punto ERP - Partes diarios técnicos
-- Ejecutar una sola vez en la base DEV.

CREATE TABLE IF NOT EXISTS technical_daily_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  hours_worked DECIMAL(5,2) NOT NULL DEFAULT 0,
  report_text TEXT NULL,
  transcription TEXT NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_technical_daily_report_event_user (event_id,user_id),
  KEY idx_technical_daily_report_user (user_id),
  CONSTRAINT fk_technical_daily_report_event FOREIGN KEY (event_id) REFERENCES technical_schedule_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_technical_daily_report_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS technical_daily_report_audio (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NULL,
  original_name VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_technical_daily_audio_report (report_id),
  CONSTRAINT fk_technical_daily_audio_report FOREIGN KEY (report_id) REFERENCES technical_daily_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
