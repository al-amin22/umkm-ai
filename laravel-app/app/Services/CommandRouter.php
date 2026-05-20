<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\WaSession;
use Illuminate\Support\Facades\Log;

class CommandRouter
{
    private float $minConfidence = 0.6;

    private array $destructiveIntents = [
        'hapus_produk',
        'tolak_pesanan',
        'tutup_toko',
    ];

    public function __construct(
        private GroqService         $groq,
        private WAService           $wa,
        private SessionService      $session,
        private NotificationService $notif,
        private LaporanService      $laporan,
        private ProductService      $product,
        private StockService        $stock,
        private OrderService        $order,
        private KeuanganService     $keuangan,
        private KontenService       $konten,
        private KomplainService     $komplain,
        private SubscriptionService $subscription,
    ) {}

    // ── Entry Point ───────────────────────────────────────────────

    public function route(string $waNumber, string $pesan, Shop $shop, WaSession $waSession): void
    {
        $ctx = $waSession->context_data ?? [];

        // 1. Ada konfirmasi pending untuk aksi destruktif
        if ($ctx['menunggu_konfirmasi'] ?? false) {
            $this->prosesKonfirmasi($waNumber, $pesan, $shop, $ctx);
            return;
        }

        // 2. Ada flow multi-step aktif — teruskan ke service yang sesuai
        $activeContext = $ctx['context'] ?? '';

        if ($activeContext === 'tambah_produk' || $activeContext === 'edit_produk') {
            if ($this->product->prosesJawabanProduk($waNumber, $pesan, $shop)) return;
        }

        if ($activeContext === 'tambah_stok') {
            if ($this->stock->prosesJawabanStok($waNumber, $pesan, $shop)) return;
        }

        if ($activeContext === 'hitung_hpp') {
            if ($this->keuangan->prosesJawabanHpp($waNumber, $pesan, $shop)) return;
        }

        if ($activeContext === 'catat_komplain') {
            if ($this->komplain->prosesJawabanKomplain($waNumber, $pesan, $shop)) return;
        }

        // 3. Parse intent via Groq
        $konteks = array_filter([
            'intent_sebelumnya' => $ctx['intent_terakhir'] ?? null,
            'step'              => $ctx['step'] ?? null,
        ]);

        $intent = $this->groq->parseIntent($pesan, $konteks, $shop->id);

        Log::info('CommandRouter intent', [
            'wa'         => $waNumber,
            'intent'     => $intent['intent'],
            'confidence' => $intent['confidence'],
        ]);

        if (($intent['confidence'] ?? 0) < $this->minConfidence) {
            $this->mintaKlarifikasi($waNumber);
            return;
        }

        if (in_array($intent['intent'], $this->destructiveIntents)) {
            $this->mintaKonfirmasi($waNumber, $intent, $shop);
            return;
        }

        $this->session->mergeContextData($waNumber, [
            'intent_terakhir' => $intent['intent'],
            'entitas'         => $intent['entities'] ?? [],
        ]);

        $this->dispatch($waNumber, $intent, $shop);
    }

    // ── Dispatcher ────────────────────────────────────────────────

