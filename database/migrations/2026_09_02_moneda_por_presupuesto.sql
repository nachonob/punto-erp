-- Punto ERP - Moneda independiente por presupuesto
-- Ejecutar UNA sola vez después de 2026_09_02_presupuestos_por_rubro.sql.
-- Conserva la moneda anterior de cada proyecto para todos sus datos existentes.

ALTER TABLE quotes ADD COLUMN currency ENUM('ARS','USD') NULL AFTER quote_category;
UPDATE quotes q JOIN projects p ON p.id=q.project_id SET q.currency=p.currency;
ALTER TABLE quotes MODIFY currency ENUM('ARS','USD') NOT NULL DEFAULT 'ARS';

ALTER TABLE charges ADD COLUMN currency ENUM('ARS','USD') NULL AFTER type;
UPDATE charges c JOIN projects p ON p.id=c.project_id SET c.currency=p.currency;
ALTER TABLE charges MODIFY currency ENUM('ARS','USD') NOT NULL DEFAULT 'ARS';

ALTER TABLE payments ADD COLUMN currency ENUM('ARS','USD') NULL AFTER project_id;
UPDATE payments pa JOIN projects p ON p.id=pa.project_id SET pa.currency=p.currency;
ALTER TABLE payments MODIFY currency ENUM('ARS','USD') NOT NULL DEFAULT 'ARS';

ALTER TABLE charges ADD INDEX idx_charges_project_currency (project_id,currency,active);
ALTER TABLE payments ADD INDEX idx_payments_project_currency (project_id,currency);
