# Instalación en computadoras universitarias

Compatibilidad comprobada con **PHP 8.0.28** (la versión fotografiada en URBE) y PHP 8.2.12 para la comprobación del entorno. No hace falta reemplazar XAMPP por una versión anterior. La corrección elimina sintaxis que requería PHP 8.1 y el lanzador ahora revisa los archivos antes de inicializar la base. Evidencia y límites: `docs/COMPATIBILIDAD_PHP.md`.

Para sustituir una copia que mostraba `unexpected identifier "int"` en `CartRepository.php`, detén sus servidores con Ctrl+C, extrae la carpeta completa del ZIP corregido y ejecuta su `INICIAR_VERDELIA.bat`. No mezcles archivos de distintas copias. El ZIP no incluye ni instala PHP/XAMPP; usa el que ya está en la PC.

1. Copia la carpeta completa `VERDELIA` desde el USB a una ubicación permitida fuera de `C:\xampp\htdocs` (o ejecútala directamente desde el USB si el antivirus/políticas lo permiten).
2. Comprueba que XAMPP compatible esté instalado y abre XAMPP Control Panel.
3. Inicia MySQL. Apache no es necesario.
4. El lanzador busca PHP en la carpeta portable `xampp`, `VERDELIA_XAMPP_HOME`/`XAMPP_HOME`, rutas XAMPP habituales de unidades Windows y `PATH`. Para otra ubicación define `VERDELIA_XAMPP_HOME=RUTA\A\xampp` o `VERDELIA_PHP_EXE=RUTA\A\php.exe` en CMD y ejecuta `INICIAR_VERDELIA.bat` desde esa misma consola.
5. El usuario MySQL predeterminado es `root` sin contraseña, solo si coincide con la configuración local de XAMPP. Para otra configuración define `VERDELIA_DB_HOST`, `VERDELIA_DB_PORT`, `VERDELIA_DB_USER` y `VERDELIA_DB_PASSWORD` en CMD antes de arrancar. No cambies cuentas del equipo institucional.
6. La primera ejecución necesita permiso SQL `CREATE DATABASE`; si no está concedido, solicita al administrador de laboratorio una base vacía y permisos limitados, luego configura `VERDELIA_DB_NAME`.
7. El lanzador crea la semilla y abre la dirección local que muestra. Busca automáticamente un puerto libre desde `8088`; puedes fijar uno con `VERDELIA_WEB_PORT`.
8. Usa Ctrl+C para detener PHP y apaga MySQL desde XAMPP cuando termines.

El chat se abre desde la tienda. Prueba el precio o el stock de una planta, una recomendación para poca luz y la tendencia de demanda del Aloe. Con la instalación habitual de XAMPP, la regresión se calcula en PHP. La misma versión admite Python 3 si se configura su ruta antes de arrancar. [Guía del asistente](docs/CHATBOT.md)

El lanzador incluido fija el servidor en `127.0.0.1`, usa `public/` como raíz web y no expone el sitio a la red. Está pensado para una demostración local en esa PC, no para que otras computadoras se conecten ni para producción. Si Apache está activo, no publiques `C:\xampp\htdocs\VERDELIA` como raíz: configura siempre `DocumentRoot` en la subcarpeta `public/`.

## Si el doble clic no abre la consola

Extrae el ZIP completo con **Extraer todo**. En XAMPP inicia **MySQL** y abre **Shell**. Cambia a la carpeta extraída que contiene `INICIAR_VERDELIA.bat` y ejecuta estos comandos. Sustituye la ruta del ejemplo por la de tu carpeta.

```bat
cd /d "C:\ruta\a\VERDELIA"
php scripts\start-local.php
```

Abre la dirección `http://127.0.0.1:8088/` o la que indique la consola. Déjala abierta mientras usas la tienda. Si aparece un error, copia la primera línea que empieza por `ERROR`. Si la PC impide abrir XAMPP Shell o ejecutar PHP, informa del mensaje al encargado del laboratorio.
