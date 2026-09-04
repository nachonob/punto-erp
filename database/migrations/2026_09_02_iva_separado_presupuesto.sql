-- Punto ERP - IVA separado para Materiales y Mano de obra
-- Ejecutar UNA sola vez después de 2026_09_02_moneda_por_presupuesto.sql.
-- Los presupuestos existentes conservan su configuración actual en ambos conceptos.

ALTER TABLE quotes
    ADD COLUMN materials_tax_mode ENUM('sin_iva','iva_incluido','mas_iva') NULL AFTER vat_rate,
    ADD COLUMN materials_vat_rate DECIMAL(5,2) NULL AFTER materials_tax_mode,
    ADD COLUMN labor_tax_mode ENUM('sin_iva','iva_incluido','mas_iva') NULL AFTER materials_vat_rate,
    ADD COLUMN labor_vat_rate DECIMAL(5,2) NULL AFTER labor_tax_mode;

UPDATE quotes
SET materials_tax_mode = tax_mode,
    materials_vat_rate = vat_rate,
    labor_tax_mode = tax_mode,
    labor_vat_rate = vat_rate;

ALTER TABLE quotes
    MODIFY materials_tax_mode ENUM('sin_iva','iva_incluido','mas_iva') NOT NULL DEFAULT 'sin_iva',
    MODIFY materials_vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21,
    MODIFY labor_tax_mode ENUM('sin_iva','iva_incluido','mas_iva') NOT NULL DEFAULT 'sin_iva',
    MODIFY labor_vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21;
