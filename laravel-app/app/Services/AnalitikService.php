<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalitikService
{
    public function __construct(
        private WAService  $wa,
        private GroqService $groq,
    ) {}

    // ── WA Handlers ───────────────────────────────────────────────

    public function handleAnalitikPenjualan(string $waNumber, array $entities, Shop $shop): void
    {
        $periode = $entities['periode'] ?? 'bulan_ini';
        [$dari, $sampai, $labelPeriode] = $this->parsePeriode($periode);

        $data = $this->hitungMetrikPenjualan($shop->id, $dari, $sampai);

        $lines = [
            "📊 *Analitik Penjualan — {$labelPeriode}*",
            "🏪 {$shop->nama_toko}",
            "",
            "💰 Omzet       : *" . $this->wa->formatRupiah($data['omzet']) . "*",
            "📦 Total Order : *{$data['total_pesanan']}*",
            "✅ Selesai     : *{$data['pesanan_done']}*",
            "❌ Dibatalkan  : *{$data['pesanan_cancelled']}*",
            "📈 Konversi    : *{$data['konversi_pct']}%*",
            "💵 Avg Order   : *" . $this->wa->formatRupiah($data['avg_order_value']) . "*",
            "",
        ];

        // Trend vs bulan lalu
        if ($data['trend_pct'] !== null) {
            $trendIcon = $data['trend_pct'] >= 0 ? '📈' : '📉';
            $trendSign = $data['trend_pct'] >= 0 ? '+' : '';
            $lines[]   = "{$trendIcon} vs bulan lalu: *{$trendSign}{$data['trend_pct']}%*";
            $lines[]   = "";
        }

        // Top 3 produk
        if (! empty($data['produk_terlaris'])) {
            $lines[] = "*🏆 Produk Terlaris:*";
            foreach (array_slice($data['produk_terlaris'], 0, 3) as $i => $p) {
                $medal   = match ($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '•' };
                $lines[] = "{$medal} {$p['nama']} — {$p['total_terjual']}x";
            }
            $lines[] = "";
        }

        $lines[] = "_Ketik *insight bisnis* untuk analisis AI._";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    public function handleInsightBisnis(string $waNumber, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber, "🤖 Sedang menganalisis data bisnis kamu...");

        [$dari, $sampai] = $this->parsePeriode('bulan_ini');
        $metrik   = $this->hitungMetrikPenjualan($shop->id, $dari, $sampai);
        $rfmRing  = $this->ringkasanRfm($shop->id);
        $stokKrit = $this->hitungStokKritis($shop->id);

        $konteks = [
            'nama_toko'          => $shop->nama_toko,
            'jenis_produk'       => $shop->jenis_produk,
            'omzet_bulan_ini'    => $metrik['omzet'],
            'total_pesanan'      => $metrik['total_pesanan'],
            'konversi_pct'       => $metrik['konversi_pct'],
            'trend_pct'          => $metrik['trend_pct'],
            'pelanggan_baru'     => $rfmRing['baru'],
            'pelanggan_beresiko' => $rfmRing['beresiko'],
            'stok_kritis_count'  => $stokKrit,
        ];

        try {
            $insight = $this->groq->generateInsightBisnis($konteks);
            $this->wa->kirimPesan($waNumber,
                "💡 *Insight Bisnis AI*\n\n{$insight}"
            );
        } catch (\Throwable $e) {
            $this->wa->kirimPesan($waNumber,
                "Maaf, gagal menghasilkan insight saat ini. Coba lagi nanti."
            );
        }
    }

    public function handleTrendMingguan(string $waNumber, Shop $shop): void
    {
        $minggu = $this->trendMingguan($shop->id);

        $lines = ["📅 *Trend Penjualan 4 Minggu Terakhir*\n"];

        foreach ($minggu as $m) {
            $bar   = str_repeat('█', min((int) ($m['omzet'] / 100_000), 10));
            $bar   = $bar ?: '▏';
            $lines[] = "*{$m['label']}*  {$bar}";
            $lines[] = "  " . $this->wa->formatRupiah($m['omzet']) . " · {$m['pesanan']}x order";
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Data Computation ──────────────────────────────────────────

    public function hitungMetrikPenjualan(int $shopId, Carbon $dari, Carbon $sampai): array
    {
        $orders = Order::where('shop_id', $shopId)
            ->whereBetween('created_at', [$dari, $sampai])
            ->get();

        $done      = $orders->where('status', 'done');
        $cancelled = $orders->where('status', 'cancelled');
        $omzet     = (float) $done->sum('total_harga');
        $total     = $orders->count();
        $avgOrder  = $done->count() > 0 ? $omzet / $done->count() : 0;
        $konversi  = $total > 0 ? round($done->count() / $total * 100, 1) : 0;

        // Produk terlaris
        $produkTerlaris = OrderItem::whereHas(
                'order',
                fn ($q) => $q->where('shop_id', $shopId)
                             ->whereBetween('created_at', [$dari, $sampai])
                             ->where('status', 'done')
            )
            ->with('product')
            ->select('product_id', DB::raw('SUM(quantity) as total_terjual'), DB::raw('SUM(subtotal) as total_omzet'))
            ->groupBy('product_id')
            ->orderByDesc('total_terjual')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'nama'          => $i->product?->nama_produk ?? '-',
                'total_terjual' => (int) $i->total_terjual,
                'total_omzet'   => (float) $i->total_omzet,
            ])
            ->toArray();

        // Trend vs bulan lalu
        $durasi       = $dari->diffInDays($sampai) + 1;
        $dariLalu     = $dari->copy()->subDays($durasi);
        $sampaiLalu   = $sampai->copy()->subDays($durasi);
        $omzetLalu    = (float) Order::where('shop_id', $shopId)
            ->whereBetween('created_at', [$dariLalu, $sampaiLalu])
            ->where('status', 'done')
            ->sum('total_harga');
        $trendPct     = $omzetLalu > 0
            ? round((($omzet - $omzetLalu) / $omzetLalu) * 100, 1)
            : null;

        return [
            'omzet'             => $omzet,
            'total_pesanan'     => $total,
            'pesanan_done'      => $done->count(),
            'pesanan_cancelled' => $cancelled->count(),
            'avg_order_value'   => $avgOrder,
            'konversi_pct'      => $konversi,
            'trend_pct'         => $trendPct,
            'omzet_lalu'        => $omzetLalu,
            'produk_terlaris'   => $produkTerlaris,
        ];
    }

    public function trendMingguan(int $shopId): array
    {
        $result = [];
        for ($i = 3; $i >= 0; $i--) {
            $dari    = now()->startOfWeek()->subWeeks($i);
            $sampai  = $dari->copy()->endOfWeek();
            $label   = $i === 0 ? 'Minggu ini' : ($i === 1 ? 'Minggu lalu' : $dari->format('d M'));

            $done   = Order::where('shop_id', $shopId)
                ->whereBetween('created_at', [$dari, $sampai])
                ->where('status', 'done')
                ->get();

            $result[] = [
                'label'   => $label,
                'omzet'   => (float) $done->sum('total_harga'),
                'pesanan' => $done->count(),
            ];
        }
        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function parsePeriode(string $periode): array
    {
        return match ($periode) {
            'kemarin'    => [
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay(),
                'Kemarin',
            ],
            'minggu_ini' => [
                now()->startOfWeek(),
                now()->endOfWeek(),
                'Minggu Ini',
            ],
            'bulan_lalu' => [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
                'Bulan Lalu',
            ],
            default => [ // bulan_ini
                now()->startOfMonth(),
                now()->endOfMonth(),
                now()->locale('id')->isoFormat('MMMM Y'),
            ],
        };
    }

    private function ringkasanRfm(int $shopId): array
    {
        return [
            'baru'      => Customer::byShop($shopId)->bySegment('Baru')->count(),
            'champions' => Customer::byShop($shopId)->bySegment('Champions')->count(),
            'beresiko'  => Customer::byShop($shopId)->bySegment('Beresiko')->count(),
            'tidur'     => Customer::byShop($shopId)->bySegment('Tidur')->count(),
        ];
    }

    private function hitungStokKritis(int $shopId): int
    {
        return \App\Models\Stock::whereHas(
            'product',
            fn ($q) => $q->where('shop_id', $shopId)->where('status', 'active')
        )->kritis()->count();
    }
}
