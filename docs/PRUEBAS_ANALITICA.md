# Pruebas de analítica

Fecha de ejecución: 2026-11-04. Ambiente: Windows, PHP CLI 8.3.33 con `pdo_mysql`/`curl`, XAMPP MariaDB 10.4.32 local. Las pruebas de integración usaron bases con nombres temporales y las eliminaron al terminar.

## Resultados

| Prueba | Resultado |
|---|---|
| `tests/analytics_models.php` | Aprobada: 22 aserciones de regresión lineal/exponencial, R²/RMSE y entradas límite. |
| `tests/analytics_integration.php database/backups/verdelia_20261104_024900.sql` | Aprobada: 19 aserciones. Migración aplicada dos veces, métricas comparadas con SQL independiente, períodos vacíos, snapshots y filtros de fecha. |
| `tests/checkout_integration.php database/backups/verdelia_20261104_030555.sql` | Aprobada: 24 aserciones; también confirma que el checkout guarda la categoría como snapshot en cada línea nueva. |
| `tests/admin_http_integration.php database/backups/verdelia_20261104_024900.sql` | Aprobada: 26 aserciones. Visitante/cliente sin acceso y administrador con dashboard, analítica y gráficos. |
| `tests/admin_fresh_bootstrap.php` | Aprobada: bootstrap inicial/repetido, 12 tablas, 12 productos, 12 imágenes, 2 cuentas y 96 pedidos; migraciones versionadas una sola vez. |
| `tests/backup_restore.php database/backups/verdelia_20261104_030555.sql` | Aprobada: 12 tablas, 12 productos, 12 imágenes, 96 pedidos, 96 líneas y 96 snapshots de categoría; base temporal eliminada. |
| Sintaxis PHP/JS | Aprobada: 53 archivos PHP con `php -l`; `node --check public/assets/js/site.js`. |
| Referencia de rendimiento local | 91 pedidos pagados entre 2026-05-03 y 2026-10-28, 179 buckets diarios. Siete ejecuciones de `AnalyticsRepository::analyze()` dieron mediana 15,84 ms y máximo 20,64 ms en este equipo. No mide una PC antigua ni incluye render HTTP/navegador. |

## Integración y semántica

- Se restauró el respaldo preanalítica `database/backups/verdelia_20261104_024900.sql` en una BD temporal, se ejecutó la migración `analytics_v1` dos veces y se comprobó que fuera idempotente.
- Se compararon los agregados de ventas con una consulta SQL independiente: pedidos confirmados, ingreso por línea, costo histórico y unidades. Las series temporales y las sumas por categoría coinciden con el total del período.
- El test renombró una categoría en la base temporal y verificó que el reporte mantuviera el nombre snapshot de la venta.
- Una ventana vacía devuelve métricas cero, buckets cero y no crea un pronóstico ficticio. Un intervalo inválido, invertido o mayor de 730 días se rechaza.
- Las pruebas HTTP validaron que solo `ADMIN` pueda ver analítica y que fechas malformadas caigan a un intervalo seguro mostrando el error.

## Respaldo y datos reales de demostración

Antes de aplicar `analytics_v1` sobre la base configurada se conservó `database/backups/verdelia_20261104_024900.sql`. Después se creó `database/backups/verdelia_20261104_030555.sql`, que se restauró correctamente en una base aislada. La migración no reinicializó ni reemplazó los 96 pedidos históricos.

La copia de demo `database/backups/verdelia_demo.sql` ahora corresponde al respaldo final; su versión inicial se archivó como `database/backups/verdelia_demo_pre_analytics_20261104.sql`.

## Limitaciones

Las pruebas comprueban las consultas y el HTML de la ruta, pero no predicen ventas reales. La serie demo es ficticia. La revisión manual del dashboard administrativo en navegador depende de una sesión admin y no forma parte de la comprobación HTTP automatizada; los modelos se verifican numéricamente y por integración. La prueba física de USB/segunda PC sigue pendiente.
