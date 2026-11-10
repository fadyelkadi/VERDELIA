# VERDELIA

Tienda universitaria ficticia de plantas, macetas y accesorios. Incluye catálogo e inventario MySQL, autenticación, carrito persistente, checkout con pago simulado, administración y analítica de ventas.

## Requisitos

- Windows y PHP 8.0 o posterior con `pdo_mysql` habilitado (se recomienda el PHP de XAMPP).
- MySQL/MariaDB de XAMPP activo, normalmente en `127.0.0.1:3306`.
- El usuario configurado necesita permiso para crear `verdelia_demo` la primera vez.
- Navegador moderno. No se necesita Apache, internet, Node.js, Composer ni instalar el proyecto en `htdocs`.

## Ejecutar

1. Inicia MySQL en XAMPP Control Panel.
2. Haz doble clic en `INICIAR_VERDELIA.bat`.
3. El lanzador selecciona automáticamente un puerto local libre desde 8088; también puedes fijarlo con `VERDELIA_WEB_PORT`.
4. Valida PHP y `pdo_mysql`, inicializa esquema/semilla idempotentemente y abre `http://localhost:<puerto>/`.
5. Detén el servidor con Ctrl+C antes de retirar el USB.

El lanzador sirve únicamente `public/` y escucha en las dos direcciones de loopback (`localhost` por IPv6 y `127.0.0.1` por IPv4), sin exponer la tienda a otras PCs. Guarda la carpeta fuera de `C:\xampp\htdocs` para evitar que Apache exponga por error archivos internos si se activa. No uses `php -S` como servidor de red o de producción; para que otras PCs accedan, configura un servidor PHP real con document root limitado a `public/` y acceso a MySQL.

El lanzador busca XAMPP junto al proyecto, en `VERDELIA_XAMPP_HOME`/`XAMPP_HOME`, en rutas comunes/unidades y finalmente en `PATH`. Si la instalación tiene otra ubicación, define `VERDELIA_XAMPP_HOME` (raíz de XAMPP) o `VERDELIA_PHP_EXE` (ruta a `php.exe`) en CMD. Para cambiar conexión, define `VERDELIA_DB_HOST`, `VERDELIA_DB_PORT`, `VERDELIA_DB_NAME`, `VERDELIA_DB_USER` y `VERDELIA_DB_PASSWORD`. El archivo `.env.example` es una plantilla documental; Windows no lo carga por sí solo. No pongas claves en Git ni en argumentos de comandos.

Para iniciar el servidor sin abrir otra pestaña (por ejemplo, desde una consola de prueba), define `VERDELIA_OPEN_BROWSER=0` antes de ejecutar el lanzador. El valor predeterminado abre la dirección local automáticamente.

## Base de demostración

Nombre: `verdelia_demo`. El bootstrap conserva 12 tablas, 4 categorías, 12 productos, 2 cuentas demo y 96 pedidos históricos. Credenciales de prueba:

| Rol | Usuario | Contraseña |
| --- | --- | --- |
| Administrador | `admin@verdelia.test` | `123456789` |
| Cliente | `cliente@verdelia.test` | `VerdeliaCliente2026!` |

Son cuentas y claves públicas destinadas exclusivamente a la demostración local. No uses estos datos en producción ni en sistemas con información real. El bootstrap no cambia las claves de cuentas que ya existen; para instalaciones creadas antes de este cambio, la contraseña inicial del administrador puede seguir siendo la anterior hasta actualizarla en esa base de datos. Los pedidos y pagos son simulados, sin dinero real.

El esquema y la semilla viven en `database/schema.sql` y `scripts/bootstrap.php`. El bootstrap no elimina tablas ni reinicia datos existentes. Tras inicializar MySQL, genera una exportación completa, con nombre de fecha/hora y sin sobrescribir respaldos, ejecutando `php scripts/backup.php`. Consulta [Restaurar base de datos](RESTAURAR_BASE_DATOS.md) y [las decisiones](docs/DECISIONES.md) antes de importar o actualizar manualmente.

## Estructura

- `public/`: único document root; la configuración, scripts y base de datos quedan fuera del servidor web.
- `config/`: valores locales para PDO.
- `database/`: esquema y copias de seguridad.
- `scripts/`: inicialización y utilidades CLI.
- `docs/`: decisiones, fases y procedimientos.
- `tests/`: pruebas de modelos matemáticos, integración MariaDB/HTTP y restauración aislada.

## Estado y limitaciones

Inicio, Tienda, filtros, orden, búsqueda, paginación y fichas consultan MySQL. El carrito se valida en el servidor y persiste para clientes autenticados; consultar o editarlo no descuenta stock. El checkout crea pedidos persistentes y ofrece resultados de pago simulados, sin cobros reales. ADMIN gestiona productos, categorías, imágenes locales, inventario y pedidos; consulta clientes en `/admin` y revisa métricas/modelos en `/admin/analitica`. Las ventas usan importes y costos históricos guardados por línea; la ganancia es bruta estimada y no descuenta gastos, impuestos ni operación.

Consulta [la guía de presentación](docs/GUIA_PRESENTACION_URBE.md), [la matriz de requisitos](docs/REQUISITOS_PROFESOR.md), [las pruebas finales](docs/PRUEBAS_FINALES.md), [el progreso](docs/PROGRESO.md) y [la restauración](RESTAURAR_BASE_DATOS.md). El arranque requiere MySQL/MariaDB compatible en cada PC. Ejecutar XAMPP desde USB no está garantizado en cualquier equipo; esta entrega no copia ni instala XAMPP.
