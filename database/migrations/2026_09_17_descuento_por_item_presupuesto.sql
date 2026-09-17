-- Descuento porcentual individual por línea de producto/presupuesto.
-- Los presupuestos existentes conservan 0% y sus importes actuales.
ALTER TABLE quote_items
    ADD COLUMN discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price;
