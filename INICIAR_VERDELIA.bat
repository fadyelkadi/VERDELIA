@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0"
if errorlevel 1 goto :error

set "XAMPP_ROOT=%VERDELIA_XAMPP_HOME%"
if not defined XAMPP_ROOT set "XAMPP_ROOT=%XAMPP_HOME%"
if not defined XAMPP_ROOT set "XAMPP_ROOT=C:\xampp"

echo Iniciando VERDELIA con Apache y PHP de XAMPP 8.0.28...
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-xampp.ps1" -XamppRoot "%XAMPP_ROOT%"
if errorlevel 1 goto :error
exit /b 0

:error
echo.
echo No se inicio VERDELIA. Revisa los requisitos de XAMPP mostrados arriba.
pause
exit /b 1
