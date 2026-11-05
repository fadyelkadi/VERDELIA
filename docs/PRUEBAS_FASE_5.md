# Validación de la Fase 5 — VERDELIA

Fecha: 2026-11-03. Entorno: Windows, PHP 8.2.12 y XAMPP MariaDB 10.4.32 local.

## Alcance verificado

Checkout autenticado, persistencia de pedidos/líneas, pago exclusivamente simulado, control transaccional de inventario, reintentos, idempotencia, recibo imprimible, historial y autorización de lectura.

## Resultado

| Comprobación | Resultado |
|---|---|
| Pruebas de servicio de checkout en BD temporal aislada | 23 aserciones aprobadas |
| Pruebas HTTP de checkout con servidor PHP y BD aislada | 32 aserciones aprobadas |
| Regresión de sesión/CSRF/límite de acceso | 20 aserciones aprobadas |
| Regresión de autenticación integrada | 18 aserciones aprobadas |
| Regresión del carrito en MariaDB | 13 aserciones aprobadas |
| Regresión HTTP general | 32 aserciones aprobadas |
| Sintaxis PHP (`php -l`) | 43 archivos aprobados |
| Sintaxis JavaScript (`node --check`) | Aprobada |
| Bootstrap repetido contra XAMPP | Aprobado; migraciones/semillas idempotentes |
| Restauración del respaldo final en BD temporal | Aprobada: 11 tablas, 12 productos, 12 imágenes, 96 pedidos y 96 líneas |

Las pruebas de servicio y HTTP usaron bases temporales aisladas y se limpiaron al terminar. La regresión HTTP general se ejecutó en el puerto local esperado y su proceso temporal fue detenido.

## Casos de checkout cubiertos

- Rechazo: conserva inventario y carrito, registra el intento y permite reintentar.
- Aprobación: marca el pedido pagado, descuenta stock una sola vez, registra el movimiento y limpia el carrito dentro de la misma transacción.
- Cancelación: deja stock y carrito intactos.
- Reenvíos con la misma clave idempotente: no duplican pedido, pago ni movimiento.
- Precio/totales manipulados o precio/stock cambiado: se rechazan y exigen revisar el carrito.
- Carrito vacío, fallo dentro de la transacción y rollback de inventario.
- Recibo e historial del cliente, rechazo de acceso a pedidos ajenos y lectura por ADMIN.
- Navegación HTTP de checkout, acceso/retorno tras autenticación, simulaciones y páginas de pedido/recibo.

## Esquema y respaldos

La migración aditiva versionada `checkout_v1` se aplicó mediante el bootstrap. Durante su verificación se detectó que el índice `UNIQUE` previo de `payments.order_id` servía también a la clave foránea; la migración quedó ordenada para crear primero un índice normal de soporte y retirar después la unicidad. El bootstrap posterior y las pruebas sobre MariaDB aprobaron.

- Respaldo previo a migración: `database/backups/verdelia_20261103_182346.sql`.
- Respaldo final: `database/backups/verdelia_20261103_183812.sql` (106,301 bytes), restaurado satisfactoriamente en una base temporal.
- Estado de la BD activa después de las pruebas: 2 usuarios, 12 productos, 4 categorías, 96 pedidos y 96 pagos. Las cuentas demo y pedidos históricos se conservaron; los casos nuevos usaron BD aisladas.
- Versiones aplicadas: `demo_seed_v1`, `catalog_images_v1`, `checkout_v1`.

## Límites de esta validación

Se verificó XAMPP en este equipo, incluido MariaDB/PDO, bootstrap, SQL real y restauración. No se pudo probar arranque desde el pendrive físico ni en una segunda PC. Los pagos son demostrativos y no realizan cobros ni interactúan con pasarelas. La fase siguiente no forma parte de esta entrega.
