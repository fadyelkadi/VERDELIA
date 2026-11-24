# Pruebas finales y estado de entrega

Actualización 2026-11-24: se recuperó el XAMPP 8.0.28-0 que ya estaba instalado. Se restauraron los binarios/extensiones PHP 8.0.28 que faltaban desde una copia local del mismo build y se reparó `mysql.db` desde el respaldo incluido, conservando una copia completa de la carpeta de datos. PHP y `pdo_mysql` cargan sin avisos; Apache pasa `httpd.exe -t`; el bootstrap conserva la semilla y la home responde HTTP 200 en `127.0.0.1:8088`. LocalWP ocupa 80/443; el lanzador de VERDELIA usa 8088. Chatbot: 170 aserciones aprobadas con PHP de XAMPP. No se encontró el instalador en Descargas, pero no hizo falta reinstalar. Estado detallado en `COMPATIBILIDAD_PHP.md`.

Actualización 2026-11-10: corregida la incompatibilidad PHP 8.0 que apareció en URBE. Con PHP 8.0.28 pasaron las integraciones de analítica (32), administración HTTP (27) y checkout HTTP (32) en bases temporales; queda resuelta la integración pendiente indicada más abajo. Ver `docs/COMPATIBILIDAD_PHP.md`. La comprobación física en URBE del paquete corregido sigue pendiente.

Actualizado: 2026-11-04. Alcance: tienda local VERDELIA, XAMPP MariaDB, migración analítica, respaldo final y preparación de documentación.

## Validaciones de cierre ejecutadas

- 53 archivos PHP pasaron `php -l` bajo PHP CLI 8.3.33, incluidos los utilitarios de selección y verificación de puerto.
- `node --check public/assets/js/site.js` terminó correctamente.
- Tras el último ajuste del renderizado Canvas, se volvió a ejecutar `node --check` y pasó; la consola del navegador quedó sin errores al recargar analítica.
- Modelos matemáticos: 22 aserciones aprobadas.
- Integración analítica en MariaDB temporal: 19 aserciones aprobadas.
- Checkout en MariaDB temporal: 24 aserciones aprobadas, incluyendo escritura de categoría snapshot para una venta nueva.
- Bootstrap limpio repetido: 4 aserciones aprobadas.
- Administración HTTP aislada: 26 aserciones aprobadas; incluye autorización de `/admin/analitica`, filtros y gráficas.
- Administración funcional aislada: 41 aserciones. Checkout HTTP: 32. Autenticación HTTP en `127.0.0.1:8088`: 32; usuarios temporales eliminados.
- Sesiones/CSRF: 20 aserciones. Autenticación transaccional: 18. Carrito transaccional: 13. Catálogo: comprobaciones de consultas, inventario e imágenes locales aprobadas.
- Restauración aislada del respaldo final: aprobada con 12 tablas, 12 productos, 12 imágenes, 96 pedidos, 96 líneas y 96 snapshots de categoría.
- Las bases temporales de estas pruebas se eliminaron al finalizar. Las pruebas analíticas/restauración no mutaron `verdelia_demo`.
- `scripts/bootstrap.php` se ejecutó contra la base VERDELIA configurada después de conservar respaldo previo. La salida confirmó que la semilla existente se conservó.
- Referencia local de rendimiento: consulta diaria sobre 91 pedidos pagados/179 días, 7 ejecuciones; mediana 15,84 ms, máximo 20,64 ms. Es evidencia de esta PC solamente, no de hardware antiguo.
- QA responsive visual con emulación de viewport: inicio, tienda, carrito, login y detalle de producto a 360 px; analítica administrativa a 360, 768, 1024 y 1440 px; productos, inventario, pedidos y clientes a 360 px. Las gráficas se ajustan sin solapamiento de etiquetas; las tablas con muchas columnas conservan desplazamiento horizontal dentro de su panel. Checkout y recibo se cubrieron mediante integración HTTP, pero no se recorrieron visualmente en navegador a los cuatro anchos.

## Cambios entregados

- Analítica administrativa diaria/semanal/mensual con comparación frente al período anterior, productos/categorías top, métricas de ingreso/costo/ganancia bruta y Canvas local.
- Regresión lineal y ajuste exponencial con límites, error, parámetros y proyecciones descriptivas.
- Migración `analytics_v1`, snapshots históricos de categoría y nuevo índice de lectura temporal.
- Guía de presentación URBE, matriz de requisitos, reporte de analítica, guía de restauración y documentos de progreso/decisiones actualizados.
- Exportación final fechada en `database/backups/verdelia_20261104_030555.sql`; demo SQL refrescada y copia anterior archivada.
- `scripts/check-port.php` detectó el sitio activo en 8088 como ocupado y `scripts/find-port.php` seleccionó 8089. El lanzador real inició bootstrap/servidor en 8089 y devolvió HTTP 200; la comprobación se hizo con `VERDELIA_OPEN_BROWSER=0` y el proceso temporal se detuvo al terminar.

## Estado de XAMPP y portátil

**Evidencia histórica del 4 de noviembre de 2026, no comprobación actual:** PDO respondió a MariaDB `10.4.32-MariaDB` en el puerto `3306`, y el bootstrap conservó la base. La nota del panel XAMPP sobre puertos alternos describe aquel entorno y ya no representa el lanzador vigente, que sirve el sitio con Apache de XAMPP.

El `mysql_error.log` conserva un intento anterior fallido con `ibdata1 must be writable`; esa línea no es el estado presente: XAMPP/MariaDB respondió a la consulta de versión y al bootstrap. No cambié `my.ini`, ACL ni archivos de datos durante esta validación. Los resultados no demuestran que el hardware de una memoria USB funcione en otra PC.

La copia Desktop existente se sincronizó con los archivos de proyecto: 111 archivos coinciden por SHA-256, con cero faltantes o diferencias. Se preservan `.git`, configuración/credenciales locales y `storage`. El arranque físico desde USB/segunda PC sigue pendiente: esta sesión solo expone la unidad C:. Tampoco hay una segunda PC de bajo rendimiento para medir allí; la medición local consignada arriba no sustituye ese benchmark. XAMPP no se incluye ni se instala desde este repositorio. No se crearon commits.

## Advertencia de utilidad

Las ventas y pagos demo no son reales. La ganancia es bruta estimada con costos históricos unitarios; no incluye impuestos, comisiones, logística, mermas, salarios ni gastos administrativos. Las regresiones son descriptivas y no garantizan resultados futuros.

## Ajuste posterior de regresiones — 2026-11-10

El panel de analítica ahora permite elegir un producto y ajustar sus ingresos o ganancia bruta por período. La regresión lineal representa pérdidas y ganancias; el ajuste exponencial explica cuándo no aplica a datos cero o negativos. La validación numérica de 27 aserciones pasó. La integración MariaDB/HTTP de esta ampliación queda pendiente hasta que MySQL de XAMPP esté activo; el estado detallado figura en `docs/PRUEBAS_ANALITICA.md`.
