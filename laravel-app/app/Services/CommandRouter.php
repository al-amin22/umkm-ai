<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\WaSession;
use Illuminate\Support\Facades\Log;

class CommandRouter
{
    // Confidence minimum sebelum minta klarifikasi
    private float $minConfidence = 0.6;

    // Intent yang butuh konfirmasi eksplisit sebelum dieksekusi
    private array $destructiveIntents = [
        'hapus_produk',
        'tolak_pesanan',
        'tutup_toko',
    ];

    public function __construct(
        private GroqService    $groq,
        private WAService      $wa,
        private SessionService $session,
        private NotificationService $notif,
        private LaporanService $laporan,
    ) {}

    // ── Entry Point ───────────────────────────────────────────────

    public function route(string $waNumber, string $pesan, Shop $shop, WaSession $waSession): void
    {
        // Cek apakah ada konfirmasi pending untuk aksi destruktif
        $ctx = $waSession->context_data ?? [];

        if (($ctx['menunggu_konfirmasi'] ?? false)) {
            $this->prosesKonfirmasi($waNumber, $pesan, $shop, $ctx);
            return;
        }

        // Parse intent via Groq
        $konteks = array_filter([
            'intent_sebelumnya' => $ctx['intent_terakhir'] ?? null,
            'step'              => $ctx['step'] ?? null,
            'entitas'           => $ctx['entitas'] ?? null,
        ]);

        $intent = $this->groq->parseIntent($pesan, $konteks, $shop->id);

        Log::info("CommandRouter: intent", [
            'wa'         => $waNumber,
            'intent'     => $intent['intent'],
            'confidence' => $intent['confidence'],
        ]);

        // Confidence terlalu rendah → minta klarifikasi
        if (($intent['confidence'] ?? 0) < $this->minConfidence) {
            $this->mintaKlarifikasi($waNumber, $pesan, $intent);
            return;
        }

        // Intent destruktif → konfirmasi dulu
        if (in_array($intent['intent'], $this->destructiveIntents)) {
            $this->mintaKonfirmasi($waNumber, $intent, $shop);
            return;
        }

        // Simpan intent ke session untuk konteks berikutnya
        $this->session->mergeContextData($waNumber, [
            'intent_terakhir' => $intent['intent'],
            'entitas'         => $intent['entities'] ?? [],
        ]);

        $this->dispatch($waNumber, $intent, $shop);
    }

    // ── Dispatcher ────────────────────────────────────────────────

    private function dispatch(string $waNumber, array $intent, Shop $shop): void
    {
        $nama  = $intent['intent'];
        $ent   = $intent['entities'] ?? [];

        match ($nama) {
            // ── Produk ──────────────────────────────────────────
            'tambah_produk'   => $this->handleTambahProduk($waNumber, $ent, $shop),
            'edit_produk'     => $this->handleEditProduk($waNumber, $ent, $shop),
            'hapus_produk'    => $this->handleHapusProduk($waNumber, $ent, $shop),

            // ── Stok ────────────────────────────────────────────
            'tambah_stok'     => $this->handleTambahStok($waNumber, $ent, $shop),
            'cek_stok'        => $this->handleCekStok($waNumber, $ent, $shop),
            'update_stok'     => $this->handleUpdateStok($waNumber, $ent, $shop),
            'lihat_stok_kritis' => $this->handleStokKritis($waNumber, $shop),

            // ── Pesanan ─────────────────────────────────────────
            'lihat_pesanan'    => $this->handleLihatPesanan($waNumber, $ent, $shop),
            'konfirmasi_pesanan'=> $this->handleKonfirmasiPesanan($waNumber, $ent, $shop),
            'tolak_pesanan'    => $this->handleTolakPesanan($waNumber, $ent, $shop),

            // ── Keuangan & Laporan ──────────────────────────────
            'lihat_laporan'   => $this->handleLihatLaporan($waNumber, $shop),
            'cek_keuangan'    => $this->handleCekKeuangan($waNumber, $shop),
            'update_harga'    => $this->handleUpdateHarga($waNumber, $ent, $shop),

            // ── Konten ──────────────────────────────────────────
            'buat_konten'     => $this->handleBuatKonten($waNumber, $ent, $shop),

            // ── Toko ────────────────────────────────────────────
            'tutup_toko'      => $this->handleTutupToko($waNumber, $ent, $shop),
            'buka_toko'       => $this->handleBukaToko($waNumber, $ent, $shop),
            'setting_toko'    => $this->handleSettingToko($waNumber, $shop),

            // ── Fallback ────────────────────────────────────────
            'tidak_dikenali'  => $this->handleTidakDikenali($waNumber, $shop),
            default           => $this->handleTidakDikenali($waNumber, $shop),
        };
    }

