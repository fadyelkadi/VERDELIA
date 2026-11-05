# Fase 6 — Panel administrativo: implementación y validación

Fecha: 2026-11-03. Entorno: Windows, PHP CLI 8.3.33 y XAMPP MariaDB 10.4.32. Workspace principal: `C:\Users\fadye\OneDrive\Documents\ChatGPT\VERDELIA`; la copia `C:\Users\fadye\OneDrive\Desktop\VERDELIA` fue identificada, pero no modificada. El plan maestro v2.0 llama esta fase **Fase 7**; esta entrega conserva la numeración de la solicitud pegada (**Fase 6**).

Este reporte refleja el cierre administrativo del 2026-11-03. La analítica se implementó posteriormente; las cifras, pruebas y pendientes vigentes están en [`PRUEBAS_ANALITICA.md`](PRUEBAS_ANALITICA.md) y [`PRUEBAS_FINALES.md`](PRUEBAS_FINALES.md).

## Arquitectura y permisos

`public/index.php` enruta las páginas y POST administrativos; `app/AdminController.php` valida método, CSRF y acción; `app/AdminRepository.php` concentra consultas PDO, validaciones, transacciones, paginación y reglas del negocio. Las vistas usan el shell visual existente, PHP/HTML/CSS/JS vanilla y datos escapados.

Cada ruta `/admin*` pasa por `verdelia_require_admin()` en el servidor. Las mutaciones son POST con token CSRF. Invitados son redirigidos al login; clientes reciben 403 tanto en páginas como en peticiones de escritura. No se exponen contraseñas ni hashes. Los listados no cargan tablas completas.

## Rutas disponibles

| Ruta | Función |
|---|---|
| `/admin` | KPIs MySQL y cinco pedidos recientes |
| `/admin/productos` | Búsqueda, filtro, listado paginado, edición y baja lógica |
| `/admin/productos/nuevo` | Alta con descripción, cuidados, categoría, precio/costo, imagen local y existencia inicial |
| `/admin/productos/{id}/editar` | Edición del catálogo; enlaza inventario separado |
| `POST /admin/productos/guardar` | Crear/editar; el stock existente no se sobrescribe aquí |
| `POST /admin/productos/{id}/estado` | Activar/desactivar sin borrar historial |
| `/admin/categorias` | Listado, alta y edición |
| `POST /admin/categorias/guardar` | Guardar categoría; nombre y slug únicos |
| `POST /admin/categorias/{id}/estado` | Activar/desactivar; bloquea baja si hay productos activos |
| `/admin/inventario` | Buscar, consultar existencias y movimientos |
| `POST /admin/inventario/ajustar` | Ajuste de stock condicionado al valor esperado |
| `/admin/pedidos` | Búsqueda, filtro por estado/cliente, pagos y referencias simuladas |
| `/admin/pedidos/{número}` | Detalle, recibo y transiciones operativas |
| `/admin/pedidos/{número}/recibo` | Recibo imprimible, incluidos los pedidos `DEMO-` históricos |
| `POST /admin/pedidos/{número}/estado` | Cambio de estado con motivo y auditoría |
| `/admin/clientes` y `/admin/usuarios` | Búsqueda paginada de clientes y acceso a pedidos asociados |

## Reglas aplicadas

- **Productos:** SKU y slug únicos, categorías existentes, importe no negativo expresado en centavos, costo y precio dentro del rango SQL, stock inicial entero no negativo. Activar un producto requiere categoría activa. Desactivar es lógico; no se elimina `products` ni `product_images`.
- **Imágenes:** selector limitado a archivos locales existentes y comprobados bajo `public/assets/images/products/`. No se implementó subida de archivos, porque el catálogo local cubre el alcance y evita introducir una superficie de escritura/ejecución pública. Los recursos actuales son ilustrativos; sus créditos siguen en `docs/IMAGENES_Y_CREDITOS.md`.
- **Categorías:** unicidad de nombre (collation MySQL) y slug; no existe borrado físico. Una categoría con productos activos no se puede desactivar.
- **Inventario:** el ajuste usa delta, `expected_stock`, bloqueo `FOR UPDATE`, actualización condicional y transacción con `stock_movements`. Existencia obsoleta o resultado negativo se rechazan. El catálogo, el carrito y checkout vuelven a consultar los valores actuales.
- **Pedidos/pagos:** el pago permanece inmutable. Solo se permite `PAID → FULFILLED` si el último intento está `APPROVED`, o `PENDING_PAYMENT → CANCELLED` si no existe pago aprobado. No se cambia el total ni se marca un rechazo como cobro. La cancelación de un pedido pendiente no repone stock porque checkout nunca lo descontó. Cada transición anota administrador, estado anterior/nuevo, razón y fecha.
- **Historial:** los importes, nombres, SKU y costos de `order_items` son snapshots; editar precio, nombre, categoría o imagen del producto no modifica recibos existentes.
- **Clientes:** la consulta solo selecciona ID, nombre, email, fecha y conteo de pedidos. El esquema no tiene estado de cuenta; se muestra la cuenta existente como activa y no se ofrecen operaciones sobre credenciales.
- **Dashboard:** ventas = total de pedidos `PAID`/`FULFILLED` con al menos un pago `APPROVED`, calculado con `EXISTS` para no multiplicar importes en pedidos con reintentos. Cancelaciones y rechazos quedan fuera. Los KPIs son conteos/consultas reales; no se implementaron regresiones ni gráficas avanzadas.

