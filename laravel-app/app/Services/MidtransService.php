<?php

namespace App\Services;

use App\Models\PaymentLog;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    private string $serverKey;
    private string $clientKey;
    private bool   $isProduction;
    private string $snapBaseUrl;

    public function __construct()
    {
        $this->serverKey    = config('services.midtrans.server_key', '');
        $this->clientKey    = config('services.midtrans.client_key', '');
        $this->isProduction = config('services.midtrans.is_production', false);
        $this->snapBaseUrl  = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    // ── Buat Transaksi Snap ───────────────────────────────────────

    public function createSnapTransaction(Shop $shop, array $paket): array
    {
        $orderId = 'SUB-' . $shop->id . '-' . time();

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $paket['harga'],
            ],
            'item_details' => [[
                'id'       => $paket['kode'],
                'price'    => (int) $paket['harga'],
                'quantity' => 1,
                'name'     => "Langganan {$paket['nama']} — {$shop->nama_toko}",
            ]],
            'customer_details' => [
                'first_name' => $shop->nama_owner ?? $shop->nama_toko,
                'phone'      => $shop->nomor_wa,
            ],
            'callbacks' => [
                'finish'     => config('app.url') . "/toko/{$shop->slug}",
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->timeout(30)
                ->post("{$this->snapBaseUrl}/transactions", $payload);

            if (! $response->successful()) {
                Log::error('MidtransService: createSnap gagal', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return ['success' => false, 'message' => 'Gagal membuat link pembayaran.'];
            }

            $data = $response->json();

            PaymentLog::create([
                'shop_id'    => $shop->id,
                'order_id'   => $orderId,
                'amount'     => $paket['harga'],
                'status'     => 'pending',
                'snap_token' => $data['token'] ?? null,
                'snap_url'   => $data['redirect_url'] ?? null,
                'payload'    => $payload,
            ]);

            return [
                'success'      => true,
                'token'        => $data['token'],
                'redirect_url' => $data['redirect_url'],
                'order_id'     => $orderId,
            ];

        } catch (\Exception $e) {
            Log::error('MidtransService: exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Terjadi kesalahan saat membuat pembayaran.'];
        }
    }

    // ── Validasi Signature Webhook ────────────────────────────────

    public function validateSignature(array $notification): bool
    {
        $orderId     = $notification['order_id']          ?? '';
        $statusCode  = $notification['status_code']       ?? '';
        $grossAmount = $notification['gross_amount']       ?? '';
        $signature   = $notification['signature_key']     ?? '';

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($expected, $signature);
    }

    // ── Proses Notifikasi Webhook ─────────────────────────────────

    public function processNotification(array $notification): void
    {
        $orderId           = $notification['order_id']          ?? '';
        $transactionStatus = $notification['transaction_status'] ?? '';
        $fraudStatus       = $notification['fraud_status']       ?? '';

        $log = PaymentLog::where('order_id', $orderId)->first();
        if (! $log) {
            Log::warning('MidtransService: order_id tidak ditemukan', ['order_id' => $orderId]);
            return;
        }

        $isPaid = $transactionStatus === 'capture' && $fraudStatus === 'accept'
            || $transactionStatus === 'settlement';

        $isCancelled = in_array($transactionStatus, ['cancel', 'deny', 'expire']);

        $log->update([
            'status'       => $isPaid ? 'paid' : ($isCancelled ? 'failed' : $transactionStatus),
            'raw_response' => $notification,
        ]);

        if ($isPaid) {
            $log->shop?->subscription?->update([
                'status' => 'active',
                'paid_at' => now(),
            ]);
        }
    }

    // ── Daftar Paket ──────────────────────────────────────────────

    public function getPaketList(): array
    {
        return [
            'bulanan' => [
                'kode'  => 'SUB-BULANAN',
                'nama'  => 'Bulanan',
                'harga' => 49000,
                'hari'  => 30,
            ],
            'tahunan' => [
                'kode'  => 'SUB-TAHUNAN',
                'nama'  => 'Tahunan',
                'harga' => 399000,
                'hari'  => 365,
            ],
        ];
    }
}
