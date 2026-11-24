# VERDELIA

Tienda de plantas, macetas y accesorios. Incluye catálogo e inventario MySQL, autenticación, carrito persistente, checkout con pago simulado, administración y analítica de ventas.

## Requisitos

- Windows y **XAMPP 8.0.28** completo (PHP 8.0.28, Apache y `pdo_mysql`).
- MySQL de XAMPP activo, normalmente en `127.0.0.1:3306`.
- El usuario configurado necesita permiso para crear `verdelia_demo` la primera vez.
- Navegador moderno. No se necesita internet, Node.js ni Composer.

## Ejecutar

1. Inicia MySQL en XAMPP Control Panel.
2. Haz doble clic en `INICIAR_VERDELIA.bat`.
3. El lanzador valida la versión exacta de PHP, inicializa la base y arranca Apache de XAMPP con una configuración aislada en el puerto 8088.
4. Abre `http://localhost:8088/`.
5. Detén Apache con Ctrl+C antes de retirar el USB.

Apache sirve únicamente `public/` y escucha en `127.0.0.1:8088`; la tienda no queda expuesta a otras PCs. La configuración temporal se deriva del `httpd.conf` de XAMPP y no modifica sus archivos instalados. El lanzador no utiliza el servidor PHP integrado.

Si XAMPP está en otra ubicación, define `VERDELIA_XAMPP_HOME` (raíz de XAMPP) antes de ejecutar el BAT. Para cambiar conexión, define `VERDELIA_DB_HOST`, `VERDELIA_DB_PORT`, `VERDELIA_DB_NAME`, `VERDELIA_DB_USER` y `VERDELIA_DB_PASSWORD`. El archivo `.env.example` es una plantilla documental; Windows no lo carga por sí solo. No pongas claves en Git ni en argumentos de comandos.

Si el puerto 8088 está ocupado, cierra el proceso local que lo usa antes de iniciar VERDELIA.

## Asistente de la tienda

Abre el chat en la tienda y prueba «¿Cuánto cuesta la Monstera?», «¿Hay stock de Sansevieria?», «Recomiéndame una planta para poca luz y de cuidado fácil» o «¿Cuál es la tendencia de demanda del Aloe?».

El asistente consulta precios y existencias del catálogo. Ordena las recomendaciones por grados de compatibilidad con la luz disponible y usa la regresión lineal sobre unidades vendidas para estimar la demanda mensual por planta. [Guía del asistente](docs/CHATBOT.md)

### Configuración opcional de Python

Desde CMD, con MySQL activo, configura la ruta a Python antes de ejecutar el lanzador.

```bat
set "VERDELIA_PYTHON_EXE=C:\ruta\a\python.exe"
INICIAR_VERDELIA.bat
```

El ZIP incluye el script de Python. En XAMPP 8.0.28, el cálculo se ejecuta en PHP cuando Python no está configurado o no responde.

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
