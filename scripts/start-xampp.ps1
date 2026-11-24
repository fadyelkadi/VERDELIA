param(
    [Parameter(Mandatory = $true)]
    [string]$XamppRoot
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$XamppRoot = [System.IO.Path]::GetFullPath($XamppRoot)
$Php = Join-Path $XamppRoot 'php\php.exe'
$ApacheRoot = Join-Path $XamppRoot 'apache'
$Httpd = Join-Path $ApacheRoot 'bin\httpd.exe'
$HttpdConfig = Join-Path $ApacheRoot 'conf\httpd.conf'
$Port = 8088
$TempConfig = Join-Path $env:TEMP ('verdelia-httpd-' + [guid]::NewGuid().ToString('N') + '.conf')

function Stop-WithError([string]$Message) {
    [Console]::Error.WriteLine("ERROR: $Message")
    exit 1
}

if ($env:VERDELIA_WEB_PORT) {
    $ParsedPort = 0
    if (![int]::TryParse($env:VERDELIA_WEB_PORT, [ref]$ParsedPort) -or $ParsedPort -lt 1024 -or $ParsedPort -gt 65535) {
        Stop-WithError 'VERDELIA_WEB_PORT debe ser un puerto entre 1024 y 65535.'
    }
    $Port = $ParsedPort
}

if (!(Test-Path -LiteralPath $Php -PathType Leaf)) {
    Stop-WithError "No existe $Php. La instalación XAMPP indicada está incompleta; se requiere el PHP de XAMPP 8.0.28."
}
if (!(Test-Path -LiteralPath $Httpd -PathType Leaf) -or !(Test-Path -LiteralPath $HttpdConfig -PathType Leaf)) {
    Stop-WithError "No se encontró Apache en $ApacheRoot. Instala XAMPP 8.0.28 completo."
}
foreach ($RequiredFile in @(
    (Join-Path $XamppRoot 'php\php8ts.dll'),
    (Join-Path $XamppRoot 'php\php8apache2_4.dll'),
    (Join-Path $XamppRoot 'php\ext\php_pdo_mysql.dll')
)) {
    if (!(Test-Path -LiteralPath $RequiredFile -PathType Leaf)) {
        Stop-WithError "Falta el componente requerido $RequiredFile. Repara o reinstala XAMPP 8.0.28 completo."
    }
}

$Version = (& $Php -r 'echo PHP_VERSION;' 2>&1 | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $Version -ne '8.0.28') {
    Stop-WithError "Se esperaba PHP 8.0.28 de XAMPP y se detectó '$Version'."
}
$PhpModules = (& $Php -m 2>&1 | Out-String)
if ($LASTEXITCODE -ne 0 -or $PhpModules -notmatch '(?im)^pdo_mysql\s*$') {
    Stop-WithError "El PHP de XAMPP 8.0.28 no carga pdo_mysql. Revisa php.ini y la carpeta php\ext."
}

Write-Host 'PHP 8.0.28 y pdo_mysql: OK.'
& $Php (Join-Path $ProjectRoot 'scripts\check-runtime.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Host 'Verificando MySQL e inicializando VERDELIA...'
& $Php (Join-Path $ProjectRoot 'scripts\bootstrap.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$WebRoot = (Resolve-Path (Join-Path $ProjectRoot 'public')).Path.Replace('\', '/')
$Config = Get-Content -LiteralPath $HttpdConfig -Raw
$Config = [regex]::Replace($Config, '(?m)^\s*Listen\s+.*$', { param($m) '# ' + $m.Value })
$Config += "`r`nListen 127.0.0.1:$Port`r`n"
$Config = [regex]::Replace($Config, '(?m)^\s*DocumentRoot\s+"[^"]+"\s*$', ('DocumentRoot "' + $WebRoot + '"'), 1)
$Config = [regex]::Replace($Config, '(?m)^\s*ServerName\s+[^\r\n]+', "ServerName localhost:$Port", 1)
$Config = [regex]::Replace($Config, '(?im)^\s*Include\s+"?conf/extra/httpd-ssl\.conf"?\s*$', '# SSL disabled for the local VERDELIA instance.')
$Config = [regex]::Replace($Config, '(?im)^\s*Include\s+"?conf/extra/httpd-verdelia\.conf"?\s*$', '# Existing machine-specific VERDELIA include disabled in this isolated config.')

$DirectoryPattern = '(?s)<Directory\s+"[^"]*htdocs"\s*>(.*?)</Directory>'
$DirectoryMatch = [regex]::Match($Config, $DirectoryPattern)
if (!$DirectoryMatch.Success) {
    Stop-WithError 'No se pudo adaptar DocumentRoot desde la configuración de Apache de XAMPP.'
}
$DirectoryBody = $DirectoryMatch.Groups[1].Value
$DirectoryBody = [regex]::Replace($DirectoryBody, '(?im)^\s*Options\s+.*$', '    Options -Indexes +FollowSymLinks')
$DirectoryBody = [regex]::Replace($DirectoryBody, '(?im)^\s*Require\s+all\s+granted\s*$', '    Require local')
$DirectoryBlock = '<Directory "' + $WebRoot + '">' + $DirectoryBody + "`r`n</Directory>"
$Config = [regex]::Replace($Config, $DirectoryPattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $DirectoryBlock }, 1)

$UploadRoot = (Join-Path $ProjectRoot 'public\uploads').Replace('\', '/')
$Config += @"

<Directory "$UploadRoot">
    Options -Indexes
    SetHandler None
    php_admin_flag engine Off
    Require local
</Directory>
"@

Set-Content -LiteralPath $TempConfig -Value $Config -Encoding Ascii
try {
    Write-Host 'Validando configuración aislada de Apache...'
    & $Httpd -t -f $TempConfig -d $ApacheRoot
    if ($LASTEXITCODE -ne 0) {
        Stop-WithError 'Apache rechazó la configuración. Copia el primer error para revisar XAMPP.'
    }
    Write-Host "VERDELIA escuchará solo en este equipo: http://localhost:$Port/"
    Write-Host 'Mantén esta ventana abierta. Para detener Apache, presiona Ctrl+C.'
    & $Httpd -f $TempConfig -d $ApacheRoot
    exit $LASTEXITCODE
}
finally {
    Remove-Item -LiteralPath $TempConfig -Force -ErrorAction SilentlyContinue
}
