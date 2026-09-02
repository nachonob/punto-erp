-- Punto ERP - Presupuestos separados por rubro
-- Ejecutar UNA sola vez sobre una instalación existente desde phpMyAdmin.
-- No elimina ni modifica importes, PDFs, pagos o proyectos existentes.

ALTER TABLE quotes
    ADD COLUMN quote_category ENUM('general','domotica','redes','camaras','alarma','audio')
        NOT NULL DEFAULT 'general' AFTER version_no,
    ADD INDEX idx_quotes_project_category (project_id, quote_category);
