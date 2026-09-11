-- Punto ERP - Arquitectos, estudios y constructoras
-- Ejecutar UNA sola vez sobre una instalación existente desde phpMyAdmin.
-- No modifica ni elimina clientes, proyectos, presupuestos o pagos existentes.

CREATE TABLE project_partners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_type ENUM('arquitecto','estudio_arquitectura','constructora','desarrolladora','otro') NOT NULL DEFAULT 'arquitecto',
    business_name VARCHAR(180) NOT NULL,
    contact_name VARCHAR(150) NULL,
    cuit VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    whatsapp VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    province VARCHAR(120) NULL,
    country VARCHAR(120) NOT NULL DEFAULT 'Argentina',
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE projects
    ADD COLUMN partner_id INT UNSIGNED NULL AFTER client_id,
    ADD INDEX idx_projects_partner_id (partner_id),
    ADD CONSTRAINT fk_projects_partner
        FOREIGN KEY (partner_id) REFERENCES project_partners(id)
        ON DELETE SET NULL;

