<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Laporan via token (sebelum /toko agar tidak bentrok)
Route::get('/laporan/{token}', [StorefrontController::class, 'laporan'])->name('storefront.laporan');

// Storefront toko
Route::prefix('toko/{slug}')->name('storefront.')->group(function () {
    Route::get('/',                  [StorefrontController::class, 'toko'])->name('toko');
    Route::get('/produk/{produkId}', [StorefrontController::class, 'produk'])->name('produk');
    Route::get('/order',             [StorefrontController::class, 'formOrder'])->name('order');
    Route::post('/order',            [StorefrontController::class, 'submitOrder'])->name('submitOrder');
    Route::get('/sukses/{order}',    [StorefrontController::class, 'sukses'])->name('sukses');
});
