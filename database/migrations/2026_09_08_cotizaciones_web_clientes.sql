-- Punto ERP · Cotizaciones web
-- Ejecutar una sola vez sobre la base usada por el ERP que recibirá los formularios.

ALTER TABLE clients
  ADD COLUMN client_number INT UNSIGNED NULL AFTER id;

SET @next_client_number := 0;
UPDATE clients
SET client_number = (@next_client_number := @next_client_number + 1)
WHERE client_number IS NULL
ORDER BY id;

ALTER TABLE clients
  ADD UNIQUE KEY uq_clients_client_number (client_number);

CREATE TABLE IF NOT EXISTS web_quote_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_number VARCHAR(30) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source VARCHAR(80) NOT NULL DEFAULT 'cotizar-proyecto',
  contact_name VARCHAR(180) NOT NULL,
  whatsapp VARCHAR(80) NOT NULL,
  email VARCHAR(190) NULL,
  location VARCHAR(190) NOT NULL,
  project_stage VARCHAR(120) NULL,
  property_type VARCHAR(80) NULL,
  surface_m2 INT NULL,
  floors INT NULL,
  estimated_date VARCHAR(20) NULL,
  bedrooms INT NULL,
  bathrooms INT NULL,
  rooms TEXT NULL,
  systems TEXT NULL,
  climate_types TEXT NULL,
  switches_qty INT NULL,
  splits_qty INT NULL,
  thermostats_qty INT NULL,
  curtains_qty INT NULL,
  cameras_qty INT NULL,
  smart_locks_qty INT NULL,
  audio_zones_qty INT NULL,
  plans_available TINYINT(1) NOT NULL DEFAULT 0,
  plans_file VARCHAR(255) NULL,
  budget_range VARCHAR(120) NULL,
  comments TEXT NULL,
  payload_json LONGTEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_web_quote_requests_number (request_number),
  KEY idx_web_quote_requests_client (client_id),
  CONSTRAINT fk_web_quote_requests_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
