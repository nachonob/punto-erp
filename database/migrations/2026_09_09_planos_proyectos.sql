-- Punto ERP DEV · Planos PDF asociados a proyectos
CREATE TABLE IF NOT EXISTS project_plan_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  file_size BIGINT UNSIGNED NULL,
  source ENUM('manual','web') NOT NULL DEFAULT 'manual',
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_plan_files_project (project_id),
  CONSTRAINT fk_project_plan_files_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
