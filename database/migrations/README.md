# Migraciones
Cada ampliación del ERP debe agregar un archivo SQL incremental, numerado y no destructivo.
### 2026_09_02_arquitectos_constructoras.sql

Agrega el registro de arquitectos, estudios, constructoras y desarrolladoras, y permite asociar opcionalmente uno de ellos a cada proyecto.

### 2026_09_02_presupuestos_por_rubro.sql

Permite clasificar cada presupuesto como Domótica, Redes, Cámaras, Alarma o Audio. Los presupuestos anteriores quedan identificados como “General / anterior”.
