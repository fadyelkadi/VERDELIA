# Decisiones y supuestos de VERDELIA

Registro iniciado el 2026-11-03; actualizado al cierre 2026-11-04. Fuente principal: `VERDELIA_PLAN.md` v2.0. Las notas por fase describen decisiones tomadas en cada entrega; el estado vigente está en `docs/PROGRESO.md` y `docs/PRUEBAS_FINALES.md`.

| Tema | Decisión para esta entrega | Motivo / pendiente |
|---|---|---|
| Motor y stack | PHP 8.x, PDO `pdo_mysql`, MySQL/MariaDB de XAMPP, HTML/CSS/JS vanilla. | Contrato confirmado por el plan. No se agregan frameworks ni runtimes. |
| Nombre de base de datos | `verdelia_demo`. | El plan maestro (secciones 4, 11 y 13) y el paquete usan ese nombre. El prompt pegado menciona `verdelia_db`; queda resuelta la discrepancia a favor del plan versionado. Se puede ajustar `VERDELIA_DB_NAME`. |
| Carga de entorno | Variables de entorno de Windows; `.env.example` es solo referencia. | No se añadió un cargador propio ni dependencia externa. |
| Datos demo | Semilla ficticia idempotente; no reinicia tablas ni stock al repetir arranque. | Los 96 pedidos históricos no rebajan el stock actual. |
| Servidor | `php -S` ligado a `127.0.0.1`, puerto preferido 8088 y búsqueda automática de alternativa libre. `VERDELIA_WEB_PORT` permite fijarlo y `VERDELIA_OPEN_BROWSER=0` evita abrir navegador en pruebas. | Demo local controlada, nunca servidor de producción. El selector se verificó cuando 8088 estaba ocupado. |
| XAMPP/USB | El lanzador busca PHP junto al proyecto, con `VERDELIA_XAMPP_HOME`/`XAMPP_HOME`, en ubicaciones habituales de unidades Windows, con `VERDELIA_PHP_EXE` o en PATH. MySQL portable se intenta solo si hay `xampp/mysql_start.bat`. | XAMPP no se incluye ni se instala. La prueba física desde USB y segunda PC sigue pendiente. |
| Ubicación | Workspace de desarrollo `C:\Users\fadye\OneDrive\Documents\ChatGPT\VERDELIA`; entrega sincronizada en `C:\Users\fadye\OneDrive\Desktop\VERDELIA`. | 110 archivos de proyecto verificados por SHA-256 sin faltantes ni diferencias. Se excluyen credenciales locales, logs y `storage` de la copia. |
| Fases | Fases 0–6 de la solicitud completadas, incluida analítica (Fase 8 del plan maestro). | La equivalencia de numeración se documenta en `FASES_DESARROLLO.md`. Pendientes externos y QA final están identificados en `docs/PRUEBAS_FINALES.md`. |
| Ambigüedades docentes | Pago PayPal concreto, tipo de regresión exponencial, formato PDF obligatorio y fechas siguen pendientes. | Mantener simulador local, lineal y exponencial cuando los datos lo permitan, recibo imprimible; confirmar con el docente antes de prometer más. |

## Auditoría del ZIP

Se reutilizaron los seis archivos presentes: lanzador, conexión PDO, schema SQL, bootstrap, router y página de estado. Se mejoró la comprobación de PHP 8 y `pdo_mysql`, se parametrizó el nombre de BD con validación, y se añadió selección de puerto libre. El ZIP no contiene README, `.env.example`, documentación de decisiones, pruebas ni respaldo SQL. El contenido de la página de inicio se conserva como pantalla de verificación, no como tienda.

## Fase 1 — Decisiones del frontend

- Se conservan PHP/HTML/CSS/JS vanilla y el router integrado. Los archivos de aplicación quedan fuera de `public/`; no se añade framework, gestor de paquetes ni URL CDN.
- La Fase 1 usa los 12 datos semilla como fixture visual porque MySQL no pudo validarse en esta sesión. En fases posteriores la lectura de catálogo deberá migrar a PDO sin cambiar los nombres de ruta ni el sistema visual.
- La interfaz avisa que los datos son ficticios. En la Fase 1, `sessionStorage` sostenía una vista previa; esa implementación quedó reemplazada por el carrito PHP/MySQL de la Fase 4. El prototipo de checkout de Fase 1 se reemplazó por el flujo persistente de pedidos y pago simulado en Fase 5.
- Las doce imágenes de catálogo son recursos ilustrativos generados con IA, locales y no verificadas como fotografía botánica exacta. Fuentes y notas en `docs/IMAGENES_Y_CREDITOS.md`.
- No se cambiaron el schema, bootstrap, conexión MySQL, lanzador ni copias de seguridad al construir la Fase 1.

