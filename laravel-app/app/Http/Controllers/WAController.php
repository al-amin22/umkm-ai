<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\WaSession;
use App\Services\CommandRouter;
use App\Services\OnboardingService;
use App\Services\SessionService;
use App\Services\WAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WAController extends Controller
{
    public function __construct(
        private OnboardingService $onboarding,
        private CommandRouter     $router,
        private SessionService    $session,
        private WAService         $wa,
    ) {}

    // ── Entry Point Utama ─────────────────────────────────────────

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wa_number'    => 'required|string',
            'pesan'        => 'nullable|string|max:4096',
            'tipe'         => 'required|in:teks,gambar,voice',
            'media_base64' => 'nullable|string',
            'timestamp'    => 'nullable|integer',
        ]);

        $waNumber = $validated['wa_number'];
        $pesan    = trim($validated['pesan'] ?? '');
        $tipe     = $validated['tipe'];

        Log::info("WAController: pesan masuk [{$tipe}]", [
            'wa'      => $waNumber,
            'preview' => mb_substr($pesan, 0, 60),
        ]);

        // Hanya proses teks untuk sementara (gambar akan di-handle di modul produk)
        if ($tipe !== 'teks' && empty($pesan)) {
            return response()->json(['status' => 'ignored', 'reason' => 'no_text']);
        }

        // Cek apakah session sedang terkunci (concurrent message handling)
        if ($this->session->isLocked($waNumber)) {
            Log::warning("WAController: session terkunci, pesan diabaikan", ['wa' => $waNumber]);
            // Kirim pesan "sedang diproses" jika sudah menunggu > 5 detik
            return response()->json(['status' => 'queued', 'reason' => 'session_locked']);
        }

        // Lock session selama proses
        $this->session->lockSession($waNumber);

        try {
            $this->prosespesan($waNumber, $pesan, $tipe, $validated);
        } catch (\Throwable $e) {
            Log::error("WAController: exception tidak tertangani", [
                'wa'    => $waNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Kirim pesan error generic ke user agar tidak menggantung
            try {
                $this->wa->kirimPesan($waNumber,
                    "Maaf, ada kendala sistem. Coba kirim pesan lagi dalam beberapa saat ya. 🙏"
                );
            } catch (\Throwable) {
                // Silent fail
            }
        } finally {
            // Selalu unlock setelah selesai
            $this->session->unlockSession($waNumber);
            $this->session->touchActivity($waNumber);
        }

        return response()->json(['status' => 'ok']);
    }

    // ── Core Processing ───────────────────────────────────────────

    private function prosespesan(string $waNumber, string $pesan, string $tipe, array $data): void
    {
        // 1. Cari semua toko yang terkait dengan nomor ini (owner atau helper)
        $shops = $this->getAssociatedShops($waNumber);

        // 2. Belum punya toko → alur onboarding
        if ($shops->isEmpty()) {
            $this->onboarding->handlePesan($waNumber, $pesan);
            return;
        }

        // 3. Punya banyak toko → pilih toko aktif dari session
        if ($shops->count() > 1) {
            $waSession     = $this->session->getSession($waNumber);
            $activeShopId  = $waSession->context_data['active_shop_id'] ?? null;

            if (! $activeShopId) {
                $this->tanyaPilihToko($waNumber, $shops, $pesan);
                return;
            }

            $shop = $shops->firstWhere('id', $activeShopId);

            if (! $shop) {
                $this->tanyaPilihToko($waNumber, $shops, $pesan);
                return;
            }
        } else {
            $shop = $shops->first();
        }

        // 4. Pastikan WaSession punya shop_id yang benar
        WaSession::where('wa_number', $waNumber)->update(['shop_id' => $shop->id]);

        // 5. Cek apakah masih dalam sesi onboarding opsional
        $waSession = $this->session->getSession($waNumber);
        $ctx       = $waSession->context_data ?? [];

        if (($ctx['menunggu'] ?? false) && str_starts_with($ctx['menunggu'] ?? '', '')) {
            // Kembalikan ke OnboardingService untuk isian opsional
            $this->onboarding->handlePesan($waNumber, $pesan);
            return;
        }

        // 6. Route ke CommandRouter untuk perintah normal
        $this->router->route($waNumber, $pesan, $shop, $waSession);
    }

    // ── Multi-Toko: Pilih Toko ────────────────────────────────────

    private function tanyaPilihToko(string $waNumber, $shops, string $pesanAsli): void
    {
        $waSession = $this->session->getSession($waNumber);
        $ctx       = $waSession->context_data ?? [];

        // Cek apakah user sedang menjawab pilihan toko
        if ($ctx['menunggu_pilih_toko'] ?? false) {
            $pilihanShops = $ctx['daftar_shop_ids'] ?? [];
            $pilihan      = (int) trim($pesanAsli);

            if ($pilihan >= 1 && $pilihan <= count($pilihanShops)) {
                $shopId = $pilihanShops[$pilihan - 1];
                $this->session->updateContext($waNumber, null, [
                    'active_shop_id'      => $shopId,
                    'menunggu_pilih_toko' => false,
                    'daftar_shop_ids'     => [],
                ]);

                $shop = $shops->firstWhere('id', $shopId);
                $this->wa->kirimPesan($waNumber,
                    "✅ Sekarang mengelola toko *{$shop->nama_toko}*.\n"
                    . "Silahkan lanjutkan perintahmu."
                );
                return;
            }

            $this->wa->kirimPesan($waNumber, "Pilihan tidak valid. Ketik angka 1 - " . count($pilihanShops) . ".");
            return;
        }

        // Tampilkan daftar toko untuk dipilih
        $lines      = ["Kamu terdaftar di *{$shops->count()} toko*. Pilih toko mana yang ingin dikelola:\n"];
        $shopIds    = [];

        foreach ($shops as $i => $s) {
            $no         = $i + 1;
            $roleLabel  = $s->pivot_role === 'owner' ? '👑 Owner' : '👤 Helper';
            $lines[]    = "{$no}. *{$s->nama_toko}* — {$roleLabel}";
            $shopIds[]  = $s->id;
        }

        $this->session->updateContext($waNumber, null, [
            'menunggu_pilih_toko' => true,
            'daftar_shop_ids'     => $shopIds,
        ]);

        $this->wa->kirimPesan($waNumber, implode("\n", $lines) . "\n\nBalas dengan angka pilihanmu.");
    }

    // ── Get Associated Shops ──────────────────────────────────────

    private function getAssociatedShops(string $waNumber): \Illuminate\Support\Collection
    {
        return Shop::whereHas('admins', function ($q) use ($waNumber) {
                $q->where('wa_number', $waNumber)->where('is_active', true);
            })
            ->with(['admins' => function ($q) use ($waNumber) {
                $q->where('wa_number', $waNumber);
            }])
            ->get()
            ->map(function ($shop) {
                $shop->pivot_role = $shop->admins->first()?->role ?? 'helper';
                return $shop;
            });
    }

    // ── Status & Heartbeat ────────────────────────────────────────

    public function status(Request $request): JsonResponse
    {
        $status = $request->input('status');

        Log::info("WAController: status update dari wa-service", ['status' => $status]);

        if ($status === 'logged_out') {
            Log::alert("WAController: WhatsApp LOGOUT terdeteksi! Perlu scan QR ulang.");
            // TODO: notifikasi ke admin
        }

        return response()->json(['status' => 'received']);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        Log::debug("WAController: heartbeat diterima", [
            'wa_status'  => $request->input('status'),
            'queue_size' => $request->input('queue_size'),
            'uptime'     => $request->input('uptime'),
        ]);

        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
