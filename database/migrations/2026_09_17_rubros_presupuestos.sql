-- Punto ERP - Rubros independientes para presupuestos
-- No elimina presupuestos, productos, categorías, marcas, plantillas ni importes.

CREATE TABLE IF NOT EXISTS quote_rubros (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE quotes
  MODIFY quote_category VARCHAR(150) NOT NULL DEFAULT 'General';

INSERT IGNORE INTO quote_rubros(name,active) VALUES
('General',1),
('Domótica',1),
('Redes',1),
('Cámaras',1),
('Alarma',1),
('Audio',1),
('Electricidad',1);

UPDATE quotes
SET quote_category=CASE quote_category
  WHEN 'general' THEN 'General'
  WHEN 'domotica' THEN 'Domótica'
  WHEN 'redes' THEN 'Redes'
  WHEN 'camaras' THEN 'Cámaras'
  WHEN 'alarma' THEN 'Alarma'
  WHEN 'audio' THEN 'Audio'
  WHEN 'electricidad' THEN 'Electricidad'
  ELSE quote_category
END
WHERE quote_category IN ('general','domotica','redes','camaras','alarma','audio','electricidad');
