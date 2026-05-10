<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\OnboardingSession;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\ShopTheme;
use App\Models\Subscription;
use App\Models\WaSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OnboardingService
{
    // Urutan step opsional setelah toko dibuat
    private array $opsionalSteps = [
        'tanya_nama_owner',
        'tanya_rekening',
        'tanya_nomor_darurat',
    ];

    public function __construct(
        private WAService $wa,
        private SessionService $session,
    ) {}

    // ── Entry Point ───────────────────────────────────────────────

    public function handlePesan(string $waNumber, string $pesan): void
    {
        $onboarding = OnboardingSession::firstOrCreate(
            ['wa_number' => $waNumber],
            ['step_terakhir' => 'mulai', 'data_terkumpul' => []]
        );

        // Jika onboarding sudah selesai, lanjutkan pengumpulan data opsional
        if ($onboarding->isCompleted()) {
            $shop = Shop::where('wa_number_owner', $waNumber)->first();
            if ($shop) {
                $this->prosesOpsional($waNumber, $pesan, $onboarding, $shop);
            }
            return;
        }

        $this->handleStep($onboarding, $pesan, $waNumber);
    }

    // ── State Machine per Step ────────────────────────────────────

    public function handleStep(OnboardingSession $onboarding, string $pesan, string $waNumber): void
    {
        $step = $onboarding->step_terakhir;

        match ($step) {
            'mulai'       => $this->prosesStepMulai($waNumber, $onboarding),
            'nama_toko'   => $this->prosesStepNamaToko($waNumber, $pesan, $onboarding),
            'jenis_produk'=> $this->prosesStepJenisProduk($waNumber, $pesan, $onboarding),
            default       => $this->kirimSambutan($waNumber),
        };
    }

    private function prosesStepMulai(string $waNumber, OnboardingSession $onboarding): void
    {
        $onboarding->update(['step_terakhir' => 'nama_toko']);

        $this->wa->kirimPesan($waNumber,
            "Halo! Selamat datang di *UMKM AI Platform* 🎉\n\n"
            . "Platform ini akan membantu kamu mengelola toko online lewat WhatsApp, "
            . "dibantu AI yang siap 24 jam.\n\n"
            . "Mari mulai setup tokomu!\n\n"
            . "❓ *Apa nama toko kamu?*\n"
            . "_Contoh: Warung Makan Bu Sari, Batik Jogja Asli, dll_"
        );
    }

    private function prosesStepNamaToko(string $waNumber, string $pesan, OnboardingSession $onboarding): void
    {
        $namaToko = trim($pesan);

        if (mb_strlen($namaToko) < 3) {
            $this->wa->kirimPesan($waNumber,
                "Nama toko terlalu pendek. Mohon masukkan nama toko yang lebih lengkap ya 😊"
            );
            return;
        }

        if (mb_strlen($namaToko) > 100) {
            $this->wa->kirimPesan($waNumber,
                "Nama toko maksimal 100 karakter. Coba persingkat sedikit ya 🙏"
            );
            return;
        }

        $data = $onboarding->data_terkumpul ?? [];
        $data['nama_toko'] = $namaToko;

        $onboarding->update([
            'step_terakhir' => 'jenis_produk',
            'data_terkumpul' => $data,
        ]);

        $this->wa->kirimPesan($waNumber,
            "Keren! Nama toko *{$namaToko}* dicatat ✅\n\n"
            . "❓ *Apa jenis produk yang kamu jual?*\n\n"
            . "Contoh:\n"
            . "• Makanan & Minuman\n"
            . "• Fashion & Pakaian\n"
            . "• Kerajinan Tangan\n"
            . "• Kosmetik & Kecantikan\n"
            . "• Elektronik\n"
            . "_Atau sebutkan jenis produkmu sendiri_"
        );
    }

    private function prosesStepJenisProduk(string $waNumber, string $pesan, OnboardingSession $onboarding): void
    {
        $jenisProduk = trim($pesan);

        if (mb_strlen($jenisProduk) < 3) {
            $this->wa->kirimPesan($waNumber,
                "Mohon sebutkan jenis produk yang lebih spesifik ya 🙏"
            );
            return;
        }

        $data                 = $onboarding->data_terkumpul ?? [];
        $data['jenis_produk'] = $jenisProduk;
        $onboarding->update(['data_terkumpul' => $data]);

        $shop = $this->buatToko($waNumber, $onboarding);

        if (! $shop) {
            $this->wa->kirimPesan($waNumber,
                "Maaf, ada kendala saat membuat toko. Coba lagi dalam beberapa saat ya 🙏"
            );
            return;
        }

        $onboarding->update([
            'step_terakhir' => 'selesai',
            'completed_at'  => now(),
        ]);

        $appUrl = config('app.url');
        $this->wa->kirimPesan($waNumber,
            "🎉 *Toko {$shop->nama_toko} berhasil dibuat!*\n\n"
            . "🔗 Link toko kamu:\n"
            . "{$appUrl}/toko/{$shop->slug}\n\n"
            . "Kamu sekarang bisa:\n"
            . "• Tambah produk → ketik *tambah produk*\n"
            . "• Lihat pesanan → ketik *pesanan*\n"
            . "• Cek laporan → ketik *laporan*\n"
            . "• Lihat panduan → ketik *bantuan*\n\n"
            . "_Masa trial 14 hari dimulai sekarang. Selamat berjualan!_ 🚀"
        );

        // Mulai kumpulkan data opsional
        $this->lanjutkanOnboarding($shop->id, $waNumber);
    }

    // ── Buat Toko (Transaksional) ─────────────────────────────────

    public function buatToko(string $waNumber, OnboardingSession $onboarding): ?Shop
    {
        $data = $onboarding->data_terkumpul ?? [];

        try {
            return DB::transaction(function () use ($waNumber, $data) {
                $namaToko = $data['nama_toko'];
                $slug     = $this->generateSlug($namaToko);

                // 1. Buat Shop
                $shop = Shop::create([
                    'wa_number_owner' => $waNumber,
                    'nama_toko'       => $namaToko,
                    'slug'            => $slug,
                    'jenis_produk'    => $data['jenis_produk'],
                    'status'          => 'active',
                ]);

                // 2. ShopAdmin (owner)
                ShopAdmin::create([
                    'shop_id'   => $shop->id,
                    'wa_number' => $waNumber,
                    'role'      => 'owner',
                    'is_active' => true,
                ]);

                // 3. ShopTheme (template default pertama)
                ShopTheme::create([
                    'shop_id'        => $shop->id,
                    'template_id'    => 1,
                    'warna_utama'    => '#3B82F6',
                    'warna_sekunder' => '#1E40AF',
                    'last_updated'   => now(),
                ]);

                // 4. Subscription trial 14 hari
                Subscription::create([
                    'shop_id'    => $shop->id,
                    'status'     => 'active',
                    'plan'       => 'trial',
                    'mulai_at'   => now(),
                    'expired_at' => now()->addDays(14),
                ]);

                // 5. NotificationPreference default
                NotificationPreference::create([
                    'shop_id'             => $shop->id,
                    'jeda_aktif'          => false,
                    'consecutive_ignored' => 0,
                    'frekuensi_mode'      => 'normal',
                ]);

                // 6. WaSession
                WaSession::updateOrCreate(
                    ['wa_number' => $waNumber],
                    [
                        'shop_id'        => $shop->id,
                        'active_context' => null,
                        'context_data'   => null,
                        'last_activity'  => now(),
                        'is_locked'      => false,
                    ]
                );

                Log::info("OnboardingService: toko dibuat", [
                    'shop_id' => $shop->id,
                    'slug'    => $slug,
                    'wa'      => $waNumber,
                ]);

                return $shop;
            });

        } catch (\Throwable $e) {
            Log::error("OnboardingService: gagal buat toko", [
                'wa'    => $waNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── Generate Slug Unik ────────────────────────────────────────

    public function generateSlug(string $namaToko): string
    {
        $base = Str::slug($namaToko);

        // Fallback jika karakter non-ASCII semua (misal nama Arab/China)
        if (empty($base)) {
            $base = 'toko-' . Str::lower(Str::random(6));
        }

        $slug     = $base;
        $counter  = 2;

        while (Shop::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    // ── Sambutan Pertama ──────────────────────────────────────────

    public function kirimSambutan(string $waNumber): void
    {
        OnboardingSession::updateOrCreate(
            ['wa_number' => $waNumber],
            ['step_terakhir' => 'mulai', 'data_terkumpul' => []]
        );

        $this->wa->kirimPesan($waNumber,
            "Halo! Selamat datang di *UMKM AI Platform* 👋\n\n"
            . "Saya adalah asisten AI yang akan membantu kamu mengelola toko online "
            . "langsung dari WhatsApp — mulai dari produk, pesanan, stok, hingga laporan.\n\n"
            . "Ketik apa saja untuk memulai pendaftaran toko kamu!"
        );
    }

    // ── Lanjutkan Onboarding Opsional ─────────────────────────────

    public function lanjutkanOnboarding(int $shopId, string $waNumber): void
    {
        $shop = Shop::find($shopId);
        if (! $shop) {
            return;
        }

        // Tanya field yang masih kosong secara berurutan
        if (empty($shop->nama_owner)) {
            $this->session->updateContext($waNumber, 'onboarding_opsional', [
                'menunggu' => 'nama_owner',
                'shop_id'  => $shopId,
            ]);
            $this->wa->kirimPesan($waNumber,
                "\n💡 *Satu lagi untuk melengkapi profil toko:*\n\n"
                . "❓ Siapa nama pemilik toko? _(boleh nama lengkap atau panggilan)_\n\n"
                . "_Ketik \"skip\" untuk lewati_"
            );
            return;
        }

        if (empty($shop->nomor_rekening)) {
            $this->session->updateContext($waNumber, 'onboarding_opsional', [
                'menunggu' => 'rekening',
                'shop_id'  => $shopId,
            ]);
            $this->wa->kirimPesan($waNumber,
                "❓ *Nomor rekening untuk menerima pembayaran?*\n\n"
                . "Format: _nama bank spasi nomor rekening_\n"
                . "Contoh: BCA 1234567890\n\n"
                . "_Ketik \"skip\" untuk lewati_"
            );
            return;
        }

        if (empty($shop->wa_nomor_darurat)) {
            $this->session->updateContext($waNumber, 'onboarding_opsional', [
                'menunggu' => 'nomor_darurat',
                'shop_id'  => $shopId,
            ]);
            $this->wa->kirimPesan($waNumber,
                "❓ *Nomor WA darurat / backup?*\n\n"
                . "Nomor ini digunakan jika nomor utama tidak bisa dihubungi.\n\n"
                . "_Ketik \"skip\" untuk lewati_"
            );
            return;
        }

        // Semua opsional sudah terisi
        $this->session->clearContext($waNumber);
        $this->wa->kirimPesan($waNumber,
            "✅ *Profil toko lengkap!*\n\n"
            . "Toko *{$shop->nama_toko}* siap beroperasi.\n"
            . "Ketik *bantuan* untuk melihat semua perintah yang tersedia. 🚀"
        );
    }

    // ── Proses Jawaban Opsional ───────────────────────────────────

    private function prosesOpsional(
        string $waNumber,
        string $pesan,
        OnboardingSession $onboarding,
        Shop $shop
    ): void {
        $waSession = $this->session->getSession($waNumber);
        $ctx       = $waSession->context_data ?? [];

        if (($ctx['menunggu'] ?? '') === '') {
            // Tidak ada konteks opsional aktif → normal command handling
            return;
        }

        $skip = strtolower(trim($pesan)) === 'skip';

        match ($ctx['menunggu']) {
            'nama_owner' => $this->simpanNamaOwner($shop, $waNumber, $pesan, $skip),
            'rekening'   => $this->simpanRekening($shop, $waNumber, $pesan, $skip),
            'nomor_darurat' => $this->simpanNomorDarurat($shop, $waNumber, $pesan, $skip),
            default      => null,
        };
    }

    private function simpanNamaOwner(Shop $shop, string $waNumber, string $pesan, bool $skip): void
    {
        if (! $skip) {
            $shop->update(['nama_owner' => trim($pesan)]);
            $this->wa->kirimPesan($waNumber, "✅ Nama owner *" . trim($pesan) . "* disimpan.");
        }
        $this->lanjutkanOnboarding($shop->id, $waNumber);
    }

    private function simpanRekening(Shop $shop, string $waNumber, string $pesan, bool $skip): void
    {
        if (! $skip) {
            // Format: "BCA 1234567890" → pisahkan bank dan nomor
            $parts = explode(' ', trim($pesan), 2);
            if (count($parts) === 2) {
                $shop->update([
                    'nama_bank'      => strtoupper($parts[0]),
                    'nomor_rekening' => $parts[1],
                ]);
                $this->wa->kirimPesan($waNumber,
                    "✅ Rekening *{$parts[0]} - {$parts[1]}* disimpan."
                );
            } else {
                $this->wa->kirimPesan($waNumber,
                    "Format salah. Contoh yang benar: *BCA 1234567890*\n"
                    . "Coba lagi atau ketik \"skip\"."
                );
                return;
            }
        }
        $this->lanjutkanOnboarding($shop->id, $waNumber);
    }

    private function simpanNomorDarurat(Shop $shop, string $waNumber, string $pesan, bool $skip): void
    {
        if (! $skip) {
            $nomor = preg_replace('/\D/', '', $pesan);
            if (! $this->wa->isValidWANumber($nomor)) {
                $this->wa->kirimPesan($waNumber,
                    "Format nomor tidak valid. Masukkan nomor WA Indonesia yang aktif.\n"
                    . "Contoh: 08123456789 atau ketik \"skip\"."
                );
                return;
            }
            $shop->update(['wa_nomor_darurat' => $this->wa->normalizeNumber($nomor)]);
            $this->wa->kirimPesan($waNumber, "✅ Nomor darurat disimpan.");
        }
        $this->lanjutkanOnboarding($shop->id, $waNumber);
    }
}
