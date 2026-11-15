# Plan interno de cierre y verificación

Actualización 2026-11-10: la integración MariaDB/HTTP de la ampliación por SKU ya pasó con PHP 8.0.28 (analítica: 32, administración HTTP: 27, checkout HTTP: 32). Se conserva abajo el estado de la revisión anterior como antecedente; las pruebas vigentes están en `docs/COMPATIBILIDAD_PHP.md`. La revisión visual y la prueba física del paquete corregido en URBE siguen pendientes.

Actualizado: 2026-11-04. Alcance: terminar la revisión integral descrita en `VERDELIA_PLAN.md` v2.0 y el objetivo de cierre universitario. No se cambia el stack ni se reimplementan fases aprobadas.

| Tarea | Criterio de aceptación | Estado y evidencia |
|---|---|---|
| Reconciliar alcance y numeración de fases | Mantener equivalencia entre fases solicitadas y plan maestro sin duplicar módulos. | Completado; `FASES_DESARROLLO.md`, `docs/REQUISITOS_PROFESOR.md`. |
| Analítica real y segura | Consultas a MySQL excluyen pagos rechazados/cancelados; agregan intervalos acotados; incluyen snapshots históricos; modelos reproducibles por SKU y variable. | Función por producto implementada; 27 aserciones numéricas y smoke SQLite aprobados. Integración MariaDB/HTTP pendiente con MySQL activo; ver `docs/PRUEBAS_ANALITICA.md`. |
| Regresión y casos límite | Mínimos cuadrados, R²/RMSE, regresión de ganancia/pérdida por producto y modelo exponencial solo con valores positivos. | Implementado y unit tests aprobados; las nuevas integraciones en `tests/analytics_integration.php` y `tests/admin_http_integration.php` esperan XAMPP MySQL. |
| Ganancia histórica | Diferenciar ingresos, costo de mercancía y margen bruto; preservar importes y snapshots anteriores. | Completado; snapshots de `order_items`, migración aditiva, integración SQL y respaldo previo/posterior. |
| Flujos funcionales | Registro→compra aprobada→recibo→historial y operaciones de administrador protegidas; datos demo persistentes. | Completado en integraciones HTTP/servicio; `docs/PRUEBAS_FINALES.md`. |
| Base, bootstrap y recuperación | Bootstrap idempotente en esquema vacío; respaldo completo restaurable en BD temporal; no borrar la base real. | Completado; `admin_fresh_bootstrap.php`, `backup_restore.php`; conteos finales consignados. |
| Modo offline y recursos locales | Sin CDN ni llamadas externas necesarias; imágenes, JS y CSS servidos localmente. | Revisión estática completada; no se hallaron referencias remotas de runtime en `app/` o `public/`; imágenes bajo `public/assets/`. |
| Rendimiento local | Límite de fechas, paginación, índices y carga diferida; medir estadísticas con datos actuales y registrar límites del equipo. | Medición local completada: mediana 15,84 ms / máximo 20,64 ms en 7 consultas diarias, 91 pedidos pagados y 179 buckets. No es benchmark de PC antigua. |
| Revisión visual final | Revisar vistas públicas y administración en anchos representativos y registrar desbordamientos/errores. | Se mantiene evidencia responsive anterior en `docs/PRUEBAS_FINALES.md`; pendiente revisar en navegador el nuevo selector de SKU y sus gráficas de barras/ajuste cuando MySQL esté disponible. |
| Portabilidad física | Ejecutar desde USB en unidad distinta y en segunda PC con XAMPP instalado. | Pendiente externo: solo está disponible C:. Alternativa comprobada: PHP CLI/XAMPP local, selección de puerto ocupado y respuesta HTTP 200 en puerto alternativo. |
| Entrega Desktop | Sincronizar sin eliminar datos locales; verificar hashes de todos los archivos de proyecto. | Completado; 110 archivos coinciden, cero faltantes/diferencias; `.git`, `storage`, logs y credenciales locales preservados/excluidos. |
| Documentación | Trazabilidad de R01–R11, guía de exposición/restauración y resultados solo de pruebas ejecutadas. | Completado, con historial de fase etiquetado y pendientes actuales en `docs/PRUEBAS_FINALES.md`. |

## Próximas acciones disponibles

1. Repetir el arranque desde un pendrive y unidad/letra distinta en un equipo con XAMPP. Mantenerlo pendiente hasta que exista acceso físico.
2. Medir el arranque y la interacción en una PC antigua cuando esté disponible; los tiempos actuales son solo referencia de este equipo.

No se considera validado un criterio cuando solo hay una inspección estática o cuando la prueba depende de hardware no disponible.
