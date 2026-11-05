# Instalación en computadoras universitarias

1. Copia la carpeta completa `VERDELIA` desde el USB a una ubicación permitida (o ejecútala directamente desde el USB si el antivirus/políticas lo permiten).
2. Comprueba que XAMPP compatible esté instalado y abre XAMPP Control Panel.
3. Inicia MySQL. Apache no es necesario.
4. El lanzador busca PHP en la carpeta portable `xampp`, `VERDELIA_XAMPP_HOME`/`XAMPP_HOME`, rutas XAMPP habituales de unidades Windows y `PATH`. Para otra ubicación define `VERDELIA_XAMPP_HOME=RUTA\A\xampp` o `VERDELIA_PHP_EXE=RUTA\A\php.exe` en CMD y ejecuta `INICIAR_VERDELIA.bat` desde esa misma consola.
5. El usuario MySQL predeterminado es `root` sin contraseña, solo si coincide con la configuración local de XAMPP. Para otra configuración define `VERDELIA_DB_HOST`, `VERDELIA_DB_PORT`, `VERDELIA_DB_USER` y `VERDELIA_DB_PASSWORD` en CMD antes de arrancar. No cambies cuentas del equipo institucional.
6. La primera ejecución necesita permiso SQL `CREATE DATABASE`; si no está concedido, solicita al administrador de laboratorio una base vacía y permisos limitados, luego configura `VERDELIA_DB_NAME`.
7. El lanzador crea la semilla y abre la dirección local que muestra. Busca automáticamente un puerto libre desde `8088`; puedes fijar uno con `VERDELIA_WEB_PORT`.
8. Usa Ctrl+C para detener PHP y apaga MySQL desde XAMPP cuando termines.

No se afirma compatibilidad con todas las PCs: hay que ejecutar el procedimiento en la computadora de exposición y registrar versión PHP, MariaDB y cualquier política que impida iniciar procesos.
