# Pruebas de analítica

Actualización de compatibilidad (2026-11-10): se completó la integración antes pendiente con PHP 8.0.28 y MariaDB: 32 aserciones de analítica, 27 de administración HTTP y 32 de checkout HTTP aprobadas en bases temporales. Esta comprobación sustituye el bloqueo de acceso local descrito al final del reporte; la revisión visual del nuevo panel por SKU sigue pendiente. Detalle en `COMPATIBILIDAD_PHP.md`.

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

## Adecuación de regresiones por producto — 2026-11-10

- El panel ahora selecciona un SKU con historial de pagos aprobados y ajusta por período los ingresos o la ganancia bruta estimada del producto. La ganancia puede quedar debajo de cero y se modela con regresión lineal.
- El modelo exponencial conserva la condición matemática de al menos tres observaciones estrictamente positivas. Si existen períodos con cero o pérdida, muestra el motivo y no inventa una curva.
- `php -l` aprobó los siete archivos PHP modificados y `tests/analytics_models.php` aprobó 27 aserciones. Un smoke test con PDO SQLite en memoria verificó la serie seleccionada, la regresión lineal con pérdidas y ganancias y el ajuste exponencial para ingresos positivos.
- La integración MariaDB y HTTP de la nueva selección queda pendiente: XAMPP MySQL no estaba activo en el puerto 3306. La inicialización de una instancia MariaDB temporal no fue posible por permisos de Windows al crear su directorio de datos; no se inició ni modificó la base VERDELIA habitual. Los casos para ambas integraciones quedaron añadidos en `tests/analytics_integration.php` y `tests/admin_http_integration.php`.
