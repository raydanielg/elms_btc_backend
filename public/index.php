<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-create .env from .env.example if it doesn't exist (for fresh deployments)
$basePath = __DIR__.'/..';
$envPath = $basePath.'/.env';
$envExamplePath = $basePath.'/.env.example';
if (!file_exists($envPath) && file_exists($envExamplePath)) {
    copy($envExamplePath, $envPath);
}

// Ensure APP_KEY is set — generate one if missing (prevents 500 on fresh deploy)
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^APP_KEY=\s*$/m', $envContent)) {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $envContent = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $envContent);
        file_put_contents($envPath, $envContent);
    }

    // If using sqlite, ensure the database file exists
    if (preg_match('/^DB_CONNECTION=sqlite/m', $envContent)) {
        $sqlitePath = $basePath.'/database/database.sqlite';
        if (!file_exists($sqlitePath)) {
            touch($sqlitePath);
        }
    }
}

// Ensure storage directories exist (prevents 500 on fresh deploy)
$storageDirs = [
    'storage/app',
    'storage/app/public',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];
foreach ($storageDirs as $dir) {
    $fullPath = $basePath.'/'.$dir;
    if (!is_dir($fullPath)) {
        @mkdir($fullPath, 0775, true);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
