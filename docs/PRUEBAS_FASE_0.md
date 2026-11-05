# Pruebas de Fase 0

Fecha de revisión: 2026-11-03. No se declara aprobada ninguna prueba que no haya sido ejecutada.

Este documento conserva la evidencia del primer intento en un entorno que todavía no tenía MariaDB disponible. Los estados “pendiente” o “bloqueado” de esa fecha fueron revaluados después; el resultado vigente está en [`PRUEBAS_FINALES.md`](PRUEBAS_FINALES.md).

## Evidencia del entorno

- Encontrado `C:\xampp\php\php.exe` y comando PHP disponible.
- No había proceso `mysqld` activo al inspeccionar.
- La ruta conocida de Escritorio es `C:\Users\fadye\OneDrive\Desktop`; el workspace writable de esta sesión es `C:\Users\fadye\OneDrive\Documents\ChatGPT\VERDELIA`.
- El paquete ZIP enumera seis archivos: lanzador, config PDO, schema, bootstrap, router y página de estado. No trae copia SQL independiente.

## Resultados

| Verificación | Estado | Evidencia |
|---|---|---|
| `php -l` para todos los PHP | Aprobado. | Los seis archivos `.php` reportaron `No syntax errors detected` con `C:\xampp\php\php.exe` (PHP 8.2.12). |
| Estructura y archivos base | Aprobado en workspace. | Están presentes configuración, schema, bootstrap, router, index, documentación y directorios de assets. |
| PHP y `pdo_mysql` | Aprobado. | PHP 8.2.12 y módulos `PDO`, `pdo_mysql` visibles. |
| Validación de puerto libre 8088 | Aprobado para disponibilidad. | `scripts/check-port.php 8088` devolvió éxito. |
| Respaldo SQL inicial | Revisión estática aprobada; restauración pendiente. | Contiene DDL para 11 tablas, semilla para 4 categorías, 12 productos, 2 cuentas y 96 pedidos/líneas/pagos históricos. No se pudo importar sin servidor MySQL. |
| Bootstrap + conexión MySQL | No aprobado; entorno bloqueado. | No había proceso `mysqld`; al intentar `scripts/bootstrap.php`, Windows rechazó el socket por permisos antes de poder conectar. El script terminó con código 1 y mensaje legible. No se creó ni modificó ninguna BD. Repetir en Windows con XAMPP/MySQL accesible. |
| Idempotencia (dos ejecuciones) | Pendiente. | Comparar conteos antes/después y confirmar que nuevos pedidos no se pierden. |
| Endpoint de pantalla | No aprobado por restricción del entorno. | Intento con el servidor PHP de prueba falló al enlazar/abrir socket: Windows devolvió “socket ... forbidden by its access permissions”. No es una validación funcional del navegador. |
| Puerto ocupado | Comprobación implementada; prueba manual pendiente. | Ocupar 8088 y comprobar que solicita puerto alternativo. |
| Restauración SQL | Pendiente. | Requiere respaldo generado y MySQL/phpMyAdmin activo. |
| USB con letra distinta / segunda PC | Pendiente. | Copiar carpeta completa, iniciar en otro volumen/equipo y registrar versiones. |
| Git sin secretos | Revisado parcialmente. | `git diff --check` no informó errores; solo `.env.example` está preparado y `.env` está ignorado. Rama activa `codex/fase-0-arquitectura`; no se creó commit ni hay archivos `.env` reales. |

## Guion manual con MySQL disponible

1. Arranca MySQL en XAMPP y confirma host/puerto/credenciales.
2. Ejecuta el validador de sintaxis PHP y resuelve cualquier error.
3. Ejecuta `php scripts/bootstrap.php` dos veces.
4. En phpMyAdmin consulta `SHOW TABLES`, y cuenta categorías/productos/usuarios/pedidos.
5. Arranca el BAT, abre la dirección local, confirma pantalla de estado y detén con Ctrl+C.
6. Importa el respaldo SQL en una instancia de prueba separada, compara conteos y prueba una consulta de productos.
7. Repite desde carpeta copiada a otro volumen y una computadora de bajos recursos; registra resultados reales aquí.
