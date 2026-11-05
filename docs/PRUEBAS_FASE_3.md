# Pruebas de Fase 3 — Autenticación, sesiones y roles

Validación de integración: 2026-11-03. Entorno: PHP 8.2.12, `pdo_mysql`, XAMPP MariaDB 10.4.32, `verdelia_demo`.

## Resultados reales

| Prueba | Resultado |
|---|---|
| `tests/auth_session.php` | Aprobada: 20 aserciones de sesión, CSRF, rotación, expiración por inactividad y límite local. |
| `tests/auth_integration.php` | Aprobada: 18 aserciones contra MariaDB; registro/hash, duplicado, login correcto/incorrecto, perfil, cambio de contraseña, sesión y rol. La transacción terminó con rollback. |
| `tests/http_integration.php` | Aprobada: 32 aserciones HTTP locales compartidas con Fase 4. Incluye registro/login/logout, cambio de contraseña, CSRF, cliente bloqueado en `/admin`, admin temporal permitido y rechazo de `role=ADMIN` en el formulario público. |
| Limpieza | Los registros HTTP temporales se eliminaron con sus carritos; quedaron 2 usuarios demo, 0 carritos, 0 líneas de carrito y 96 pedidos. |

## Comportamiento comprobado

- Registro público guarda hashes con `password_hash()` y siempre asigna `CUSTOMER`, incluso si el formulario agrega `role=ADMIN`.
- Login valida con `password_verify()`, usa mensajes genéricos y rota el ID de sesión. Se verificó el acceso de un administrador temporal y el 403 de un cliente en `/admin`.
- Logout requiere POST con CSRF y deja `/cuenta` protegido.
- El cambio de contraseña exige la clave actual; la clave anterior deja de autenticar y la nueva permite iniciar sesión.
- La cookie de sesión permanece entre peticiones HTTP y el carrito del cliente vuelve a cargarse tras volver a iniciar sesión.
- Las cuentas demo originales no se modificaron. Los usuarios temporales de HTTP usaron prefijo `fase4-http-` y se eliminaron al terminar.

## XAMPP y datos

MariaDB respondió de forma estable tras un reinicio ordenado desde el panel. El fallo previo estaba en metadatos inválidos de replicación guardados en `multi-master.info`; se preservaron en cuarentena durante la reparación, sin borrar bases de datos. La corrección y sus manifiestos se documentaron al resolver el incidente. Los procesos MySQL de Lightning no se tocaron.

No hubo cambios de esquema en Fase 3. La tabla `users` conserva sus 2 cuentas demo. El respaldo de compatibilidad pre-Fase 3 `database/backups/verdelia_20261103_165046.sql` se mantiene.

Para repetir en XAMPP local:

```powershell
C:\xampp\php\php.exe tests\auth_session.php
C:\xampp\php\php.exe tests\auth_integration.php
C:\xampp\php\php.exe tests\http_integration.php
```

El test HTTP requiere que `http://127.0.0.1:8091` sirva el proyecto; el servidor de prueba se inició aparte del lanzador.
