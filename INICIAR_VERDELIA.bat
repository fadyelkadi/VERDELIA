@echo off
setlocal
cd /d "%~dp0"

set "PHP_EXE="
if exist "%~dp0xampp\php\php.exe" set "PHP_EXE=%~dp0xampp\php\php.exe"
if not defined PHP_EXE if defined VERDELIA_XAMPP_HOME if exist "%VERDELIA_XAMPP_HOME%\php\php.exe" set "PHP_EXE=%VERDELIA_XAMPP_HOME%\php\php.exe"
if not defined PHP_EXE if defined XAMPP_HOME if exist "%XAMPP_HOME%\php\php.exe" set "PHP_EXE=%XAMPP_HOME%\php\php.exe"
if not defined PHP_EXE if defined VERDELIA_PHP_EXE if exist "%VERDELIA_PHP_EXE%" set "PHP_EXE=%VERDELIA_PHP_EXE%"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\Program Files\xampp\php\php.exe" set "PHP_EXE=C:\Program Files\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\Program Files (x86)\xampp\php\php.exe" set "PHP_EXE=C:\Program Files (x86)\xampp\php\php.exe"
for %%D in (D E F G H I J K L M N O P Q R S T U V W X Y Z) do if not defined PHP_EXE if exist "%%D:\xampp\php\php.exe" set "PHP_EXE=%%D:\xampp\php\php.exe"
if not defined PHP_EXE for %%P in (php.exe) do set "PHP_EXE=%%~$PATH:P"
if not defined PHP_EXE (
  echo ERROR: No se encontro PHP. Instala XAMPP o define VERDELIA_PHP_EXE.
  goto :error
)

"%PHP_EXE%" -r "if(PHP_VERSION_ID<80000){fwrite(STDERR,'PHP 8.0+ requerido. Detectado: '.PHP_VERSION.PHP_EOL);exit(1);} if(!extension_loaded('pdo_mysql')){fwrite(STDERR,'Falta la extension pdo_mysql en este PHP.'.PHP_EOL);exit(2);} echo 'PHP '.PHP_VERSION.' y pdo_mysql: OK'.PHP_EOL;"
if errorlevel 1 goto :error

if exist "%~dp0xampp\mysql_start.bat" (
  echo Intentando iniciar MySQL desde la carpeta xampp del USB...
  start "Verdelia MySQL" /min cmd /c ""%~dp0xampp\mysql_start.bat""
  timeout /t 4 /nobreak >nul
)

echo.
echo Verificando MySQL e inicializando VERDELIA...
"%PHP_EXE%" "%~dp0scripts\bootstrap.php"
if errorlevel 1 goto :error

set "PORT=8088"
if defined VERDELIA_WEB_PORT goto :configured_port
set "PORT="
for /f "delims=" %%P in ('"%PHP_EXE%" "%~dp0scripts\find-port.php" 8088') do set "PORT=%%P"
if not defined PORT (
  echo ERROR: No se encontro un puerto libre. Define VERDELIA_WEB_PORT para indicar uno.
  goto :error
)
goto :port_ready

:configured_port
set "PORT=%VERDELIA_WEB_PORT%"
"%PHP_EXE%" "%~dp0scripts\check-port.php" %PORT%
if errorlevel 1 goto :error

:port_ready
if not defined PORT (
  echo ERROR: No se pudo seleccionar un puerto local.
  goto :error
)

echo.
echo VERDELIA iniciara en http://127.0.0.1:%PORT%/
echo Solo accesible desde esta computadora. Deten el servidor con Ctrl+C.
if /I not "%VERDELIA_OPEN_BROWSER%"=="0" start "" "http://127.0.0.1:%PORT%/"
"%PHP_EXE%" -S 127.0.0.1:%PORT% -t "%~dp0public" "%~dp0public\router.php"
if errorlevel 1 echo ERROR: El servidor PHP se detuvo con un error.
pause
exit /b 1

:error
echo.
echo No se inicio VERDELIA. Revisa XAMPP, MySQL y los mensajes anteriores.
pause
exit /b 1
