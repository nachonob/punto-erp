-- Distingue los planos PDF de los archivos CAD del proyecto.
-- La aplicación también aplica esta columna automáticamente para instalaciones DEV existentes.
ALTER TABLE project_plan_files
  ADD COLUMN file_kind VARCHAR(10) NOT NULL DEFAULT 'pdf' AFTER project_id;