## Esquema y respaldos

La migración aditiva e idempotente `admin_v1` agrega el índice único de nombre de categoría, índices para consultas administrativas y `admin_order_status_history`. El bootstrap continúa comprobando migraciones posteriores aunque `checkout_v1` ya esté aplicada. `database/schema.sql` incluye el esquema para bases nuevas; `database/migrations/20261103_02_admin.sql` documenta la migración de bases existentes.

- Copia anterior a `admin_v1`: `database/backups/verdelia_20261103_180211.sql` (106,301 bytes).
- Copia final posterior: `database/backups/verdelia_20261103_180511.sql` (107,665 bytes).
- Restauración del respaldo final: aprobada en una BD temporal, 12 tablas, 12 productos, 12 imágenes, 96 pedidos y 96 líneas; el restaurador verificó también `admin_v1` y la tabla de auditoría.
- Después de la migración/bootstrap: 4 categorías, 12 productos, 2 usuarios demo, 96 pedidos, 96 líneas y 96 pagos. Las pruebas de escritura se ejecutaron en bases aisladas; regresiones directas usaron rollback o solo lectura. No quedaron usuarios, carritos o bases temporales de prueba.
- El respaldo anterior a Fase 6 y respaldos previos de las fases anteriores se conservaron sin sobrescritura.

## Pruebas ejecutadas

| Prueba | Resultado |
|---|---|
| `tests/admin_integration.php` | 41 aserciones: CRUD, categorías, valores inválidos, imágenes locales, inventario concurrente, snapshots, integración catálogo/carrito/checkout, transición y auditoría |
| `tests/admin_http_integration.php` | 22 aserciones: invitado/cliente/admin, POST directo, CSRF, CRUD HTTP, búsqueda, recibo histórico, estado y persistencia tras reiniciar el servidor PHP |
| `tests/catalog_integration.php` | 31 aserciones en XAMPP; esquema de 12 tablas, catálogo, filtros, 12 imágenes locales y pedidos preservados |
| `tests/auth_session.php` | 20 aserciones de sesión, CSRF y límite de intentos |
| `tests/auth_integration.php` | 18 aserciones; transacción revertida y sin usuarios de prueba residuales |
| `tests/cart_integration.php` | 13 aserciones; transacción revertida y carrito limpio |
| `tests/checkout_integration.php` | 23 aserciones en BD aislada |
| `tests/checkout_http_integration.php` | 32 aserciones en BD aislada |
| `tests/admin_fresh_bootstrap.php` | 3 aserciones: primera instalación limpia y segunda ejecución sin duplicación |
| `php -l` | 48 archivos PHP sin errores |
| `node --check public/assets/js/site.js` | Aprobado |
| Bootstrap XAMPP | Dos ejecuciones, sin duplicación de semillas ni cambios a pedidos demo |
| Restauración final | Aprobada; solo se eliminó la BD temporal de restauración |

Total de los grupos de aserciones anotados: **203**. Reejecución de Fase 6: las pruebas de administración (41), HTTP (22), bootstrap limpio (3), catálogo (31), sesión/CSRF (20), autenticación (18), carrito (13) y checkout (23 + 32) aprobaron. Los tests con base temporal la eliminaron al finalizar; regresiones de autenticación/carrito revirtieron sus transacciones. El lint completo aprobó los 48 archivos PHP y `node --check` terminó con código 0. El test HTTP reinició el servidor PHP efímero y verificó que el nuevo producto continuaba visible. No se reinició MariaDB durante esta fase.

El aislamiento de ejecución bloqueó inicialmente la conexión al socket local de MySQL. Las pruebas contra MariaDB se reejecutaron con acceso local explícito y aprobaron; el XAMPP Control Panel mostraba Apache activo en 4433/8080 y MySQL activo en 3306. No se modificó la configuración ni el esquema de XAMPP durante esta revalidación.

## Límites pendientes

- No se probó el arranque desde el pendrive físico ni en una segunda PC.
- No se hizo una revisión visual manual de todas las páginas admin en navegador/tamaños; la respuesta HTML y flujos HTTP sí fueron ejercitados.
- Analítica, regresión, dashboards gráficos y exportaciones quedan pendientes. El plan maestro etiqueta administración como Fase 7 y analítica como Fase 8, mientras la solicitud usa Fase 6 para administración; resolver la numeración antes de la siguiente entrega.
