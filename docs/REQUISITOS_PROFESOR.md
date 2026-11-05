# Matriz de requisitos y evidencia

Equivalencia de numeración: carrito solicitado como Fase 4 = Fase 5 del plan maestro; checkout solicitado como Fase 5 = Fase 6; panel solicitado como Fase 6 = Fase 7; analítica = Fase 8 del plan maestro.

| Req. | Cumplimiento en VERDELIA | Dónde verlo | Evidencia de verificación |
|---|---|---|---|
| R01 — Roles y autorización | Registro público fija `CUSTOMER`; `/admin*` exige rol `ADMIN` en servidor. | `/registro`, `/admin`, `/admin/analitica`; `app/auth.php`, `public/index.php` | `tests/auth_integration.php`, `tests/admin_http_integration.php`; cliente/visitante bloqueados. |
| R02 — Base relacional | MySQL/MariaDB, claves y restricciones; bootstrap repetible y seed demo. | `database/schema.sql`, `scripts/bootstrap.php` | `tests/admin_fresh_bootstrap.php`; respaldo restaurado en base temporal. |
| R03 — Autenticación | Hash de contraseña, sesión PHP, cierre de sesión, token CSRF y controles de acceso. | `/login`, `/registro`, `/cuenta`; `app/AuthService.php` | `tests/auth_session.php`, `tests/auth_integration.php`, pruebas HTTP. |
| R04 — Carrito | Validación de SKU/cantidad/stock, persistencia para usuarios autenticados y cálculo en servidor. | `/carrito`, `public/api/cart.php`, `app/CartRepository.php` | `tests/cart_integration.php`, `tests/http_integration.php`; no descuenta stock antes de pagar. |
| R05 — Pedido e inventario | Checkout transaccional, líneas e importes snapshot, stock bloqueado y movimiento de salida. | `/checkout`, `/cuenta/pedidos`, `app/OrderRepository.php` | `tests/checkout_integration.php`, `tests/checkout_http_integration.php`. |
| R06 — Pago simulado | Resultados aprobado/rechazado/cancelado con referencia ficticia; sin pasarela ni captura de tarjeta. | Checkout y detalle/recibo del pedido | Pruebas de integración de checkout; `SIM-*` en datos de demo. |
| R07 — Recibo protegido | Comprobante imprimible; lectura por propietario o administrador. | `/pedido/{número}/recibo`, `/admin/pedidos/{número}/recibo` | `tests/checkout_http_integration.php`, `tests/admin_http_integration.php`. |
| R08 — Métricas | Pedidos, unidades, ingreso de productos, costo, ganancia bruta estimada y comparación por fechas. | `/admin/analitica` | `tests/analytics_integration.php` compara agregados contra SQL independiente. |
| R09 — Regresión lineal | Pendiente/intercepto por mínimos cuadrados, R², RMSE y proyecciones limitadas. | Sección “Tendencia lineal y modelo exponencial” | `tests/analytics_models.php` con valores conocidos; integración prueba ventas persistidas. |
| R10 — Modelo exponencial | Ajuste log-lineal condicionado a tres o más períodos positivos; sin generar ajuste cuando no aplica. | `/admin/analitica` | `tests/analytics_models.php` valida curva conocida, ceros, negativos y límites numéricos. |
| R11 — Ingreso, costo y beneficio | Precio/costo histórico por producto/categoría y período; clasificación conserva nombre histórico. | `/admin/analitica` | `tests/analytics_integration.php` valida sumas, comparación SQL y renombre de categoría en BD temporal. |

## Evidencia técnica adicional

- Migraciones versionadas: `checkout_v1`, `admin_v1`, `analytics_v1`.
- `tests/admin_http_integration.php`: 26 comprobaciones HTTP que incluyen permisos de analítica, ruta administrativa, filtros, formularios, CSRF y operaciones del panel.
- `tests/admin_fresh_bootstrap.php`: base temporal nueva, bootstrap dos veces y conteos sin duplicación.
- `tests/backup_restore.php`: valida 12 tablas, 12 productos, 12 imágenes, 96 pedidos, 96 líneas y 96 snapshots de categoría al restaurar una copia temporal.
- Los resultados ejecutados en el cierre se encuentran en `docs/PRUEBAS_ANALITICA.md` y `docs/PRUEBAS_FINALES.md`.

## Condiciones de alcance

- Pedidos, pagos y productos de muestra son ficticios; no se procesa dinero.
- Ganancia mostrada es margen bruto estimado, no utilidad neta ni estado financiero auditado.
- El arranque en otra PC o USB físico requiere verificar PHP, `pdo_mysql`, MySQL/MariaDB y permisos locales; esa validación física queda pendiente.
