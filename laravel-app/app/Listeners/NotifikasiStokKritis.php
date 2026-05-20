<?php

namespace App\Listeners;

use App\Events\StokKritis;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifikasiStokKritis implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private NotificationService $notif) {}

    public function handle(StokKritis $event): void
    {
        try {
            $this->notif->dispatch(
                $event->shop->id,
                "⚠️ *Stok Kritis!*\n\n"
                . "Produk: *{$event->product->nama_produk}*\n"
                . "Stok sekarang: *{$event->jumlahSekarang}*\n"
                . "Batas minimum: *{$event->batasMinimum}*\n\n"
                . "Ketik *tambah stok {$event->product->nama_produk}* untuk restock.",
                'urgent'
            );
        } catch (\Throwable $e) {
            Log::warning('NotifikasiStokKritis: gagal kirim notifikasi', [
                'shop_id'    => $event->shop->id,
                'product_id' => $event->product->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
