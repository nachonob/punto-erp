-- Punto ERP DEV - Inventario multidepósito
-- Compatible con MySQL 5.7 / Ferozo.
-- Ejecutar una sola vez sobre la base DEV.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  warehouse_type ENUM('principal','vehiculo','obra','otro') NOT NULL DEFAULT 'otro',
  project_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_warehouses_name (name),
  INDEX idx_warehouses_project(project_id),
  CONSTRAINT fk_warehouses_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO warehouses(name,warehouse_type,active)
VALUES ('Depósito principal','principal',1)
ON DUPLICATE KEY UPDATE active=1;

CREATE TABLE IF NOT EXISTS inventory_stock (
  warehouse_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  reserved_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  min_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(warehouse_id,product_id),
  INDEX idx_inventory_stock_product(product_id),
  CONSTRAINT fk_inventory_stock_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_stock_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  movement_date DATETIME NOT NULL,
  movement_type ENUM('entrada','salida','ajuste','transferencia','reserva','liberacion','consumo') NOT NULL,
  quantity DECIMAL(15,3) NOT NULL,
  warehouse_from_id INT UNSIGNED NULL,
  warehouse_to_id INT UNSIGNED NULL,
  project_id INT UNSIGNED NULL,
  reference_type VARCHAR(60) NULL,
  reference_id INT UNSIGNED NULL,
  reference VARCHAR(190) NULL,
  notes TEXT NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inventory_movements_product_date(product_id,movement_date),
  INDEX idx_inventory_movements_project(project_id),
  INDEX idx_inventory_movements_from(warehouse_from_id),
  INDEX idx_inventory_movements_to(warehouse_to_id),
  CONSTRAINT fk_inventory_movements_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_movements_from FOREIGN KEY(warehouse_from_id) REFERENCES warehouses(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_movements_to FOREIGN KEY(warehouse_to_id) REFERENCES warehouses(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_movements_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_movements_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  quote_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  quantity DECIMAL(15,3) NOT NULL,
  status ENUM('activa','liberada','consumida') NOT NULL DEFAULT 'activa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  INDEX idx_inventory_res_project(project_id),
  INDEX idx_inventory_res_product(product_id),
  CONSTRAINT fk_inventory_res_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_res_quote FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_res_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_res_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrar stock actual al Depósito principal sin duplicar si se vuelve a consultar el archivo.
SET @principal_id := (SELECT id FROM warehouses WHERE name='Depósito principal' LIMIT 1);
INSERT INTO inventory_stock(warehouse_id,product_id,quantity,reserved_quantity,min_quantity)
SELECT @principal_id,p.id,p.stock_quantity,0,0
FROM products p
WHERE p.track_stock=1
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);

SELECT 'Inventario creado' AS resultado,
       (SELECT COUNT(*) FROM warehouses) AS depositos,
       (SELECT COUNT(*) FROM inventory_stock) AS posiciones_stock;
