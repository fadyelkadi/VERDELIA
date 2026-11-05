# Progreso de VERDELIA

Actualizado: 2026-11-04

## Fase 0 — Preparación y arquitectura

- [x] Auditar el plan y los seis archivos del ZIP.
- [x] Registrar discrepancias, supuestos y restricciones del entorno.
- [x] Preparar estructura, `.gitignore`, `.gitattributes`, README y plantilla de entorno.
- [x] Crear la rama de trabajo `codex/fase-0-arquitectura`.
- [x] Definir comprobaciones previas y flujo de arranque desde Windows/USB.
- [x] Preparar correspondencia de requisitos R01–R11 y guía manual.
- [x] Validar ejecución integrada PHP + MySQL/MariaDB.
- [x] Sincronizar copia Desktop existente y comprobar igualdad SHA-256 de archivos de proyecto.
- [ ] Validar arranque desde una unidad USB física y en una segunda PC.
- [x] Generar y restaurar el respaldo SQL desde una BD activa.

Estado: PHP CLI 8.3.33, `pdo_mysql` y XAMPP MariaDB 10.4.32 en el puerto 3306 responden por PDO. XAMPP Apache está en 8080/4433 para evitar la ocupación de 80/443 por Local/Nginx. La copia Desktop se sincronizó y 111 archivos de proyecto coinciden por SHA-256; cero faltantes o diferencias. Se conservó respaldo pre-migración, se aplicó `analytics_v1` y se restauró el respaldo final en una BD aislada. La revisión visual responsive final cubrió tienda y rutas principales en móvil, analítica en cuatro anchos, y tablas admin en móvil. La prueba física desde USB/segunda PC y el benchmark en equipo antiguo continúan pendientes.

## Fases siguientes

## Fase 1 — Identidad visual y frontend

- [x] Sistema visual, logo SVG, favicon y recursos locales.
- [x] Rutas visuales para inicio, colección, ficha, carrito, acceso/registro, prototipo de checkout, administración y páginas institucionales.
- [x] Interacciones de prototipo sin persistencia en MySQL ni servicios externos.
- [x] Validación visual de interacción principal y tamaños móvil, tablet y escritorio en navegador.
- [x] Validación visual de escritorio a 1440 px.
- [ ] Recorrido de todos los enlaces/formularios y auditoría de consola.

Estado: la revisión inicial fue un prototipo visual. El catálogo, carrito, autenticación, checkout, administración y analítica usan PHP/MySQL. La revisión visual histórica está en `docs/PRUEBAS_FASE_1.md`; el cierre y limitaciones actuales en `docs/PRUEBAS_FINALES.md`.

## Fase 2 — Integración MySQL, catálogo e inventario

- [x] PDO centralizado y manejo seguro de errores.
- [x] Catálogo, categorías, precios, stock y fichas servidos desde MySQL.
- [x] Filtros SQL, búsqueda, ordenamiento y paginación.
- [x] Imágenes locales enlazadas a productos en base de datos.
- [x] Indicadores de disponibilidad, bajo stock y vistas de producto agotado.
- [x] Seed idempotente, 12 productos, 4 categorías y 96 pedidos históricos conservados.
- [x] Respaldo fechado y restauración validada en una BD temporal.
- [x] Persistencia tras reinicio ordenado de MariaDB y prueba de `INICIAR_VERDELIA.bat` en el equipo actual.

Estado: consultas y páginas probaron contra XAMPP/MariaDB con resultados correctos. El catálogo conserva 12 productos, 4 categorías y 96 pedidos. El reinicio ordenado y el bootstrap del lanzador también quedaron validados. Ver `docs/PRUEBAS_FASE_2.md` y `docs/PRUEBAS_FASE_4.md`.

## Fase 3 — Autenticación, sesiones y roles

- [x] Servicio PDO para registro, credenciales, perfil y cambio de contraseña.
- [x] Alta pública limitada a `CUSTOMER`, hashes con `password_hash()` y login con `password_verify()`.
- [x] CSRF en registro, login, logout y cambios de perfil; límite local por IP/correo hasheados.
- [x] Sesión nativa con HttpOnly, SameSite=Lax, Secure bajo HTTPS, rotación de ID y 30 min de inactividad.
- [x] Protección servidor para `/cuenta`, `/checkout` y `/admin`.
- [x] Navegación por rol y página privada de perfil.
- [x] Pruebas sin BD: 20 aserciones de sesión/CSRF/límite y render CLI de login/registro.
- [x] Se reutiliza la tabla `users`; no se modificó el esquema ni se tocó información de MySQL.
- [x] `tests/auth_integration.php`: 18 aserciones con MariaDB; transacción revertida.
- [x] Registro/login/logout, CSRF, cambio de contraseña, usuario persistido temporal y rechazo de cliente en `/admin` probados por HTTP.
- [x] Recorrido de rutas principales mediante el lanzador `INICIAR_VERDELIA.bat`.

Estado: autenticación verificada contra MariaDB en servicio y por HTTP. Los dos usuarios demo se conservaron; las cuentas HTTP temporales fueron eliminadas. Ver `docs/PRUEBAS_FASE_3.md`.

## Fase 4 — Carrito persistente

