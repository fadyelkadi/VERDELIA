# Instalación en computadoras URBE

VERDELIA está preparado para la versión fotografiada en el laboratorio: **XAMPP 8.0.28-0 para Windows x64, PHP 8.0.28 y Apache 2.4**, con `pdo_mysql` habilitado.

## Inicio

1. Copia o extrae el proyecto completo en una carpeta local. No combines archivos de dos versiones diferentes.
2. Abre XAMPP Control Panel e inicia **MySQL**.
3. Ejecuta `INICIAR_VERDELIA.bat`. Comprueba que informe PHP 8.0.28 y `pdo_mysql` antes de crear o actualizar la base.
4. El lanzador ejecuta `scripts/bootstrap.php` con el PHP de XAMPP y luego levanta **Apache de XAMPP** con `public/` como raíz web, limitado a `127.0.0.1:8088`.
5. Abre `http://localhost:8088/`. Mantén abierta la consola; `Ctrl+C` detiene Apache.

La configuración de Apache se genera temporalmente para el proyecto. No modifica el `httpd.conf` instalado, no publica la carpeta de código en `htdocs` y no usa el servidor web integrado de PHP. Si el proyecto está en otra ubicación, establece `VERDELIA_XAMPP_HOME` con la carpeta raíz de XAMPP antes de ejecutar el BAT.

## Base de datos

La configuración predeterminada usa MySQL local con `root` sin contraseña, si coincide con esa instalación. Para otra cuenta, define `VERDELIA_DB_HOST`, `VERDELIA_DB_PORT`, `VERDELIA_DB_USER`, `VERDELIA_DB_PASSWORD` y, si es necesario, `VERDELIA_DB_NAME`. La carga inicial requiere permiso SQL `CREATE DATABASE`. No cambies cuentas del equipo institucional; si no tienes permisos, pide al administrador una base vacía.

## Errores de inicio

- **Falta `php.exe`, `php8ts.dll`, `php8apache2_4.dll` o `php_pdo_mysql.dll`:** la copia instalada de XAMPP está incompleta; instala o repara la distribución completa XAMPP 8.0.28-0. El lanzador se detiene sin sustituirla por el PHP del sistema.
- **PHP detectado distinto de 8.0.28:** confirma que `VERDELIA_XAMPP_HOME` apunta a la carpeta correcta.
- **MySQL no responde:** inicia el módulo MySQL en el panel XAMPP y confirma host, puerto y credenciales.
- **Apache no puede escuchar en 8088:** cierra la aplicación que ocupa el puerto y vuelve a iniciar el proyecto.
- **Apache rechaza configuración:** copia el primer error de la validación; normalmente indica que la instalación XAMPP no tiene todos los módulos y DLL esperados.

El proyecto usa compras simuladas, no realiza cobros ni despacha productos.
