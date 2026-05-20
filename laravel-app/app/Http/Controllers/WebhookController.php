<?php

namespace App\Http\Controllers;

use App\Models\PaymentLog;
use App\Services\MidtransService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private MidtransService     $midtrans,
        private SubscriptionService $subscription,
    ) {}

    public function midtrans(Request $request): Response
    {
        $notification = $request->all();

        Log::info('Midtrans webhook masuk', ['order_id' => $notification['order_id'] ?? null]);

        // Validasi signature
        if (! $this->midtrans->validateSignature($notification)) {
            Log::warning('Midtrans webhook: signature tidak valid', $notification);
            return response('Unauthorized', 401);
        }

        $this->midtrans->processNotification($notification);

        // Jika pembayaran berhasil, aktifkan langganan
        $transactionStatus = $notification['transaction_status'] ?? '';
        $fraudStatus       = $notification['fraud_status']       ?? '';

        $isPaid = ($transactionStatus === 'capture' && $fraudStatus === 'accept')
            || $transactionStatus === 'settlement';

        if ($isPaid) {
            $log = PaymentLog::where('order_id', $notification['order_id'])->first();
            if ($log) {
                $this->subscription->aktivasiSetelahBayar($log);
            }
        }

        return response('OK', 200);
    }
}
