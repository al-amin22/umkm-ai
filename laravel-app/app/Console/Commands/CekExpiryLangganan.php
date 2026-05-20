<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Subscription;
use App\Services\NotificationService;
use App\Services\PlanGate;
use Illuminate\Console\Command;

class CekExpiryLangganan extends Command
{
    protected $signature   = 'umkm:cek-expiry';
    protected $description = 'Cek langganan yang hampir/sudah expired dan kirim notifikasi';

    public function __construct(
        private NotificationService $notif,
        private PlanGate            $gate,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Mengecek expiry langganan...');

        // Notifikasi 7 hari sebelum expired
        $sevenDays = now()->addDays(7)->toDateString();
        $expiringSoon = Subscription::where('status', 'active')
            ->whereDate('expired_at', $sevenDays)
            ->with('shop')
            ->get();

        foreach ($expiringSoon as $sub) {
            if (! $sub->shop) continue;
            $this->notif->dispatch(
                $sub->shop_id,
                "⚠️ Langganan toko *{$sub->shop->nama_toko}* akan berakhir dalam 7 hari ({$sub->expired_at->format('d M Y')}).\n"
                . "Ketik *perpanjang* untuk lanjutkan.",
                'penting'
            );
        }

        // Notifikasi 1 hari sebelum expired
        $tomorrow = now()->addDay()->toDateString();
        $expiring1Day = Subscription::where('status', 'active')
            ->whereDate('expired_at', $tomorrow)
            ->with('shop')
            ->get();

        foreach ($expiring1Day as $sub) {
            if (! $sub->shop) continue;
            $this->notif->dispatch(
                $sub->shop_id,
                "🔴 Langganan toko *{$sub->shop->nama_toko}* berakhir BESOK!\n"
                . "Segera ketik *perpanjang* agar toko tetap aktif.",
                'urgent'
            );
        }

        // Nonaktifkan langganan yang sudah expired
        $expired = Subscription::where('status', 'active')
            ->where('expired_at', '<', now())
            ->with('shop')
            ->get();

        foreach ($expired as $sub) {
            $sub->update(['status' => 'expired']);

            if ($sub->shop) {
                $sub->shop->update(['status' => 'inactive']);
                $this->gate->flushCache($sub->shop_id);
                $this->notif->dispatch(
                    $sub->shop_id,
                    "❌ Langganan toko *{$sub->shop->nama_toko}* telah berakhir. Toko dinonaktifkan.\n"
                    . "Ketik *perpanjang* untuk aktifkan kembali.",
                    'urgent'
                );
            }
        }

        $this->info("Selesai. Hampir expired: {$expiringSoon->count()}, 1 hari lagi: {$expiring1Day->count()}, Expired: {$expired->count()}");
        return Command::SUCCESS;
    }
}
