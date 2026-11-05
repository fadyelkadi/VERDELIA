# Fases y backlog de VERDELIA

Backlog priorizado desde la matriz R01–R11 del plan maestro. P0 = requisito mínimo/arquitectura; P1 = flujo central; P2 = evidencia, presentación o extra.

| Prioridad | Req. | Trabajo | Fase | Evidencia de aceptación |
|---|---|---|---|---|
| P0 | R02 | Esquema relacional MySQL/MariaDB, bootstrap idempotente, integridad y semilla. | 2 | Tablas/datos en SQL, segunda ejecución sin duplicación. |
| P0 | R03 | Registro, login/logout, contraseñas con hash y sesiones. | 3 | Alta y posterior login; contraseña nunca en claro. |
| P0 | R01 | Roles ADMIN/CUSTOMER y autorización comprobada en servidor. | 3, 7 | Cliente bloqueado en `/admin`; admin permitido. |
| P0 | R04 | Carrito multiartículo, cantidades, persistencia y totales recalculados en servidor. | 5 | Dos referencias y cantidades con importe correcto. |
| P0 | R05 | Checkout local con pedido persistido y stock transaccional. | 6 | Pedido, líneas, estados e inventario coherentes. |
| P0 | R06 | Pago simulado aprobado/rechazado, sin cobros reales. | 6 | Dos resultados reproducibles con referencia ficticia. |
| P0 | R07 | Recibo protegido, imprimible y con desglose de pedido. | 6 | El cliente solo ve su comprobante. |
| P0 | R08 | Dashboard de ventas, unidades y ganancias con datos de BD. | 8 | Barras con agregados verificables por SQL. |
| P0 | R09 | Regresión lineal, pendiente, pronóstico y explicación. | 8 | Resultado numérico reproducible y graficado. |
| P0 | R10 | Ajuste exponencial solo si datos positivos cumplen supuestos. | 8 | Curva/ecuación y limitaciones explicadas. |
| P0 | R11 | Ventas, costos y beneficio por periodo/producto. | 8 | Comparación rastreable a líneas históricas. |
| P1 | — | Identidad visual, catálogo y contenido responsive. | 1, 2, 4 | Vista a 360/768/1440 px, datos reales y assets locales. |
| P1 | — | Administración de productos, stock, categorías y pedidos. | 7 | CRUD con validación, permiso y auditoría básica. |
| P1 | — | Auditoría de seguridad, accesibilidad y pruebas de integración/E2E. | 9 | Guion reproducible y resultados documentados. |
| P2 | — | Imagen, páginas informativas, PDF de recibo y extras UX. | 4, 6, 10 | Créditos, rendimiento y evidencia de defensa. |

## Correspondencia de fases

Las solicitudes recibidas usan una numeración distinta del plan maestro. Se mantiene la etiqueta del encargo y se indica su equivalencia en `VERDELIA_PLAN.md` v2.0:

| Solicitud recibida | Plan maestro v2.0 | Entrega |
|---|---:|---|
| Fase 4 — Carrito | Fase 5 | `docs/PRUEBAS_FASE_4.md` |
| Fase 5 — Checkout y pedidos | Fase 6 | `docs/PRUEBAS_FASE_5.md` |
| Fase 6 — Panel administrativo | Fase 7 | `docs/PRUEBAS_FASE_6.md` |
| Analítica de ventas y modelos | Fase 8 | `docs/PRUEBAS_ANALITICA.md` |

## Dependencias

F0 → F1 → F2 → F3 → F4 → F5 → F6 → F7 → F8 → F9 → F10. Las fases de autenticación, autorización, carrito transaccional, checkout y pagos requieren revisión profunda; ninguna fase se considera aprobada sin sus pruebas reales.

## Fase 1 — Identidad visual, UX y frontend

- [x] Tokens visuales locales, marca SVG y favicon.
- [x] Layout compartido, navegación responsive, botones, campos, tarjetas, aviso/toast y estados vacíos.
- [x] Inicio, tienda con búsqueda/filtros/orden, detalle, carrito, login/registro, prototipo inicial de checkout, panel administrativo visual y páginas institucionales. Checkout quedó conectado en Fase 5.
- [x] Recursos locales para los 12 productos y notas de procedencia.
- [x] Interacciones de prototipo sin crear cuentas, pedidos, mensajes ni pagos persistidos.
- [x] Validar visualmente a 360/768/1440 px en navegador real; corregir desbordamiento móvil.
- [x] Probar interacciones principales y rutas en un navegador con servidor PHP local.

