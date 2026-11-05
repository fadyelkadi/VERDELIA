# Plan interno de cierre y verificación

Actualizado: 2026-11-04. Alcance: terminar la revisión integral descrita en `VERDELIA_PLAN.md` v2.0 y el objetivo de cierre universitario. No se cambia el stack ni se reimplementan fases aprobadas.

| Tarea | Criterio de aceptación | Estado y evidencia |
|---|---|---|
| Reconciliar alcance y numeración de fases | Mantener equivalencia entre fases solicitadas y plan maestro sin duplicar módulos. | Completado; `FASES_DESARROLLO.md`, `docs/REQUISITOS_PROFESOR.md`. |
| Analítica real y segura | Consultas a MySQL excluyen pagos rechazados/cancelados; agregan intervalos acotados; incluyen snapshots históricos; modelos matemáticos reproducibles y errores visibles. | Completado; `AnalyticsRepository`, `AnalyticsModels`, `analytics_v1`, pruebas 22 + 19. |
| Regresión y casos límite | Mínimos cuadrados, R²/RMSE, modelo exponencial solo con valores positivos, manejo de series insuficientes/constantes/cero/negativas y límites numéricos. | Completado; `tests/analytics_models.php` (22), `docs/PRUEBAS_ANALITICA.md`. |
| Ganancia histórica | Diferenciar ingresos, costo de mercancía y margen bruto; preservar importes y snapshots anteriores. | Completado; snapshots de `order_items`, migración aditiva, integración SQL y respaldo previo/posterior. |
| Flujos funcionales | Registro→compra aprobada→recibo→historial y operaciones de administrador protegidas; datos demo persistentes. | Completado en integraciones HTTP/servicio; `docs/PRUEBAS_FINALES.md`. |
| Base, bootstrap y recuperación | Bootstrap idempotente en esquema vacío; respaldo completo restaurable en BD temporal; no borrar la base real. | Completado; `admin_fresh_bootstrap.php`, `backup_restore.php`; conteos finales consignados. |
| Modo offline y recursos locales | Sin CDN ni llamadas externas necesarias; imágenes, JS y CSS servidos localmente. | Revisión estática completada; no se hallaron referencias remotas de runtime en `app/` o `public/`; imágenes bajo `public/assets/`. |
| Rendimiento local | Límite de fechas, paginación, índices y carga diferida; medir estadísticas con datos actuales y registrar límites del equipo. | Medición local completada: mediana 15,84 ms / máximo 20,64 ms en 7 consultas diarias, 91 pedidos pagados y 179 buckets. No es benchmark de PC antigua. |
| Revisión visual final | Revisar vistas públicas y administración en anchos representativos y registrar desbordamientos/errores. | Completado en navegador con emulación responsive: portada, tienda, carrito, login y ficha de producto a 360 px; analítica administrativa y gráficos a 360/768/1024/1440 px; tablas de productos, inventario, pedidos y clientes a 360 px. Se corrigió el solapamiento de etiquetas del gráfico y los datos tabulares caben en contenedores desplazables. No se visualizó el flujo checkout/recibo en navegador en los cuatro anchos; sus integraciones HTTP sí pasaron. Ver `docs/PRUEBAS_FINALES.md`. |
| Portabilidad física | Ejecutar desde USB en unidad distinta y en segunda PC con XAMPP instalado. | Pendiente externo: solo está disponible C:. Alternativa comprobada: PHP CLI/XAMPP local, selección de puerto ocupado y respuesta HTTP 200 en puerto alternativo. |
| Entrega Desktop | Sincronizar sin eliminar datos locales; verificar hashes de todos los archivos de proyecto. | Completado; 110 archivos coinciden, cero faltantes/diferencias; `.git`, `storage`, logs y credenciales locales preservados/excluidos. |
| Documentación | Trazabilidad de R01–R11, guía de exposición/restauración y resultados solo de pruebas ejecutadas. | Completado, con historial de fase etiquetado y pendientes actuales en `docs/PRUEBAS_FINALES.md`. |

## Próximas acciones disponibles

1. Repetir el arranque desde un pendrive y unidad/letra distinta en un equipo con XAMPP. Mantenerlo pendiente hasta que exista acceso físico.
2. Medir el arranque y la interacción en una PC antigua cuando esté disponible; los tiempos actuales son solo referencia de este equipo.

No se considera validado un criterio cuando solo hay una inspección estática o cuando la prueba depende de hardware no disponible.
