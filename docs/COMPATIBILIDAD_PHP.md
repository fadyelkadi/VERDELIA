# Compatibilidad con PHP del laboratorio URBE

Revisión: 2026-11-24 (America/Caracas).

Estado del equipo local (2026-11-24): XAMPP Control Panel identifica XAMPP `8.0.28-0`; PHP 8.0.28, Apache 2.4.56 y MariaDB 10.4.28 coinciden con la distribución de URBE observada. La copia `C:\xampp\php` tenía archivos PHP y extensiones ausentes; se restauraron desde la copia local del mismo build y se corrigieron los nombres de `openssl` y `ftp` en `php.ini`. PHP carga `pdo_mysql` sin avisos y `httpd.exe -t` pasa. No fue necesario borrar ni instalar otra distribución; no se encontró el instalador en la carpeta Descargas.

LocalWP está activo y Nginx ocupa los puertos `80` y `443`; eso impide arrancar Apache de XAMPP en esos puertos. VERDELIA usa una configuración temporal de Apache que escucha solo en `127.0.0.1:8088`, por lo que no compite con LocalWP. El servidor MySQL de XAMPP usa `127.0.0.1:3306`.

MariaDB registró un formato incorrecto en la tabla de permisos `mysql.db`. Se restauró esa tabla desde el respaldo incluido en el mismo XAMPP 8.0.28. Antes se conservó una copia completa de `mysql\data`; la base `verdelia_demo` se mantuvo y el bootstrap confirmó que la semilla histórica se conserva.

En una terminal normal, el comando global `php` resuelve a PHP 8.3.33 de WinGet. El lanzador no usa ese ejecutable: valida y ejecuta explícitamente `C:\xampp\php\php.exe` para mantener la versión de URBE.

Como evidencia histórica, las fotografías compartidas desde URBE mostraban PHP 8.0.28, `pdo_mysql`, bootstrap exitoso y un servidor iniciado en esa PC. El fallo posterior `unexpected identifier "int", expecting variable` en `app/CartRepository.php:6` se reprodujo con esa versión al leer `public readonly int $httpStatus`. `readonly` y el tipo de retorno `never` se introdujeron en PHP 8.1. Esa captura no confirma que el XAMPP instalado ahora en esta PC esté completo.

## Corrección

- Las excepciones de carrito, pedidos y administración usan propiedades tipadas compatibles con PHP 8.0.
- Los métodos que finalizan la respuesta HTTP usan `void` y conservan sus llamadas a `exit`.
- `INICIAR_VERDELIA.bat` ejecuta `scripts/check-runtime.php`: valida PHP mínimo 8.0, `pdo_mysql` y sintaxis de los 42 archivos de ejecución antes del bootstrap.
- El lanzador usa Apache y PHP de XAMPP en el puerto local `8088`; no usa el servidor web integrado de PHP.

## Comprobaciones ejecutadas

| Comprobación con PHP 8.0.28 | Resultado |
|---|---|
| Error original en las tres excepciones | Reproducido antes de corregir |
| Sintaxis de los 55 PHP del proyecto | Aprobada después de corregir |
| Preflight: archivos válidos, archivo incompatible y ausencia de pdo_mysql | Aprobado; informa el archivo o extensión que falla |
| Carga de clases y contratos de excepciones | Aprobada |
| Modelos matemáticos | 27 aserciones aprobadas |
| Sesiones, CSRF y control de intentos | 20 aserciones aprobadas |
| Integración analítica MariaDB aislada | 32 aserciones aprobadas |
| Administración HTTP aislada | 27 aserciones aprobadas |
| Compra HTTP aislada | 32 aserciones aprobadas; rechazo, aprobación, cancelación, carrito, recibo e historial |
| Inicio, login y carrito por HTTP | Respuesta 200 sin errores de sintaxis |
| PHP 8.0.28 y `pdo_mysql` de `C:\xampp` | Aprobado, sin avisos de carga de extensiones |
| Configuración Apache de XAMPP | `httpd.exe -t`: `Syntax OK` |
| Inicio de VERDELIA mediante Apache de XAMPP | Bootstrap completado; página de inicio HTTP 200 en `127.0.0.1:8088` |
| Recomendaciones del chatbot | 170 aserciones aprobadas con PHP de XAMPP 8.0.28 |

Las integraciones reconstruyeron bases temporales desde un respaldo y las eliminaron al finalizar. Los tests HTTP fijan la contraseña de prueba del administrador solo en esas copias, para no depender de la contraseña guardada en un respaldo histórico. No modifican las cuentas de la base habitual.

La comprobación local reproduce la versión PHP de las fotos; no demuestra que las políticas, permisos y configuración de todas las PCs URBE sean idénticos. El arranque desde el lanzador ya se verificó en esta PC; la comprobación física en una PC URBE sigue pendiente. No se garantiza cualquier versión anterior a PHP 8.0.

Referencia oficial: https://www.php.net/manual/en/migration81.new-features.php (readonly y never).
