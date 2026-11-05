<?php
declare(strict_types=1);
// SOLO DEMOSTRACION LOCAL. Para otras configuraciones, definir variables de entorno.
return [
    'host' => getenv('VERDELIA_DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('VERDELIA_DB_PORT') ?: '3306'),
    'database' => getenv('VERDELIA_DB_NAME') ?: 'verdelia_demo',
    'user' => getenv('VERDELIA_DB_USER') ?: 'root',
    'password' => getenv('VERDELIA_DB_PASSWORD') !== false ? getenv('VERDELIA_DB_PASSWORD') : '',
];
