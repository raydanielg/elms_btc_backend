<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-create .env from .env.example if it doesn't exist (for fresh deployments)
$envPath = __DIR__.'/../.env';
$envExamplePath = __DIR__.'/../.env.example';
if (!file_exists($envPath) && file_exists($envExamplePath)) {
    copy($envExamplePath, $envPath);
}

// Ensure APP_KEY is set — generate one if missing (prevents 500 on fresh deploy)
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^APP_KEY=\s*$/m', $envContent)) {
        // Generate a random base64 key (Laravel-compatible)
        $key = 'base64:'.base64_encode(random_bytes(32));
        $envContent = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $envContent);
        file_put_contents($envPath, $envContent);
    }

    // If using sqlite, ensure the database file exists
    if (preg_match('/^DB_CONNECTION=sqlite/m', $envContent)) {
        $sqlitePath = __DIR__.'/../database/database.sqlite';
        if (!file_exists($sqlitePath)) {
            touch($sqlitePath);
        }
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
