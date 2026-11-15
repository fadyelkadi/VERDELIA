# VERDELIA

Tienda de plantas, macetas y accesorios. Incluye catálogo e inventario MySQL, autenticación, carrito persistente, checkout con pago simulado, administración y analítica de ventas.

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

## Asistente de la tienda

Abre el chat en la tienda y prueba «¿Cuánto cuesta la Monstera?», «¿Hay stock de Sansevieria?», «Recomiéndame una planta para poca luz y de cuidado fácil» o «¿Cuál es la tendencia de demanda del Aloe?».

El asistente consulta precios y existencias del catálogo. Ordena las recomendaciones por grados de compatibilidad con la luz disponible y usa la regresión lineal sobre unidades vendidas para estimar la demanda mensual por planta. [Guía del asistente](docs/CHATBOT.md)

### Configuración opcional de Python

Desde CMD, con MySQL activo, configura la ruta a Python antes de ejecutar el lanzador.

```bat
set "VERDELIA_PYTHON_EXE=C:\ruta\a\python.exe"
INICIAR_VERDELIA.bat
```

El ZIP incluye el script de Python. En las PCs con XAMPP, el cálculo se ejecuta en PHP cuando Python no está configurado o no responde.

## Base local y cuentas de prueba

Nombre: `verdelia_demo`. Al arrancar con una base vacía, VERDELIA crea 12 tablas, 4 categorías, 12 productos y un historial simulado de 13 meses completos más la parte transcurrida del mes actual. Los períodos completos alimentan la regresión. Una base ya inicializada conserva sus pedidos. Credenciales de prueba:

| Rol | Usuario | Contraseña |
| --- | --- | --- |
| Administrador | `admin@verdelia.test` | `123456789` |
| Cliente | `cliente@verdelia.test` | `VerdeliaCliente2026!` |

Son cuentas y claves públicas para las pruebas locales. No uses estos datos en producción ni en sistemas con información real. El bootstrap no cambia las claves de cuentas que ya existen; para instalaciones creadas antes de este cambio, la contraseña inicial del administrador puede seguir siendo la anterior hasta actualizarla en esa base de datos. Los pedidos y pagos son simulados, sin dinero real.

El esquema y la semilla viven en `database/schema.sql` y `scripts/bootstrap.php`. El bootstrap no elimina tablas ni reinicia datos existentes. Tras inicializar MySQL, genera una exportación completa, con nombre de fecha/hora y sin sobrescribir respaldos, ejecutando `php scripts/backup.php`. Consulta [Restaurar base de datos](RESTAURAR_BASE_DATOS.md) y [las decisiones](docs/DECISIONES.md) antes de importar o actualizar manualmente.

## Estructura

- `public/`: único document root; la configuración, scripts y base de datos quedan fuera del servidor web.
- `config/`: valores locales para PDO.
- `database/`: esquema y copias de seguridad.
- `scripts/`: inicialización y utilidades CLI.
- `docs/`: decisiones, fases y procedimientos.
- `tests/`: pruebas de modelos matemáticos, integración MariaDB/HTTP y restauración aislada.

## Estado y limitaciones

Inicio, Tienda, filtros, orden, búsqueda, paginación y fichas consultan MySQL. El carrito se valida en el servidor y persiste para clientes autenticados; consultar o editarlo no descuenta stock. El checkout crea pedidos persistentes y ofrece resultados de pago simulados con recibo imprimible, sin cobros reales. ADMIN gestiona productos, categorías, imágenes locales, inventario y pedidos; consulta clientes en `/admin` y revisa métricas/modelos en `/admin/analitica`. Allí puede elegir un producto y ajustar regresiones lineal y exponencial sobre sus ingresos o ganancia bruta estimada. La ganancia no descuenta gastos, impuestos ni operación; el ajuste exponencial requiere valores positivos.

Consulta la [guía de restauración](RESTAURAR_BASE_DATOS.md) y la [referencia de compatibilidad con PHP](docs/COMPATIBILIDAD_PHP.md). El arranque requiere MySQL/MariaDB compatible en cada PC. Ejecutar XAMPP desde USB no está garantizado en cualquier equipo; esta entrega no copia ni instala XAMPP.