## Fase 2 — Decisiones de datos y catálogo

- Se conserva `verdelia_demo` y las 11 tablas existentes. La inspección confirmó PK/FK, precios y costos en centavos, stock entero, estado `active`, fecha de creación, categorías, imágenes locales, pedidos y movimientos. No hizo falta migración estructural SQL.
- El bootstrap conserva `demo_seed_v1` y agrega la versión de datos `catalog_images_v1`. Esta migración añade relaciones SKU→`product_images` solo cuando faltan; no cambia stock, pedidos ni imágenes ya existentes. Los datos semilla viven en `app/data/catalog-seed.php`; el frontend ya no carga el fixture de Fase 1.
- PDO se centraliza en `app/database.php`; acceso al catálogo en `app/CatalogRepository.php`. Filtros, orden y límites usan allowlists, prepared statements y `LIMIT/OFFSET` entero. El límite es 8 productos por página.
- El umbral visual de stock bajo es 15 unidades, suficiente para identificar Palma Areca (12) y Ficus Lyrata (9) en la semilla. El agotamiento se representa cuando `stock = 0`; la semilla conserva las cantidades originales del plan.
- La Fase 2 dejó `/api/cart-preview` como vista de consulta; la Fase 4 reemplazó ese endpoint por `/api/cart`, con mutaciones protegidas por CSRF y persistencia. El carrito nunca descuenta ni reserva inventario.
- Cuando MySQL no está disponible, las rutas dinámicas responden 503 con mensaje genérico; el detalle técnico se registra en `storage/logs/app.log` fuera de `public/` y los logs están ignorados por Git.
- El respaldo inicial `database/backups/verdelia_demo.sql` se conserva. `scripts/backup.php` ahora crea exportaciones fechadas y sin sobrescribir en `database/backups/`; la Fase 2 generó `verdelia_20261103_165046.sql` y validó restauración en una BD temporal que luego eliminó.
- Se conectó XAMPP MariaDB 10.4.32 local. No se tocó el proceso MySQL de Lightning ni otras bases. Luego se reparó la causa documentada en la Fase 4: metadatos de replicación inválidos en `multi-master.info`, apartados en cuarentena reversible. MariaDB inició desde el panel, pasó PDO y el reinicio ordenado. El lanzador también pasó localmente; el USB físico no fue probado.

## Fase 3 — Autenticación y autorización

- Se usa la tabla existente `users`: `password_hash`, correo único, `role ENUM('ADMIN','CUSTOMER')` y `created_at` cubren el contrato. No se modificó `schema.sql`, ni se añadieron sesiones a MySQL; PHP administra sesiones nativas.
- El rol de registro se fija con literal SQL `CUSTOMER`; el servidor ignora cualquier `role` enviado por el cliente. `ADMIN` solo puede proceder de las cuentas demo ya sembradas o de cambios operativos fuera del registro público.
- La sesión guarda solamente `user_id`, CSRF aleatorio y marca de actividad. Los archivos viven en `storage/sessions/`, fuera de `public/` y excluidos de Git, para no depender de permisos de `C:\xampp\tmp` al ejecutar desde USB. Cada petición autenticada consulta rol y datos actuales en `users`, para que un cambio o eliminación no dependa de un rol viejo guardado en la cookie.
- Se limita el acceso a `/cuenta`, `/checkout` y `/admin` en el front controller. `/admin` completo requiere rol `ADMIN`; controles visuales no sustituyen esa comprobación. Los módulos administrativos siguen en modo lectura demo por alcance de esta fase.
- El límite de login es local por combinación de correo normalizado e IP, con seis fallos por 15 minutos; el archivo guarda solo una clave hash y queda fuera de Git. Es una protección razonable para demo local, no un limitador distribuido.
- Registro/login/logout y actualizaciones usan token CSRF de sesión. Cookies: HttpOnly, SameSite=Lax y Secure solo si la petición es HTTPS para que localhost HTTP continúe funcionando.
- Los hashes y roles existentes no se resetean al repetir el bootstrap. Las credenciales académicas están en `docs/CREDENCIALES_DEMO.local.md`, excluido de Git; el respaldo SQL de Fase 2 contiene hashes, no contraseñas en claro.
- `tests/auth_integration.php` usa transacción y rollback: aprobó con 18 aserciones. La prueba HTTP añadió registros temporales con prefijo identificable y los eliminó al terminar; las cuentas de demo permanecieron intactas. Los hashes demo permanecen en los respaldos SQL, nunca contraseñas en claro.
- El carrito se implementó con las tablas ya existentes, sin migración: `carts.user_id` mantiene un carrito por cliente y `cart_items` conserva producto/cantidad. Invitados usan `$_SESSION['guest_cart']`; al registrarse o iniciar sesión, el carrito visitante se fusiona con el de usuario, limitando las cantidades al stock actual.
- Todas las mutaciones del carrito son POST con token CSRF, cantidades enteras de 1 a 999 y validación de SKU/stock en PHP. Los precios se leen de MySQL en cada respuesta; valores enviados por el navegador se ignoran. Subtotal y total se expresan en centavos; entrega queda fuera de cálculo. Ninguna operación del carrito descuenta stock.

