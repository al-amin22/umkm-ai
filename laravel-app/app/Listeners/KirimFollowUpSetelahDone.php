<?php

namespace App\Listeners;

use App\Events\OrderDone;
use App\Services\WAService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class KirimFollowUpSetelahDone implements ShouldQueue
{
    // Delay 1 jam setelah pesanan selesai sebelum kirim follow-up
    public int $delay = 3600;

    public string $queue = 'notifications';

    public function __construct(private WAService $wa) {}

    public function handle(OrderDone $event): void
    {
        $order = $event->order->load('shop');
        $shop  = $order->shop;

        if (! $shop || ! $order->buyer_phone) {
            return;
        }

        $hp = $this->normalizePhone($order->buyer_phone);

        try {
            $nomor = $order->nomor_pesanan ?? "#{$order->id}";
            $this->wa->kirimPesan($hp,
                "Halo *{$order->buyer_name}*! 👋\n\n"
                . "Pesanan kamu *{$nomor}* dari *{$shop->nama_toko}* sudah selesai. "
                . "Semoga produknya memuaskan ya! 😊\n\n"
                . "Ada pertanyaan atau keluhan? Balas pesan ini."
            );
        } catch (\Throwable $e) {
            Log::warning('KirimFollowUpSetelahDone: gagal kirim ke buyer', [
                'order_id' => $order->id,
                'phone'    => $order->buyer_phone,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}
