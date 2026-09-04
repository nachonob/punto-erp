# Migraciones
Cada ampliación del ERP debe agregar un archivo SQL incremental, numerado y no destructivo.
### 2026_09_02_arquitectos_constructoras.sql

Agrega el registro de arquitectos, estudios, constructoras y desarrolladoras, y permite asociar opcionalmente uno de ellos a cada proyecto.

### 2026_09_02_presupuestos_por_rubro.sql

Permite clasificar cada presupuesto como Domótica, Redes, Cámaras, Alarma o Audio. Los presupuestos anteriores quedan identificados como “General / anterior”.

### 2026_09_02_moneda_por_presupuesto.sql

Mueve la selección operativa de moneda a cada presupuesto y conserva la moneda también en sus cargos, pagos y recibos. Permite mezclar presupuestos ARS y USD dentro de un mismo proyecto sin sumar monedas diferentes.

### 2026_09_02_iva_separado_presupuesto.sql

Permite definir por separado el tratamiento y la alícuota de IVA de Materiales y Mano de obra. Los presupuestos existentes conservan en ambos conceptos la configuración de IVA que ya tenían.

### 2026_09_03_perfiles_usuarios.sql

Agrega perfiles de usuario por área y permisos independientes para ver o gestionar cada módulo. Los administradores mantienen acceso total.

### 2026_09_03_productos_precios_stock.sql

Crea productos, categorías, múltiples listas de precios, imágenes y movimientos de stock. También importa los 323 registros del Excel inicial, incluidas sus 43 imágenes disponibles.

### 2026_09_03_moneda_por_precio_producto.sql

Permite elegir ARS o USD en el precio de cada producto, aunque pertenezcan a una misma lista.
