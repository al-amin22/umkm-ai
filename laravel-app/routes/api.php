<?php

use App\Http\Controllers\WAController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WA Service Webhook Routes
|--------------------------------------------------------------------------
| Semua route ini divalidasi oleh middleware ValidateWASecret
| yang memeriksa header X-WA-Secret atau field "secret" di body.
*/

Route::middleware('wa.secret')->prefix('wa')->group(function () {

    // Entry point utama — semua pesan masuk dari WA
    Route::post('/incoming', [WAController::class, 'handle']);

    // Status koneksi dari wa-service (connected / logged_out)
    Route::post('/status', [WAController::class, 'status']);

    // Heartbeat setiap 5 menit dari wa-service
    Route::post('/heartbeat', [WAController::class, 'heartbeat']);
});
