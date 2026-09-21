CREATE TABLE IF NOT EXISTS delivery_notes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_number VARCHAR(40) NULL UNIQUE,
 quote_id INT UNSIGNED NOT NULL,
 project_id INT UNSIGNED NOT NULL,
 location_id INT UNSIGNED NOT NULL,
 delivery_date DATE NOT NULL,
 status ENUM('borrador','confirmado','anulado') NOT NULL DEFAULT 'borrador',
 recipient_name VARCHAR(190) NULL,
 recipient_document VARCHAR(40) NULL,
 delivery_address VARCHAR(255) NULL,
 notes TEXT NULL,
 confirmed_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 confirmed_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_delivery_quote (quote_id,status),
 INDEX idx_delivery_project (project_id,delivery_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delivery_note_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_note_id BIGINT UNSIGNED NOT NULL,
 quote_item_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NULL,
 sku VARCHAR(190) NULL,
 description VARCHAR(255) NOT NULL,
 unit VARCHAR(60) NOT NULL DEFAULT 'Unidad',
 quantity DECIMAL(15,3) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_delivery_quote_item (delivery_note_id,quote_item_id),
 INDEX idx_delivery_item_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