Estado: frontend responsive verificado. La revisión inicial está registrada en `docs/PRUEBAS_FASE_1.md`; el frontend ahora consume los datos reales conectados en Fase 2.

## Fase 2 — MySQL, catálogo e inventario

- [x] Centralizar PDO con `utf8mb4`, consultas preparadas y respuesta segura ante caída de MySQL.
- [x] Conservar las 11 tablas iniciales; no se necesitó alterar el esquema.
- [x] Completar el seed de imágenes locales mediante migración idempotente `catalog_images_v1` en `schema_versions`.
- [x] Conectar inicio, categorías, tienda, filtros, búsqueda, orden, paginación y fichas a consultas MySQL.
- [x] Consultar precio y stock en MySQL; indicar agotados y stock bajo; mantener carrito sin descuento de inventario (Fase 4 lo conectó a persistencia).
- [x] Crear respaldo fechado y probar restauración en una base temporal separada.
- [x] Ejecutar pruebas SQL, HTTP y de navegador en XAMPP/PHP local.
- [x] Confirmar persistencia con reinicio ordenado de MySQL y probar el lanzador `INICIAR_VERDELIA.bat` en el equipo actual.

Estado: el catálogo dinámico y el seed se probaron contra XAMPP MariaDB 10.4.32. El reinicio ordenado conservó productos y pedidos; el arranque del lanzador pasó. El USB físico sigue pendiente. Ver `docs/PRUEBAS_FASE_2.md` y `docs/PRUEBAS_FASE_4.md`.

## Fase 3 — Autenticación, sesiones y roles

- [x] Registro PDO con validación, correo único, CSRF y rol `CUSTOMER` fijo en servidor.
- [x] Inicio/cierre de sesión con hash verificado, errores de acceso genéricos y límite local de intentos.
- [x] Cookies de sesión HttpOnly/SameSite, Secure bajo HTTPS, rotación del ID y expiración por inactividad.
- [x] Perfil autenticado para editar nombre/cambiar contraseña; controles CSRF y escape de salida.
- [x] Middleware servidor para `/cuenta`, `/checkout` y todos los paths `/admin`; menú cambia por rol.
- [x] Se conserva `users`; no se requiere cambio estructural ni nueva tabla.
- [x] Credenciales demo locales excluidas de Git y separadas de respaldos SQL.
- [x] Prueba funcional de registro, login, roles, sesión, perfil y cambio de contraseña contra XAMPP MariaDB.
- [x] Prueba HTTP/servidor completo después del reinicio de MariaDB y desde el lanzador.

Estado: autenticación y autorización verificadas en MariaDB con usuarios temporales y limpieza exacta. Los usuarios demo no se tocaron. Ver `docs/PRUEBAS_FASE_3.md`.

## Fase 4 — Carrito funcional y persistencia

- [x] API PHP con CSRF para agregar, cambiar cantidades, quitar líneas y vaciar el carrito.
- [x] Precios y existencias leídos del catálogo MySQL en el servidor; rechaza SKUs inexistentes, cantidades inválidas y exceso de stock.
- [x] Carrito de visitante en sesión PHP; carrito de cliente en `carts`/`cart_items`, separado por `user_id`.
- [x] Fusión al registrarse o iniciar sesión, limitada a existencias actuales.
- [x] El cliente renderiza cantidades, precios actuales, subtotales y total desde la respuesta del servidor; no envía importes confiables.
- [x] Sin descuento ni reserva de inventario; checkout permanece de demostración.
- [x] 13 aserciones de servicio y 32 aserciones HTTP reales en MariaDB; autenticación integrada y catálogo probados.
- [x] Backup fechado y restauración verificada en una base temporal aislada.
- [x] Lanzador validado en el equipo actual en `127.0.0.1:8088`.
- [ ] Arranque desde una unidad USB física y segunda PC.

Estado: Fase 4 completada contra XAMPP MariaDB. Las tablas existentes bastaron; no se creó migración ni se alteraron los 96 pedidos demo. Resultados en `docs/PRUEBAS_FASE_4.md`.

## Fase 5 — Checkout, pedidos, pagos simulados y recibos

