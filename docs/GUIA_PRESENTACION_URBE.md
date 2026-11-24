# Guía de presentación — VERDELIA

**Proyecto académico:** tienda ficticia de plantas, macetas y accesorios. Las ventas, usuarios históricos y pagos son datos de demostración; no se cobra dinero real.

## Preparar el equipo

1. Abrir XAMPP Control Panel e iniciar **MySQL**. El lanzador inicia el Apache incluido en XAMPP y requiere PHP 8.0.28.
2. Confirmar que MySQL aparece activo en el puerto configurado (por defecto `3306`). Si XAMPP reporta conflicto o apagado inesperado, revisar sus Logs antes de iniciar otro servidor MySQL.
3. Abrir la carpeta raíz del proyecto y ejecutar `INICIAR_VERDELIA.bat`.
4. Esperar la validación de Apache y abrir `http://localhost:8088/`. El servidor solo escucha en loopback; si 8088 está ocupado, cierra el proceso que lo usa.
5. En el workspace principal, las cuentas académicas están en `docs/CREDENCIALES_DEMO.local.md`, archivo local excluido de Git y de la sincronización Desktop. El bootstrap local contiene los usuarios demo. Para una exposición, usar solo cuentas ficticias y no mostrar datos personales.

## Recorrido sugerido (5–7 minutos)

1. **Inicio y catálogo:** mostrar categorías, ficha de producto, precio y stock que provienen de MariaDB.
2. **Cuenta cliente:** iniciar sesión con el usuario demo; revisar carrito y añadir un producto disponible. Cambiar cantidad para mostrar que el servidor recalcula importes y controla inventario.
3. **Checkout simulado:** completar datos ficticios y probar rechazo o aprobación. Aprobación crea pedido, recibo e historial; descuenta stock una vez. No introducir tarjeta ni datos reales.
4. **Panel administrativo (`/admin`):** enseñar dashboard, búsquedas de productos, stock, clientes y pedidos. Un cliente no autorizado recibe denegación aunque visite la ruta directamente.
5. **Analítica (`/admin/analitica`):** seleccionar fechas y agrupación diaria/semanal/mensual. Mostrar ingresos, costo histórico, ganancia bruta estimada, variación frente al intervalo anterior, mejores productos/categorías y períodos sin ventas.
6. **Regresión por producto:** elegir un SKU y analizar sus ingresos o ganancia bruta estimada. Mostrar en la gráfica los datos históricos, la recta, la curva exponencial disponible y las tres proyecciones. Explicar que `y = a + bx` admite ganancias y pérdidas, mientras que `y = A·e^(Bx)` requiere tres o más valores estrictamente positivos; si no se cumple, la pantalla explica por qué no aplica. Comparar RMSE en la escala original de la variable elegida; las proyecciones son exploratorias.
7. **Cierre:** mostrar `docs/PRUEBAS_FINALES.md` y el respaldo `database/backups/verdelia_demo.sql`. Detener el servidor local con Ctrl+C.

## Explicación de la analítica

- Solo cuenta pedidos `PAID`/`FULFILLED` con pago aprobado. Se cuenta el importe de productos desde las líneas históricas; el envío de esta demo es cero y no se suma.
- El costo y categoría se guardan como snapshots por línea para que cambios posteriores al catálogo no modifiquen reportes pasados.
- La ganancia es **bruta estimada** (ventas de producto menos costo de mercancía). No descuenta impuestos, salarios, comisiones, envío, mermas ni otros gastos.
- R² puede no estar definido cuando la serie es constante. La regresión lineal puede representar períodos con pérdida; la exponencial no se calcula si hay períodos nulos o negativos. Una ventana sin pedidos no genera pronóstico.
- Los registros son ilustrativos; no deben presentarse como transacciones reales del negocio.

## Si se necesita restaurar la base

La restauración reemplaza información de la base importada. Primero genera y conserva un respaldo actual con `php scripts/backup.php`. Para la guía paso a paso consulta `RESTAURAR_BASE_DATOS.md`. El archivo `verdelia_demo.sql` es la demo empaquetada; los archivos fechados preservan puntos de restauración anteriores.

## Límites comprobados

El proyecto se validó en el equipo actual con PHP 8.3.33 y XAMPP MariaDB 10.4.32. La copia en Desktop está sujeta a sincronización. El arranque desde una memoria USB física y en una segunda PC no se probó, y XAMPP no forma parte del paquete portable; cada equipo necesita PHP con `pdo_mysql` y una instancia compatible de MySQL/MariaDB.
