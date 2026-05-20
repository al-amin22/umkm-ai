<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class BroadcastService
{
    // Interval antar pengiriman pesan broadcast (ms) agar tidak di-ban WA
    private const DELAY_MS = 1500;

    // Maksimum penerima per broadcast agar tidak spam
    private const MAX_RECIPIENTS = 200;

    public function __construct(
        private WAService      $wa,
        private SessionService $session,
    ) {}

    // ── WA Handlers ───────────────────────────────────────────────

    /**
     * Mulai alur broadcast: tampilkan pilihan segmen atau langsung tanya pesan.
     */
    public function handleBuatBroadcast(string $waNumber, array $entities, Shop $shop): void
    {
        $segment = $entities['segmen'] ?? null;
        $total   = $this->countRecipients($shop, $segment);

        if ($total === 0) {
            $this->wa->kirimPesan($waNumber,
                "Tidak ada pelanggan" . ($segment ? " segmen *{$segment}*" : "") . " yang bisa dikirim broadcast.\n"
                . "Pelanggan terdaftar saat ada pesanan yang selesai."
            );
            return;
        }

        $segmenInfo = $segment ? " (segmen *{$segment}*)" : " (semua pelanggan)";

        $this->session->mergeContextData($waNumber, [
            'context'            => 'buat_broadcast',
            'broadcast_segmen'   => $segment,
            'broadcast_total'    => $total,
        ]);

        $this->wa->kirimPesan($waNumber,
            "📣 *Buat Broadcast*{$segmenInfo}\n\n"
            . "Akan dikirim ke *{$total} pelanggan*.\n\n"
            . "Ketik pesan broadcast yang ingin dikirim:\n"
            . "_Contoh: Promo akhir bulan! Diskon 20% untuk semua produk hari ini._\n\n"
            . "_Ketik *batal* untuk membatalkan._"
        );
    }

    /**
     * Proses jawaban multi-step: pesan broadcast → konfirmasi → kirim.
     * Return true jika konteks ini berhasil ditangani.
     */
    public function prosesJawabanBroadcast(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);

        if (($ctx['context'] ?? '') !== 'buat_broadcast') {
            return false;
        }

        if (strtolower(trim($pesan)) === 'batal') {
            $this->session->clearContext($waNumber);
            $this->wa->kirimPesan($waNumber, "Broadcast dibatalkan.");
            return true;
        }

        $step = $ctx['broadcast_step'] ?? 'input_pesan';

        if ($step === 'input_pesan') {
            return $this->prosesInputPesan($waNumber, $pesan, $shop, $ctx);
        }

        if ($step === 'konfirmasi') {
            return $this->prosesKonfirmasiBroadcast($waNumber, $pesan, $shop, $ctx);
        }

        return false;
    }

    // ── Private: Flow Steps ───────────────────────────────────────

    private function prosesInputPesan(string $waNumber, string $pesan, Shop $shop, array $ctx): bool
    {
        $total   = $ctx['broadcast_total'] ?? 0;
        $segmen  = $ctx['broadcast_segmen'] ?? null;
        $preview = mb_substr($pesan, 0, 100) . (mb_strlen($pesan) > 100 ? '...' : '');

        $this->session->mergeContextData($waNumber, [
            'broadcast_step'  => 'konfirmasi',
            'broadcast_pesan' => $pesan,
        ]);

        $segmenInfo = $segmen ? " ke segmen *{$segmen}*" : " ke *semua pelanggan*";

        $this->wa->kirimPesan($waNumber,
            "📋 *Konfirmasi Broadcast*\n\n"
            . "Kirim{$segmenInfo}: *{$total} orang*\n\n"
            . "*Isi pesan:*\n_{$preview}_\n\n"
            . "Balas *ya* untuk kirim, atau *batal* untuk batal."
        );

        return true;
    }

    private function prosesKonfirmasiBroadcast(string $waNumber, string $pesan, Shop $shop, array $ctx): bool
    {
        $jawaban = strtolower(trim($pesan));

        if (! in_array($jawaban, ['ya', 'iya', 'yes', 'ok', 'oke', 'y'])) {
            $this->session->clearContext($waNumber);
            $this->wa->kirimPesan($waNumber, "Broadcast dibatalkan.");
            return true;
        }

        $pesanBroadcast = $ctx['broadcast_pesan'] ?? '';
        $segmen         = $ctx['broadcast_segmen'] ?? null;

        $this->session->clearContext($waNumber);

        // Kirim konfirmasi langsung, broadcast jalan di background
        $this->wa->kirimPesan($waNumber,
            "⏳ Broadcast sedang dikirim... Kamu akan mendapat notifikasi saat selesai."
        );

        $this->kirimBroadcast($shop, $pesanBroadcast, $segmen, $waNumber);

        return true;
    }

    // ── Core: Kirim ke Semua Penerima ─────────────────────────────

    public function kirimBroadcast(Shop $shop, string $pesan, ?string $segmen, string $reportTo): void
    {
        $query = Customer::byShop($shop->id)
            ->whereNotNull('nomor_hp')
            ->limit(self::MAX_RECIPIENTS);

        if ($segmen) {
            $query->bySegment($segmen);
        }

        $customers = $query->get();
        $berhasil  = 0;
        $gagal     = 0;

        foreach ($customers as $customer) {
            try {
                $hp = $this->normalizePhone($customer->nomor_hp);
                $this->wa->kirimPesan($hp, $pesan);
                $berhasil++;
                // Throttle agar tidak kena rate limit WA
                usleep(self::DELAY_MS * 1000);
            } catch (\Throwable $e) {
                $gagal++;
                Log::warning("BroadcastService: gagal kirim ke {$customer->nomor_hp}", [
                    'customer_id' => $customer->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $icon = $gagal === 0 ? '✅' : '⚠️';
        $this->wa->kirimPesan($reportTo,
            "{$icon} *Broadcast Selesai*\n\n"
            . "✅ Berhasil: {$berhasil}\n"
            . ($gagal > 0 ? "❌ Gagal: {$gagal}\n" : "")
            . "Total: " . ($berhasil + $gagal) . " penerima"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function countRecipients(Shop $shop, ?string $segmen): int
    {
        $query = Customer::byShop($shop->id)->whereNotNull('nomor_hp');
        if ($segmen) {
            $query->bySegment($segmen);
        }
        return min($query->count(), self::MAX_RECIPIENTS);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}
