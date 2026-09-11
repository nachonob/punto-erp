-- Módulo comercial de presupuestos basado en productos y listas de precios.

CREATE TABLE sales_quote_templates (
  proposal_type ENUM('lifesmart','control4','shelly','general') PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  intro_pdf_path VARCHAR(255) NULL,
  final_pdf_path VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_quotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_number VARCHAR(50) NOT NULL UNIQUE,
  client_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NULL,
  price_list_id INT UNSIGNED NOT NULL,
  proposal_type ENUM('lifesmart','control4','shelly','general') NOT NULL DEFAULT 'general',
  contact_name VARCHAR(150) NULL,
  contact_email VARCHAR(190) NULL,
  contact_whatsapp VARCHAR(50) NULL,
  issue_date DATE NOT NULL,
  valid_until DATE NULL,
  currency ENUM('ARS','USD') NOT NULL DEFAULT 'USD',
  tax_mode ENUM('sin_iva','iva_incluido','mas_iva') NOT NULL DEFAULT 'sin_iva',
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21,
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  vat_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  total DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('borrador','enviado','visto','aprobado','rechazado','vencido') NOT NULL DEFAULT 'borrador',
  public_token VARCHAR(64) NOT NULL UNIQUE,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sales_quotes_client(client_id,issue_date),
  INDEX idx_sales_quotes_project(project_id),
  INDEX idx_sales_quotes_status(status,valid_until),
  CONSTRAINT fk_sales_quote_client FOREIGN KEY(client_id) REFERENCES clients(id),
  CONSTRAINT fk_sales_quote_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_quote_price_list FOREIGN KEY(price_list_id) REFERENCES price_lists(id),
  CONSTRAINT fk_sales_quote_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_quote_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_quote_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  sku VARCHAR(190) NULL,
  description VARCHAR(500) NOT NULL,
  quantity DECIMAL(15,3) NOT NULL DEFAULT 1,
  unit VARCHAR(60) NOT NULL DEFAULT 'Unidad',
  unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_quote_items_quote(sales_quote_id,sort_order),
  CONSTRAINT fk_sales_quote_item_quote FOREIGN KEY(sales_quote_id) REFERENCES sales_quotes(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_quote_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_quote_item_category FOREIGN KEY(category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_quote_sends (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_quote_id INT UNSIGNED NOT NULL,
  channel ENUM('email','whatsapp') NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  sent_by INT UNSIGNED NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_quote_sends_quote(sales_quote_id,sent_at),
  CONSTRAINT fk_sales_quote_send_quote FOREIGN KEY(sales_quote_id) REFERENCES sales_quotes(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_quote_send_user FOREIGN KEY(sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sales_quote_templates(proposal_type,name,intro_pdf_path,final_pdf_path,active) VALUES
('lifesmart','LifeSmart','storage/proposal-templates/lifesmart-intro.pdf','storage/proposal-templates/lifesmart-final.pdf',1),
('control4','Control4','storage/proposal-templates/control4-intro.pdf','storage/proposal-templates/control4-final.pdf',1),
('shelly','Shelly','storage/proposal-templates/shelly-intro.pdf','storage/proposal-templates/shelly-final.pdf',1),
('general','General',NULL,NULL,1);

INSERT INTO profile_module_permissions(profile_id,module_key,can_view,can_manage)
SELECT profile_id,'sales_quotes',can_view,can_manage
FROM profile_module_permissions
WHERE module_key='products'
ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_manage=VALUES(can_manage);
