<?php
declare(strict_types=1);

// Comprobar el proyecto completo antes de inicializar la BD o abrir el navegador.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
if (PHP_VERSION_ID < 80000) {
    fwrite(STDERR, 'ERROR: Se requiere PHP 8.0 o superior. Detectado: '.PHP_VERSION.PHP_EOL);
    exit(1);
}
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, 'ERROR: Falta pdo_mysql. PHP usado: '.PHP_BINARY.PHP_EOL);
    exit(1);
}
if (!function_exists('token_get_all')) {
    fwrite(STDERR, 'ERROR: Falta tokenizer para comprobar la sintaxis antes del arranque.'.PHP_EOL);
    exit(1);
}
$root = dirname(__DIR__);
$checked = 0;
try {
    foreach (['app','public','config','scripts'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            if ($source === false) throw new RuntimeException('No se pudo leer '.$file->getPathname());
            try {
                token_get_all($source, TOKEN_PARSE);
            } catch (ParseError $error) {
                throw new RuntimeException($file->getPathname().': '.$error->getMessage(), 0, $error);
            }
            $checked++;
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: Proyecto incompatible o incompleto para PHP '.PHP_VERSION.': '.$error->getMessage().PHP_EOL);
    exit(1);
}
echo 'PHP '.PHP_VERSION.' y pdo_mysql: OK. Sintaxis comprobada en '.$checked.' archivos.'.PHP_EOL;
