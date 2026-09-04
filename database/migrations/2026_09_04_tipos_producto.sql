-- Actualiza los tipos de producto a Bienes, Servicio y Combinación.

ALTER TABLE products
  MODIFY COLUMN product_type ENUM('producto','servicio','descuento','bienes','combinacion')
  NOT NULL DEFAULT 'producto';

UPDATE products SET product_type='bienes' WHERE product_type='producto';
UPDATE products SET product_type='combinacion' WHERE product_type='descuento';

ALTER TABLE products
  MODIFY COLUMN product_type ENUM('bienes','servicio','combinacion')
  NOT NULL DEFAULT 'bienes';