    // ── Handler Produk ────────────────────────────────────────────

    private function handleTambahProduk(string $waNumber, array $ent, Shop $shop): void
    {
        $this->session->updateContext($waNumber, 'tambah_produk', [
            'step'         => 'nama_produk',
            'shop_id'      => $shop->id,
            'data_produk'  => $ent,
        ]);

        $namaFromIntent = $ent['nama_produk'] ?? null;

        if ($namaFromIntent) {
            $this->wa->kirimPesan($waNumber,
                "Oke! Menambah produk *{$namaFromIntent}*.\n\n"
                . "❓ Berapa harganya? _(contoh: 25000)_"
            );
            $this->session->mergeContextData($waNumber, [
                'step'        => 'harga',
                'nama_produk' => $namaFromIntent,
            ]);
        } else {
            $this->wa->kirimPesan($waNumber,
                "Oke! Mari tambah produk baru. 📦\n\n"
                . "❓ *Apa nama produknya?*"
            );
        }
    }

    private function handleEditProduk(string $waNumber, array $ent, Shop $shop): void
    {
        $nama = $ent['nama_produk'] ?? null;
        $this->session->updateContext($waNumber, 'edit_produk', [
            'shop_id'     => $shop->id,
            'nama_produk' => $nama,
        ]);

        $this->wa->kirimPesan($waNumber,
            $nama
                ? "Oke, edit produk *{$nama}*.\n❓ Apa yang ingin diubah? _(nama / harga / deskripsi / foto / status)_"
                : "❓ Produk mana yang ingin diedit? Sebutkan nama produknya."
        );
    }

    private function handleHapusProduk(string $waNumber, array $ent, Shop $shop): void
    {
        // Sudah lolos konfirmasi di sini
        $this->wa->kirimPesan($waNumber,
            "Produk berhasil dihapus. _(fitur hapus produk segera hadir)_"
        );
    }

    // ── Handler Stok ──────────────────────────────────────────────

    private function handleTambahStok(string $waNumber, array $ent, Shop $shop): void
    {
        $this->session->updateContext($waNumber, 'tambah_stok', [
            'shop_id'     => $shop->id,
            'nama_produk' => $ent['nama_produk'] ?? null,
            'jumlah'      => $ent['jumlah'] ?? null,
        ]);

        if ($ent['nama_produk'] && $ent['jumlah']) {
            $this->wa->kirimPesan($waNumber,
                "Menambah *{$ent['jumlah']}* stok untuk *{$ent['nama_produk']}*. "
                . "_(fitur stok segera hadir)_"
            );
        } else {
            $this->wa->kirimPesan($waNumber,
                "❓ Stok produk mana yang ingin ditambah? Dan berapa jumlahnya?\n"
                . "_Contoh: tambah stok kopi 50_"
            );
        }
    }

