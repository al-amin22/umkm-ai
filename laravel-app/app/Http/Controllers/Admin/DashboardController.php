<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\AnalitikService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AnalitikService $analitik) {}

    public function index(Request $request): View
    {
        $shop = $request->attributes->get('admin_shop');

        $metrik = $this->analitik->hitungMetrikPenjualan(
            $shop->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $pesananPending = Order::where('shop_id', $shop->id)
            ->where('status', 'pending')
            ->count();

        $pesananKonfirmasi = Order::where('shop_id', $shop->id)
            ->where('status', 'confirmed')
            ->count();

        $totalProdukAktif = Product::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->count();

        $trendMingguan = $this->analitik->trendMingguan($shop->id);

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
