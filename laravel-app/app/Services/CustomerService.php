<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private WAService $wa,
    ) {}

    // ── Sync Customer ─────────────────────────────────────────────
    // Dipanggil setiap kali pesanan baru dibuat untuk upsert data pelanggan.

    public function syncCustomer(Shop $shop, Order $order): void
    {
        DB::transaction(function () use ($shop, $order) {
            $customer = Customer::firstOrCreate(
                [
                    'shop_id'  => $shop->id,
                    'nomor_hp' => $order->buyer_phone,
                ],
                [
                    'nama'   => $order->buyer_name,
                    'alamat' => $order->buyer_address,
                    'kota'   => $order->buyer_city,
                ]
            );

            // Selalu update nama & alamat dengan data terbaru
            $customer->update([
                'nama'   => $order->buyer_name,
                'alamat' => $order->buyer_address,
                'kota'   => $order->buyer_city,
            ]);

            // Link order ke customer
            $order->update(['customer_id' => $customer->id]);
        });
    }

    // Dipanggil saat pesanan selesai (done) untuk update statistik akumulatif.

    public function updateStatsOnDone(Order $order): void
    {
        if (! $order->customer_id) return;

        Customer::where('id', $order->customer_id)->update([
            'total_pesanan' => DB::raw('total_pesanan + 1'),
            'total_belanja' => DB::raw("total_belanja + {$order->total_harga}"),
            'last_order_at' => $order->done_at ?? now(),
        ]);
    }

    // ── WA Handlers ───────────────────────────────────────────────

    public function handleLihatPelanggan(string $waNumber, Shop $shop): void
    {
        $customers = Customer::byShop($shop->id)
            ->orderByDesc('last_order_at')
            ->limit(10)
            ->get();

        if ($customers->isEmpty()) {
            $this->wa->kirimPesan($waNumber,
                "Belum ada data pelanggan.\n"
                . "Data pelanggan otomatis tercatat saat ada pesanan masuk."
            );
            return;
        }

        $lines = ["👥 *Pelanggan Toko*\n"];

        foreach ($customers as $c) {
            $tier = $c->tier;
            $icon = match ($tier) {
                'VIP'     => '⭐',
                'Regular' => '🔵',
                default   => '🟢',
            };

            $lastOrder = $c->last_order_at
                ? $c->last_order_at->diffForHumans()
                : 'belum ada pesanan selesai';

            $lines[] = "{$icon} *{$c->nama}*";
            $lines[] = "   📱 {$c->nomor_hp}";
            $lines[] = "   🛍️ {$c->total_pesanan}x pesanan · " . $this->wa->formatRupiah($c->total_belanja);
            $lines[] = "   🕐 Terakhir: {$lastOrder}";
            $lines[] = "";
        }

        $total   = Customer::byShop($shop->id)->count();
        $lines[] = "_Total {$total} pelanggan. Ketik *cari pelanggan [nama/hp]* untuk cari spesifik._";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    public function handleDetailPelanggan(string $waNumber, array $entities, Shop $shop): void
    {
        $keyword = $entities['nama_pelanggan'] ?? $entities['nomor_hp'] ?? $entities['keyword'] ?? null;

        if (! $keyword) {
            $this->wa->kirimPesan($waNumber,
                "❓ Siapa pelanggannya? Contoh: *detail pelanggan Budi*"
            );
            return;
        }

        $customer = Customer::byShop($shop->id)
            ->search((string) $keyword)
            ->first();

        if (! $customer) {
            $this->wa->kirimPesan($waNumber,
                "Pelanggan *{$keyword}* tidak ditemukan.\n"
                . "Ketik *cari pelanggan [nama]* untuk mencari."
            );
            return;
        }

        $recentOrders = Order::where('shop_id', $shop->id)
            ->where('customer_id', $customer->id)
            ->with('items.product')
            ->latest()
            ->limit(5)
            ->get();

        $tier = $customer->tier;

        $lines = [
            "👤 *{$customer->nama}*",
            "📱 {$customer->nomor_hp}",
            "📍 " . ($customer->kota ?? $customer->alamat ?? 'Alamat tidak tersedia'),
            "⭐ Tier: {$tier}",
            "🛍️ Total pesanan: {$customer->total_pesanan}x",
            "💰 Total belanja: " . $this->wa->formatRupiah($customer->total_belanja),
            "",
            "*5 Pesanan Terakhir:*",
        ];

        if ($recentOrders->isEmpty()) {
            $lines[] = "Belum ada pesanan yang selesai.";
        } else {
            foreach ($recentOrders as $o) {
                $statusIcon = match ($o->status) {
                    'done'      => '✅',
                    'cancelled' => '❌',
                    'shipped'   => '🚚',
                    'confirmed' => '📦',
                    default     => '⏳',
                };
                $itemList = $o->items->map(fn ($i) => "{$i->quantity}x {$i->product?->nama_produk}")->implode(', ');
                $lines[]  = "{$statusIcon} #{$o->id} · " . $o->created_at->format('d/m/Y');
                $lines[]  = "   {$itemList}";
                $lines[]  = "   " . $this->wa->formatRupiah($o->total_harga);
            }
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    public function handleCariPelanggan(string $waNumber, array $entities, Shop $shop): void
    {
        $keyword = $entities['keyword'] ?? $entities['nama_pelanggan'] ?? null;

        if (! $keyword) {
            $this->wa->kirimPesan($waNumber,
                "❓ Cari pelanggan dengan kata kunci apa?\n"
                . "Contoh: *cari pelanggan Budi* atau *cari pelanggan 0812*"
            );
            return;
        }

        $results = Customer::byShop($shop->id)
            ->search((string) $keyword)
            ->limit(8)
            ->get();

        if ($results->isEmpty()) {
            $this->wa->kirimPesan($waNumber,
                "Tidak ada pelanggan dengan kata kunci *{$keyword}*."
            );
            return;
        }

        $lines = ["🔍 *Hasil Pencarian: {$keyword}*\n"];

        foreach ($results as $c) {
            $lines[] = "• *{$c->nama}* — {$c->nomor_hp}";
            $lines[] = "  {$c->total_pesanan}x pesanan · " . $this->wa->formatRupiah($c->total_belanja);
        }

        $lines[] = "\n_Ketik *detail pelanggan [nama]* untuk rincian._";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    public function handlePelangganTeratas(string $waNumber, Shop $shop): void
    {
        $tops = Customer::byShop($shop->id)
            ->orderByDesc('total_belanja')
            ->limit(5)
            ->get();

        if ($tops->isEmpty()) {
            $this->wa->kirimPesan($waNumber,
                "Belum ada data pelanggan. Data terbentuk otomatis dari pesanan yang selesai."
            );
            return;
        }

        $lines = ["🏆 *Pelanggan Teratas*\n"];

        foreach ($tops as $i => $c) {
            $no      = $i + 1;
            $medal   = match ($no) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => "{$no}." };
            $lines[] = "{$medal} *{$c->nama}*";
            $lines[] = "   " . $this->wa->formatRupiah($c->total_belanja) . " · {$c->total_pesanan}x pesanan";
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }
}
