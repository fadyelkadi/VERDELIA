# Compatibilidad con PHP del laboratorio URBE

Revisión: 2026-11-10 (America/Caracas).

Las fotografías muestran PHP 8.0.28, `pdo_mysql` disponible, bootstrap exitoso y servidor local iniciado. El fallo posterior `unexpected identifier "int", expecting variable` en `app/CartRepository.php:6` se reproduce con esa versión al leer `public readonly int $httpStatus`. `readonly` y el tipo de retorno `never` se introdujeron en PHP 8.1. No era un fallo del puerto o de la creación de la base de datos.

## Corrección

- Las excepciones de carrito, pedidos y administración usan propiedades tipadas compatibles con PHP 8.0.
- Los métodos que finalizan la respuesta HTTP usan `void` y conservan sus llamadas a `exit`.
- `INICIAR_VERDELIA.bat` ejecuta `scripts/check-runtime.php`: valida PHP mínimo 8.0, `pdo_mysql` y sintaxis de los 42 archivos de ejecución antes del bootstrap.
- El runtime oficial PHP 8.0.28 usado para pruebas permanece en `storage/compat/`, excluido de Git y del paquete de entrega. No se cambió la instalación XAMPP.

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

Las integraciones reconstruyeron bases temporales desde un respaldo y las eliminaron al finalizar. Los tests HTTP fijan la contraseña de prueba del administrador solo en esas copias, para no depender de la contraseña guardada en un respaldo histórico. No modifican las cuentas de la base habitual.

La comprobación local reproduce la versión PHP de las fotos; no demuestra que las políticas, permisos y configuración de todas las PCs URBE sean idénticos. El último paso es ejecutar el paquete corregido en la PC elegida, con MySQL activo. No se garantiza cualquier versión anterior a PHP 8.0.

Referencia oficial: https://www.php.net/manual/en/migration81.new-features.php (readonly y never).