## Fase 4 — Cierre del carrito

- La solicitud actual llama Fase 4 al carrito, mientras `VERDELIA_PLAN.md` v2.0 lo enumera como Fase 5. Para esta entrega se sigue la numeración de la solicitud; no se avanzó a pedidos, checkout ni pagos.
- Se añadió `CartRepository` y `CartController`, endpoint `GET/POST /api/cart` y acciones add/set/remove/clear. `CartController` rechaza mutaciones sin CSRF; `CartRepository` usa transacciones y filas bloqueadas para verificar stock sin modificarlo.
- `carts` y `cart_items` ya estaban presentes en la BD real. No hubo cambios de esquema, migraciones, ni cambios a `orders`; se conservaron los 96 pedidos del seed.
- El guest cart vive en la sesión PHP durante la navegación; el cart autenticado se persiste en MariaDB y está separado por `user_id`. La fusión agrega cantidades sin sobrepasar stock ni 999 por SKU.
- Los flujos HTTP de carrito/autenticación aprobaron contra MariaDB con usuarios temporales eliminados al finalizar; no se modificaron cuentas demo. Se generó y restauró un respaldo final en base temporal aislada. Ver `docs/PRUEBAS_FASE_3.md` y `docs/PRUEBAS_FASE_4.md`.

## Fase 5 — Pedidos y pago simulado

- Se conserva el modelo PHP/PDO y se amplía `orders` con datos de entrega e idempotency key. `payments` admite intentos repetidos con `attempt_key`; la migración `checkout_v1` es aditiva e introspectiva para poder aplicarse en bases ya inicializadas. Añade primero un índice normal para la FK antes de retirar la unicidad anterior de `payments.order_id`.
- La transacción de pedido vuelve a leer y bloquear las filas de carrito/productos, verifica precio vigente y stock, y calcula subtotal/total en servidor. El envío de demostración vale 0; el navegador no puede aportar importes ni descuentos confiables.
- Estados implementados: aprobación → pedido `PAID`/pago `APPROVED`; rechazo → `PENDING_PAYMENT`/`REJECTED`, con carrito y stock intactos para reintentar; cancelación → pedido `CANCELLED`/pago `CANCELLED`, también sin descontar stock.
- En aprobación, decremento condicional de stock, movimiento, pago, estado del pedido y vaciado del carrito se confirman juntos. La protección combina transacción/bloqueo, claves idempotentes y estado de pedido para no cobrar ni descontar dos veces.
- Los pagos son exclusivamente simulados en el servidor local y usan referencias `SIM-*`; no se piden datos de tarjeta ni se conecta un proveedor. El usuario ve solo sus pedidos/recibos; `ADMIN` puede consultar y listar pedidos.
- Se generó respaldo previo `database/backups/verdelia_20261103_182346.sql` y respaldo posterior `database/backups/verdelia_20261103_183812.sql`. La migración y pruebas no alteraron los 96 pedidos históricos ni los dos usuarios demo. Evidencia y límites de validación: `docs/PRUEBAS_FASE_5.md`.

## Fase 6 — Gestión administrativa

