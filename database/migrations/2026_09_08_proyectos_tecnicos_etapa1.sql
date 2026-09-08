-- Punto ERP - Proyectos técnicos / Etapa 1
-- Ejecutar una sola vez sobre la base DEV.

ALTER TABLE projects
  ADD COLUMN technical_status ENUM('pendiente_planificacion','planificado','en_instalacion','esperando_material','esperando_terceros','puesta_en_marcha','finalizado') NOT NULL DEFAULT 'pendiente_planificacion' AFTER status,
  ADD COLUMN technical_manager_user_id INT UNSIGNED NULL AFTER technical_status,
  ADD INDEX idx_projects_technical_status (technical_status),
  ADD INDEX idx_projects_technical_manager (technical_manager_user_id),
  ADD CONSTRAINT fk_projects_technical_manager FOREIGN KEY (technical_manager_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE project_technical_users (
  project_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assigned_by_user_id INT UNSIGNED NULL,
  PRIMARY KEY (project_id,user_id),
  KEY idx_project_technical_users_user (user_id),
  CONSTRAINT fk_project_technical_users_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_technical_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_technical_users_assigned_by FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO modules(module_key,name,enabled,sort_order)
VALUES ('technical_projects','Proyectos técnicos',1,45)
ON DUPLICATE KEY UPDATE name=VALUES(name),enabled=1,sort_order=VALUES(sort_order);

INSERT INTO permissions(permission_key,name,module_key)
VALUES
 ('technical_projects.view','Ver proyectos técnicos','technical_projects'),
 ('technical_projects.manage','Administrar proyectos técnicos','technical_projects')
ON DUPLICATE KEY UPDATE name=VALUES(name),module_key=VALUES(module_key);
