<?php

namespace App\Services;

use App\Models\PaymentLog;
use App\Models\Shop;
use App\Models\Subscription;

class SubscriptionService
{
    public function __construct(
        private WAService       $wa,
        private MidtransService $midtrans,
        private NotificationService $notif,
    ) {}

    // ── Lihat Status Langganan ────────────────────────────────────

    public function handleCekLangganan(string $waNumber, Shop $shop): void
    {
        $sub = Subscription::where('shop_id', $shop->id)->latest()->first();

        if (! $sub) {
            $this->wa->kirimPesan($waNumber,
                "❓ Belum ada data langganan.\nKetik *perpanjang* untuk berlangganan."
            );
            return;
        }

        $statusIcon = match ($sub->status) {
            'trial'   => '🎁',
            'active'  => '✅',
            'grace'   => '⚠️',
            'expired' => '❌',
            default   => '❓',
        };

        $sisaHari = $sub->hariTersisa();
        $expired  = $sub->expired_at?->setTimezone('Asia/Jakarta')->format('d M Y');

        $this->wa->kirimPesan($waNumber,
            "💳 *Status Langganan — {$shop->nama_toko}*\n\n"
            . "Paket: *{$sub->paket}*\n"
            . "Status: {$statusIcon} *{$sub->status}*\n"
            . "Berlaku sampai: {$expired}\n"
            . "Sisa: *{$sisaHari} hari*\n\n"
            . ($sub->isActive()
                ? ($sisaHari <= 7 ? "⚠️ Segera perpanjang! Ketik *perpanjang* untuk lanjutkan." : "")
                : "Ketik *perpanjang* untuk aktifkan kembali toko kamu."
            )
        );
    }

    // ── Perpanjang Langganan ──────────────────────────────────────

    public function handlePerpanjang(string $waNumber, array $entities, Shop $shop): void
    {
        $pilihanPaket = $entities['paket'] ?? null;
        $paketList    = $this->midtrans->getPaketList();

        if (! $pilihanPaket || ! isset($paketList[$pilihanPaket])) {
            $this->wa->kirimPesan($waNumber,
                "💳 *Pilih Paket Berlangganan*\n\n"
                . "1️⃣ *Bulanan* — Rp 49.000/bulan\n"
                . "2️⃣ *Tahunan* — Rp 399.000/tahun _(hemat 32%)_\n\n"
                . "Balas:\n"
                . "• *perpanjang bulanan*\n"
                . "• *perpanjang tahunan*"
            );
            return;
        }

        $paket  = $paketList[$pilihanPaket];
        $result = $this->midtrans->createSnapTransaction($shop, $paket);

        if (! $result['success']) {
            $this->wa->kirimPesan($waNumber,
                "❌ Gagal membuat link pembayaran. Coba lagi nanti."
            );
            return;
        }

        // Buat record subscription dengan status pending
        Subscription::create([
            'shop_id'    => $shop->id,
            'paket'      => $pilihanPaket,
            'status'     => 'pending',
            'harga'      => $paket['harga'],
            'expired_at' => now()->addDays($paket['hari']),
        ]);

        $this->wa->kirimPesan($waNumber,
            "💳 *Link Pembayaran — Paket " . ucfirst($pilihanPaket) . "*\n\n"
            . "Total: *" . $this->wa->formatRupiah($paket['harga']) . "*\n\n"
            . "🔗 Klik untuk bayar:\n"
            . $result['redirect_url'] . "\n\n"
            . "_Link berlaku 24 jam. Setelah bayar, toko aktif otomatis._"
        );
    }

    // ── Aktifkan Trial ────────────────────────────────────────────

    public function aktivasiTrial(int $shopId, int $hari = 14): Subscription
    {
        return Subscription::create([
            'shop_id'    => $shopId,
            'paket'      => 'trial',
            'status'     => 'trial',
            'harga'      => 0,
            'expired_at' => now()->addDays($hari),
        ]);
    }

    // ── Aktifkan Setelah Pembayaran ───────────────────────────────

    public function aktivasiSetelahBayar(PaymentLog $log): void
    {
        $sub = Subscription::where('shop_id', $log->shop_id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $sub) return;

        $paketList = $this->midtrans->getPaketList();
        $hari      = $paketList[$sub->paket]['hari'] ?? 30;

        $sub->update([
            'status'     => 'active',
            'paid_at'    => now(),
            'expired_at' => now()->addDays($hari),
        ]);

        $shop = $sub->shop;
        if ($shop) {
            $shop->update(['status' => 'active']);
            $this->notif->dispatch(
                $shop->id,
                "✅ Pembayaran berhasil! Langganan *{$sub->paket}* aktif hingga "
                . $sub->expired_at->format('d M Y') . ".\nToko kamu sudah aktif kembali.",
                'urgent'
            );
        }
    }
}
