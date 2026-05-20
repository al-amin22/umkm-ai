<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use App\Models\Subscription;
use App\Services\MidtransService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LanggananController extends Controller
{
    public function __construct(
        private MidtransService     $midtrans,
        private SubscriptionService $subscription,
    ) {}

    public function index(Request $request): View
    {
        $shop = $request->attributes->get('admin_shop');

        $sub = Subscription::where('shop_id', $shop->id)
            ->latest()
            ->first();

        $riwayat = PaymentLog::where('shop_id', $shop->id)
            ->latest()
            ->limit(10)
            ->get();

        $paketList = $this->midtrans->getPaketList();

        return view('admin.langganan.index', compact('shop', 'sub', 'riwayat', 'paketList'));
    }

    public function checkout(Request $request)
    {
        $shop   = $request->attributes->get('admin_shop');
        $paket  = $request->input('paket');
        $pakets = $this->midtrans->getPaketList();

        if (! isset($pakets[$paket])) {
            return back()->withErrors(['paket' => 'Paket tidak valid.']);
        }

        $result = $this->midtrans->createSnapTransaction($shop, $pakets[$paket]);

        if (! $result['success']) {
            return back()->with('error', 'Gagal membuat link pembayaran. Coba lagi nanti.');
        }

        // Create subscription record in grace status
        Subscription::create([
            'shop_id'    => $shop->id,
            'plan'       => $paket,
            'status'     => 'grace',
            'mulai_at'   => now(),
            'expired_at' => now()->addDays($pakets[$paket]['hari']),
        ]);

        return redirect($result['redirect_url']);
    }
}
