# Punto ERP

ERP modular. La primera versión habilita **Cuentas por cobrar**: clientes, proyectos, presupuestos versionados, materiales, mano de obra, adelanto de ingeniería no reintegrable, pagos, imputaciones y recibos por email/WhatsApp.

## Instalación nueva en Ferozo
1. Crear la carpeta `punto-erp` dentro de `public_html`.
2. Crear una base MySQL y asignarle un usuario.
3. Importar `database/schema.sql` desde phpMyAdmin.
4. Copiar `config.example.php` como `config.php` y completar todos los valores.
5. Subir el contenido completo y verificar permiso 755 en `storage/uploads`.
6. Abrir `https://puntodomotica.com/punto-erp/` y crear el primer administrador.

## Arquitectura
- `index.php`: entrada única y registro de módulos.
- `app/Core`: servicios compartidos.
- `app/Modules`: módulos independientes.
- `database`: esquema y futuras migraciones.
- `storage`: adjuntos y registros privados.
- `config.php`: configuración local no incluida en el ZIP.

`config.php` y los archivos de `storage/uploads` contienen información privada y no deben subirse al repositorio. El despliegue automático los conserva en el hosting.

Los módulos futuros ya están registrados, pero permanecen deshabilitados hasta ser desarrollados.

## Actualización de una instalación existente

1. Hacer una copia de seguridad de la base de datos.
2. Importar una sola vez `database/migrations/2026_08_28_clientes_datos_fiscales.sql`.
3. Importar una sola vez `database/migrations/2026_08_28_monedas_y_recibos.sql`.
4. Importar una sola vez `database/migrations/2026_09_02_arquitectos_constructoras.sql`.
5. Importar una sola vez `database/migrations/2026_09_02_presupuestos_por_rubro.sql`.
6. Importar una sola vez `database/migrations/2026_09_02_moneda_por_presupuesto.sql`.
7. Importar una sola vez `database/migrations/2026_09_02_iva_separado_presupuesto.sql`.
8. Importar una sola vez `database/migrations/2026_09_03_perfiles_usuarios.sql`.
9. Reemplazar los archivos de la aplicación conservando `config.php` y `storage/uploads`.

Cada proyecto trabaja íntegramente en ARS o USD; el sistema no convierte ni mezcla monedas. Cada pago genera automáticamente un recibo y se imputa a los cargos elegidos. Materiales y Mano de obra se mantienen separados. Cuando se carga el presupuesto final, lo abonado previamente como Ingeniería se transfiere automáticamente a Mano de obra.
