-- Punto ERP - Planificación técnica / Schedule
-- Ejecutar una sola vez en la base DEV.

CREATE TABLE IF NOT EXISTS technical_schedule_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  title VARCHAR(190) NOT NULL DEFAULT 'Trabajo en obra',
  notes TEXT NULL,
  status ENUM('planificado','realizado','cancelado') NOT NULL DEFAULT 'planificado',
  created_by_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_technical_schedule_date (work_date),
  KEY idx_technical_schedule_project (project_id),
  CONSTRAINT fk_technical_schedule_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_technical_schedule_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS technical_schedule_users (
  event_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id,user_id),
  KEY idx_technical_schedule_users_user (user_id),
  CONSTRAINT fk_technical_schedule_users_event FOREIGN KEY (event_id) REFERENCES technical_schedule_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_technical_schedule_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