- [x] Operaciones validadas en servidor: agregar, cambiar, quitar y vaciar.
- [x] Precios consultados en MySQL; subtotal y total recalculados en PHP; CSRF para mutaciones.
- [x] Invitados conservan el carrito en sesión PHP; clientes en tablas `carts` y `cart_items`.
- [x] Fusión de carrito al registrarse/iniciar sesión y aislamiento por usuario.
- [x] 13 aserciones de servicio, 32 aserciones HTTP y prueba visual de añadir/consultar/vaciar.
- [x] Sin reserva o descuento de inventario; pedidos históricos permanecen en 96.
- [x] Sin cambios de esquema. Backup final restaurado en base temporal aislada.
- [ ] Arranque desde USB físico/segunda PC.

Estado: Fase 4 completada para la demostración local. Ver `docs/PRUEBAS_FASE_4.md`.

## Fase 5 — Checkout, pedidos, pagos simulados y recibos

- [x] Checkout autenticado con validación de contacto/dirección, CSRF y recuperación segura después del login/registro.
- [x] Pedidos y líneas persistidos con snapshots y totales calculados/validados desde MySQL en una transacción.
- [x] Aprobación/rechazo/cancelación de pago local, referencias ficticias, reintentos e idempotencia.
- [x] Aprobación actualiza stock y movimiento una sola vez y limpia el carrito atómicamente; rechazo/cancelación no alteran inventario.
- [x] Recibo/detalle por pedido, historial de cliente y lectura administrativa con control de propietario/rol.
- [x] Migración de esquema `checkout_v1` aplicada sin modificar los 96 pedidos existentes.
- [x] 23 aserciones de servicio, 32 de HTTP de checkout; regresiones: auth 20 + 18, carrito 13 y HTTP general 32.
- [x] 43 archivos PHP pasaron `php -l`; `node --check` pasó; respaldo restaurado en BD temporal aislada.
- [ ] Arranque desde USB físico/segunda PC.

Estado al cierre de esa entrega: Fase 5 finalizada y verificada en XAMPP MariaDB 10.4.32 en este equipo. La continuación administrativa se completó después en Fase 6. Ver `docs/PRUEBAS_FASE_5.md` y `docs/PRUEBAS_FASE_6.md`.

## Fase 6 — Panel administrativo funcional

- [x] Indicadores del dashboard y pedidos recientes se consultan en MySQL; ventas requieren pago `APPROVED` y pedido `PAID`/`FULFILLED`.
- [x] Productos/categorías con formularios validados, búsqueda, paginación, edición, baja lógica y selector de assets locales.
- [x] Ajustes de stock con bloqueo/valor esperado; no acepta valores negativos ni sobreescribe cambios concurrentes; crea movimientos con motivo y fecha.
- [x] Pedidos filtrables, pagos y referencias consultables, recibos visibles y solo transiciones operativas permitidas con motivo/auditoría.
- [x] Clientes paginados y buscables, pedidos asociados; no se consultan contraseñas ni hashes.
- [x] Acceso y mutaciones admin protegidos por rol/CSRF. No hay carga arbitraria de archivos ni borrado físico.
- [x] Migración `admin_v1` con índice único de nombre de categoría, índices de consulta y tabla `admin_order_status_history`.
- [x] Pruebas aisladas de servicio/HTTP e integración con catálogo/carrito/checkout; informe en `docs/PRUEBAS_FASE_6.md`.
- [ ] QA visual final de administración/analítica a 360, 768, 1024 y 1440 px; el viewport integrado no ofrece control de tamaño en esta sesión.
- [ ] Arranque desde USB físico/segunda PC.

Estado: panel operativo y conectado a datos en XAMPP local. La analítica quedó completada como Fase 7 de las solicitudes (Fase 8 del plan maestro).

## Fase 7 solicitada — Analítica comercial (Fase 8 del plan maestro)

- [x] Reporte con filtros de fecha y agrupación diaria/semanal/mensual, comparación con intervalo anterior y períodos vacíos explícitos.
- [x] Ingresos y costos desde snapshots de líneas confirmadas/pagadas, ganancias brutas, unidades, pedidos, categorías y productos principales.
- [x] Canvas JS local con barras, serie ingreso/costo/ganancia y curvas/proyecciones lineal y exponencial comparadas por RMSE.
- [x] Migración idempotente `analytics_v1`; backfill de nombres de categoría y soporte histórico sin depender del nombre actual.
- [x] Modelos analíticos 22; sesiones 20; integración analítica 19; bootstrap limpio 4; administración de servicio 41/HTTP 26; checkout 24/HTTP 32; auth 18, carrito 13 y HTTP general 32; restauración final verificada.
- [x] PHP lint: 53 archivos; `node --check` para JS.
- [x] Lanzador validado en XAMPP local: puerto ocupado detectado, selector encontró 8089 y el servidor real respondió HTTP 200; se añadió `VERDELIA_OPEN_BROWSER=0` para iniciar en consola sin abrir pestaña.
- [ ] Copia/arranque desde USB físico y segunda PC.

Estado: se respaldó la BD antes de migrar, se conservó el historial demo y se creó/restauró una exportación posterior con 12 tablas, 12 productos, 96 pedidos, 96 líneas y 96 snapshots de categoría. Las pruebas se ejecutaron contra XAMPP MariaDB en bases temporales aisladas salvo el bootstrap aditivo de la base VERDELIA local.
