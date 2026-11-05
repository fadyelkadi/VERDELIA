# Restaurar base de datos

El respaldo final de demostración se encuentra en `database/backups/verdelia_demo.sql`; exportaciones de una instalación activa se crean con `php scripts/backup.php`, llevan fecha/hora y se guardan en `database/backups/` sin sobrescribir archivos anteriores. Los respaldos fechados históricos se conservan.

## Importar desde phpMyAdmin

1. Inicia MySQL y abre phpMyAdmin desde XAMPP.
2. Para una instalación limpia, crea/selecciona la base según el encabezado del SQL e importa `database/backups/verdelia_demo.sql`.
3. Comprueba que aparece `verdelia_demo` con 12 tablas; revisa conteos de `categories`, `products`, `users`, `orders` y `order_items`.
4. Si la base ya existe con datos que debas conservar, no importes encima sin copia previa. El bootstrap normal no elimina ni reinicia datos.

El respaldo restaurado incluye snapshots históricos de precio, costo, nombre y categoría de cada línea, junto con el estado `analytics_v1`. No importes un respaldo encima de tablas que contienen datos que debas conservar. Importar un SQL es una restauración, no una actualización selectiva. Genera una exportación nueva antes de operaciones de exposición y conserva los respaldos anteriores.
