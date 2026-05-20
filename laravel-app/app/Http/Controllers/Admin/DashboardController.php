<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\AnalitikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AnalitikService $analitik) {}

    public function index(Request $request): View
    {
        $shop = $request->attributes->get('admin_shop');

        $shopId = $shop->id;

        $metrik = Cache::remember("dashboard.metrik.{$shopId}", 300, fn () =>
            $this->analitik->hitungMetrikPenjualan(
                $shopId,
                now()->startOfMonth(),
                now()->endOfMonth(),
            )
        );

        $pesananPending = Cache::remember("dashboard.pending.{$shopId}", 120, fn () =>
            Order::where('shop_id', $shopId)->where('status', 'pending')->count()
        );

        $pesananKonfirmasi = Cache::remember("dashboard.confirmed.{$shopId}", 120, fn () =>
            Order::where('shop_id', $shopId)->where('status', 'confirmed')->count()
        );

        $totalProdukAktif = Cache::remember("dashboard.produk.{$shopId}", 300, fn () =>
            Product::where('shop_id', $shopId)->where('status', 'active')->count()
        );

        $trendMingguan = Cache::remember("dashboard.trend.{$shopId}", 300, fn () =>
            $this->analitik->trendMingguan($shopId)
        );

        $recentOrders = Order::where('shop_id', $shop->id)
            ->with('items.product')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'shop',
            'metrik',
            'pesananPending',
            'pesananKonfirmasi',
            'totalProdukAktif',
            'trendMingguan',
            'recentOrders',
        ));
    }
}
