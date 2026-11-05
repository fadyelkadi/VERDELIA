# Pruebas de Fase 2 — MySQL, catálogo e inventario

Fecha: 2026-11-03. Ejecutadas con PHP 8.2.12, extensión `pdo_mysql` y XAMPP MariaDB 10.4.32 local. Las credenciales no se imprimieron ni se guardaron en el repositorio.

## Base de datos y respaldo

| Verificación | Resultado | Evidencia |
|---|---|---|
| Estado inicial de `verdelia_demo` | Aprobado. | La conexión PDO local funcionó y confirmó que la base aún no existía; se creó únicamente `verdelia_demo`. |
| Esquema | Aprobado. | 11 tablas originales, con claves/relaciones conservadas. No se necesitó alterar `schema.sql` ni crear migración estructural. |
| Datos después del bootstrap | Aprobado. | 4 categorías, 12 productos, 12 imágenes, 2 cuentas demo, 96 pedidos, 96 líneas, 96 pagos; suma de stock inicial: 277. |
| Migración idempotente de imágenes | Aprobado. | `catalog_images_v1` se registra en `schema_versions` y relaciona cada SKU con un asset de `product_images`. |
| Repetición del bootstrap | Aprobado. | Dos ejecuciones posteriores dejaron sin cambio los conteos de categorías/productos/imágenes/usuarios/pedidos/líneas/pagos ni stock. |
| Respaldo fechado | Aprobado. | [verdelia_20261103_165046.sql](../database/backups/verdelia_20261103_165046.sql), generado por `scripts/backup.php`. `verdelia_demo.sql` inicial se conservó. |
| Restauración aislada | Aprobado. | El respaldo restauró 11 tablas, 12 productos, 12 imágenes, 96 pedidos y 96 líneas en `verdelia_restore_check_…`; se eliminó únicamente esa base temporal. La base original no se borró. |

## Catálogo y HTTP

| Verificación | Resultado | Evidencia |
|---|---|---|
| PDO y manejo de errores | Aprobado. | Configuración centralizada, PDO nativo, `utf8mb4`, consultas preparadas. Al forzar un puerto MySQL inválido, Inicio devolvió HTTP 503 con mensaje genérico y sin SQLSTATE ni rutas locales. |
| Consultas de datos | Aprobado. | Inicio, categorías y ficha de Monstera mostraron datos de MySQL; precio de `$42.00`, stock 14. SKU inexistente devolvió 404. |
| Filtros | Aprobado. | SQL filtró por categoría, precio máximo, disponibilidad, cuidado, luz y palabra clave. La consulta de texto de inyección no cambió la estructura ni devolvió productos. |
| Orden y paginación | Aprobado. | Precio ascendente/descendente validado. 12 productos se sirven en páginas de 8 y 4 sin duplicación. `recent` está limitado por allowlist. |
| Inventario | Aprobado. | `stock=0` se muestra como agotado y cantidades de compra quedan limitadas a existencias. El indicador de stock bajo usa el umbral de 15 (2 productos semilla). Consultar/agregar a la vista previa no modifica el stock. |
| API de carrito de muestra | Aprobado. | POST con PLA-006 a cantidad 999 devolvió cantidad máxima 14 y precio actual 4200 centavos; el SKU inválido se descartó. No reserva ni descuenta inventario. |
| Rutas HTTP | Aprobado. | 20 páginas devolvieron 200 sin avisos PHP/SQL; producto ausente 404 y `GET /api/cart-preview` 405. El asset local se sirvió con 200. |
| Consola de navegador | Aprobado. | Sin errores o advertencias en la revisión de la sesión. |
| Browser responsive | Aprobado. | Tienda revisada a 360, 768 y 1440 px. `documentElement.scrollWidth` fue 345, 753 y 1425 px respectivamente, sin elementos fuera del viewport. Se corrigió una referencia a `size` inexistente que imprimía warnings en tarjetas de macetas y provocaba overflow a 360 px. |
| Flujo de vista previa en navegador | Aprobado. | Añadir Monstera mostró aviso de que no se reserva stock; carrito consultó precio/inventario y mostró `$42.00`, cantidad 1 y subtotal `$42.00`. |

## Persistencia y limitaciones del entorno

- Se solicitó apagado limpio de la instancia XAMPP de prueba para comparar los conteos tras reiniciarla. El siguiente proceso de MariaDB terminó después de abrir el socket y antes de aceptar el handshake PDO; por tanto, **persistencia tras reinicio no se declara aprobada**. El último conteo verificado antes de apagar fue 4 categorías, 12 productos, 12 imágenes, 96 pedidos y stock total 277. El respaldo SQL sí se pudo restaurar aisladamente.
- La instancia XAMPP probada quedó detenida. Otros dos procesos `mysqld` de Lightning en `%APPDATA%` no escuchaban en 3306 y no se iniciaron/detuvieron ni modificaron.
- El lanzador `INICIAR_VERDELIA.bat` no se ejecutó completo en esta ronda. Sus componentes PHP, bootstrap, test de puerto, y servidor/router PHP local se probaron por separado; falta arrancar MySQL estable y hacer el recorrido del BAT desde una sesión Windows completa.
- No se probaron Apache/`htdocs`, segunda computadora ni USB con letra distinta. Pagos, autenticación, operaciones administrativas y checkout real siguen fuera del alcance de Fase 2.

## Reproducir

```powershell
C:\xampp\php\php.exe scripts\bootstrap.php
C:\xampp\php\php.exe tests\catalog_integration.php
C:\xampp\php\php.exe tests\backup_restore.php database\backups\verdelia_20261103_165046.sql
C:\xampp\php\php.exe scripts\backup.php
```

Inicia MySQL desde XAMPP antes de los comandos y usa únicamente una base de datos de prueba al repetir la validación de restauración.
