-- Punto ERP - Moneda por proyecto y apartado de recibos
-- Ejecutar UNA sola vez sobre una instalación existente desde phpMyAdmin.
-- Los proyectos existentes quedan expresados en pesos argentinos (ARS).

ALTER TABLE projects
    ADD COLUMN currency ENUM('ARS','USD') NOT NULL DEFAULT 'ARS' AFTER status;

