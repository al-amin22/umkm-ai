<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Cache;

/**
 * Mengatur akses fitur berdasarkan plan langganan aktif toko.
 *
 * Trial  → hanya fitur dasar (produk, stok, pesanan, toko)
 * Starter → + keuangan, konten, komplain, pelanggan, laporan
 * Growth  → semua fitur termasuk broadcast & analytics lanjut
 */
class PlanGate
{
    // Mapping intent → plan minimum yang dibutuhkan
    private const INTENT_PLAN = [
        // ── Selalu tersedia (trial+) ─────────────────────────────
        'tambah_produk'      => 'trial',
        'edit_produk'        => 'trial',
        'hapus_produk'       => 'trial',
        'daftar_produk'      => 'trial',
        'tambah_stok'        => 'trial',
        'cek_stok'           => 'trial',
        'update_stok'        => 'trial',
        'kurangi_stok'       => 'trial',
        'lihat_stok_kritis'  => 'trial',
        'lihat_pesanan'      => 'trial',
        'detail_pesanan'     => 'trial',
        'konfirmasi_pesanan' => 'trial',
        'tolak_pesanan'      => 'trial',
        'kirim_pesanan'      => 'trial',
        'selesai_pesanan'    => 'trial',
        'tutup_toko'         => 'trial',
        'buka_toko'          => 'trial',
        'setting_toko'       => 'trial',
        'cek_langganan'      => 'trial',
        'perpanjang'         => 'trial',
        'tidak_dikenali'     => 'trial',

        // ── Starter+ ────────────────────────────────────────────
        'hitung_hpp'         => 'starter',
        'saran_harga'        => 'starter',
        'cek_margin'         => 'starter',
        'cek_keuangan'       => 'starter',
        'update_harga'       => 'starter',
        'buat_konten'        => 'starter',
        'riwayat_konten'     => 'starter',
        'setting_konten'     => 'starter',
        'catat_komplain'     => 'starter',
        'lihat_komplain'     => 'starter',
        'pola_komplain'      => 'starter',
        'lihat_pelanggan'    => 'starter',
        'detail_pelanggan'   => 'starter',
        'cari_pelanggan'     => 'starter',
        'lihat_laporan'      => 'starter',

        // ── Growth+ ─────────────────────────────────────────────
        'pelanggan_teratas'  => 'growth',
        'analitik_pelanggan' => 'growth',
        'broadcast'          => 'growth',
        'kirim_broadcast'    => 'growth',
    ];

    private const PLAN_RANK = [
        'trial'   => 0,
        'starter' => 1,
        'growth'  => 2,
    ];

    public function __construct(
        private WAService $wa,
    ) {}

    /**
     * Cek apakah intent boleh diakses oleh shop berdasarkan plan aktif.
     * Return true jika diizinkan, false jika tidak (dan kirim pesan penolakan).
     */
    public function allow(string $waNumber, string $intent, Shop $shop): bool
    {
        $requiredPlan = self::INTENT_PLAN[$intent] ?? 'starter';

        if ($requiredPlan === 'trial') {
            return true;
        }

        $activePlan = $this->getActivePlan($shop);

        if ($activePlan === null) {
            $this->kirimPesanUpgrade($waNumber, $requiredPlan, $shop, blocked: true);
            return false;
        }

        $currentRank  = self::PLAN_RANK[$activePlan]  ?? 0;
        $requiredRank = self::PLAN_RANK[$requiredPlan] ?? 0;

        if ($currentRank < $requiredRank) {
            $this->kirimPesanUpgrade($waNumber, $requiredPlan, $shop);
            return false;
        }

        return true;
    }

    /**
     * Ambil nama plan aktif dari subscription toko.
     * Di-cache per shop_id selama 5 menit agar tidak query DB setiap pesan.
     */
    public function getActivePlan(Shop $shop): ?string
    {
        return Cache::remember("plan:{$shop->id}", 300, function () use ($shop) {
            $sub = $shop->activeSubscription;
            return $sub?->plan;
        });
    }

    /**
     * Hapus cache plan saat subscription berubah (dipanggil oleh SubscriptionService).
     */
    public function flushCache(int $shopId): void
    {
        Cache::forget("plan:{$shopId}");
    }

    private function kirimPesanUpgrade(
        string $waNumber,
        string $requiredPlan,
        Shop   $shop,
        bool   $blocked = false,
    ): void {
        if ($blocked) {
            $this->wa->kirimPesan($waNumber,
                "⛔ Langganan kamu sudah tidak aktif.\n\n"
                . "Ketik *perpanjang* untuk memilih paket dan lanjutkan menggunakan fitur premium."
            );
            return;
        }

        $label = match ($requiredPlan) {
            'starter' => 'Starter',
            'growth'  => 'Growth',
            default   => ucfirst($requiredPlan),
        };

        $currentLabel = ucfirst($this->getActivePlan($shop) ?? 'trial');

        $this->wa->kirimPesan($waNumber,
            "🔒 Fitur ini membutuhkan paket *{$label}* atau lebih tinggi.\n"
            . "Plan kamu saat ini: *{$currentLabel}*\n\n"
            . "Ketik *cek langganan* untuk detail paket atau *perpanjang* untuk upgrade."
        );
    }
}
