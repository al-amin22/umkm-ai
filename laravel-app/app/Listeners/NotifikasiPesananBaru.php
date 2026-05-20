<?php

namespace App\Listeners;

use App\Events\PesananBaru;
use App\Services\NotificationService;
use App\Services\WAService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifikasiPesananBaru implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private NotificationService $notif,
        private WAService $wa,
    ) {}

    public function handle(PesananBaru $event): void
    {
        $order = $event->order->load('items.product');
        $shop  = $event->shop;

        try {
            $nomor    = $order->nomor_pesanan ?? "#{$order->id}";
            $namaItem = $order->items
                ->map(fn ($i) => "{$i->quantity}x {$i->product?->nama_produk}")
                ->implode(', ');

            $this->notif->dispatch(
                $shop->id,
                "🛍️ *Pesanan Baru {$nomor}*\n"
                . "Dari: *{$order->buyer_name}*\n"
                . "Item: {$namaItem}\n"
                . "Total: *" . $this->wa->formatRupiah($order->total_harga) . "*\n\n"
                . "Ketik *konfirmasi {$order->id}* untuk konfirmasi.",
                'urgent'
            );
        } catch (\Throwable $e) {
            Log::warning('NotifikasiPesananBaru: gagal kirim notifikasi', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