- Se amplía PDO con `AdminRepository` y `AdminController`; las rutas `/admin*` requieren `ADMIN` desde el front controller. Toda mutación usa POST y token CSRF; listados son paginados; valores se validan/escapan en servidor.
- Productos editables conservan precio/costo en centavos, categoría, descripción, cuidados, disponibilidad e imagen local. El stock solo se crea como existencia inicial con movimiento auditable o se cambia desde Inventario. Productos y categorías no se eliminan físicamente. Los pedidos guardan snapshots y no se reescriben al editar catálogo.
- La biblioteca de imagen se limita a archivos ya presentes en `public/assets/images/products/`, cotejados contra las rutas locales y recursos asociados en BD. No se implementó subida: el catálogo ilustrativo existente cubre esta fase sin abrir una ruta de escritura pública. La autoría sigue documentada en `docs/IMAGENES_Y_CREDITOS.md`.
- Stock se cambia por delta contra un `expected_stock`; se bloquea la fila del producto, se compara el valor leído y se actualiza condicionalmente dentro de transacción junto con `stock_movements`. Un formulario obsoleto se rechaza y obliga a recargar.
- Las únicas transiciones admin son `PAID→FULFILLED` con último pago aprobado y `PENDING_PAYMENT→CANCELLED` sin pago aprobado. No se cambia el estado de pagos ni importes, no se repone stock por una cancelación que no descontó inventario y no se interpreta un rechazo como cobro. Cada cambio registra admin, origen/destino, motivo y fecha en `admin_order_status_history`.
- Ventas del dashboard suman totales con existencia de un pago `APPROVED` y pedido en `PAID`/`FULFILLED`; usa `EXISTS` para que los reintentos de pago no multipliquen ingresos. Los pedidos cancelados y los rechazos quedan fuera.
- Clientes se consultan solo con columnas básicas y rol `CUSTOMER`; no hay una columna de estado en el esquema actual, así que la interfaz muestra la cuenta existente como activa y no ofrece desactivación ni edición de credenciales.
- `admin_v1` agrega `uq_categories_name`, índices de listado/búsqueda y `admin_order_status_history`; runner introspectivo continúa después de `checkout_v1` y puede ejecutarse repetidamente. No se alteran snapshots ni pedidos históricos. Evidencia y límites están en `docs/PRUEBAS_FASE_6.md`.

## Fase 7 solicitada — Analítica (Fase 8 del plan maestro)

- `AnalyticsRepository` agrega ventas confirmadas por `orders.confirmed_at`, estados `PAID`/`FULFILLED` y al menos un pago `APPROVED`. Las consultas usan `EXISTS` para que reintentos no dupliquen ingresos. El importe analizado son líneas de producto; el envío se excluye (la demo es $0).
- Los importes y costos provienen de `order_items.unit_price_cents`, `unit_cost_cents`, cantidad y `line_total_cents`; el costo histórico no se vuelve a leer del producto actual. La ganancia reportada es bruta estimada y no representa utilidad neta: no resta nómina, impuestos, operación, comisiones ni mermas.
- `analytics_v1` agrega `category_name_snapshot`, rellena el histórico con la categoría existente y añade `idx_orders_analytics`. Los nuevos pedidos guardan snapshots de categoría. Si la categoría se renombra después, ventas pasadas conservan su clasificación original. El cambio es aditivo y puede repetirse; no altera pedidos, precios ni existencias.
- Se admiten intervalos inclusivos de hasta 730 días y agregación diaria, semanal ISO o mensual. El servicio crea buckets vacíos para que la serie cubra todo el intervalo, pero no ajusta modelos si no hubo pedidos confirmados.
- La regresión lineal ajusta mínimos cuadrados sobre los importes observados. La curva exponencial ajusta log(ventas), por lo que requiere al menos tres períodos y valores positivos en todos. R² puede no definirse en una serie constante; RMSE compara ambos modelos en la escala original. Se limitan proyecciones a 12 períodos y se muestran como estimaciones descriptivas, no como garantía.
- Las gráficas son Canvas local y no usan bibliotecas/CDN. Son visualizaciones de apoyo; los valores y explicaciones también aparecen como texto/tabla para que la información no dependa del dibujo.
- El historial incluye 96 pedidos de demostración con costos simulados predefinidos. El snapshot histórico hace reproducibles los reportes frente a ediciones posteriores del catálogo; no implica que los datos sean ventas reales.
- Copia final de base verificada: `database/backups/verdelia_20261104_030555.sql`; copia previa a migración preservada en `database/backups/verdelia_20261104_024900.sql`. La copia `verdelia_demo.sql` se refrescará solo tras archivar su versión inicial.
