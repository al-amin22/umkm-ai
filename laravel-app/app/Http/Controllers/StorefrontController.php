<?php

namespace App\Http\Controllers;

use App\Models\LaporanToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\NotificationService;
use App\Services\WAService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private WAService           $wa,
        private NotificationService $notif,
    ) {}

    // ── Halaman Toko ──────────────────────────────────────────────

    public function toko(string $slug): View|\Illuminate\Http\Response
    {
        $shop = Shop::where('slug', $slug)->where('status', 'active')->first();

        if (! $shop) {
            abort(404, 'Toko tidak ditemukan atau sedang tidak aktif.');
        }

        $produk = Product::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->with('stock')
            ->orderBy('nama_produk')
            ->get();

        $theme = $shop->theme ?? null;

        return view('storefront.toko', compact('shop', 'produk', 'theme'));
    }

    // ── Halaman Produk Detail ─────────────────────────────────────

    public function produk(string $slug, int $produkId): View
    {
        $shop = Shop::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $produk = Product::where('shop_id', $shop->id)
            ->where('id', $produkId)
            ->where('status', 'active')
            ->firstOrFail();

        return view('storefront.produk', compact('shop', 'produk'));
    }

    // ── Form Order ────────────────────────────────────────────────

    public function formOrder(string $slug): View
    {
        $shop = Shop::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $produk = Product::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->with('stock')
            ->get()
            ->filter(fn ($p) => ($p->stock?->jumlah_sekarang ?? 0) > 0);

        return view('storefront.order', compact('shop', 'produk'));
    }

    // ── Submit Order ──────────────────────────────────────────────

    public function submitOrder(Request $request, string $slug): \Illuminate\Http\RedirectResponse
    {
        $shop = Shop::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $validated = $request->validate([
            'buyer_name'    => 'required|string|max:100',
            'buyer_phone'   => 'required|string|max:20',
            'buyer_address' => 'required|string|max:500',
            'buyer_city'    => 'nullable|string|max:100',
            'catatan'       => 'nullable|string|max:300',
            'items'         => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1|max:999',
        ]);

        $totalHarga = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            $produk = Product::where('id', $item['product_id'])
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->first();

            if (! $produk) continue;

            $subtotal    = $produk->harga * $item['quantity'];
            $totalHarga += $subtotal;

            $orderItems[] = [
                'product_id' => $produk->id,
                'quantity'   => $item['quantity'],
                'harga_saat_pesan' => $produk->harga,
                'subtotal'   => $subtotal,
            ];
        }

        if (empty($orderItems)) {
            return back()->withErrors(['items' => 'Tidak ada produk yang valid.']);
        }

        $order = Order::create([
            'shop_id'       => $shop->id,
            'buyer_name'    => $validated['buyer_name'],
            'buyer_phone'   => $validated['buyer_phone'],
            'buyer_address' => $validated['buyer_address'],
            'buyer_city'    => $validated['buyer_city'] ?? null,
            'catatan'       => $validated['catatan'] ?? null,
            'total_harga'   => $totalHarga,
            'status'        => 'pending',
        ]);

        $order->items()->createMany($orderItems);

        $this->notif->dispatch(
            $shop->id,
            "🛍️ *Pesanan Baru #{$order->id}*\n"
            . "Dari: {$order->buyer_name}\n"
            . "Total: " . $this->wa->formatRupiah($totalHarga) . "\n\n"
            . "Ketik *konfirmasi {$order->id}* untuk konfirmasi.",
            'urgent'
        );

        return redirect()->route('storefront.sukses', ['slug' => $slug, 'order' => $order->id]);
    }

    // ── Halaman Sukses ────────────────────────────────────────────

    public function sukses(string $slug, int $orderId): View
    {
        $shop  = Shop::where('slug', $slug)->firstOrFail();
        $order = Order::where('id', $orderId)->where('shop_id', $shop->id)->firstOrFail();

        return view('storefront.sukses', compact('shop', 'order'));
    }

    // ── Laporan via Token ─────────────────────────────────────────

    public function laporan(string $token): View
    {
        $laporanToken = LaporanToken::where('token', $token)
            ->whereNull('used_at')
            ->where('expired_at', '>', now())
            ->with('shop')
            ->first();

        if (! $laporanToken) {
            abort(404, 'Link laporan tidak valid atau sudah kadaluarsa.');
        }

        $laporanToken->markAsUsed();

        $shop = $laporanToken->shop;
        if (! $shop) {
            abort(404, 'Data toko tidak ditemukan.');
        }

        $bulanIni  = now()->startOfMonth();
        $bulanLalu = now()->subMonth()->startOfMonth();

        $ordersIni = Order::where('shop_id', $shop->id)
            ->where('status', 'done')
            ->where('created_at', '>=', $bulanIni)
            ->with('items.product')
            ->get();

        $ordersLalu = Order::where('shop_id', $shop->id)
            ->where('status', 'done')
            ->whereBetween('created_at', [$bulanLalu, $bulanIni])
            ->get();

        $omzetIni  = $ordersIni->sum('total_harga');
        $omzetLalu = $ordersLalu->sum('total_harga');
        $growth    = $omzetLalu > 0
            ? round((($omzetIni - $omzetLalu) / $omzetLalu) * 100, 1)
            : null;

        // Top produk
        $topProduk = $ordersIni->flatMap->items
            ->groupBy('product_id')
            ->map(fn ($items) => [
                'nama'    => $items->first()->product?->nama_produk ?? 'Unknown',
                'terjual' => $items->sum('quantity'),
                'omzet'   => $items->sum('subtotal'),
            ])
            ->sortByDesc('terjual')
            ->take(5)
            ->values();

        return view('storefront.laporan', compact(
            'shop', 'ordersIni', 'omzetIni', 'omzetLalu', 'growth', 'topProduk'
        ));
    }
}