- [x] Formulario de checkout autenticado para contacto y dirección; CSRF, validación y retorno seguro desde acceso/registro.
- [x] Creación transaccional de pedidos y líneas con snapshots del producto, precio y datos de entrega.
- [x] Revalidación y bloqueo de carrito/productos/stock; cantidades/precios/totales confiables calculados en el servidor.
- [x] Simulación local de aprobación, rechazo y cancelación; sin captura de tarjeta ni proveedor externo.
- [x] Aprobación descuenta stock una sola vez, registra movimiento y vacía el carrito en la misma transacción. Rechazo/cancelación conservan stock y carrito.
- [x] Reintentos de pago con referencias ficticias e idempotencia; rechazo de totales alterados y reenvíos duplicados.
- [x] Historial, detalle y recibo imprimible protegidos por propietario o rol ADMIN; lista administrativa paginada.
- [x] Migración aditiva versionada `checkout_v1`; copia anterior al cambio y respaldo final restaurable.
- [x] Pruebas de servicio/HTTP, regresiones de autenticación/carrito, lint PHP/JS y restauración documentadas en `docs/PRUEBAS_FASE_5.md`.
- [ ] Arranque desde una unidad USB física y en una segunda PC.

Estado al cierre de esa entrega: Fase 5 implementada y validada contra XAMPP MariaDB local. Los 96 pedidos históricos se conservaron; el panel se implementó después en Fase 6.

## Fase 6 — Panel administrativo funcional

La solicitud del panel denomina **Fase 6** y el plan maestro v2.0 lo enumera como Fase 7. El reporte de ese cierre está en `docs/PRUEBAS_FASE_6.md`; la analítica se completó después y está probada según `docs/PRUEBAS_ANALITICA.md` y `docs/PRUEBAS_FINALES.md`.

- [x] Dashboard de conteos reales: productos activos/agotados/bajo stock, clientes, pedidos, pendientes, pagados y ventas con pagos aprobados; cinco pedidos recientes.
- [x] CRUD de productos y categorías con validación del servidor, CSRF, consultas preparadas, selección de imágenes locales y desactivación lógica.
- [x] Inventario separado del formulario de producto; ajustes transaccionales por delta, valor esperado, motivo, fecha y movimiento auditable.
- [x] Pedidos paginados con búsqueda/filtro, cliente, total, pago/reference simulado, recibo y transiciones permitidas `PAID→FULFILLED`, `PENDING_PAYMENT→CANCELLED`.
- [x] Clientes CUSTOMER paginados/buscables, pedidos asociados y datos de cuenta sin seleccionar ni exponer hash.
- [x] Auditoría de cambios de estado, índices de consulta y migración versionada `admin_v1`; 96 pedidos previos conservados.
- [x] Integración probada con catálogo, carrito, checkout y snapshots históricos; detalles en `docs/PRUEBAS_FASE_6.md`.
- [ ] Arranque desde un USB físico y en una segunda PC.

Estado: panel implementado y probado contra XAMPP MariaDB en base aislada y en la base local. Las tablas administrativas se revisaron a 360 px y la analítica a 360/768/1024/1440 px; el arranque físico en USB/segunda PC sigue pendiente.

## Fase 7 solicitada — Analítica de ventas (Fase 8 del plan maestro)

- [x] Filtros de fecha y agregación diaria, semanal o mensual; períodos vacíos aparecen como cero.
- [x] Métricas de pedidos, unidades, ingresos, costos históricos y ganancia bruta con comparación frente al período anterior.
- [x] Productos y categorías principales basados en snapshots de pedido; renombrar el catálogo no reescribe ventas pasadas.
- [x] Gráficas Canvas locales, adaptables y sin CDN para barras, evolución de ingreso/costo/ganancia y comparación de modelos.
- [x] Regresión lineal y ajuste exponencial con parámetros, R², RMSE, proyección limitada y explicación de cuándo no aplica.
- [x] Migración aditiva/idempotente `analytics_v1`; la base previa se respalda y sus registros se conservan.
- [x] Pruebas matemáticas y de integración MariaDB aisladas, permisos HTTP y restauración del respaldo final. Ver `docs/PRUEBAS_ANALITICA.md` y `docs/PRUEBAS_FINALES.md`.
- [ ] Arranque desde una unidad USB física y en una segunda PC.

Estado: analítica implementada con costos históricos y probada contra XAMPP MariaDB; el respaldo final se restauró en base temporal independiente. Las gráficas se revisaron visualmente a 360/768/1024/1440 px y se ajustaron etiquetas para evitar solapamientos. Siguen pendientes la medición en un equipo antiguo y el arranque desde USB/segunda PC.
