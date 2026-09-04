-- Separación del catálogo comercial y el módulo de Inventario.
-- Conserva products, stock_quantity y stock_movements como compatibilidad histórica.

CREATE TABLE inventory_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  location_type ENUM('deposito','vehiculo','obra','otro') NOT NULL DEFAULT 'deposito',
  address VARCHAR(255) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory_items (
  product_id INT UNSIGNED PRIMARY KEY,
  track_stock TINYINT(1) NOT NULL DEFAULT 1,
  minimum_stock DECIMAL(15,3) NOT NULL DEFAULT 0,
  reorder_point DECIMAL(15,3) NOT NULL DEFAULT 0,
  allow_negative TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inventory_item_product
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory_balances (
  product_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(product_id,location_id),
  CONSTRAINT fk_inventory_balance_product
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_balance_location
    FOREIGN KEY(location_id) REFERENCES inventory_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  movement_date DATE NOT NULL,
  movement_type ENUM(
    'inicial','entrada','salida','ajuste',
    'transferencia_salida','transferencia_entrada'
  ) NOT NULL,
  quantity DECIMAL(15,3) NOT NULL,
  source_module ENUM(
    'manual','ventas','compras','proyectos','importacion'
  ) NOT NULL DEFAULT 'manual',
  reference_type VARCHAR(60) NULL,
  reference_id BIGINT UNSIGNED NULL,
  document_number VARCHAR(120) NULL,
  notes TEXT NULL,
  transfer_group VARCHAR(64) NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inventory_movement_product_date(product_id,movement_date),
  INDEX idx_inventory_movement_location_date(location_id,movement_date),
  INDEX idx_inventory_movement_source(source_module,reference_type,reference_id),
  INDEX idx_inventory_movement_transfer(transfer_group),
  CONSTRAINT fk_inventory_movement_product
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_movement_location
    FOREIGN KEY(location_id) REFERENCES inventory_locations(id),
  CONSTRAINT fk_inventory_movement_user
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inventory_locations(code,name,location_type,is_default,active)
VALUES('DEP-PRINCIPAL','Depósito principal','deposito',1,1);

SET @default_inventory_location := (
  SELECT id FROM inventory_locations WHERE code='DEP-PRINCIPAL' LIMIT 1
);

INSERT INTO inventory_items(product_id,track_stock,minimum_stock,reorder_point,allow_negative)
SELECT id,track_stock,0,0,0
FROM products
ON DUPLICATE KEY UPDATE track_stock=VALUES(track_stock);

INSERT INTO inventory_balances(product_id,location_id,quantity)
SELECT id,@default_inventory_location,stock_quantity
FROM products
WHERE track_stock=1
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);

INSERT INTO inventory_movements(
  product_id,location_id,movement_date,movement_type,quantity,
  source_module,document_number,notes,user_id,created_at
)
SELECT
  sm.product_id,@default_inventory_location,sm.movement_date,
  CASE sm.movement_type
    WHEN 'inicial' THEN 'inicial'
    WHEN 'entrada' THEN 'entrada'
    WHEN 'salida' THEN 'salida'
    ELSE 'ajuste'
  END,
  sm.quantity,'importacion',sm.reference,sm.notes,sm.user_id,sm.created_at
FROM stock_movements sm
WHERE NOT EXISTS (
  SELECT 1 FROM inventory_movements im
  WHERE im.source_module='importacion'
    AND im.product_id=sm.product_id
    AND im.movement_date=sm.movement_date
    AND im.quantity=sm.quantity
    AND COALESCE(im.document_number,'')=COALESCE(sm.reference,'')
);

INSERT INTO profile_module_permissions(profile_id,module_key,can_view,can_manage)
SELECT profile_id,'inventory',can_view,can_manage
FROM profile_module_permissions
WHERE module_key='products'
ON DUPLICATE KEY UPDATE
  can_view=VALUES(can_view),
  can_manage=VALUES(can_manage);