    private function handleCekStok(string $waNumber, array $ent, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "📦 _(Fitur cek stok sedang dikembangkan, segera hadir!)_"
        );
    }

    private function handleUpdateStok(string $waNumber, array $ent, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "📦 _(Fitur update stok sedang dikembangkan, segera hadir!)_"
        );
    }

    private function handleStokKritis(string $waNumber, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "⚠️ _(Fitur lihat stok kritis sedang dikembangkan, segera hadir!)_"
        );
    }

    // ── Handler Pesanan ───────────────────────────────────────────

    private function handleLihatPesanan(string $waNumber, array $ent, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "🛍️ _(Fitur lihat pesanan sedang dikembangkan, segera hadir!)_"
        );
    }

    private function handleKonfirmasiPesanan(string $waNumber, array $ent, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "✅ _(Fitur konfirmasi pesanan sedang dikembangkan, segera hadir!)_"
        );
    }

    private function handleTolakPesanan(string $waNumber, array $ent, Shop $shop): void
    {
        // Sudah lolos konfirmasi
        $this->wa->kirimPesan($waNumber,
            "❌ _(Fitur tolak pesanan sedang dikembangkan, segera hadir!)_"
        );
    }

    // ── Handler Keuangan & Laporan ────────────────────────────────

    private function handleLihatLaporan(string $waNumber, Shop $shop): void
    {
        try {
            $briefing = $this->laporan->generateMorningBriefing($shop->id);
            $this->wa->kirimPesan($waNumber, $briefing);
        } catch (\Throwable $e) {
            Log::error("CommandRouter: generateMorningBriefing gagal", ['err' => $e->getMessage()]);
            $this->wa->kirimPesan($waNumber, "Maaf, gagal mengambil laporan. Coba lagi sebentar ya.");
        }
    }

    private function handleCekKeuangan(string $waNumber, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "💰 _(Fitur cek keuangan sedang dikembangkan, segera hadir!)_"
        );
    }

    private function handleUpdateHarga(string $waNumber, array $ent, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "💲 _(Fitur update harga sedang dikembangkan, segera hadir!)_"
        );
    }

    // ── Handler Konten ────────────────────────────────────────────

    private function handleBuatKonten(string $waNumber, array $ent, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "✍️ _(Fitur buat konten/caption sedang dikembangkan, segera hadir!)_"
        );
    }

    // ── Handler Toko ─────────────────────────────────────────────

    private function handleTutupToko(string $waNumber, array $ent, Shop $shop): void
    {
        // Sudah lolos konfirmasi
        $durasi = $ent['jumlah'] ?? null;

        $shop->update([
            'status'      => 'inactive',
            'buka_lagi_at'=> $durasi ? now()->addHours((int) $durasi) : null,
        ]);

        $pesan = $durasi
            ? "🔴 Toko *{$shop->nama_toko}* ditutup sementara selama {$durasi} jam.\n"
              . "Akan buka otomatis jam " . now()->addHours((int) $durasi)->format('H:i') . "."
            : "🔴 Toko *{$shop->nama_toko}* ditutup.\nKetik *buka toko* untuk membuka kembali.";

        $this->wa->kirimPesan($waNumber, $pesan);
        $this->session->clearContext($waNumber);
    }

    private function handleBukaToko(string $waNumber, array $ent, Shop $shop): void
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
            . "Apa yang ingin diubah?\n"
            . "• Ketik *nama toko [nama baru]*\n"
            . "• Ketik *rekening [bank nomor]*\n"
            . "• Ketik *jam buka [jam]*\n"
            . "• Ketik *nomor darurat [nomor]*"
        );
    }

    private function handleTidakDikenali(string $waNumber, Shop $shop): void
    {
        $this->wa->kirimPesan($waNumber,
            "Maaf, saya belum memahami perintahnya. 🤔\n\n"
            . "Beberapa perintah yang bisa dicoba:\n"
            . "• *tambah produk* — tambah produk baru\n"
            . "• *lihat pesanan* — cek pesanan masuk\n"
            . "• *cek stok* — lihat stok produk\n"
            . "• *laporan* — laporan penjualan\n"
            . "• *tutup toko* — tutup toko sementara\n\n"
            . "_Ketik *bantuan* untuk panduan lengkap._"
        );
    }

    // ── Konfirmasi Aksi Destruktif ────────────────────────────────

    private function mintaKonfirmasi(string $waNumber, array $intent, Shop $shop): void
    {
        $labelIntent = match ($intent['intent']) {
            'hapus_produk'  => "menghapus produk *" . ($intent['entities']['nama_produk'] ?? '?') . "*",
            'tolak_pesanan' => "menolak pesanan",
            'tutup_toko'    => "menutup toko *{$shop->nama_toko}*",
            default         => "melakukan aksi ini",
        };

        $this->session->mergeContextData($waNumber, [
            'menunggu_konfirmasi' => true,
            'konfirmasi_intent'   => $intent,
        ]);

        $this->wa->kirimPesan($waNumber,
            "⚠️ Kamu yakin ingin {$labelIntent}?\n\n"
            . "Balas *ya* untuk lanjut atau *tidak* untuk batal."
        );
    }

    private function prosesKonfirmasi(string $waNumber, string $pesan, Shop $shop, array $ctx): void
    {
        $jawaban = strtolower(trim($pesan));
        $intent  = $ctx['konfirmasi_intent'] ?? null;

        // Reset flag konfirmasi
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

    // ── Minta Klarifikasi ─────────────────────────────────────────

    private function mintaKlarifikasi(string $waNumber, string $pesan, array $intent): void
    {
        $this->wa->kirimPesan($waNumber,
            "Hmm, saya kurang yakin maksud pesannya. 🤔\n\n"
            . "Apakah kamu ingin:\n"
            . "• Kelola *produk* (tambah/edit/hapus)\n"
            . "• Cek *pesanan*\n"
            . "• Lihat *stok*\n"
            . "• Lihat *laporan*\n"
            . "• Atur *toko*\n\n"
            . "_Atau ceritakan lebih detail kebutuhannya._"
        );
    }
}
