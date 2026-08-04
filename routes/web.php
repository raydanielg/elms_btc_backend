<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\InstallerController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Installer routes
Route::get('/installer', [InstallerController::class, 'index']);
Route::get('/installer/requirements', [InstallerController::class, 'requirements']);
Route::get('/installer/database', [InstallerController::class, 'databaseForm']);
Route::post('/installer/install', [InstallerController::class, 'runInstall']);

Route::get('/', function () {
    $dbOk = true;
    try {
        DB::connection()->getPdo();
    } catch (\Exception $e) {
        $dbOk = false;
    }

    return view('status', [
        'appName' => config('app.name'),
        'dbOk' => $dbOk,
        'laravelVersion' => app()->version(),
        'phpVersion' => phpversion(),
        'env' => config('app.env'),
    ]);
});

Route::get('/docs', [ApiDocsController::class, 'index']);
