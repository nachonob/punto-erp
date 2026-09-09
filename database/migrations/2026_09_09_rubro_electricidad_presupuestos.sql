-- Punto ERP DEV · Habilitar rubro Electricidad en presupuestos
ALTER TABLE quotes
  MODIFY quote_category ENUM('general','domotica','redes','camaras','alarma','audio','electricidad')
  NOT NULL DEFAULT 'general';

-- Corrige presupuestos que habían intentado guardarse como Electricidad
-- antes de que el ENUM admitiera ese valor. MySQL los dejó como cadena vacía.
UPDATE quotes
SET quote_category='electricidad'
WHERE quote_category='';
