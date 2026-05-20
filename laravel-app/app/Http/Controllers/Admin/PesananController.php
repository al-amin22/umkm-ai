<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PesananController extends Controller
{
    public function __construct(private OrderService $order) {}

    public function index(Request $request): View
    {
        $shop   = $request->attributes->get('admin_shop');
        $status = $request->query('status', 'all');

        $query = Order::where('shop_id', $shop->id)->with('items.product');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $pesanan = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'all'       => Order::where('shop_id', $shop->id)->count(),
            'pending'   => Order::where('shop_id', $shop->id)->where('status', 'pending')->count(),
            'confirmed' => Order::where('shop_id', $shop->id)->where('status', 'confirmed')->count(),
            'shipped'   => Order::where('shop_id', $shop->id)->where('status', 'shipped')->count(),
            'done'      => Order::where('shop_id', $shop->id)->where('status', 'done')->count(),
            'cancelled' => Order::where('shop_id', $shop->id)->where('status', 'cancelled')->count(),
        ];

        return view('admin.pesanan.index', compact('shop', 'pesanan', 'status', 'counts'));
    }

    public function show(Request $request, int $id): View
    {
        $shop    = $request->attributes->get('admin_shop');
        $pesanan = Order::where('shop_id', $shop->id)
            ->with(['items.product', 'customer'])
            ->findOrFail($id);

        return view('admin.pesanan.show', compact('shop', 'pesanan'));
    }

    public function konfirmasi(Request $request, int $id): RedirectResponse
    {
        $shop    = $request->attributes->get('admin_shop');
        $shopAdmin = $request->attributes->get('shop_admin');

        // Delegate ke OrderService (reuse WA logic, pass owner WA number)
        $this->order->handleKonfirmasiPesanan(
            $shop->wa_number_owner,
            ['order_id' => $id],
            $shop
        );

        return redirect()->route('admin.pesanan.show', $id)
            ->with('success', "Pesanan #{$id} dikonfirmasi.");
    }

    public function kirim(Request $request, int $id): RedirectResponse
    {
        $shop = $request->attributes->get('admin_shop');
        $resi = $request->input('resi');

        $this->order->handleShippedPesanan(
            $shop->wa_number_owner,
            ['order_id' => $id, 'resi' => $resi],
            $shop
        );

        return redirect()->route('admin.pesanan.show', $id)
            ->with('success', "Status pesanan #{$id} diubah ke Dikirim.");
    }

    public function selesai(Request $request, int $id): RedirectResponse
    {
        $shop = $request->attributes->get('admin_shop');

        $this->order->handleSelesaiPesanan(
            $shop->wa_number_owner,
            ['order_id' => $id],
            $shop
        );

        return redirect()->route('admin.pesanan.show', $id)
            ->with('success', "Pesanan #{$id} diselesaikan.");
    }

    public function batal(Request $request, int $id): RedirectResponse
    {
        $shop  = $request->attributes->get('admin_shop');
        $alasan = $request->input('alasan');

        $this->order->handleBatalPesanan(
            $shop->wa_number_owner,
            ['order_id' => $id, 'keterangan' => $alasan],
            $shop
        );

        return redirect()->route('admin.pesanan.show', $id)
            ->with('success', "Pesanan #{$id} dibatalkan.");
    }
}