    private function dispatch(string $waNumber, array $intent, Shop $shop): void
    {
        $nama = $intent['intent'];
        $ent  = $intent['entities'] ?? [];

        match ($nama) {
            // ── Produk ──────────────────────────────────────────
            'tambah_produk'      => $this->product->handleTambahProduk($waNumber, $ent, $shop),
            'edit_produk'        => $this->product->handleEditProduk($waNumber, $ent, $shop),
            'hapus_produk'       => $this->product->handleHapusProduk($waNumber, $ent, $shop),
            'daftar_produk'      => $this->product->handleDaftarProduk($waNumber, $shop),

            // ── Stok ────────────────────────────────────────────
            'tambah_stok'        => $this->stock->handleTambahStok($waNumber, $ent, $shop),
            'cek_stok'           => $this->stock->handleCekStok($waNumber, $ent, $shop),
            'update_stok'        => $this->stock->handleKoreksiStok($waNumber, $ent, $shop),
            'kurangi_stok'       => $this->stock->handleKurangiStok($waNumber, $ent, $shop),
            'lihat_stok_kritis'  => $this->stock->handleStokKritis($waNumber, $shop),

            // ── Pesanan ─────────────────────────────────────────
            'lihat_pesanan'      => $this->order->handleLihatPesanan($waNumber, $ent, $shop),
            'detail_pesanan'     => $this->order->handleDetailPesanan($waNumber, $ent, $shop),
            'konfirmasi_pesanan' => $this->order->handleKonfirmasiPesanan($waNumber, $ent, $shop),
            'tolak_pesanan'      => $this->order->handleBatalPesanan($waNumber, $ent, $shop),
            'kirim_pesanan'      => $this->order->handleShippedPesanan($waNumber, $ent, $shop),
            'selesai_pesanan'    => $this->order->handleSelesaiPesanan($waNumber, $ent, $shop),

            // ── Keuangan ────────────────────────────────────────
            'hitung_hpp'         => $this->keuangan->handleHitungHpp($waNumber, $ent, $shop),
            'saran_harga'        => $this->keuangan->handleSaranHarga($waNumber, $ent, $shop),
            'cek_margin'         => $this->keuangan->handleCekMargin($waNumber, $ent, $shop),
            'cek_keuangan'       => $this->keuangan->handleRingkasanKeuangan($waNumber, $shop),
            'update_harga'       => $this->product->handleEditProduk($waNumber, array_merge($ent, ['field' => 'harga']), $shop),

            // ── Laporan ─────────────────────────────────────────
            'lihat_laporan'      => $this->handleLihatLaporan($waNumber, $shop),

            // ── Konten ──────────────────────────────────────────
            'buat_konten'        => $this->konten->handleBuatKonten($waNumber, $ent, $shop),
            'riwayat_konten'     => $this->konten->handleRiwayatKonten($waNumber, $shop),
            'setting_konten'     => $this->konten->handleSettingKonten($waNumber, $ent, $shop),

            // ── Komplain ────────────────────────────────────────
            'catat_komplain'     => $this->komplain->handleCatatKomplain($waNumber, $ent, $shop),
            'lihat_komplain'     => $this->komplain->handleLihatKomplain($waNumber, $ent, $shop),
            'pola_komplain'      => $this->komplain->handlePolaKomplain($waNumber, $shop),

            // ── Langganan ───────────────────────────────────────
            'cek_langganan'      => $this->subscription->handleCekLangganan($waNumber, $shop),
            'perpanjang'         => $this->subscription->handlePerpanjang($waNumber, $ent, $shop),

            // ── Toko ────────────────────────────────────────────
            'tutup_toko'         => $this->handleTutupToko($waNumber, $ent, $shop),
            'buka_toko'          => $this->handleBukaToko($waNumber, $shop),
            'setting_toko'       => $this->handleSettingToko($waNumber, $shop),

            // ── Fallback ────────────────────────────────────────
            'tidak_dikenali', default => $this->handleTidakDikenali($waNumber),
        };
    }

    // ── Handler Laporan ───────────────────────────────────────────

    private function handleLihatLaporan(string $waNumber, Shop $shop): void
    {
        try {
            $token = $this->laporan->generateToken($shop->id);
            $url   = config('app.url') . "/laporan/{$token}";

            $briefing = $this->laporan->generateMorningBriefing($shop->id);
            $this->wa->kirimPesan($waNumber, $briefing . "\n\n📊 Laporan lengkap:\n{$url}");
        } catch (\Throwable $e) {
            Log::error('CommandRouter: handleLihatLaporan gagal', ['err' => $e->getMessage()]);
            $this->wa->kirimPesan($waNumber, "Maaf, gagal mengambil laporan. Coba lagi sebentar ya.");
        }
    }

    // ── Handler Toko ─────────────────────────────────────────────

