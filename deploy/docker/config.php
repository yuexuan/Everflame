<?php
// Deployment adapter only: preserve all upstream configuration defaults.
require __DIR__ . '/.config.example.php';
foreach ([
    'db_host' => 'DB_HOST', 'db_database' => 'DB_DATABASE',
    'db_username' => 'DB_USERNAME', 'db_password' => 'DB_PASSWORD',
    'redis_host' => 'REDIS_HOST', 'key' => 'APP_KEY', 'muKey' => 'WEBAPI_KEY',
    'baseUrl' => 'APP_URL',
] as $setting => $variable) {
    $value = getenv($variable);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing deployment variable: ' . $variable);
    }
    $_ENV[$setting] = $value;
}
$_ENV['appName'] = 'Everflame';
$_ENV['theme'] = 'malio';
$_ENV['subUrl'] = $_ENV['baseUrl'];
$_ENV['webAPIUrl'] = $_ENV['baseUrl'];
