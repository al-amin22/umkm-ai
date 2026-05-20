<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LanggananController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\PelangganController;
use App\Http\Controllers\Admin\PesananController;
use App\Http\Controllers\Admin\ProdukController;
use App\Http\Controllers\Admin\StokController;
use App\Http\Controllers\Admin\TokoController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('landing');

// ── Admin Auth ────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login',     [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
    Route::get('/register',  [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10');

    // Protected admin routes
    Route::middleware(['auth', 'admin.shop'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Produk
        Route::prefix('produk')->name('produk.')->group(function () {
            Route::get('/',         [ProdukController::class, 'index'])->name('index');
            Route::get('/create',   [ProdukController::class, 'create'])->name('create');
            Route::post('/',        [ProdukController::class, 'store'])->name('store');
            Route::get('/{id}/edit',[ProdukController::class, 'edit'])->name('edit');
            Route::put('/{id}',     [ProdukController::class, 'update'])->name('update');
            Route::delete('/{id}',  [ProdukController::class, 'destroy'])->name('destroy');
        });

        // Langganan
        Route::prefix('langganan')->name('langganan.')->group(function () {
            Route::get('/',          [LanggananController::class, 'index'])->name('index');
            Route::post('/checkout', [LanggananController::class, 'checkout'])->name('checkout');
        });

        // Pelanggan
        Route::prefix('pelanggan')->name('pelanggan.')->group(function () {
            Route::get('/',      [PelangganController::class, 'index'])->name('index');
            Route::get('/{id}',  [PelangganController::class, 'show'])->name('show');
        });

        // Toko Settings
        Route::prefix('toko')->name('toko.')->group(function () {
            Route::get('/edit',  [TokoController::class, 'edit'])->name('edit');
            Route::put('/edit',  [TokoController::class, 'update'])->name('update');
        });

        // Laporan
        Route::prefix('laporan')->name('laporan.')->group(function () {
            Route::get('/',       [LaporanController::class, 'index'])->name('index');
            Route::get('/csv',    [LaporanController::class, 'exportCsv'])->name('csv');
            Route::get('/cetak',  [LaporanController::class, 'cetak'])->name('cetak');
        });

        // Pesanan
        Route::prefix('pesanan')->name('pesanan.')->group(function () {
            Route::get('/',                     [PesananController::class, 'index'])->name('index');
            Route::get('/{id}',                 [PesananController::class, 'show'])->name('show');
            Route::get('/{id}/invoice',         [PesananController::class, 'invoice'])->name('invoice');
            Route::post('/{id}/konfirmasi',     [PesananController::class, 'konfirmasi'])->name('konfirmasi');
            Route::post('/{id}/kirim',          [PesananController::class, 'kirim'])->name('kirim');
            Route::post('/{id}/selesai',        [PesananController::class, 'selesai'])->name('selesai');
            Route::post('/{id}/batal',          [PesananController::class, 'batal'])->name('batal');
        });

        // Stok
        Route::prefix('stok')->name('stok.')->group(function () {
            Route::get('/opname',                           [StokController::class, 'opname'])->name('opname');
            Route::post('/opname',                          [StokController::class, 'simpanOpname'])->name('opname.simpan');
            Route::get('/produk/{productId}/riwayat',       [StokController::class, 'riwayat'])->name('riwayat');
        });

        // Workflow Monitoring
        Route::get('/workflow', [WorkflowController::class, 'index'])->name('workflow.index');
    });
});

// ── Storefront ────────────────────────────────────────────────────
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
