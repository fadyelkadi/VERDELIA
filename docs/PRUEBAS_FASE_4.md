# Pruebas de Fase 4 — Carrito persistente

Fecha: 2026-11-03. Entorno real: Windows, PHP 8.2.12, XAMPP MariaDB 10.4.32, PDO MySQL y base `verdelia_demo`.

Registro histórico del cierre del carrito; checkout y administración se completaron después. Los resultados y el estado vigente se encuentran en [`PRUEBAS_FINALES.md`](PRUEBAS_FINALES.md).

## Hallazgos de inspección

- Antes de esta fase, el carrito era una vista previa en `sessionStorage`; `POST /api/cart-preview` solo consultaba catálogo y stock.
- La BD real ya tenía las 11 tablas del proyecto, incluidas `carts` y `cart_items`. No hizo falta migración ni alteración de esquema.
- Estado inicial comprobado: 2 usuarios demo, 12 productos, 4 categorías, 0 carritos, 0 líneas de carrito y 96 pedidos históricos.
- El bootstrap ya tenía las semillas aplicadas. Su nueva ejecución no duplicó datos ni modificó pedidos o stock.

## Implementación cubierta

- `GET/POST /api/cart`: consulta actual y operaciones `add`, `set`, `remove`, `clear`.
- Carrito de visitante en la sesión PHP; carrito autenticado en MariaDB, con una fila por `user_id` y cantidades en `cart_items`.
- Al iniciar sesión o registrarse, las líneas de visitante se suman a las de la cuenta. La fusión limita cada SKU a existencias actuales y 999 unidades.
- Los importes se leen en PHP desde MySQL. Los datos de precio que pudiese enviar JavaScript se ignoran. El servidor calcula subtotal/total y devuelve stock actual.
- CSRF requerido para todas las mutaciones. Cantidades inferiores a 1, mayores al stock y productos inexistentes se rechazan.
- Agregar/modificar/eliminar/vaciar no reserva ni descuenta inventario. Checkout, pedidos y pagos siguen pendientes de la siguiente fase.

## Matriz de resultados

| Prueba real | Resultado |
|---|---|
| `tests/cart_integration.php` | Aprobada: 13 aserciones contra MariaDB; transacción revertida. Incluye cantidades, subtotales, stock, persistencia en tablas, aislamiento y fusión. |
| `tests/http_integration.php` | Aprobada: 32 aserciones HTTP. Incluye API, navegación/refresh, límites, SKU inválido, CSRF, precio alterado, registro/login/logout, cambio de contraseña, rol cliente/admin, fusión y aislamiento. Usuarios temporales eliminados. |
| `tests/auth_integration.php` | Aprobada: 18 aserciones; sin usuarios residuales. |
| `tests/auth_session.php` | Aprobada: 20 aserciones de sesión y CSRF. |
| `scripts/bootstrap.php` | Aprobado: informó base preparada; conteos posteriores se mantuvieron. |
| UI en navegador | Aprobada: se añadió Aloe Vera desde su ficha; carrito mostró cantidad 1, subtotal/total `$14.90`; se vació y volvió al estado vacío. |
| Catálogo | Aprobadas por HTTP: inicio, tienda con filtros/orden, categoría, ficha, login y registro respondieron 200 en el puerto 8088. |
| Stock y pedidos | Sin cambios: stock coincide con su snapshot y siguen exactamente 96 pedidos. |

Después de limpiar pruebas: 2 usuarios demo, 0 carritos, 0 líneas y 96 pedidos. La tabla `users` no recibió cambios permanentes.

## XAMPP y lanzador

- XAMPP MariaDB se inició desde el panel y se comprobó en el puerto 3306. `mysqladmin ping`, SQL y PDO respondieron correctamente.
- Se comprobó un apagado ordenado y nuevo inicio desde el botón Start de XAMPP; los datos y el catálogo siguieron disponibles.
- `INICIAR_VERDELIA.bat` detectó PHP 8.2.12 y `pdo_mysql`, conectó con MySQL, ejecutó el bootstrap y sirvió la tienda en `http://127.0.0.1:8088/`.
- El puerto predeterminado 8088 estaba libre; el BAT lo seleccionó sin pedir alternativa. Inicio, tienda, categoría, producto, carrito, login y registro respondieron 200.
- El BAT se ejecutó desde la carpeta de trabajo en OneDrive. No se probó el arranque desde un pendrive físico ni en una segunda PC.

## Respaldo y restauración

Respaldo final fechado, creado sin sobrescribir copias previas:

`database/backups/verdelia_20261103_181458.sql` (86,540 bytes).

`tests/backup_restore.php` lo restauró en la base temporal aislada `verdelia_restore_check_20261103_171826_7b09fb`, comprobó 11 tablas, 12 productos, 12 imágenes, 96 pedidos y 96 líneas, y limpió solo esa base temporal.

## Pendiente

- Probar el mismo lanzador desde un USB físico y una segunda PC.
- Checkout, pedidos, pagos y descuento transaccional de inventario no se implementaron; pertenecen a la fase siguiente.

La solicitud recibida denomina este trabajo Fase 4; `VERDELIA_PLAN.md` v2.0 enumera el carrito como Fase 5. Se siguió la numeración de la solicitud y no se inició checkout.