    private function handleTutupToko(string $waNumber, array $ent, Shop $shop): void
    {
        $durasi = $ent['jumlah'] ?? null;

        $shop->update([
            'status'       => 'inactive',
            'buka_lagi_at' => $durasi ? now()->addHours((int) $durasi) : null,
        ]);

        $pesan = $durasi
            ? "🔴 Toko *{$shop->nama_toko}* ditutup selama {$durasi} jam.\n"
              . "Buka otomatis jam " . now()->addHours((int) $durasi)->setTimezone('Asia/Jakarta')->format('H:i') . "."
            : "🔴 Toko *{$shop->nama_toko}* ditutup.\nKetik *buka toko* untuk membuka kembali.";

        $this->wa->kirimPesan($waNumber, $pesan);
        $this->session->clearContext($waNumber);
    }

    private function handleBukaToko(string $waNumber, Shop $shop): void
    {
        $shop->update(['status' => 'active', 'buka_lagi_at' => null]);
        $this->wa->kirimPesan($waNumber,
            "🟢 Toko *{$shop->nama_toko}* sudah buka kembali! Siap menerima pesanan."
        );
        $this->session->clearContext($waNumber);
    }

    private function handleSettingToko(string $waNumber, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "⚙️ *Pengaturan Toko*\n\n"
            . "• *nama toko [nama baru]* — ubah nama toko\n"
            . "• *rekening [bank nomor]* — update rekening\n"
            . "• *nomor darurat [nomor]* — nomor backup\n"
            . "• *set tone [friendly/formal/santai]* — gaya konten\n"
            . "• *batas stok [produk] [angka]* — atur minimum stok\n\n"
            . "Link toko: " . config('app.url') . "/toko/{$shop->slug}"
        );
    }

    // ── Konfirmasi Aksi Destruktif ────────────────────────────────

    private function mintaKonfirmasi(string $waNumber, array $intent, Shop $shop): void
    {
        $label = match ($intent['intent']) {
            'hapus_produk'  => "menghapus produk *" . ($intent['entities']['nama_produk'] ?? '?') . "*",
            'tolak_pesanan' => "membatalkan pesanan *#" . ($intent['entities']['order_id'] ?? '?') . "*",
            'tutup_toko'    => "menutup toko *{$shop->nama_toko}*",
            default         => "melakukan aksi ini",
        };

        $this->session->mergeContextData($waNumber, [
            'menunggu_konfirmasi' => true,
            'konfirmasi_intent'   => $intent,
        ]);

        $this->wa->kirimPesan($waNumber,
            "⚠️ Kamu yakin ingin {$label}?\n\nBalas *ya* untuk lanjut atau *tidak* untuk batal."
        );
    }

    private function prosesKonfirmasi(string $waNumber, string $pesan, Shop $shop, array $ctx): void
    {
        $jawaban = strtolower(trim($pesan));
        $intent  = $ctx['konfirmasi_intent'] ?? null;

        $this->session->mergeContextData($waNumber, [
            'menunggu_konfirmasi' => false,
            'konfirmasi_intent'   => null,
        ]);

        if (in_array($jawaban, ['ya', 'iya', 'yes', 'ok', 'oke', 'yep', 'y'])) {
            if ($intent) {
                $this->dispatch($waNumber, $intent, $shop);
            }
        } else {
            $this->wa->kirimPesan($waNumber, "Oke, dibatalkan. Ada yang bisa dibantu lagi?");
            $this->session->clearContext($waNumber);
        }
    }

    // ── Fallback ─────────────────────────────────────────────────

    private function mintaKlarifikasi(string $waNumber): void
    {
        $this->wa->kirimPesan($waNumber,
            "Hmm, saya kurang paham maksudnya. 🤔\n\n"
            . "Contoh perintah:\n"
            . "• *tambah produk* — tambah produk baru\n"
            . "• *lihat pesanan* — cek pesanan masuk\n"
            . "• *cek stok* — lihat stok produk\n"
            . "• *hitung hpp [produk]* — kalkulator biaya produksi\n"
            . "• *buat caption [produk]* — konten media sosial\n"
            . "• *laporan* — laporan penjualan\n\n"
            . "_Ceritakan lebih detail atau ketik *bantuan*._"
        );
    }

    private function handleTidakDikenali(string $waNumber): void
    {
        $this->mintaKlarifikasi($waNumber);
    }
}
