<?php

namespace App\Services;

use App\Models\LaporanToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Stock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LaporanService
{
    private WAService $wa;

    public function __construct(WAService $wa)
    {
        $this->wa = $wa;
    }

    // ── Morning Briefing ──────────────────────────────────────────

    public function generateMorningBriefing(int $shopId): string
    {
        $shop    = Shop::with(['products.stock'])->find($shopId);
        $tanggal = now()->locale('id')->isoFormat('dddd, D MMMM Y');

        if (! $shop) {
            return "Selamat pagi! Toko tidak ditemukan.";
        }

        $harian    = $this->generateLaporanHarian($shopId, today()->toDateString());
        $kritisStok = $this->getStokKritis($shopId);

        $lines = [
            "☀️ *Selamat Pagi, {$shop->nama_owner}!*",
            "_" . $tanggal . "_",
            "",
            "📊 *Ringkasan Kemarin:*",
            "• Pesanan masuk: *{$harian['total_pesanan']}* order",
            "• Pesanan selesai: *{$harian['pesanan_done']}*",
            "• Omzet: *" . $this->wa->formatRupiah($harian['omzet']) . "*",
        ];

        if ($harian['pesanan_pending'] > 0) {
            $lines[] = "";
            $lines[] = "⏳ *Menunggu Konfirmasi: {$harian['pesanan_pending']} pesanan*";
        }

        if (count($kritisStok) > 0) {
            $lines[] = "";
            $lines[] = "⚠️ *Stok Kritis (" . count($kritisStok) . " produk):*";
            foreach (array_slice($kritisStok, 0, 5) as $stok) {
                $lines[] = "• {$stok['nama']}: *{$stok['jumlah']}* tersisa";
            }
            if (count($kritisStok) > 5) {
                $lines[] = "  _...dan " . (count($kritisStok) - 5) . " produk lainnya_";
            }
        }

        $token = $this->generateToken($shopId);
        $appUrl = config('app.url');
        $lines[] = "";
        $lines[] = "📈 *Lihat laporan lengkap:*";
        $lines[] = "{$appUrl}/laporan/{$token}";
        $lines[] = "_Link berlaku 24 jam_";

        return implode("\n", $lines);
    }

    // ── Laporan Harian ────────────────────────────────────────────

    public function generateLaporanHarian(int $shopId, string $tanggal): array
    {
        $date   = Carbon::parse($tanggal);
        $orders = Order::where('shop_id', $shopId)
            ->whereDate('created_at', $date)
            ->get();

        $done      = $orders->where('status', 'done');
        $pending   = $orders->where('status', 'pending');
        $confirmed = $orders->where('status', 'confirmed');
        $cancelled = $orders->where('status', 'cancelled');

        $omzet         = $done->sum('total_harga');
        $omzetPending  = $orders->whereIn('status', ['pending', 'confirmed'])->sum('total_harga');

        $produkTerlaris = $this->getProdukTerlaris($shopId, $date, $date);

        return [
            'tanggal'          => $tanggal,
            'total_pesanan'    => $orders->count(),
            'pesanan_done'     => $done->count(),
            'pesanan_pending'  => $pending->count(),
            'pesanan_confirmed'=> $confirmed->count(),
            'pesanan_cancelled'=> $cancelled->count(),
            'omzet'            => (float) $omzet,
            'omzet_pending'    => (float) $omzetPending,
            'produk_terlaris'  => $produkTerlaris,
            'stok_kritis'      => $this->getStokKritis($shopId),
        ];
    }

    // ── Laporan Mingguan ──────────────────────────────────────────

    public function generateLaporanMingguan(int $shopId): array
    {
        $mulai = now()->startOfWeek();
        $akhir = now()->endOfWeek();

        $orders = Order::where('shop_id', $shopId)
            ->whereBetween('created_at', [$mulai, $akhir])
            ->get();

        $done = $orders->where('status', 'done');

        // Omzet per hari
        $omzetPerHari = [];
        for ($i = 0; $i < 7; $i++) {
            $hari             = $mulai->copy()->addDays($i);
            $omzetHari        = $done
                ->filter(fn ($o) => Carbon::parse($o->created_at)->isSameDay($hari))
                ->sum('total_harga');
            $omzetPerHari[]   = [
                'hari'  => $hari->locale('id')->isoFormat('ddd'),
                'omzet' => (float) $omzetHari,
            ];
        }

        $produkTerlaris = $this->getProdukTerlaris($shopId, $mulai, $akhir);

        return [
            'periode_mulai'   => $mulai->toDateString(),
            'periode_akhir'   => $akhir->toDateString(),
            'total_pesanan'   => $orders->count(),
            'pesanan_done'    => $done->count(),
            'pesanan_cancelled'=> $orders->where('status', 'cancelled')->count(),
            'omzet_total'     => (float) $done->sum('total_harga'),
            'rata_omzet_harian'=> $done->count() > 0
                ? round($done->sum('total_harga') / 7, 0)
                : 0,
            'omzet_per_hari'  => $omzetPerHari,
            'produk_terlaris' => $produkTerlaris,
            'stok_kritis'     => $this->getStokKritis($shopId),
        ];
    }

    // ── Token Management ──────────────────────────────────────────

    public function generateToken(int $shopId): string
    {
        // Hapus token lama yang belum dipakai milik toko ini
        LaporanToken::where('shop_id', $shopId)
            ->whereNull('used_at')
            ->where('expired_at', '<', now())
            ->delete();

        $token = Str::upper(Str::random(12));

        LaporanToken::create([
            'shop_id'    => $shopId,
            'token'      => $token,
            'expired_at' => now()->addHours(24),
            'used_at'    => null,
        ]);

        return $token;
    }

    public function validateToken(string $token): ?int
    {
        $record = LaporanToken::where('token', strtoupper($token))
            ->whereNull('used_at')
            ->where('expired_at', '>', now())
            ->first();

        return $record?->shop_id;
    }

    public function consumeToken(string $token): ?int
    {
        $record = LaporanToken::where('token', strtoupper($token))
            ->whereNull('used_at')
            ->where('expired_at', '>', now())
            ->first();

        if (! $record) {
            return null;
        }

        $record->update(['used_at' => now()]);

        return $record->shop_id;
    }

    // ── Private Helpers ───────────────────────────────────────────

    private function getStokKritis(int $shopId): array
    {
        return Stock::whereHas('product', fn ($q) => $q->where('shop_id', $shopId)->where('status', 'active'))
            ->with('product')
            ->kritis()
            ->get()
            ->map(fn ($s) => [
                'product_id' => $s->product_id,
                'nama'       => $s->product->nama_produk,
                'jumlah'     => $s->jumlah_sekarang,
                'minimum'    => $s->batas_minimum,
            ])
            ->toArray();
    }

    private function getProdukTerlaris(int $shopId, Carbon $dari, Carbon $sampai): array
    {
        return \App\Models\OrderItem::whereHas(
                'order',
                fn ($q) => $q->where('shop_id', $shopId)
                             ->whereBetween('created_at', [$dari, $sampai])
                             ->where('status', 'done')
            )
            ->with('product')
            ->selectRaw('product_id, SUM(quantity) as total_terjual, SUM(subtotal) as total_omzet')
            ->groupBy('product_id')
            ->orderByDesc('total_terjual')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'product_id'   => $item->product_id,
                'nama'         => $item->product?->nama_produk ?? '-',
                'total_terjual'=> (int) $item->total_terjual,
                'total_omzet'  => (float) $item->total_omzet,
            ])
            ->toArray();
    }
}
