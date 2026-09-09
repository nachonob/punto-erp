-- Punto ERP DEV · Habilitar plantilla Electricidad en presupuestos
-- Ejecutar UNA sola vez en la base DEV.
ALTER TABLE quotes
  MODIFY COLUMN quote_template_family ENUM('lifesmart','control4','shelly','electricidad') NULL;
