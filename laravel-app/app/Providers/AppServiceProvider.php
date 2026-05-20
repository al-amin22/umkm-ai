<?php

namespace App\Providers;

use App\Events\OrderDone;
use App\Events\PesananBaru;
use App\Events\StokKritis;
use App\Listeners\KirimFollowUpSetelahDone;
use App\Listeners\NotifikasiPesananBaru;
use App\Listeners\NotifikasiStokKritis;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(OrderDone::class,   KirimFollowUpSetelahDone::class);
        Event::listen(StokKritis::class,  NotifikasiStokKritis::class);
        Event::listen(PesananBaru::class, NotifikasiPesananBaru::class);
    }
}
