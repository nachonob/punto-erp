-- Punto ERP - Actualización del módulo Clientes
-- Ejecutar UNA sola vez sobre una instalación existente desde phpMyAdmin.
-- No elimina clientes, proyectos, pagos ni presupuestos existentes.

ALTER TABLE clients
    ADD COLUMN iva_condition VARCHAR(60) NULL AFTER cuit,
    ADD COLUMN city VARCHAR(120) NULL AFTER address,
    ADD COLUMN province VARCHAR(120) NULL AFTER city,
    ADD COLUMN country VARCHAR(120) NOT NULL DEFAULT 'Argentina' AFTER province;

-- La columna phone anterior se conserva en la base para no perder información
-- histórica, pero Punto ERP deja de mostrarla y utilizarla.

