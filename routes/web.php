<?php

use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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
